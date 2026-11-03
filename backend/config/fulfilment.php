<?php

return ['provider' => 'local_sandbox', 'scenario' => env('COURIER_SANDBOX_SCENARIO', 'normal'), 'webhook_secret' => env('COURIER_SANDBOX_WEBHOOK_SECRET', ''), 'services' => ['sandbox_standard' => ['name' => 'Sandbox standard — no real postage', 'cost_minor' => 395], 'sandbox_express' => ['name' => 'Sandbox express — no real postage', 'cost_minor' => 695]], 'origin_note' => 'Active inventory location is a test dispatch origin; real dispatch address/courier mapping pending.'];
