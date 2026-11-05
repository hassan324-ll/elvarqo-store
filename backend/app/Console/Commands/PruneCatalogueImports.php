<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PruneCatalogueImports extends Command
{
    protected $signature = 'catalogue:prune-imports';

    protected $description = 'Remove expired private preview files; retain reports and supplier duplicate protection';

    public function handle(): int
    {
        $count = 0;
        foreach (DB::table('catalogue_import_batches')->where('expires_at', '<', now())->pluck('id') as $id) {
            if (Str::isUuid($id)) {
                $path = config('catalogue_import.root').'/'.$id;
                if (is_dir($path) && ! is_link($path)) {
                    File::deleteDirectory($path);
                    $count++;
                }
            }
        }
        $this->info('Expired preview directories removed: '.$count.'. Reports and supplier identities retained.');

        return self::SUCCESS;
    }
}
