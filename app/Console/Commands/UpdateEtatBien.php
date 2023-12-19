<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;







class UpdateEtatBien extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-etat-bien';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commande pour modifier etat de bien / notif to responsable';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')->whereNull('deleted_at')->get();
        DatabaseHelper::update_etat_bien_pre_reserve($databases);
    }
}
