<?php

namespace Tests\Feature;

use App\Jobs\FoundationProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_contract_and_allowed_origins(): void
    {
        foreach (['http://localhost:3000', 'http://localhost:5173'] as $origin) {
            $response = $this->withHeaders(['Origin' => $origin])->getJson('/api/v1/health');
            $response->assertOk()->assertJsonPath('data.status', 'ok')->assertJsonPath('data.database', 'ok')
                ->assertHeader('Access-Control-Allow-Origin', $origin)->assertJsonStructure(['meta' => ['request_id', 'timestamp']]);
            $this->assertSame($response->json('meta.request_id'), $response->headers->get('X-Request-ID'));
        }
    }

    public function test_other_origins_are_not_allowed(): void
    {
        $this->withHeaders(['Origin' => 'https://untrusted.example'])->getJson('/api/v1/health')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_preflight_is_scoped(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'GET'])->options('/api/v1/health')
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_database_outage_returns_safe_error(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('private database detail'));
        $this->getJson('/api/v1/health')->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE')->assertDontSee('private database detail');
    }

    public function test_api_errors_have_context(): void
    {
        $this->getJson('/api/v1/missing')->assertNotFound()->assertJsonStructure(['error' => ['code', 'message', 'details'], 'meta' => ['request_id', 'timestamp']])->assertHeader('X-Request-ID');
        Route::post('/api/v1/test-validation', fn () => request()->validate(['name' => 'required']));
        $this->postJson('/api/v1/test-validation', [])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonStructure(['error' => ['details' => ['name']]]);
    }

    public function test_probe_uses_database_queue_mail_and_storage(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'log']);
        Event::fake([MessageSent::class]);
        Storage::fake('local');
        FoundationProbe::dispatch('automated-probe');
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->getSubject() === 'ELVARQO local foundation probe');
        Storage::disk('local')->assertExists('foundation/automated-probe.txt');
    }
}
