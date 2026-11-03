<?php

use App\Commerce\Checkout;
use App\Commerce\WiseBankTransfer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../mysql-bootstrap.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Race worker requires the guarded MySQL test database.');
}
config(['commerce.provider' => 'wise_bank_transfer', 'commerce.wise.enabled' => true, 'commerce.wise.account_holder' => 'ELVARQO TEST ONLY', 'commerce.wise.sort_code' => '123456', 'commerce.wise.account_number' => '12345678']);
$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($argv[1].'.ready', 'ready');
$limit = microtime(true) + 15;
while (! is_file($argv[2])) {
    if (microtime(true) > $limit) {
        throw new RuntimeException('Race barrier timed out.');
    }
    usleep(10000);
}
try {
    if ($input['action'] === 'checkout') {
        $r = Request::create('/');
        $r->headers->set('Authorization', 'Bearer '.$input['token']);
        app(Checkout::class)->start($r, $input['values']);
    } else {
        app(WiseBankTransfer::class)->confirm($input['order'], $input['values'], $input['actor']);
    }
    echo json_encode(['outcome' => 'ok']);
} catch (ValidationException) {
    echo json_encode(['outcome' => 'rejected']);
}
