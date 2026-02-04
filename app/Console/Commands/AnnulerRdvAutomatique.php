<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use App\Models\Rendez_vous;
use App\Models\User;
use App\Models\Societe;
use Illuminate\Support\Facades\DB;







class AnnulerRdvAutomatique extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'annuler_rdv_automatique';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'chaque heure annuler les rendez vous  non traités';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->get();
        DatabaseHelper::annuler_rdv_automatique($databases);
    }
}
