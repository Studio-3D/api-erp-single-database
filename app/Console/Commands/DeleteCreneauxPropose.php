<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use App\Models\Proposition;
use App\Models\User;
use App\Models\Societe;
use Illuminate\Support\Facades\DB;







class DeleteCreneauxPropose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete_creneau_propose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'delete row crenau propose after 2min of proposition';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->get();
        DatabaseHelper::deleteCreneauPropose($databases);
    }
}
