<?php

namespace Tests\Feature;

use App\Catalogue\ImageProcessor;
use App\Models\CatalogueProduct;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    private StaffUser $staff;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/import-'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        config(['catalogue_import.root' => $this->root.'/staging', 'catalogue.media_root' => $this->root.'/media', 'catalogue.image_driver' => 'gd']);
        $this->staff = $this->staff('owner');
        $this->login($this->staff);
        // Deliberately nonstandard IDs prove selectors resolve the database hierarchy.
        DB::table('catalogue_collections')->insert(['id' => 42, 'title' => 'Women', 'slug' => 'women', 'kind' => 'collection', 'active' => true]);
        DB::table('catalogue_collections')->insert(['id' => 97, 'title' => 'Knitwear', 'slug' => 'womens-knitwear', 'kind' => 'category', 'parent_id' => 42, 'active' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function staff(string $role): StaffUser
    {
        return StaffUser::create(['name' => 'Import QA', 'email' => Str::uuid().'@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', $role)->value('id'), 'two_factor_confirmed_at' => now()])->fresh();
    }

    private function login(StaffUser $staff): void
    {
        $this->actingAs($staff, 'staff')->withSession(['_token' => 'import-csrf', 'staff_version' => $staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
    }

    private function headers(): array
    {
        return ['Accept' => 'application/json', 'Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'import-csrf'];
    }

    private function product(string $id = '1005010280852799', string $slug = 'imported-knitwear'): array
    {
        return ['title' => 'Isolated QA knitted jumper', 'slug' => $slug, 'groups' => [['collection_slug' => 'women', 'category_slug' => 'womens-knitwear']], 'supplier' => ['name' => 'AliExpress', 'product_id' => $id, 'url' => 'https://www.aliexpress.com/item/'.$id.'.html']];
    }

    private function archive(array $entries, ?int $attributes = null): UploadedFile
    {
        $path = $this->root.'/'.Str::uuid().'.zip';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        foreach ($entries as $name => $bytes) {
            $z->addFromString($name, $bytes);
            if ($attributes !== null) {
                $z->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $attributes);
            }
        }
        $z->close();

        return new UploadedFile($path, 'images.zip', 'application/zip', null, true);
    }

    private function image(): string
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD runtime required for image integration.');
        }
        $im = imagecreatetruecolor(400, 500);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    private function preview(array $products, ?UploadedFile $zip = null)
    {
        $body = ['products' => UploadedFile::fake()->createWithContent('products.json', json_encode(['schema_version' => 1, 'products' => $products]))];
        if ($zip) {
            $body['images'] = $zip;
        }

        return $this->post('/api/v1/staff/catalogue/imports/preview', $body, $this->headers());
    }

    private function execute(array $batch, int $row = 0, array $body = ['confirm_matches' => true])
    {
        return $this->postJson('/api/v1/staff/catalogue/imports/'.$batch['id'].'/items/'.$batch['items'][$row]['id'], $body, $this->headers());
    }

    public function test_preview_is_write_free_for_catalogue_and_resolves_actual_group_ids(): void
    {
        $batch = $this->preview([$this->product()])->assertOk()->json('data.batch');
        $this->assertSame('ready', $batch['items'][0]['status']);
        $this->assertSame([42, 97], array_column($batch['items'][0]['preview']['groups'], 'id'));
        $this->assertDatabaseCount('catalogue_products', 0);
        $this->assertDatabaseCount('catalogue_supplier_products', 0);
        $this->assertDatabaseHas('staff_audit_events', ['action' => 'catalogue.import_previewed']);
    }

    public function test_missing_values_remain_empty_and_publication_checks_still_apply(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        $result = $this->execute($batch)->assertOk()->json('data.batch');
        $id = $result['items'][0]['product_id'];
        $this->assertSame('imported', $result['items'][0]['status']);
        $this->assertDatabaseHas('catalogue_products', ['id' => $id, 'status' => 'draft', 'material' => null, 'fit' => null, 'care' => null]);
        $this->assertDatabaseCount('catalogue_variants', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseHas('catalogue_supplier_products', ['product_id' => $id, 'cost_minor' => null, 'checked_at' => null]);
        $this->postJson('/api/v1/staff/catalogue/products/'.$id.'/publish', ['version' => 1], $this->headers())->assertUnprocessable();
        $this->getJson('/api/v1/catalogue/products/imported-knitwear')->assertNotFound();
    }

    public function test_confirm_is_required_and_completed_rows_are_idempotent(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        $this->execute($batch, 0, [])->assertUnprocessable();
        $this->assertDatabaseCount('catalogue_products', 0);
        $first = $this->execute($batch)->assertOk()->json('data.batch.items.0.product_id');
        $this->assertSame($first, $this->execute($batch)->assertOk()->json('data.batch.items.0.product_id'));
        $this->assertDatabaseCount('catalogue_products', 1);
    }

    public function test_duplicate_ids_in_file_and_other_batches_never_overwrite_products(): void
    {
        $p = $this->product();
        $batch = $this->preview([$p, $p])->json('data.batch');
        $this->assertSame('duplicate', $batch['items'][1]['status']);
        $second = $this->preview([$p])->json('data.batch');
        $this->execute($batch)->assertOk();
        $this->assertSame('failed', $this->execute($second)->assertOk()->json('data.batch.items.0.status'));
        $duplicate = $this->preview([$this->product(slug: 'different-content')])->json('data.batch');
        $this->assertSame('duplicate', $duplicate['items'][0]['status']);
        $this->assertDatabaseCount('catalogue_products', 1);
        $this->assertDatabaseHas('catalogue_products', ['slug' => 'imported-knitwear']);
    }

    public function test_supplier_identity_is_retained_after_product_deletion(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        $id = $this->execute($batch)->json('data.batch.items.0.product_id');
        $this->postJson('/api/v1/staff/catalogue/products/'.$id.'/delete', ['version' => 1, 'confirmation' => 'DELETE'], $this->headers())->assertOk();
        $this->assertDatabaseHas('catalogue_supplier_products', ['product_id' => null, 'external_id' => '1005010280852799']);
        $this->assertSame('duplicate', $this->preview([$this->product()])->json('data.batch.items.0.status'));
    }

    public function test_supplier_variants_link_to_complete_local_variants_and_keep_incomplete_values_pending(): void
    {
        $p = $this->product();
        $p += ['product_type' => 'Knitwear', 'description' => 'QA description', 'material' => 'QA composition', 'fit' => 'QA fit', 'care' => 'QA care', 'seo_title' => 'QA title', 'seo_description' => 'QA description', 'measurements' => [['size' => 'M', 'chest_cm' => 100, 'length_cm' => 60, 'shoulder_cm' => 40]]];
        $p['supplier'] += ['cost_minor' => 1050, 'currency' => 'USD', 'shipping_cost_minor' => 150, 'checked_at' => '2026-01-01'];
        $p['variants'] = [['sku' => 'IMPORT-QA-M', 'colour' => 'Blue', 'size' => 'M', 'price_minor' => 2499, 'currency' => 'GBP', 'supplier_variant_id' => 'supplier-known-m', 'supplier_cost_minor' => 1000, 'supplier_currency' => 'USD'], ['size' => 'L', 'supplier_variant_id' => null, 'price_minor' => null]];
        $batch = $this->preview([$p])->assertOk()->json('data.batch');
        $this->assertSame(1, $batch['items'][0]['preview']['pending_variant_count']);
        $id = $this->execute($batch)->assertOk()->json('data.batch.items.0.product_id');
        $local = DB::table('catalogue_variants')->first();
        $this->assertNotNull($local);
        $this->assertSame(2499, $local->price_minor);
        $this->assertDatabaseHas('catalogue_supplier_variants', ['variant_id' => $local->id, 'external_id' => 'supplier-known-m', 'cost_minor' => 1000]);
        $pending = DB::table('catalogue_supplier_variants')->whereNull('variant_id')->first();
        $this->assertNull($pending->external_id);
        $this->assertSame('L', json_decode($pending->pending_catalogue, true)['size']);
        $this->assertDatabaseHas('catalogue_products', ['id' => $id, 'seo_title' => 'QA title', 'material' => 'QA composition']);
    }

    public function test_image_pipeline_preserves_original_bytes_and_creates_private_webp_derivatives(): void
    {
        $bytes = $this->image();
        $p = $this->product();
        $p['images'] = [['file' => 'knitwear/front.png', 'alt' => 'QA knitted jumper front']];
        $batch = $this->preview([$p], $this->archive(['knitwear/front.png' => $bytes]))->assertOk()->json('data.batch');
        $this->assertTrue($batch['items'][0]['preview']['images'][0]['matched']);
        $this->get('/api/v1/staff/catalogue/imports/'.$batch['id'].'/items/'.$batch['items'][0]['id'].'/images/0', $this->headers())->assertOk();
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'imported');
        $media = DB::table('catalogue_media')->first();
        $this->assertSame(hash('sha256', $bytes), $media->sha256);
        $this->assertSame($bytes, file_get_contents($this->root.'/media/'.$media->id.'/original'));
        $this->assertFileExists($this->root.'/media/'.$media->id.'/640.webp');
        $this->assertFileExists($this->root.'/staging/'.$batch['id'].'/images/knitwear/front.png');
    }

    public function test_image_failure_rolls_back_product_supplier_and_media_then_can_retry(): void
    {
        $bytes = $this->image();
        $p = $this->product();
        $p['images'] = [['file' => 'front.png', 'alt' => 'QA front'], ['file' => 'back.png', 'alt' => 'QA back']];
        $batch = $this->preview([$p], $this->archive(['front.png' => $bytes, 'back.png' => $bytes]))->json('data.batch');
        $real = new ImageProcessor;
        $calls = 0;
        $this->mock(ImageProcessor::class)->shouldReceive('process')->andReturnUsing(function (...$args) use (&$calls, $real) {
            if (++$calls === 2) {
                throw new \RuntimeException('WRITE_FAILED');
            }

            return $real->process(...$args);
        });
        $failed = $this->execute($batch)->assertOk()->json('data.batch');
        $this->assertSame('failed', $failed['items'][0]['status']);
        $this->assertDatabaseCount('catalogue_products', 0);
        $this->assertDatabaseCount('catalogue_supplier_products', 0);
        $this->assertDatabaseCount('catalogue_media', 0);
        $this->assertEmpty(File::directories($this->root.'/media'));
        $this->app->instance(ImageProcessor::class, $real);
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'imported')->assertJsonPath('data.batch.items.0.attempts', 2);
        $this->assertDatabaseCount('catalogue_media', 2);
    }

    public function test_mixed_rows_import_independently_and_stale_identifiers_are_revalidated(): void
    {
        $good = $this->product();
        $bad = $this->product('1005005987989484', 'bad-seo');
        $bad['seo_title'] = str_repeat('x', 71);
        $batch = $this->preview([$good, $bad])->json('data.batch');
        $this->assertSame('invalid', $batch['items'][1]['status']);
        $this->execute($batch, 1)->assertConflict();
        DB::table('catalogue_collections')->where('id', 97)->update(['deleted_at' => now()]);
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'failed');
        $this->assertDatabaseCount('catalogue_products', 0);
        DB::table('catalogue_collections')->where('id', 97)->update(['deleted_at' => null]);
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'imported');
        $this->assertDatabaseCount('catalogue_products', 1);
    }

    public function test_zip_paths_links_types_limits_and_case_duplicates_are_rejected(): void
    {
        foreach ([['../escape.png' => 'x'], ['/absolute.png' => 'x'], ['C:/drive.png' => 'x'], ['folder\\escape.png' => 'x'], ['front.php' => '<?php'], ['front.png' => 'x', 'FRONT.PNG' => 'x'], ['photo.avif' => 'avif']] as $entries) {
            $this->preview([$this->product()], $this->archive($entries))->assertUnprocessable();
        }
        $this->preview([$this->product()], $this->archive(['link.png' => 'target'], 0120777 << 16))->assertUnprocessable();
        config(['catalogue_import.max_image_bytes' => 20]);
        $this->preview([$this->product()], $this->archive(['large.png' => str_repeat('x', 21)]))->assertUnprocessable();
        $this->assertDatabaseCount('catalogue_import_batches', 0);
    }

    public function test_missing_and_disguised_images_are_visible_validation_errors(): void
    {
        $p = $this->product();
        $p['images'] = [['file' => 'missing.webp', 'alt' => 'QA missing']];
        $batch = $this->preview([$p])->assertOk()->json('data.batch');
        $this->assertSame('invalid', $batch['items'][0]['status']);
        $this->assertFalse($batch['items'][0]['preview']['images'][0]['matched']);
        $p['images'][0]['file'] = 'fake.png';
        $this->assertSame('invalid', $this->preview([$p], $this->archive(['fake.png' => '<?php echo 1;']))->assertOk()->json('data.batch.items.0.status'));
    }

    public function test_bad_json_oversized_batches_unknown_fields_costs_and_mismatched_hierarchy(): void
    {
        $this->post('/api/v1/staff/catalogue/imports/preview', ['products' => UploadedFile::fake()->createWithContent('bad.json', '{bad')], $this->headers())->assertUnprocessable();
        $this->preview(array_fill(0, 51, $this->product()))->assertUnprocessable();
        foreach ([['seo_description' => str_repeat('x', 161)], ['status' => 'published'], ['supplier' => ['name' => 'AliExpress', 'product_id' => '1005010280852799', 'url' => 'https://evil.test']], ['supplier' => $this->product()['supplier'] + ['cost_minor' => 100]], ['supplier' => $this->product()['supplier'] + ['cost_minor' => -1, 'currency' => 'USD']], ['groups' => [['collection_slug' => 'women', 'category_slug' => 'not-a-child']]]] as $change) {
            $this->assertSame('invalid', $this->preview([array_replace($this->product(), $change)])->assertOk()->json('data.batch.items.0.status'));
        }
        $this->assertDatabaseCount('catalogue_products', 0);
    }

    public function test_permissions_csrf_origin_ownership_expiry_and_private_supplier_data(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        $this->postJson('/api/v1/staff/catalogue/imports/'.$batch['id'].'/items/'.$batch['items'][0]['id'], ['confirm_matches' => true], ['Origin' => config('staff.admin_origin')])->assertStatus(419);
        $this->postJson('/api/v1/staff/catalogue/imports/preview', [], ['Origin' => 'https://evil.test'])->assertForbidden();
        $this->login($this->staff('operations'));
        $this->getJson('/api/v1/staff/catalogue/imports/'.$batch['id'], $this->headers())->assertNotFound();
        $this->login($this->staff('support'));
        foreach (['imports', 'imports/template', 'products/1/supplier', 'imports/'.$batch['id']] as $path) {
            $this->getJson('/api/v1/staff/catalogue/'.$path, $this->headers())->assertForbidden();
        }
        $this->login($this->staff);
        DB::table('catalogue_import_batches')->where('id', $batch['id'])->update(['expires_at' => now()->subMinute()]);
        $this->execute($batch)->assertStatus(410);
    }

    public function test_supplier_data_is_not_added_to_customer_facing_product_responses(): void
    {
        $product = $this->product();
        $product['variants'] = [['sku' => 'PUBLIC-PRIVACY-QA', 'colour' => 'Blue', 'size' => 'M', 'price_minor' => 2499, 'active' => true, 'supplier_variant_id' => 'private-supplier-variant']];
        $batch = $this->preview([$product])->json('data.batch');
        $id = $this->execute($batch)->json('data.batch.items.0.product_id');
        $this->getJson('/api/v1/staff/catalogue/products/'.$id.'/supplier', $this->headers())->assertOk()->assertJsonPath('data.supplier.external_id', '1005010280852799');
        // Isolated fixture visibility only, not publication logic.
        CatalogueProduct::where('id', $id)->update(['status' => 'published']);
        $public = $this->getJson('/api/v1/catalogue/products/imported-knitwear');
        $public->assertOk();
        $this->assertStringNotContainsString('AliExpress', $public->getContent());
        $this->assertStringNotContainsString('private-supplier-variant', $public->getContent());
        $this->assertStringNotContainsString('1005010280852799', $public->getContent());
    }

    public function test_template_and_expired_staging_cleanup_keep_reports(): void
    {
        $this->get('/api/v1/staff/catalogue/imports/template', $this->headers())->assertOk()->assertHeader('Content-Type', 'application/json');
        $batch = $this->preview([$this->product()])->json('data.batch');
        $directory = $this->root.'/staging/'.$batch['id'];
        $this->assertDirectoryExists($directory);
        DB::table('catalogue_import_batches')->where('id', $batch['id'])->update(['expires_at' => now()->subMinute()]);
        $this->artisan('catalogue:prune-imports')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($directory);
        $this->getJson('/api/v1/staff/catalogue/imports/'.$batch['id'], $this->headers())->assertOk()->assertJsonPath('data.batch.expired', true);
    }

    public function test_staged_image_tampering_and_duplicate_slug_between_preview_rows_are_rejected(): void
    {
        $bytes = $this->image();
        $p = $this->product();
        $p['images'] = [['file' => 'front.png', 'alt' => 'QA front']];
        $batch = $this->preview([$p], $this->archive(['front.png' => $bytes]))->json('data.batch');
        file_put_contents($this->root.'/staging/'.$batch['id'].'/images/front.png', 'changed');
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'failed');
        $this->assertDatabaseCount('catalogue_products', 0);
        $batch = $this->preview([$this->product(), $this->product('1005005987989484')])->json('data.batch');
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'imported');
        $this->execute($batch, 1)->assertOk()->assertJsonPath('data.batch.items.1.status', 'failed');
        $this->assertDatabaseCount('catalogue_products', 1);
    }

    public function test_archive_expansion_entry_count_ratio_and_malformed_field_types_are_bounded(): void
    {
        config(['catalogue_import.max_entries' => 1]);
        $this->preview([$this->product()], $this->archive(['a.png' => 'a', 'b.png' => 'b']))->assertUnprocessable();
        config(['catalogue_import.max_entries' => 200, 'catalogue_import.max_expanded_bytes' => 10]);
        $this->preview([$this->product()], $this->archive(['a.png' => str_repeat('a', 11)]))->assertUnprocessable();
        config(['catalogue_import.max_expanded_bytes' => 134217728, 'catalogue_import.max_ratio' => 2]);
        $this->preview([$this->product()], $this->archive(['a.png' => str_repeat('a', 10000)]))->assertUnprocessable();
        config(['catalogue_import.max_ratio' => 1000]);
        $this->preview([$this->product()], $this->archive(['file.png' => 'a', 'file.png/child.png' => 'b']))->assertUnprocessable();
        foreach ([['title' => ['nested']], ['collection_ids' => [['nested']]], ['variants' => [['price_minor' => -1]]]] as $change) {
            $this->assertSame('invalid', $this->preview([array_replace($this->product(), $change)])->assertOk()->json('data.batch.items.0.status'));
        }
    }

    public function test_prepared_six_product_package_with_actual_images_in_isolated_database(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD runtime required for full image package acceptance.');
        }
        $directory = getenv('BULK_IMPORT_BATCH_DIR');
        if (! $directory || ! is_file($directory.'/products.json') || ! is_file($directory.'/women-knitwear-images.zip')) {
            $this->markTestSkipped('Set BULK_IMPORT_BATCH_DIR to the prepared six-product package for full image acceptance.');
        }
        $originalJson = file_get_contents($directory.'/products.json');
        $sha = hash_file('sha256', $directory.'/women-knitwear-images.zip');
        $products = json_decode($originalJson, true)['products'];
        $batch = $this->preview($products, new UploadedFile($directory.'/women-knitwear-images.zip', 'images.zip', 'application/zip', null, true))->assertOk()->json('data.batch');
        $this->assertCount(6, $batch['items']);
        foreach ($batch['items'] as $index => $item) {
            $this->assertSame('ready', $item['status'], json_encode($item['errors']));
            $this->assertSame([42, 97], array_column($item['preview']['groups'], 'id'));
            $this->execute($batch, $index)->assertOk()->assertJsonPath('data.batch.items.'.$index.'.status', 'imported');
        }
        $this->assertDatabaseCount('catalogue_products', 6);
        $this->assertSame(6, CatalogueProduct::where('status', 'draft')->count());
        $this->assertDatabaseCount('catalogue_variants', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseCount('catalogue_supplier_products', 6);
        $this->assertDatabaseCount('catalogue_media', 67);
        $this->assertSame(6, $this->preview($products)->assertOk()->json('data.batch.summary.duplicate'));
        $this->assertSame($originalJson, file_get_contents($directory.'/products.json'));
        $this->assertSame($sha, hash_file('sha256', $directory.'/women-knitwear-images.zip'));
    }

    public function test_supplier_identity_has_a_database_unique_constraint(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        $this->execute($batch)->assertOk();
        $this->expectException(QueryException::class);
        DB::table('catalogue_supplier_products')->insert(['product_id' => null, 'supplier' => 'AliExpress', 'external_id' => '1005010280852799', 'url' => 'https://www.aliexpress.com/item/1005010280852799.html']);
    }

    public function test_images_are_authenticated_and_active_preview_limit_is_enforced(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        for ($i = 0; $i < 9; $i++) {
            DB::table('catalogue_import_batches')->insert(['id' => (string) Str::uuid(), 'staff_user_id' => $this->staff->id, 'source_sha256' => str_repeat('a', 64), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->preview([$this->product()])->assertUnprocessable();
        $this->app['auth']->guard('staff')->logout();
        $this->getJson('/api/v1/staff/catalogue/imports/'.$batch['id'].'/items/'.$batch['items'][0]['id'].'/images/0')->assertUnauthorized();
    }

    public function test_category_moved_after_preview_requires_a_new_review(): void
    {
        $batch = $this->preview([$this->product()])->json('data.batch');
        DB::table('catalogue_collections')->insert(['id' => 43, 'title' => 'Men', 'slug' => 'men', 'kind' => 'collection', 'active' => true]);
        DB::table('catalogue_collections')->where('id', 97)->update(['parent_id' => 43]);
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'failed');
        $this->assertDatabaseCount('catalogue_products', 0);
        DB::table('catalogue_collections')->where('id', 97)->update(['parent_id' => 42, 'title' => 'Renamed category']);
        $this->execute($batch)->assertOk()->assertJsonPath('data.batch.items.0.status', 'failed');
        $this->assertDatabaseCount('catalogue_products', 0);
    }
}
