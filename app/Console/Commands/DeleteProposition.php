<?php

namespace App\Console\Commands;


use App\Models\Societe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Proposition;
use App\Enum\EtatBien;
use App\Http\Helpers\RoleHelper;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;

class DeleteProposition extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete_proposition';

    /**
     *% The console command description.
     *
     * @var string
     */
    protected $description = 'Delete propositions every day at midnight';

    /**
     * Execute the console command.
     */
    public function handle()
    {

                /*DatabaseHelper::Config();

                $biens_proposition=Proposition::on('temp')->get();

                foreach($biens_proposition as $b_p){
                    if($b_p->bien->etat==EtatBien::DISPONIBLE->name){
                        $b_p->forceDelete();
                    }
                }*/


    }
}
