<?php

namespace Tests\Feature;

use App\Models\CatalogueCollection;
use App\Models\CatalogueProduct;
use App\Models\CatalogueVariant;
use App\Models\StockLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $title = 'QA Polo', string $slug = 'qa-polo', string $status = 'published', int $price = 6800): CatalogueProduct
    {
        $p = CatalogueProduct::create(['title' => $title, 'slug' => $slug, 'status' => $status, 'description' => 'Verified fixture description', 'product_type' => 'Knitwear', 'material' => 'Test composition', 'fit' => 'Test regular fit', 'care' => 'Test care', 'measurements' => [['size' => 'M', 'chest_cm' => 104, 'length_cm' => 70, 'shoulder_cm' => 44]], 'seo_title' => $title.' | ELVARQO', 'seo_description' => 'Test-only fixture', 'published_at' => now(), 'first_published_at' => now()]);
        DB::table('catalogue_slugs')->insert(['slug' => $slug, 'product_id' => $p->id]);
        $options = [];
        foreach (['colour', 'size'] as $name) {
            $options[$name] = DB::table('catalogue_options')->insertGetId(['product_id' => $p->id, 'name' => $name]);
        }
        foreach ([['Navy', 'M', $price, 5], ['Sand', 'L', $price + 2000, 0]] as [$colour,$size,$selling,$stock]) {
            $c = DB::table('catalogue_option_values')->insertGetId(['option_id' => $options['colour'], 'value' => $colour]);
            $s = DB::table('catalogue_option_values')->insertGetId(['option_id' => $options['size'], 'value' => $size]);
            $v = CatalogueVariant::create(['product_id' => $p->id, 'colour_value_id' => $c, 'size_value_id' => $s, 'sku' => 'QA-'.$p->id.'-'.$size, 'price_minor' => $selling, 'currency' => 'GBP', 'active' => true]);
            $l = StockLocation::firstOrCreate(['code' => 'QA'], ['name' => 'Test-only warehouse']);
            DB::table('stock_balances')->insert(['variant_id' => $v->id, 'location_id' => $l->id, 'on_hand' => $stock, 'reserved' => 0]);
        }

        return $p;
    }

    public function test_trending_uses_recent_confirmed_paid_sales_scoped_to_visible_categories(): void
    {
        $this->travelTo(now()->startOfSecond());
        $root = CatalogueCollection::create(['title' => 'Women', 'slug' => 'women', 'kind' => 'collection', 'active' => true]);
        $category = CatalogueCollection::create(['title' => 'Knitwear', 'slug' => 'womens-knitwear', 'kind' => 'category', 'parent_id' => $root->id, 'active' => true]);
        $a = $this->fixture('Recent bestseller', 'bestseller');
        $a->update(['first_published_at' => now()->subDays(10)]);
        $b = $this->fixture('Newest launch', 'launch');
        $outside = $this->fixture('Outside Women', 'outside-women');
        $draft = $this->fixture('Private', 'private-trending', 'draft');
        foreach ([$a, $b, $draft] as $p) {
            DB::table('catalogue_collection_product')->insert(['collection_id' => $category->id, 'product_id' => $p->id, 'position' => 0]);
        }
        $sale = function ($p, $quantity, $days = 1, $payment = 'paid', $state = 'confirmed') {
            $bag = (string) Str::uuid();
            $order = (string) Str::uuid();
            DB::table('commerce_bags')->insert(['id' => $bag, 'token_hash' => hash('sha256', $bag), 'items' => '[]', 'revision' => 1, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_orders')->insert(['id' => $order, 'bag_id' => $bag, 'reference' => 'QA-'.$order, 'order_state' => $state, 'payment_state' => $payment, 'fulfilment_state' => 'unfulfilled', 'total_minor' => 6800 * $quantity, 'currency' => 'GBP', 'reservation_expires_at' => now()->addHour(), 'paid_at' => now()->subDays($days), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('commerce_order_items')->insert(['order_id' => $order, 'variant_id' => CatalogueVariant::where('product_id', $p->id)->value('id'), 'product_id' => $p->id, 'sku' => 'QA', 'title' => $p->title, 'colour' => 'Navy', 'size' => 'M', 'quantity' => $quantity, 'unit_minor' => 6800, 'line_minor' => 6800 * $quantity, 'currency' => 'GBP', 'created_at' => now()]);
        };
        $sale($a, 3);
        $sale($b, 100, 31); // Old sales must not affect recent trends.
        $sale($b, 100, 1, 'pending');
        $sale($b, 100, 1, 'paid', 'cancelled');
        $sale($outside, 100);
        $sale($draft, 100);
        $response = $this->getJson('/api/v1/catalogue/products?collection=women&sort=trending')->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.items.0.id', $a->id)->assertJsonMissingPath('data.items.0.trend_score');
        $this->assertSame([$a->id, $b->id], array_column($response->json('data.items'), 'id'));
        $this->getJson('/api/v1/catalogue/products?collection=women&sort=newest')->assertJsonPath('data.items.0.id', $b->id);
        $this->travel(31)->days();
        $this->getJson('/api/v1/catalogue/products?collection=women&sort=trending')->assertJsonPath('data.items.0.id', $b->id);
        $category->update(['active' => false]);
        $this->getJson('/api/v1/catalogue/products?collection=women&sort=trending')->assertJsonPath('data.total', 0);
        $this->travelBack();
    }

    public function test_navigation_lists_only_active_collections_in_admin_order_even_before_products(): void
    {
        $later = CatalogueCollection::create(['title' => 'Knitwear', 'slug' => 'knitwear', 'active' => true, 'position' => 20]);
        $first = CatalogueCollection::create(['title' => 'T-Shirts', 'slug' => 't-shirts', 'active' => true, 'position' => 1]);
        CatalogueCollection::create(['title' => 'Private group', 'slug' => 'private-group', 'active' => false, 'position' => 0]);
        $draft = $this->fixture('Private draft', 'private-draft', 'draft');
        DB::table('catalogue_collection_product')->insert(['collection_id' => $first->id, 'product_id' => $draft->id, 'position' => 0]);
        $this->getJson('/api/v1/catalogue/navigation')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.id', $first->id)
            ->assertJsonPath('data.items.1.id', $later->id)->assertJsonMissingPath('data.items.0.version');
        $this->getJson('/api/v1/catalogue/products?collection=t-shirts')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/catalogue/products/private-draft')->assertNotFound();
        $this->getJson('/api/v1/catalogue/collections')->assertJsonCount(0, 'data.items');
        $first->update(['active' => false]);
        $this->getJson('/api/v1/catalogue/navigation')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $later->id);
        $later->update(['title' => 'New layers', 'position' => 0]);
        $this->getJson('/api/v1/catalogue/navigation')->assertJsonPath('data.items.0.title', 'New layers');
    }

    public function test_variant_filters_must_match_one_active_variant_and_return_matching_price(): void
    {
        $p = $this->fixture();
        foreach (['colour=Navy&size=L', 'size=L&max_price=7000', 'size=L&stock=in_stock'] as $query) {
            $this->getJson('/api/v1/catalogue/products?'.$query)->assertOk()->assertJsonPath('data.total', 0);
        }
        $this->getJson('/api/v1/catalogue/products?colour=sand&size=L&min_price=8000')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.listing_price_minor', 8800)->assertJsonCount(1, 'data.items.0.matched_variant_ids')->assertJsonCount(2, 'data.items.0.variants');
    }

    public function test_search_is_literal_bound_and_never_exposes_drafts_inactive_skus_or_hidden_facets(): void
    {
        $this->fixture();
        $draft = $this->fixture('Secret hidden velvet', 'secret-draft', 'draft');
        $p = $this->fixture('100% linen fixture', 'percent');
        $variant = CatalogueVariant::where('product_id', $draft->id)->first();
        $this->getJson('/api/v1/catalogue/products?q=secret')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/catalogue/products?q='.urlencode('%'))->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.items.0.id', $p->id);
        $this->getJson('/api/v1/catalogue/products?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/catalogue/products?q='.$variant->sku)->assertJsonPath('data.total', 0);
        CatalogueVariant::where('product_id', $p->id)->update(['active' => false]);
        $this->getJson('/api/v1/catalogue/products?q=linen')->assertJsonPath('data.total', 0);
    }

    public function test_sort_and_pagination_have_stable_counts_and_no_duplicate_products(): void
    {
        $a = $this->fixture('QA First', 'first', 'published', 6800);
        $b = $this->fixture('QA Cheapest', 'cheapest', 'published', 4500);
        $c = $this->fixture('QA Same price', 'same', 'published', 6800);
        $r = $this->getJson('/api/v1/catalogue/products?sort=price_asc&per_page=2')->assertOk()->assertJsonPath('data.total', 3)->assertJsonPath('data.last_page', 2);
        $this->assertSame([$b->id, $a->id], array_column($r->json('data.items'), 'id'));
        $this->getJson('/api/v1/catalogue/products?sort=price_asc&per_page=2&page=2')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $c->id);
        $r = $this->getJson('/api/v1/catalogue/products?sort=price_desc')->assertOk();
        $this->assertSame([$a->id, $c->id, $b->id], array_column($r->json('data.items'), 'id'));
    }

    public function test_empty_collections_hidden_and_collection_order_is_respected(): void
    {
        $a = $this->fixture();
        $b = $this->fixture('Other', 'other');
        $parent = CatalogueCollection::create(['title' => 'QA root', 'slug' => 'qa-root', 'active' => true, 'kind' => 'collection']);
        $collection = CatalogueCollection::create(['title' => 'QA active', 'slug' => 'qa-active', 'active' => true, 'kind' => 'category', 'parent_id' => $parent->id]);
        $empty = CatalogueCollection::create(['title' => 'Empty', 'slug' => 'empty', 'active' => true]);
        foreach ([[$a->id, 2], [$b->id, 1]] as [$id,$pos]) {
            DB::table('catalogue_collection_product')->insert(['collection_id' => $collection->id, 'product_id' => $id, 'position' => $pos]);
        }
        $this->getJson('/api/v1/catalogue/collections')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $parent->id);
        $this->getJson('/api/v1/catalogue/products?collection=qa-active')->assertJsonPath('data.items.0.id', $b->id);
        $this->getJson('/api/v1/catalogue/products?collection=missing')->assertNotFound();
    }

    public function test_validation_rejects_invalid_ranges_values_and_unbounded_batches(): void
    {
        foreach (['min_price=-1', 'min_price=2.5', 'min_price=100&max_price=50', 'sort=raw-sql', 'stock=maybe', 'q='.str_repeat('x', 101), 'page=0', 'per_page=999'] as $query) {
            $this->getJson('/api/v1/catalogue/products?'.$query)->assertUnprocessable();
        }
        foreach (['', '1,0', '1,-2', '1,2,sql', implode(',', range(1, 51)), '9007199254740999'] as $ids) {
            $this->getJson('/api/v1/catalogue/saved-products?ids='.$ids)->assertUnprocessable();
        }
    }

    public function test_saved_reads_keep_id_order_rename_safely_and_do_not_return_unpublished_data(): void
    {
        $a = $this->fixture();
        $b = $this->fixture('Second', 'second');
        $draft = $this->fixture('Hidden', 'hidden', 'draft');
        $a->slug = 'renamed';
        $a->save();
        DB::table('catalogue_slugs')->insert(['slug' => 'renamed', 'product_id' => $a->id]);
        $r = $this->getJson('/api/v1/catalogue/saved-products?ids='.$b->id.','.$a->id.','.$draft->id.',99999')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.1.slug', 'renamed');
        $this->assertSame([$b->id, $a->id], array_column($r->json('data.items'), 'id'));
        $this->assertSame([$draft->id, 99999], $r->json('data.unavailable_ids'));
        $this->assertStringNotContainsString('Hidden', $r->getContent());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_read_stock_uses_only_active_locations_and_available_subtracts_reserved(): void
    {
        $p = $this->fixture();
        DB::table('stock_balances')->update(['reserved' => DB::raw('on_hand')]);
        $this->getJson('/api/v1/catalogue/products?stock=in_stock')->assertJsonPath('data.total', 0);
        DB::table('stock_balances')->update(['reserved' => 0]);
        StockLocation::query()->update(['active' => false]);
        $this->getJson('/api/v1/catalogue/products?stock=in_stock')->assertJsonPath('data.total', 0);
        $this->getJson('/api/v1/catalogue/products')->assertJsonPath('data.items.0.variants.0.available', 0);
    }
}
