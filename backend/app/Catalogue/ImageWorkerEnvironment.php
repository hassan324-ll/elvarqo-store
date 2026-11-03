<?php

namespace App\Catalogue;

class ImageWorkerEnvironment
{
    public static function values(): array
    {
        $env = ['OPENSSL_CONF' => false, 'OPENSSL_MODULES' => false];
        // Laravel/Symfony can omit these after filtering $_SERVER on Windows.
        // Node's crypto initialization requires the Windows system directory.
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (['SystemRoot', 'WINDIR'] as $name) {
                $value = getenv($name);
                if ($value !== false && $value !== '') {
                    $env[$name] = $value;
                }
            }
        }

        return $env;
    }
}
