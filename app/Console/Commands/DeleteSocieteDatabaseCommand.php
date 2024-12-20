<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Societe;
use App\Http\Helpers\DatabaseHelper;

class DeleteSocieteDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-societe-database-command';

    /**
     *% The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a societe database';

    /**
     */
    public function handle()
    {
        \Log::info('Cron job started at: ' . now());

        $oneDayAgo = now()->subDay()->toDateTimeString();

        //  in  this case i do just oneday  to  testt can add more to  use one month or one years (med)
        \Log::info('One day ago: ' . $oneDayAgo);

        $databases = DB::table('societes')
                        ->whereNotNull('deleted_at')
                        ->where('deleted_at', '<', $oneDayAgo)
                        ->get();

        \Log::info('Number of databases to delete: ' . $databases->count());

        \Log::info('IDs of databases to delete: ' . $databases->pluck('id')->implode(','));

        DatabaseHelper::Deletedatabase($databases);

        \Log::info('Cron job complete ');
    }
}
