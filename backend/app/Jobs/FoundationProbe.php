<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class FoundationProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $probeId) {}

    public function handle(): void
    {
        Mail::raw('ELVARQO foundation probe '.$this->probeId, function ($message) {
            $message->to('sandbox@example.test')->subject('ELVARQO local foundation probe');
        });
        Storage::disk('local')->put('foundation/'.$this->probeId.'.txt', 'Queue, sandbox email and local storage exercised at '.now()->toISOString());
    }
}
