<?php

namespace App\Http\Helpers;

use App\Models\FreinEtage;
use App\Models\Frein;
use App\Models\Tranche;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\CompositionBien; 
use App\Models\TypeBien;
use App\Models\Immeuble;
use App\Http\Helpers\Bien_Helper;
use Illuminate\Support\Facades\Log;


class ImportExcelHelper {

    public static function ImportStockByProjetWithoutTrancheAndBlocAndImmeuble($data,$projet_id){

        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_tranches==0 && $projet->nbre_blocs==0 && $projet->nbre_immeubles==0)
        {
            foreach($data as $row)
            {
               Bien_Helper::checkAndCreateBien($projet_id, null,  null, null, $row);
            }
        }
        else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);
        }
        
    }


    public static function ImportStockByProjetWithoutTrancheAndBloc($data, $projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_blocs==0 && $projet->nbre_tranches==0 && $projet->nbre_immeubles>0){
            foreach($data  as $row){
                $immeuble = Immeuble::on('temp')
                                    ->where('nom', $row['immeuble'])
                                    ->where('projet_id', $projet_id)
                                    ->first();
                if(!$immeuble) {
                    $immeuble=new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom=$row['immeuble'];
                    $immeuble->projet_id=$projet_id;
                    $immeuble->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, null,  null, $immeuble->id, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }

    public function ImportStockByProjetWithoutTrancheAndImmeuble($data, $projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_tranches==0 && $projet->nbre_immeubles==0 && $projet->nbre_blocs>0){
            foreach($data as $row){
                $bloc = Bloc::on('temp')
                                ->where('nom', $row['Bloc'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$bloc){
                    $bloc=new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom=$row['Bloc'];
                    $bloc->projet_id=$projet_id;
                    $bloc->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, null, $bloc->id, null, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }

    public static function ImportStockByProjetWithoutTranche($data, $projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_tranches==0 && $projet->nbre_blocs>0 && $projet->nbre_immeubles>0){
            foreach($data as $row){
                $bloc = Bloc::on('temp')
                                ->where('nom', $row['Bloc'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$bloc){
                    $bloc=new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom=$row['Bloc'];
                    $bloc->projet_id=$projet_id;
                    $bloc->save();
                }
                $immeuble = Immeuble::on('temp')
                                    ->where('nom', $row['immeuble'])
                                    ->where('projet_id', $projet_id)
                                    ->where('bloc_id', $bloc->id)
                                    ->first();
                if(!$immeuble){
                    $immeuble=new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom=$row['immeuble'];
                    $immeuble->projet_id=$projet_id;
                    $immeuble->bloc_id=$bloc->id;
                    $immeuble->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, null, $bloc->id, $immeuble->id, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }
    
    public static function ImportStockByProjetWithoutBlocAndImmeuble($data,$projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_blocs==0 && $projet->nbre_immeubles==0 && $projet->nbre_tranches>0){
            foreach($data as $row){
                $tranche =Tranche::on('temp')
                                ->where('nom', $row['tranche'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$tranche){
                    $tranche=new Tranche();
                    $tranche->setConnection('temp');
                    $tranche->nom=$row['tranche'];
                    $tranche->projet_id=$projet_id;
                    $tranche->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, $tranche->id, null, null, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }

    public static function ImportStockByProjetWithoutBloc($data,$projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_blocs==0 && $projet->nbre_tranches>0 && $projet->nbre_immeubles>0){
            foreach($data as $row){
                $tranche =Tranche::on('temp')
                                ->where('nom', $row['tranche'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$tranche){
                    $tranche=new Tranche();
                    $tranche->setConnection('temp');
                    $tranche->nom=$row['tranche'];
                    $tranche->projet_id=$projet_id;
                    $tranche->save();
                }
                $immeuble = Immeuble::on('temp')
                                    ->where('nom', $row['immeuble'])
                                    ->where('tranche_id',  $tranche->id)
                                    ->where('projet_id', $projet_id)
                                    ->first();
                if(!$immeuble){
                        $immeuble=new Immeuble();
                        $immeuble->setConnection('temp');
                        $immeuble->nom=$row['immeuble'];
                        $immeuble->projet_id=$projet_id;
                        $immeuble->tranche_id=$tranche->id;
                        $immeuble->save();

                }
                Bien_Helper::checkAndCreateBien( $projet_id, $tranche->id, null, $immeuble->id, $row);           
                       
            }

        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }
        
    public static function ImportStockByProjetWithoutImmeuble($data,$projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_immeubles==0 && $projet->nbre_tranches>0 && $projet->nbre_blocs>0){
            foreach($data as $row){
                $tranche =Tranche::on('temp')
                                ->where('nom', $row['tranche'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$tranche){
                    $tranche=new Tranche();
                    $tranche->setConnection('temp');
                    $tranche->nom=$row['tranche'];
                    $tranche->projet_id=$projet_id;
                    $tranche->save();
                }
                $bloc = Bloc::on('temp')
                                ->where('nom', $row['Bloc'])
                                ->where('tranche_id', $tranche->id)
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$bloc){
                    $bloc=new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom=$row['Bloc'];
                    $bloc->projet_id=$projet_id;
                    $bloc->tranche_id=$tranche->id;
                    $bloc->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, $tranche->id, $bloc->id, null, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }
   
    public static function ImportStockByProjet($data, $projet_id){
        $projet=Projet::findOrfail($projet_id);
        if($projet->nbre_tranches>0 && $projet->nbre_blocs>0 && $projet->nbre_immeubles>0){
            foreach($data as $row){
                $tranche =Tranche::on('temp')
                                ->where('nom', $row['tranche'])
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$tranche){
                    $tranche=new Tranche();
                    $tranche->setConnection('temp');
                    $tranche->nom=$row['tranche'];
                    $tranche->projet_id=$projet_id;
                    $tranche->save();
                }
                $bloc = Bloc::on('temp')
                                ->where('nom', $row['Bloc'])
                                ->where('tranche_id', $tranche->id)
                                ->where('projet_id', $projet_id)
                                ->first();
                if(!$bloc){
                    $bloc=new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom=$row['Bloc'];
                    $bloc->projet_id=$projet_id;
                    $bloc->tranche_id=$tranche->id;
                    $bloc->save();
                }
                $immeuble = Immeuble::on('temp')
                                    ->where('nom', $row['immeuble'])
                                    ->where('tranche_id',  $tranche->id)
                                    ->where('bloc_id', $bloc->id)
                                    ->where('projet_id', $projet_id)
                                    ->first();
                if(!$immeuble){
                    $immeuble=new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom=$row['immeuble'];
                    $immeuble->projet_id=$projet_id;
                    $immeuble->tranche_id=$tranche->id;
                    $immeuble->bloc_id=$bloc->id;
                    $immeuble->save();
                }
                Bien_Helper::checkAndCreateBien($projet_id, $tranche->id, $bloc->id, $immeuble->id, $row);
            }
        }else{
            return response()->json(['error' => 'Project does not meet the required conditions.'], 400);

        }
    }


}