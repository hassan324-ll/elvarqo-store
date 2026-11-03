<?php

return ['image_driver' => env('MEDIA_IMAGE_DRIVER', 'sharp'), 'node_binary' => env('MEDIA_NODE_BINARY', 'node'), 'media_root' => storage_path('app/private/catalogue'), 'max_bytes' => 8 * 1024 * 1024];
