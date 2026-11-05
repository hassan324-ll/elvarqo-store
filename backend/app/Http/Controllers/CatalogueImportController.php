<?php

namespace App\Http\Controllers;

use App\Catalogue\CatalogueData;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\ImportArchive;
use App\Catalogue\ImportProduct;
use App\Catalogue\MediaWriter;
use App\Models\CatalogueVariant;
use App\Security\StaffAudit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CatalogueImportController extends Controller
{
    public function template()
    {
        return response()->file(resource_path('imports/products.example.json'), ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="elvarqo-products.example.json"', 'Cache-Control' => 'no-store, private']);
    }

    public function history()
    {
        return CatalogueData::ok(['items' => DB::table('catalogue_import_batches')->where('staff_user_id', auth('staff')->id())->orderByDesc('created_at')->limit(25)->get(['id', 'created_at', 'expires_at'])]);
    }

    public function preview(Request $r)
    {
        $r->validate(['products' => 'required|file|max:1024', 'images' => 'nullable|file|max:32768']);
        try {
            $document = json_decode(file_get_contents($r->file('products')->getRealPath()), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            CatalogueWriter::fail('products', 'Choose a valid UTF-8 JSON document (maximum depth 32).');
        }
        validator(['document' => $document], ['document' => 'required|array:schema_version,products', 'document.schema_version' => 'required|integer|in:1', 'document.products' => 'required|array|min:1|max:'.config('catalogue_import.max_products'), 'document.products.*' => 'required|array'])->validate();
        if (! array_is_list($document['products'])) {
            CatalogueWriter::fail('products', 'Products must be a JSON list.');
        }
        if (DB::table('catalogue_import_batches')->where('staff_user_id', auth('staff')->id())->where('expires_at', '>', now())->count() >= 10) {
            CatalogueWriter::fail('products', 'You have 10 active previews. Resume an existing batch or wait until a preview expires.');
        }
        $id = (string) Str::uuid();
        $root = config('catalogue_import.root').'/'.$id;
        File::ensureDirectoryExists($root, 0700);
        try {
            $files = $r->hasFile('images') ? ImportArchive::unpack($r->file('images')->getRealPath(), $root.'/images') : [];
            $source = $root.'/products.json';
            copy($r->file('products')->getRealPath(), $source);
            chmod($source, 0600);
            DB::transaction(function () use ($document, $id, $files, $source) {
                DB::table('catalogue_import_batches')->insert(['id' => $id, 'staff_user_id' => auth('staff')->id(), 'source_sha256' => hash_file('sha256', $source), 'expires_at' => now()->addDays(2), 'created_at' => now(), 'updated_at' => now()]);
                $seen = [];
                foreach ($document['products'] as $index => $raw) {
                    $external = $raw['supplier']['product_id'] ?? null;
                    $external = is_string($external) && preg_match('/^[0-9]{8,40}$/D', $external) ? $external : null;
                    $title = is_string($raw['title'] ?? null) ? Str::limit($raw['title'], 160, '') : 'Untitled row '.($index + 1);
                    $payload = [];
                    $preview = ['title' => $title, 'groups' => [], 'images' => [], 'warnings' => []];
                    foreach (is_array($raw['images'] ?? null) ? array_slice($raw['images'], 0, 30) : [] as $image) {
                        if (is_array($image) && is_string($image['file'] ?? null)) {
                            $file = $files[$image['file']] ?? [];
                            $preview['images'][] = ['file' => Str::limit($image['file'], 220, ''), 'alt' => is_string($image['alt'] ?? null) ? Str::limit($image['alt'], 240, '') : '', 'matched' => isset($files[$image['file']]), 'error' => $file['error'] ?? null];
                        }
                    }
                    $errors = [];
                    $status = 'ready';
                    try {
                        $plan = ImportProduct::plan($raw, $files);
                        $payload = $plan['payload'];
                        $preview = $plan['preview'];
                    } catch (ValidationException $e) {
                        $errors = $e->errors();
                        $status = 'invalid';
                    }
                    if ($external && (isset($seen[$external]) || DB::table('catalogue_supplier_products')->where('supplier', 'AliExpress')->where('external_id', $external)->exists())) {
                        $errors['supplier.product_id'] = ['AliExpress ID already exists in this file or the store (including deleted products). Existing products are never overwritten.'];
                        $status = 'duplicate';
                    }
                    if ($external) {
                        $seen[$external] = true;
                    }
                    DB::table('catalogue_import_items')->insert(['batch_id' => $id, 'position' => $index + 1, 'title' => $title, 'external_id' => $external, 'status' => $status, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'preview' => json_encode($preview, JSON_THROW_ON_ERROR), 'errors' => json_encode($errors, JSON_THROW_ON_ERROR), 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
                }
                StaffAudit::record('catalogue.import_previewed', auth('staff')->id(), 'import:'.$id);
            });
        } catch (\Throwable $e) {
            File::deleteDirectory($root);
            throw $e;
        }

        return $this->show($id);
    }

    private function batch(string $id): object
    {
        $batch = DB::table('catalogue_import_batches')->where('id', $id)->where('staff_user_id', auth('staff')->id())->first();
        abort_unless($batch, 404);

        return $batch;
    }

    public function show(string $batch)
    {
        $record = $this->batch($batch);
        $items = DB::table('catalogue_import_items')->where('batch_id', $batch)->orderBy('position')->get()->map(function ($item) {
            return ['id' => $item->id, 'position' => $item->position, 'status' => $item->status, 'product_id' => $item->product_id, 'attempts' => $item->attempts, 'preview' => json_decode($item->preview, true), 'errors' => json_decode($item->errors, true)];
        });

        return CatalogueData::ok(['batch' => ['id' => $batch, 'expires_at' => $record->expires_at, 'expired' => now()->greaterThan($record->expires_at), 'items' => $items, 'summary' => $items->countBy('status')]])->header('Cache-Control', 'no-store, private');
    }

    public function run(Request $r, string $batch, int $item)
    {
        $record = $this->batch($batch);
        abort_if(now()->greaterThan($record->expires_at), 410, 'Preview expired. Upload a new preview; existing supplier IDs remain protected.');
        $r->validate(['confirm_matches' => 'required|accepted']);
        $directories = [];
        try {
            DB::transaction(function () use ($batch, $item, &$directories) {
                $row = DB::table('catalogue_import_items')->where('batch_id', $batch)->where('id', $item)->lockForUpdate()->first();
                abort_unless($row, 404);
                if ($row->status === 'imported') {
                    return;
                }
                if (! in_array($row->status, ['ready', 'failed'], true)) {
                    abort(409, 'Correct this row and upload a new preview.');
                }
                $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
                if (DB::table('catalogue_supplier_products')->where('supplier', 'AliExpress')->where('external_id', $payload['supplier']['product_id'])->exists()) {
                    CatalogueWriter::fail('supplier.product_id', 'Already imported in another batch. Existing content was not changed.');
                }
                $snapshots = $payload['group_snapshots'] ?? [];
                $currentGroups = DB::table('catalogue_collections')->whereIn('id', array_column($snapshots, 'id'))->whereNull('deleted_at')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($snapshots as $snapshot) {
                    $current = $currentGroups->get($snapshot['id']);
                    foreach (['title', 'slug', 'kind', 'parent_id'] as $field) {
                        if (! $current || (string) $current->$field !== (string) $snapshot[$field]) {
                            CatalogueWriter::fail('groups', 'A reviewed collection/category was renamed, moved or removed. Upload a new preview to confirm the current assignments.');
                        }
                    }
                }
                $product = CatalogueWriter::save(new Request($payload['catalogue']));
                $supplier = $payload['supplier'];
                $supplierId = DB::table('catalogue_supplier_products')->insertGetId(['product_id' => $product->id, 'supplier' => 'AliExpress', 'external_id' => $supplier['product_id'], 'url' => $supplier['url'], 'cost_minor' => $supplier['cost_minor'] ?? null, 'currency' => $supplier['currency'] ?? null, 'shipping_cost_minor' => $supplier['shipping_cost_minor'] ?? null, 'checked_at' => $supplier['checked_at'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($payload['supplier_variants'] as $variant) {
                    $index = $variant['catalogue_index'];
                    $localId = $index === null ? null : CatalogueVariant::where('product_id', $product->id)->where('sku', $payload['catalogue']['variants'][$index]['sku'])->value('id');
                    DB::table('catalogue_supplier_variants')->insert(['supplier_product_id' => $supplierId, 'variant_id' => $localId, 'external_id' => $variant['external_id'], 'cost_minor' => $variant['cost_minor'], 'currency' => $variant['currency'], 'shipping_cost_minor' => $variant['shipping_cost_minor'], 'checked_at' => $variant['checked_at'], 'pending_catalogue' => $variant['pending_catalogue'] === null ? null : json_encode($variant['pending_catalogue'], JSON_THROW_ON_ERROR)]);
                }
                foreach ($payload['images'] as $image) {
                    $path = config('catalogue_import.root').'/'.$batch.'/images/'.$image['file'];
                    if (! is_file($path) || ! hash_equals($image['sha256'], hash_file('sha256', $path))) {
                        CatalogueWriter::fail('images', 'Staged image is missing or changed. Upload a new preview.');
                    }
                    // Keep the staged original for retries; the existing upload pipeline moves its input.
                    $temporary = tempnam(config('catalogue_import.root').'/'.$batch, 'upload-');
                    try {
                        if (! copy($path, $temporary)) {
                            throw new \RuntimeException('Image staging failed.');
                        }
                        $upload = new Request(['version' => $product->version, 'alt' => $image['alt']]);
                        $upload->files->set('file', new UploadedFile($temporary, basename($image['file']), null, null, true));
                        $saved = MediaWriter::upload($upload, $product->id);
                        $directories[] = config('catalogue.media_root').'/'.$saved['media']->id;
                        $product = $saved['product'];
                    } finally {
                        if (is_file($temporary)) {
                            unlink($temporary);
                        }
                    }
                }
                DB::table('catalogue_import_items')->where('id', $item)->update(['status' => 'imported', 'product_id' => $product->id, 'errors' => '[]', 'attempts' => $row->attempts + 1, 'updated_at' => now()]);
                StaffAudit::record('catalogue.import_completed', auth('staff')->id(), 'import-item:'.$item);
            });
        } catch (\Throwable $e) {
            foreach ($directories as $directory) {
                File::deleteDirectory($directory);
            }
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
            $errors = $e instanceof ValidationException ? $e->errors() : ['import' => ['The product was rolled back. Check storage, image processing and unique supplier/SKU identifiers, then retry. Reference: '.$batch.'/'.$item]];
            if (! $e instanceof ValidationException) {
                Log::warning('Catalogue import failed.', ['batch' => $batch, 'item' => $item, 'exception_class' => $e::class]);
            }
            DB::table('catalogue_import_items')->where('batch_id', $batch)->where('id', $item)->whereIn('status', ['ready', 'failed'])->update(['status' => 'failed', 'errors' => json_encode($errors, JSON_THROW_ON_ERROR), 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
            StaffAudit::record('catalogue.import_failed', auth('staff')->id(), 'import-item:'.$item, 'failed');
        }

        return $this->show($batch);
    }

    public function supplier(int $id)
    {
        $supplier = DB::table('catalogue_supplier_products')->where('product_id', $id)->first();
        $variants = $supplier ? DB::table('catalogue_supplier_variants')->where('supplier_product_id', $supplier->id)->get()->map(function ($row) {
            $row->pending_catalogue = $row->pending_catalogue ? json_decode($row->pending_catalogue, true) : null;

            return $row;
        }) : [];

        return CatalogueData::ok(['supplier' => $supplier, 'variants' => $variants])->header('Cache-Control', 'no-store, private');
    }

    public function image(string $batch, int $item, int $index)
    {
        $this->batch($batch);
        $row = DB::table('catalogue_import_items')->where('batch_id', $batch)->where('id', $item)->first();
        abort_unless($row, 404);
        $image = json_decode($row->payload, true)['images'][$index] ?? null;
        abort_unless($image && ImportArchive::safePath($image['file']), 404);
        $path = config('catalogue_import.root').'/'.$batch.'/images/'.$image['file'];
        abort_unless(is_file($path), 404);

        return response()->file($path, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
