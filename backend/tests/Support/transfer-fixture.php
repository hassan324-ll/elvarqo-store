<?php

use App\Commerce\Bags;
use App\Commerce\Checkout;
use App\Commerce\Pricing;
use App\Commerce\WiseBankTransfer;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceFixture as F;

// Invoked only on a disposable SQLite database by the MySQL transfer suite.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'sqlite') {
    throw new RuntimeException('Fixture requires an isolated testing SQLite connection.');
}

config(['commerce.provider' => 'wise_bank_transfer', 'commerce.wise.enabled' => true, 'commerce.wise.account_holder' => 'ELVARQO TEST ONLY', 'commerce.wise.sort_code' => '123456', 'commerce.wise.account_number' => '12345678']);
$actor = StaffUser::create(['name' => 'Transfer QA', 'email' => 'transfer@example.test', 'password' => 'TestOnly-Password-42!', 'staff_role_id' => StaffRole::where('name', 'owner')->value('id'), 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
$variant = F::seed(20);
$root = DB::table('catalogue_collections')->insertGetId(['title' => 'Women', 'slug' => 'women', 'kind' => 'collection', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
$child = DB::table('catalogue_collections')->insertGetId(['title' => 'Knitwear é', 'slug' => 'womens-knitwear', 'kind' => 'category', 'parent_id' => $root, 'image_id' => (string) Str::uuid(), 'image_webp' => base64_encode('Synthetic image bytes'), 'image_alt' => 'QA image', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
DB::table('catalogue_collection_product')->insert(['collection_id' => $child, 'product_id' => DB::table('catalogue_variants')->where('id', $variant)->value('product_id')]);
$token = app(Bags::class)->create()['token'];
$r = Request::create('/');
$r->headers->set('Authorization', 'Bearer '.$token);
app(Bags::class)->change($r, ['variant_id' => $variant, 'quantity' => 1, 'revision' => 1]);
$details = F::details();
$q = app(Pricing::class)->quote(app(Bags::class)->owned($r), $details);
$o = app(Checkout::class)->start($r, $details + ['quote_hash' => $q['quote_hash'], 'attempt_id' => (string) Str::uuid(), 'terms' => true]);
app(WiseBankTransfer::class)->confirm($o['id'], ['operation_id' => (string) Str::uuid(), 'confirmed' => true, 'amount_minor' => $o['total_minor'], 'currency' => 'GBP', 'payment_reference' => $o['reference'], 'receipt_reference' => 'TRANSFER-QA-001'], $actor->id);
$c = DB::table('customers')->insertGetId(['name' => 'Transfer customer', 'email' => 'customer@example.test', 'password' => password_hash('TestOnly-Password-42!', PASSWORD_BCRYPT), 'created_at' => now(), 'updated_at' => now()]);
DB::table('customer_orders')->insert(['customer_id' => $c, 'order_id' => $o['id'], 'created_at' => now()]);
DB::table('staff_sessions')->insert(['id' => 'do-not-transfer', 'payload' => 'private-session', 'last_activity' => time()]);
echo "Synthetic SQLite transfer fixture ready.\n";
