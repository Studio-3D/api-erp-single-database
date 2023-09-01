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
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a societe database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $databases = DB::table('societes')->whereNotNull('deleted_at')->get();
        DatabaseHelper::Deletedatabase($databases);
    }
}