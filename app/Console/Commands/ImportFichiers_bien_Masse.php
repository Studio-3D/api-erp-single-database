<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ChroneJobHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



class ImportFichiers_bien_Masse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import_fichiers_biens_en_masse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commande pour importer les Fichiers Pour Modifier bien en masse ';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')
        ->whereNull('deleted_at')
        ->whereNot('id', 1)   // Filtrer uniquement la société avec id = 292
        ->get();

        DatabaseHelper::import_fichiers_biens_en_masse($databases);

    }
}
