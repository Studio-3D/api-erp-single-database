<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



class Edit_bien_et_titre_foncier_en_Masse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:edit_biens_et_titre_foncier_en_masse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commande pou Modifier biens en masse / titre foncier';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')
        ->whereNull('deleted_at')
        ->whereNot('id', 1)   // Filtrer uniquement la société avec id = 292
        ->get();

        DatabaseHelper::edit_biens_titre_foncier_en_masse($databases);

    }
}
