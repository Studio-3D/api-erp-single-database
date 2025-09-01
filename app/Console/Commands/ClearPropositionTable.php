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







class ClearPropositionTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clear-proposition-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Libérer le bien après 30 minutes s\'il est toujours en cours de proposition.';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        $databases = DB::table('societes')
                        ->whereNull('deleted_at')
                        ->where('id', '!=', 1)
                        ->get();
        DatabaseHelper::deletePropositionTable($databases);
    }
}
