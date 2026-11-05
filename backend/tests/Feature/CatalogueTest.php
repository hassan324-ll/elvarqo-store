<?php

namespace Tests\Feature;

use App\Models\CatalogueCollection;
use App\Models\CatalogueProduct;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private StaffUser $staff;

    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaRoot = storage_path('framework/testing/catalogue-'.Str::uuid());
        config(['catalogue.media_root' => $this->mediaRoot]);
        $this->staff = StaffUser::create(['name' => 'Catalogue Test', 'email' => Str::uuid().'@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()]);
        $this->staff->refresh();
        $this->login();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        parent::tearDown();
    }

    private function login(): void
    {
        $this->withSession(['staff_version' => $this->staff->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
        $this->actingAs($this->staff, 'staff');
    }

    private function api(string $path, ?array $body = null)
    {
        $this->withSession(['_token' => 'catalogue-test-token']);

        return $this->json($body === null ? 'GET' : 'POST', '/api/v1/staff/catalogue/'.$path, $body ?? [], ['Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'catalogue-test-token']);
    }

    private function payload(): array
    {
        return ['title' => 'Test-only Polo', 'slug' => 'test-only-polo', 'product_type' => 'Knitwear', 'description' => 'Verified test description', 'material' => 'Test fibre description', 'fit' => 'Test regular fit', 'care' => 'Test label care', 'seo_title' => 'Test-only Polo | ELVARQO', 'seo_description' => 'Isolated catalogue acceptance fixture.', 'measurements' => [['size' => 'M', 'chest_cm' => 104, 'length_cm' => 70, 'shoulder_cm' => 44], ['size' => 'L', 'chest_cm' => 110, 'length_cm' => 72, 'shoulder_cm' => 46]], 'collection_ids' => [], 'variants' => array_map(fn ($x) => ['sku' => 'QA-'.$x[0].'-'.$x[1], 'colour' => $x[0], 'size' => $x[1], 'price_minor' => 6800, 'currency' => 'GBP', 'low_stock_threshold' => 5, 'active' => true], [['NAVY', 'M'], ['NAVY', 'L'], ['SAND', 'M'], ['SAND', 'L']])];
    }

    private function create(): array
    {
        $v = $this->payload();
        $c = $this->api('collections', ['title' => 'QA collection', 'slug' => 'qa-collection', 'kind' => 'collection', 'active' => true, 'position' => 0])->assertCreated()->json('data.collection.id');
        $v['collection_ids'] = [$c];

        return $this->api('products', $v)->assertCreated()->json('data.product');
    }

    private function upload(array $p, string $file = 'test.png', string $mime = 'image/png', ?string $contents = null)
    {
        if ($contents === null) {
            $path = $this->mediaRoot.'-fixture.png';
            $process = new Process([config('catalogue.node_binary'), '-e', "require('./media/node_modules/sharp')({create:{width:800,height:1000,channels:3,background:'#334455'}}).png().toFile(process.argv[1])", $path], base_path());
            $process->mustRun();
            $contents = file_get_contents($path);
            unlink($path);
        }
        $upload = UploadedFile::fake()->createWithContent($file, $contents);
        $this->withSession(['_token' => 'catalogue-test-token']);

        return $this->post('/api/v1/staff/catalogue/products/'.$p['id'].'/media', ['version' => $p['version'], 'alt' => 'Test-only navy polo front', 'file' => $upload], ['Accept' => 'application/json', 'Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'catalogue-test-token']);
    }

    private function publish(): array
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');

        return $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->json('data.product');
    }

    private function saveBody(array $p): array
    {
        $v = array_intersect_key($p, $this->payload() + ['version' => 0]);
        $v['collection_ids'] = array_column($p['collections'], 'id');
        $v['variants'] = array_map(fn ($x) => array_intersect_key($x, array_flip(['id', 'sku', 'barcode', 'colour', 'size', 'price_minor', 'compare_at_minor', 'currency', 'weight_grams', 'low_stock_threshold', 'active', 'measurements', 'fit', 'care'])), $p['variants']);

        return $v;
    }

    public function test_category_image_upload_replace_remove_and_visibility(): void
    {
        $p = $this->upload($this->create())->json('data.product');
        $bytes = file_get_contents($this->mediaRoot.'/'.$p['media'][0]['id'].'/original');
        $parent = CatalogueCollection::where('kind', 'collection')->first();
        $base = ['title' => 'Tees', 'slug' => 'image-tees', 'kind' => 'category', 'parent_id' => $parent->id, 'active' => true, 'position' => 0, 'image_alt' => 'Tees thumbnail'];
        $send = function (string $path, array $data, string $contents) {
            return $this->post('/api/v1/staff/catalogue/'.$path, array_merge($data, ['image' => UploadedFile::fake()->createWithContent('category.png', $contents)]), ['Accept' => 'application/json', 'Origin' => config('staff.admin_origin'), 'X-CSRF-TOKEN' => 'catalogue-test-token']);
        };
        $this->withSession(['_token' => 'catalogue-test-token']);
        $send('collections', $base, 'not an image')->assertUnprocessable();
        $this->assertDatabaseMissing('catalogue_collections', ['slug' => 'image-tees']);
        $thumbnail = new Process([config('catalogue.node_binary'), '-e', "require('./media/node_modules/sharp')({create:{width:120,height:120,channels:3,background:'#334455'}}).png().toBuffer().then(b=>process.stdout.write(b))"], base_path());
        $thumbnail->mustRun();
        $bytes = $thumbnail->getOutput();
        $tiny = new Process([config('catalogue.node_binary'), '-e', "require('./media/node_modules/sharp')({create:{width:32,height:32,channels:3,background:'#334455'}}).png().toBuffer().then(b=>process.stdout.write(b))"], base_path());
        $tiny->mustRun();
        $send('collections', $base, $tiny->getOutput())->assertUnprocessable()->assertJsonPath('error.details.image.0', 'This image is 32x32px. Choose a category image at least 64px on each side; 400px or larger is recommended.');
        // Herd PHP may export OpenSSL settings incompatible with Node's bundled OpenSSL.
        $serverEnvironment = $_SERVER;
        $dotenvEnvironment = $_ENV;
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (array_keys($_SERVER) as $name) {
                if (in_array(strtolower($name), ['systemroot', 'windir'], true)) {
                    unset($_SERVER[$name]);
                }
            }
            foreach (array_keys($_ENV) as $name) {
                if (in_array(strtolower($name), ['systemroot', 'windir'], true)) {
                    unset($_ENV[$name]);
                }
            }
        }
        $openssl = getenv('OPENSSL_CONF');
        $modules = getenv('OPENSSL_MODULES');
        putenv('OPENSSL_CONF='.base_path('tests/missing-php-openssl.cnf'));
        putenv('OPENSSL_MODULES='.base_path('tests/missing-php-openssl-modules'));
        try {
            $category = $send('collections', $base, $bytes)->assertCreated()->assertJsonMissingPath('data.collection.image_webp')->json('data.collection');
            $this->upload($p, 'openssl-test.png', 'image/png', file_get_contents($this->mediaRoot.'/'.$p['media'][0]['id'].'/original'))->assertCreated();
        } finally {
            $_SERVER = $serverEnvironment;
            $_ENV = $dotenvEnvironment;
            putenv($openssl === false ? 'OPENSSL_CONF' : 'OPENSSL_CONF='.$openssl);
            putenv($modules === false ? 'OPENSSL_MODULES' : 'OPENSSL_MODULES='.$modules);
        }
        $this->assertNotEmpty($category['image_url']);
        $this->get($category['image_url'], ['Origin' => config('staff.admin_origin')])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $public = '/api/v1/catalogue/category-images/'.$category['id'].'/'.$category['image_id'];
        $this->get($public)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $menu = $this->getJson('/api/v1/catalogue/navigation')->json('data.items');
        $child = collect($menu)->firstWhere('id', $parent->id)['categories'][0];
        $this->assertStringNotContainsString('/staff/', $child['image_url']);
        $this->assertArrayNotHasKey('image_webp', $child);
        $parent->update(['active' => false]);
        $this->get($public)->assertNotFound();
        $parent->update(['active' => true]);
        $send('collections/'.$category['id'], array_merge($base, ['version' => $category['version'] + 1]), $bytes)->assertConflict();
        $updated = $send('collections/'.$category['id'], array_merge($base, ['version' => $category['version']]), $bytes)->assertOk()->json('data.collection');
        $this->assertNotEquals($category['image_id'], $updated['image_id']);
        $this->get($public)->assertNotFound();
        $this->api('collections/'.$category['id'], array_merge($base, ['version' => $updated['version'], 'remove_image' => true]))->assertOk()->assertJsonPath('data.collection.image_url', null);
        $this->assertNull(CatalogueCollection::find($category['id'])->image_webp);
    }

    public function test_permanent_deletion_removes_unused_product_variants_and_images(): void
    {
        $p = $this->upload($this->create())->assertCreated()->json('data.product');
        $mediaId = $p['media'][0]['id'];
        $this->assertDirectoryExists($this->mediaRoot.'/'.$mediaId);
        $this->api('products/'.$p['id'].'/delete', ['version' => $p['version'] + 1, 'confirmation' => 'DELETE'])->assertConflict();
        $this->api('products/'.$p['id'].'/delete', ['version' => $p['version']])->assertUnprocessable();
        $this->api('products/'.$p['id'].'/delete', ['version' => $p['version'], 'confirmation' => 'DELETE'])->assertOk()->assertJsonPath('data.deleted', true)->assertJsonPath('data.image_cleanup_pending', false);
        $this->assertDatabaseMissing('catalogue_products', ['id' => $p['id']]);
        $this->assertDatabaseMissing('catalogue_variants', ['product_id' => $p['id']]);
        $this->assertDatabaseMissing('catalogue_collection_product', ['product_id' => $p['id']]);
        $this->assertDatabaseMissing('catalogue_slugs', ['product_id' => $p['id']]);
        $this->assertDatabaseMissing('catalogue_media', ['product_id' => $p['id']]);
        $this->assertDirectoryDoesNotExist($this->mediaRoot.'/'.$mediaId);
        $this->api('products?status=all')->assertJsonPath('data.total', 0);
    }

    public function test_permanent_delete_accepts_archived_unused_product_but_preserves_stock_history(): void
    {
        $p = $this->create();
        $this->api('products/'.$p['id'].'/archive', ['version' => $p['version'], 'confirmation' => 'ARCHIVE'])->assertOk();
        $this->api('products/'.$p['id'].'/delete', ['version' => $p['version'] + 1, 'confirmation' => 'DELETE'])->assertOk();
        $this->assertDatabaseMissing('catalogue_products', ['id' => $p['id']]);
        $payload = $this->payload();
        $p = $this->api('products', $payload)->assertCreated()->json('data.product');
        $location = $this->api('locations', ['name' => 'Main', 'code' => 'MAIN'])->json('data.location.id');
        $this->api('adjustments', ['operation_id' => (string) Str::uuid(), 'variant_id' => $p['variants'][0]['id'], 'location_id' => $location, 'delta' => 1, 'reason' => 'Test initial stock'])->assertCreated();
        $this->api('products/'.$p['id'].'/delete', ['version' => $p['version'], 'confirmation' => 'DELETE'])->assertUnprocessable();
        $this->assertDatabaseHas('catalogue_products', ['id' => $p['id']]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_archiving_groups_preserves_products_and_memberships_and_rejects_stale_or_parent_deletion(): void
    {
        $root = CatalogueCollection::create(['title' => 'Men', 'slug' => 'men', 'kind' => 'collection', 'active' => true]);
        $child = CatalogueCollection::create(['title' => 'Tees', 'slug' => 'tees', 'kind' => 'category', 'parent_id' => $root->id, 'active' => true]);
        $p = $this->create();
        DB::table('catalogue_collection_product')->insert(['collection_id' => $child->id, 'product_id' => $p['id'], 'position' => 0]);
        $root->refresh();
        $child->refresh();
        $this->api('collections/'.$root->id.'/archive', ['version' => $root->version, 'confirmation' => 'ARCHIVE'])->assertUnprocessable();
        $this->api('collections/'.$child->id.'/archive', ['version' => $child->version + 1, 'confirmation' => 'ARCHIVE'])->assertConflict();
        $this->api('collections/'.$child->id.'/archive', ['version' => $child->version])->assertUnprocessable();
        $this->api('collections/'.$child->id.'/archive', ['version' => $child->version, 'confirmation' => 'ARCHIVE'])->assertOk();
        $this->assertSoftDeleted('catalogue_collections', ['id' => $child->id]);
        $this->assertDatabaseHas('catalogue_collection_product', ['collection_id' => $child->id, 'product_id' => $p['id']]);
        $this->api('products/'.$p['id'])->assertOk();
        $this->getJson('/api/v1/catalogue/products?collection=tees')->assertNotFound();
        $root->refresh();
        $this->api('collections/'.$root->id.'/archive', ['version' => $root->version, 'confirmation' => 'ARCHIVE'])->assertOk();
        $this->assertSoftDeleted('catalogue_collections', ['id' => $root->id]);
        $this->getJson('/api/v1/catalogue/navigation')->assertJsonMissing(['slug' => 'men']);
        $this->api('collections/'.$child->id, ['title' => 'Restore', 'slug' => 'restore', 'kind' => 'category', 'parent_id' => $root->id, 'position' => 0, 'active' => true, 'version' => 1])->assertNotFound();
        $support = StaffUser::create(['name' => 'Support', 'email' => 'archive-support@example.test', 'password' => 'Test-only-password-42!', 'staff_role_id' => StaffRole::where('name', 'support')->value('id'), 'two_factor_confirmed_at' => now()]);
        $support->refresh();
        $this->actingAs($support, 'staff')->withSession(['staff_version' => $support->auth_version, 'staff_authenticated_at' => now()->timestamp, 'staff_seen_at' => now()->timestamp]);
        $this->api('collections/1/archive', ['version' => 1, 'confirmation' => 'ARCHIVE'])->assertForbidden();
    }

    public function test_category_requires_a_top_level_parent_and_rejects_invalid_tree_changes(): void
    {
        $base = ['title' => 'Root', 'slug' => 'root', 'kind' => 'collection', 'active' => true, 'position' => 0];
        $root = $this->api('collections', $base)->assertCreated()->json('data.collection');
        $category = ['title' => 'Shirts', 'slug' => 'shirts', 'kind' => 'category', 'active' => true, 'position' => 1];
        $this->api('collections', $category)->assertUnprocessable();
        $category['parent_id'] = $root['id'];
        $child = $this->api('collections', $category)->assertCreated()->json('data.collection');
        $bad = array_merge($category, ['title' => 'Nested', 'slug' => 'nested', 'parent_id' => $child['id']]);
        $this->api('collections', $bad)->assertUnprocessable();
        $this->api('collections/'.$child['id'], array_merge($category, ['version' => $child['version'], 'parent_id' => $child['id']]))->assertUnprocessable();
        $this->api('collections/'.$root['id'], array_merge($base, ['version' => $root['version'], 'kind' => 'category', 'parent_id' => $child['id']]))->assertUnprocessable();
        $this->api('collections', array_merge($base, ['title' => 'Bad root', 'slug' => 'bad-root', 'parent_id' => $root['id']]))->assertUnprocessable();
        $this->api('collections/'.$child['id'], array_merge($category, ['version' => $child['version'] + 1]))->assertConflict();
        $this->assertDatabaseHas('catalogue_collections', ['id' => $child['id'], 'parent_id' => $root['id'], 'kind' => 'category']);
    }

    public function test_nested_products_roll_up_to_collection_and_public_menu_is_collections_only(): void
    {
        $a = DB::table('catalogue_collections')->insertGetId(['title' => 'Men', 'slug' => 'men', 'kind' => 'collection', 'active' => true]);
        $b = DB::table('catalogue_collections')->insertGetId(['title' => 'Women', 'slug' => 'women', 'kind' => 'collection', 'active' => true]);
        $cat = DB::table('catalogue_collections')->insertGetId(['title' => 'Tees', 'slug' => 'tees', 'kind' => 'category', 'active' => true, 'parent_id' => $a]);
        $orphan = DB::table('catalogue_collections')->insertGetId(['title' => 'Legacy orphan', 'slug' => 'legacy-orphan', 'kind' => 'category', 'active' => true]);
        $p = $this->create();
        CatalogueProduct::findOrFail($p['id'])->update(['status' => 'published']);
        DB::table('catalogue_collection_product')->where('product_id', $p['id'])->delete();
        DB::table('catalogue_collection_product')->insert(['collection_id' => $cat, 'product_id' => $p['id'], 'position' => 0]);
        $this->api('products?status=all&collection_id='.$a)->assertJsonPath('data.total', 1);
        $this->getJson('/api/v1/catalogue/products?collection=men')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/v1/catalogue/products?collection=tees')->assertOk()->assertJsonPath('data.total', 1);
        $menu = $this->getJson('/api/v1/catalogue/navigation')->assertOk()->json('data.items');
        $this->assertNotContains($cat, array_column($menu, 'id'));
        $this->assertNotContains($orphan, array_column($menu, 'id'));
        $this->assertEquals($cat, collect($menu)->firstWhere('id', $a)['categories'][0]['id']);
        $this->getJson('/api/v1/catalogue/products?collection=legacy-orphan')->assertNotFound();
        $this->getJson('/api/v1/catalogue/collections')->assertJsonPath('data.items.0.id', $a);
        // Overlapping old direct assignment must not duplicate the product.
        DB::table('catalogue_collection_product')->insert(['collection_id' => $a, 'product_id' => $p['id'], 'position' => 0]);
        $this->api('products?status=all&collection_id='.$a)->assertJsonPath('data.total', 1);
        DB::table('catalogue_collection_product')->where('collection_id', $a)->delete();
        DB::table('catalogue_collections')->where('id', $cat)->update(['parent_id' => $b]);
        $this->getJson('/api/v1/catalogue/products?collection=men')->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/catalogue/products?collection=women')->assertJsonPath('data.total', 1);
        DB::table('catalogue_collections')->where('id', $b)->update(['active' => false]);
        $this->getJson('/api/v1/catalogue/products?collection=tees')->assertNotFound();
        $menu = $this->getJson('/api/v1/catalogue/navigation')->json('data.items');
        $this->assertNotContains($b, array_column($menu, 'id'));
        $this->assertDatabaseHas('catalogue_collection_product', ['collection_id' => $cat, 'product_id' => $p['id']]);
    }

    public function test_staff_collection_filters_combine_all_statuses_search_and_pagination(): void
    {
        $a = DB::table('catalogue_collections')->insertGetId(['title' => 'Shirts', 'slug' => 'shirts', 'kind' => 'category', 'active' => true]);
        $b = DB::table('catalogue_collections')->insertGetId(['title' => 'Essentials', 'slug' => 'essentials', 'kind' => 'collection', 'active' => false]);
        $ids = [];
        for ($i = 1; $i <= 13; $i++) {
            $p = CatalogueProduct::create(['title' => 'Filtered shirt '.$i, 'slug' => 'filtered-shirt-'.$i, 'status' => $i === 1 ? 'published' : 'draft']);
            $ids[] = $p->id;
            DB::table('catalogue_collection_product')->insert(['collection_id' => $a, 'product_id' => $p->id, 'position' => $i]);
            if ($i === 13) {
                DB::table('catalogue_collection_product')->insert(['collection_id' => $b, 'product_id' => $p->id, 'position' => 1]);
                $p->update(['status' => 'archived']);
                $p->delete();
            }
        }
        $this->api('products?status=all&collection_id='.$a)->assertOk()->assertJsonPath('data.total', 13)->assertJsonCount(10, 'data.items')->assertJsonPath('data.last_page', 2);
        $this->api('products?status=all&collection_id='.$a.'&page=2')->assertOk()->assertJsonCount(3, 'data.items');
        $this->api('products?status=all&collection_id='.$b)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.status', 'archived');
        $this->api('products?status=draft&collection_id='.$a)->assertOk()->assertJsonPath('data.total', 11);
        $this->api('products?status=published&collection_id='.$a)->assertOk()->assertJsonPath('data.total', 1);
        $this->api('products?status=all&collection_id='.$b.'&q=missing')->assertOk()->assertJsonPath('data.total', 0);
        $this->api('products?status=all')->assertJsonPath('data.total', 13);
        $groups = $this->api('collections')->assertOk()->assertJsonPath('data.catalogue_total', 13)->json('data.items');
        $this->assertEquals(13, collect($groups)->firstWhere('id', $a)['product_count']);
        $this->assertEquals(1, collect($groups)->firstWhere('id', $b)['product_count']);
        $this->api('products?collection_id=999999')->assertUnprocessable();
        $this->getJson('/api/v1/catalogue/products/filtered-shirt-13')->assertNotFound();
    }

    public function test_staff_product_list_paginates_ten_with_stable_ids_and_filters(): void
    {
        $ids = [];
        for ($i = 1; $i <= 23; $i++) {
            $p = CatalogueProduct::create(['title' => 'Page product '.$i, 'slug' => 'page-product-'.$i]);
            $ids[] = $p->id;
        }
        $seen = [];
        foreach ([1 => 10, 2 => 10, 3 => 3] as $page => $count) {
            $response = $this->api('products?page='.$page)->assertOk()
                ->assertJsonCount($count, 'data.items')->assertJsonPath('data.total', 23)
                ->assertJsonPath('data.page', $page)->assertJsonPath('data.last_page', 3);
            $seen = array_merge($seen, array_column($response->json('data.items'), 'id'));
        }
        $this->assertCount(23, array_unique($seen));
        $this->assertEqualsCanonicalizing($ids, $seen);
        $this->api('products?q=Page%20product%2023')->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $ids[22])->assertJsonPath('data.last_page', 1);
        CatalogueProduct::findOrFail($ids[0])->delete();
        $this->api('products?page=3')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.total', 22);
        $this->api('products?q=Page%20product%2023')->assertJsonPath('data.items.0.id', $ids[22]);
    }

    public function test_full_create_preview_publish_public_unpublish_journey(): void
    {
        $p = $this->create();
        $this->assertCount(4, $p['variants']);
        $this->assertDatabaseCount('catalogue_options', 2);
        $this->assertDatabaseCount('catalogue_option_values', 4);
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertNotFound();
        $this->api('products/'.$p['id'])->assertOk()->assertJsonPath('data.product.status', 'draft');
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $media = $p['media'][0]['id'];
        $this->get('/api/v1/catalogue/media/'.$media.'/640')->assertNotFound();
        $p = $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->json('data.product');
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertOk()->assertJsonCount(4, 'data.product.variants')->assertJsonMissingPath('data.product.version');
        $this->get('/api/v1/catalogue/media/'.$media.'/640')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->api('products/'.$p['id'].'/unpublish', ['version' => $p['version']])->assertOk();
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertNotFound();
        $this->get('/api/v1/catalogue/media/'.$media.'/640')->assertNotFound();
    }

    public function test_optional_material_fit_and_care_do_not_block_publication(): void
    {
        $p = $this->upload($this->create())->assertCreated()->json('data.product');
        $body = $this->saveBody($p);
        $body['material'] = null;
        $body['fit'] = '';
        $body['care'] = null;
        $p = $this->api('products/'.$p['id'], $body)->assertOk()->json('data.product');
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->assertJsonPath('data.product.status', 'published');
        $this->assertDatabaseHas('catalogue_products', ['id' => $p['id'], 'material' => null, 'fit' => null, 'care' => null, 'status' => 'published']);
    }

    public function test_incomplete_draft_cannot_publish_and_errors_preserve_records(): void
    {
        $p = $this->create();
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertUnprocessable()->assertJsonValidationErrors('media', 'error.details');
        $this->assertDatabaseHas('catalogue_products', ['id' => $p['id'], 'status' => 'draft']);
    }

    public function test_duplicate_skus_barcodes_combinations_negative_fractional_prices_and_invalid_collections_fail(): void
    {
        $p = $this->create();
        foreach (['sku', 'barcode'] as $field) {
            $body = $this->saveBody($p);
            if ($field === 'barcode') {
                $body['variants'][0]['barcode'] = 'UNIQUE-123';
                $p = $this->api('products/'.$p['id'], $body)->assertOk()->json('data.product');
            }$bad = $this->payload();
            $bad['slug'] = 'other-'.$field;
            if ($field === 'barcode') {
                foreach ($bad['variants'] as &$row) {
                    $row['sku'] = 'OTHER-'.$row['sku'];
                }unset($row);
            }
            $bad['variants'][0][$field] = $field === 'sku' ? $p['variants'][0]['sku'] : 'UNIQUE-123';
            $this->api('products', $bad)->assertUnprocessable()->assertJsonValidationErrors('variants.0.'.$field, 'error.details');
        }
        foreach ([-1, 12.5] as $price) {
            $bad = $this->payload();
            $bad['variants'][0]['price_minor'] = $price;
            $this->api('products', $bad)->assertUnprocessable()->assertJsonValidationErrors('variants.0.price_minor', 'error.details');
        }
        $bad = $this->payload();
        $bad['variants'][1]['size'] = 'M';
        $this->api('products', $bad)->assertUnprocessable();
        $bad = $this->payload();
        $bad['collection_ids'] = [99999];
        $this->api('products', $bad)->assertUnprocessable();
    }

    public function test_published_edits_require_unpublish_and_stale_version_is_conflict(): void
    {
        $p = $this->publish();
        $this->api('products/'.$p['id'], $this->saveBody($p))->assertUnprocessable();
        $this->api('products/'.$p['id'].'/unpublish', ['version' => 1])->assertConflict();
        $this->assertDatabaseHas('catalogue_products', ['status' => 'published']);
    }

    public function test_slug_aliases_redirect_only_when_target_is_published_and_cannot_be_reused(): void
    {
        $p = $this->publish();
        $p = $this->api('products/'.$p['id'].'/unpublish', ['version' => $p['version']])->assertOk()->json('data.product');
        $body = $this->saveBody($p);
        $body['slug'] = 'renamed-polo';
        $p = $this->api('products/'.$p['id'], $body)->assertOk()->json('data.product');
        $this->getJson('/api/v1/catalogue/products/test-only-polo')->assertNotFound();
        $p = $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->json('data.product');
        $this->getJson('/api/v1/catalogue/products/test-only-polo')->assertStatus(301)->assertHeader('Location', url('/api/v1/catalogue/products/renamed-polo'));
        $bad = $this->payload();
        $this->api('products', $bad)->assertUnprocessable()->assertJsonValidationErrors('slug', 'error.details');
    }

    public function test_public_only_active_variants_and_media_no_internal_fields(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $b = $this->saveBody($p);
        $b['variants'][0]['active'] = false;
        $p = $this->api('products/'.$p['id'], $b)->assertOk()->json('data.product');
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk();
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertJsonCount(3, 'data.product.variants')->assertJsonMissingPath('data.product.variants.0.barcode')->assertJsonMissingPath('data.product.media.0.sha256');
        $this->getJson('/api/v1/catalogue/products?per_page=500')->assertUnprocessable();
    }

    public function test_media_invalid_svg_corruption_size_and_wrong_variant_are_rejected(): void
    {
        $p = $this->create();
        foreach ([['bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'], ['fake.png', 'not an image'], ['huge.png', str_repeat('x', 9 * 1024 * 1024)]] as [$name,$content]) {
            $this->upload($p, $name, 'image/png', $content)->assertUnprocessable();
        }$this->assertDatabaseCount('catalogue_media', 0);
    }

    public function test_responsive_derivatives_order_alt_and_archival_keep_original(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $first = $p['media'][0]['id'];
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $items = array_map(fn ($m) => ['id' => $m['id'], 'alt' => 'Updated descriptive alt', 'variant_id' => $p['variants'][0]['id']], array_reverse($p['media']));
        $p = $this->api('products/'.$p['id'].'/media-order', ['version' => $p['version'], 'items' => $items])->assertOk()->assertJsonPath('data.product.media.0.id', $items[0]['id'])->json('data.product');
        $this->assertCount(2, $p['media'][0]['sources']);
        $this->assertSame([640, 800], array_column($p['media'][0]['sources'], 'width'));
        $this->assertFileExists($this->mediaRoot.'/'.$first.'/original');
        $this->api('products/'.$p['id'].'/media/'.$first.'/archive', ['version' => $p['version'], 'confirmation' => 'ARCHIVE'])->assertOk();
        $this->assertDatabaseHas('catalogue_media', ['id' => $first]);
        $this->assertFileExists($this->mediaRoot.'/'.$first.'/1280.webp');
        $this->get('/api/v1/catalogue/media/'.$first.'/640')->assertNotFound();
    }

    private function location(string $code = 'MAIN'): int
    {
        return $this->api('locations', ['name' => 'QA '.$code, 'code' => $code])->assertCreated()->json('data.location.id');
    }

    private function adjustment(array $p, int $loc, int $delta = 20): array
    {
        return ['operation_id' => (string) Str::uuid(), 'variant_id' => $p['variants'][0]['id'], 'location_id' => $loc, 'delta' => $delta, 'reason' => 'QA supplier delivery', 'reference' => 'QA-PO-001'];
    }

    public function test_stock_reason_idempotency_history_reserved_floor_and_actor(): void
    {
        $p = $this->create();
        $loc = $this->location();
        $v = $this->adjustment($p, $loc);
        $bad = $v;
        $bad['reason'] = '';
        $this->api('adjustments', $bad)->assertUnprocessable();
        $this->api('adjustments', $v)->assertCreated()->assertJsonPath('data.movements.0.on_hand_after', 20)->assertJsonPath('data.movements.0.staff_user_id', $this->staff->id);
        $this->api('adjustments', $v)->assertOk()->assertJsonPath('data.replayed', true);
        $bad = $v;
        $bad['delta'] = 99;
        $this->api('adjustments', $bad)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 1);
        DB::table('stock_balances')->update(['reserved' => 5]);
        $this->api('adjustments', $this->adjustment($p, $loc, -16))->assertUnprocessable();
        $this->api('adjustments', $this->adjustment($p, $loc, -15))->assertCreated();
        $this->api('inventory')->assertOk()->assertJsonPath('data.items.0.balances.0.available', 0);
        $this->api('movements?variant_id='.$p['variants'][0]['id'])->assertOk()->assertJsonCount(2, 'data.items');
    }

    public function test_transfers_are_atomic_and_retry_safe(): void
    {
        $p = $this->create();
        $a = $this->location();
        $b = $this->location('SECOND');
        $this->api('adjustments', $this->adjustment($p, $a))->assertCreated();
        $v = ['operation_id' => (string) Str::uuid(), 'variant_id' => $p['variants'][0]['id'], 'location_id' => $a, 'to_location_id' => $b, 'quantity' => 8, 'reason' => 'QA relocation'];
        $this->api('transfers', $v)->assertCreated()->assertJsonCount(2, 'data.movements');
        $this->api('transfers', $v)->assertOk()->assertJsonPath('data.replayed', true);
        $bad = $v;
        $bad['operation_id'] = (string) Str::uuid();
        $bad['quantity'] = 99;
        $this->api('transfers', $bad)->assertUnprocessable();
        $this->assertDatabaseHas('stock_balances', ['location_id' => $a, 'on_hand' => 12]);
        $this->assertDatabaseHas('stock_balances', ['location_id' => $b, 'on_hand' => 8]);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_movements_are_immutable_in_database(): void
    {
        $p = $this->create();
        $this->api('adjustments', $this->adjustment($p, $this->location()))->assertCreated();
        foreach (['update', 'delete'] as $op) {
            try {
                $op === 'update' ? DB::table('stock_movements')->update(['delta' => 9]) : DB::table('stock_movements')->delete();
                $this->fail('Ledger mutation allowed');
            } catch (QueryException $e) {
                $this->assertStringContainsString('immutable', $e->getMessage());
            }
        }
    }

    public function test_archival_keeps_variant_stock_media_references_and_sku_reserved(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $location = $this->location();
        $this->api('adjustments', $this->adjustment($p, $location))->assertCreated();
        $this->api('products/'.$p['id'].'/archive', ['version' => $p['version'], 'confirmation' => 'ARCHIVE'])->assertOk();
        $this->assertSoftDeleted('catalogue_products', ['id' => $p['id']]);
        $this->assertDatabaseCount('catalogue_variants', 4);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('catalogue_media', 1);
        $bad = $this->payload();
        $bad['slug'] = 'another';
        $this->api('products', $bad)->assertUnprocessable();
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertNotFound();
        $this->api('adjustments', $this->adjustment($p, $location))->assertNotFound();
    }

    public function test_collections_order_and_public_visibility(): void
    {
        $p = $this->create();
        $c = $p['collections'][0]['id'];
        $this->api('collections/'.$c.'/order', ['version' => 2, 'product_ids' => [$p['id']]])->assertOk();
        $this->api('collections/'.$c.'/order', ['version' => 3, 'product_ids' => []])->assertUnprocessable();
        $this->getJson('/api/v1/catalogue/collections')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/catalogue/products?collection=qa-collection')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_support_can_read_but_every_write_family_is_server_denied(): void
    {
        $this->staff->staff_role_id = StaffRole::where('name', 'support')->value('id');
        $this->staff->save();
        $this->staff->refresh();
        $this->login();
        foreach (['products', 'collections', 'locations', 'adjustments', 'transfers', 'products/1/publish', 'products/1/media-order', 'products/1/media'] as $path) {
            $this->api($path, [])->assertForbidden();
        }$this->api('products')->assertOk();
        $this->assertDatabaseCount('catalogue_products', 0);
    }

    public function test_unauthenticated_and_missing_csrf_writes_denied(): void
    {
        $this->postJson('/api/v1/staff/catalogue/products', $this->payload(), ['Origin' => config('staff.admin_origin')])->assertStatus(419);
        Auth::guard('staff')->logout();
        $this->withSession([]);
        $this->api('products')->assertUnauthorized();
        $this->api('products', $this->payload())->assertUnauthorized();
    }

    public function test_media_cannot_link_to_other_products_and_inactive_linked_media_is_private(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $media = $p['media'][0];
        $other = $this->payload();
        $other['slug'] = 'other-product';
        foreach ($other['variants'] as &$v) {
            $v['sku'] = 'OTHER-'.$v['sku'];
        }unset($v);
        $other = $this->api('products', $other)->assertCreated()->json('data.product');
        $this->api('products/'.$p['id'].'/media-order', ['version' => $p['version'], 'items' => [['id' => $media['id'], 'alt' => 'Test alt', 'variant_id' => $other['variants'][0]['id']]]])->assertUnprocessable()->assertJsonValidationErrors('items', 'error.details');
        $p = $this->api('products/'.$p['id'].'/media-order', ['version' => $p['version'], 'items' => [['id' => $media['id'], 'alt' => 'Test alt', 'variant_id' => $p['variants'][0]['id']]]])->assertOk()->json('data.product');
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $b = $this->saveBody($p);
        $b['variants'][0]['active'] = false;
        $p = $this->api('products/'.$p['id'], $b)->assertOk()->json('data.product');
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk();
        $this->getJson('/api/v1/catalogue/products/'.$p['slug'])->assertOk()->assertJsonCount(1, 'data.product.media');
        $this->get('/api/v1/catalogue/media/'.$media['id'].'/640')->assertNotFound();
    }

    public function test_archived_rows_with_historical_references_cannot_be_hard_deleted(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $location = $this->location();
        $this->api('adjustments', $this->adjustment($p, $location))->assertCreated();
        foreach (['catalogue_products' => $p['id'], 'catalogue_variants' => $p['variants'][0]['id'], 'stock_locations' => $location] as $table => $id) {
            try {
                DB::table($table)->where('id', $id)->delete();
                $this->fail('Historical foreign key allowed deletion: '.$table);
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_reservation_quantities_and_publication_state_cannot_be_forged(): void
    {
        $p = $this->create();
        $body = $this->saveBody($p);
        $body['status'] = 'published';
        $this->api('products/'.$p['id'], $body)->assertOk()->assertJsonPath('data.product.status', 'draft');
        $v = $this->adjustment($p, $this->location());
        $v['reserved'] = 20;
        $this->api('adjustments', $v)->assertUnprocessable()->assertJsonValidationErrors('reserved', 'error.details');
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_missing_measurements_for_some_sizes_do_not_block_publication(): void
    {
        $p = $this->create();
        $p = $this->upload($p)->assertCreated()->json('data.product');
        $body = $this->saveBody($p);
        $body['measurements'] = [$body['measurements'][0]];
        $p = $this->api('products/'.$p['id'], $body)->assertOk()->json('data.product');
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->assertJsonPath('data.product.status', 'published');
    }

    public function test_empty_measurements_can_publish_but_supplied_invalid_measurements_are_rejected(): void
    {
        $p = $this->upload($this->create())->assertCreated()->json('data.product');
        $body = $this->saveBody($p);
        $body['measurements'] = [['size' => 'M', 'chest_cm' => -1, 'length_cm' => 0, 'shoulder_cm' => 40]];
        $this->api('products/'.$p['id'], $body)->assertUnprocessable();
        $body['measurements'] = [];
        $p = $this->api('products/'.$p['id'], $body)->assertOk()->json('data.product');
        $this->api('products/'.$p['id'].'/publish', ['version' => $p['version']])->assertOk()->assertJsonPath('data.product.status', 'published');
        $this->assertDatabaseHas('catalogue_products', ['id' => $p['id'], 'measurements' => '[]']);
    }

    public function test_product_edits_preserve_collection_product_order(): void
    {
        $p = $this->create();
        $collection = $p['collections'][0]['id'];
        DB::table('catalogue_collection_product')->where('product_id', $p['id'])->update(['position' => 9]);
        $body = $this->saveBody($p);
        $body['title'] = 'Edited without reordering';
        $this->api('products/'.$p['id'], $body)->assertOk();
        $this->assertDatabaseHas('catalogue_collection_product', ['product_id' => $p['id'], 'collection_id' => $collection, 'position' => 9]);
    }
}
