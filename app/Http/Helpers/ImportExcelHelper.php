<?php

namespace App\Http\Helpers;

use App\Models\FreinEtage;
use App\Models\Frein;
use App\Models\Tranche;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\Projet;

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
    public static function ImportStockByProjetWithoutTranche($column, $projet_id){
          
        log::info('messing column tranche');

        $bloc = Bloc::on('temp')
        ->where('nom', $column['Bloc'])
        ->where('projet_id', $projet_id)
        ->first();
        
          
        if($bloc)
        {
            
            Log::info('bloc from db  where column  tranche  nottt exist ');

            // foreach($bloc as $blocs)
            // {
                
                Log::info($bloc->id);
                $immeuble = Immeuble::on('temp')
                ->where('nom', $column['immeuble'])
                ->where('projet_id', $projet_id)
                ->where('bloc_id', $bloc->id)->first();

                
            
                Log::info($immeuble);
                
                if($immeuble)
                {
                    Log::info('immeuble exist  from  db  where column  tranche  nottt exist');
                //    foreach($immeuble as $immeubles)
                //    {
                    
           
                  
                  
                    Bien_Helper::checkAndCreateBien(null,$projet_id, $bloc, $immeuble, $column);
                   
                   }
                   
                // }
                //  immeuble else

                else{

                    log::info('im  in  else imme   where column  tranche  nottt exist');
                   $immeuble=new Immeuble();
                   $immeuble->setConnection('temp');
                   $immeuble->nom=$column['immeuble'];
                   $immeuble->projet_id=$projet_id;
                   $immeuble->bloc_id=$bloc->id;
                   if($immeuble->save()){
                   
                    // $bien_exist=Bien::on('temp')->where(function ($query) use ($column){
                    //     $query->where('propriete_dite_bien',$column['Appt_Num'])->orwhere('propriete_dite_bien',$column['magasin_num']);
                    // })->where('tranche_id', $tranche->id)->where('projet_id', $projet_id)->where('bloc_id', $blocs->id)->where('immeuble_id', $immeubles->id)->count();
                    
                    // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                    
                    Bien_Helper::checkAndCreateBien(null,$projet_id, $bloc, $immeuble, $column);
                    }

                // }

            }
        }
        // bloc else 
        else{
            
            Log::info('im  i   else bloc   where column  tranche  nottt exist');
            
         
          $bloc=new Bloc();
          $bloc->setConnection('temp');
          $bloc->nom=$column['Bloc'];
          $bloc->projet_id=$projet_id;
          if($bloc->save()){
            $immeuble=new Immeuble();
            $immeuble->setConnection('temp');
            $immeuble->nom=$column['immeuble'];
            $immeuble->projet_id=$projet_id;
            $immeuble->bloc_id=$bloc->id;
            if($immeuble->save()){
                // $bien_exist=Bien::on('temp')->where(function ($query ) use ($column){
                //     $query->where('propriete_dite_bien',$column['Appt_Num'])->orwhere('propriete_dite_bien',$column['magasin_num']);
                // })->where('tranche_id', $tranches->id)->where('projet_id', $projet_id)->where('bloc_id', $bloc->id)->where('immeuble_id',$immeuble->id)->count();

                // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                Bien_Helper::checkAndCreateBien(null,$projet_id, $bloc, $immeuble, $column);

            }
            
          }

        
        }
    }
    public static function ImportStockByProjet($column, $projet_id){
         $tranche =Tranche::on('temp')
        ->where('nom', $column['tranche'])
        ->where('projet_id', $projet_id)
        ->first();
        

        if ($tranche){
            if(array_key_exists('Bloc',$column)){

                $bloc = Bloc::on('temp')
                ->where('nom', $column['Bloc'])
                ->where('tranche_id', $tranche->id)
                ->where('projet_id', $projet_id)
                ->first();
             
                if($bloc)
                {
                    $immeuble = Immeuble::on('temp')
                        ->where('nom', $column['immeuble'])
                        ->where('tranche_id',  $tranche->id)
                        ->where('projet_id', $projet_id)
                        ->where('bloc_id', $bloc->id)->first();

                       
                    if($immeuble){
                         Bien_Helper::checkAndCreateBien($tranche, $projet_id, $bloc, $immeuble, $column);
                    }
                    else{

                        
                           $immeuble=new Immeuble();
                           $immeuble->setConnection('temp');
                           $immeuble->nom=$column['immeuble'];
                           $immeuble->projet_id=$projet_id;
                           $immeuble->tranche_id=$tranche->id;
                           $immeuble->bloc_id=$bloc->id;
                           if($immeuble->save()){
                                $nv=0;
                                Bien_Helper::checkAndCreateBien($tranche, $projet_id, $bloc, $immeuble, $column);
                            }
                    }
                    
                }
                else{
                    $bloc=new Bloc();
                    $bloc->setConnection('temp');
                    $bloc->nom=$column['Bloc'];
                    $bloc->projet_id=$projet_id;
                    $bloc->tranche_id=$tranche->id;
                    if($bloc->save()){
                        $immeuble=new Immeuble();
                        $immeuble->setConnection('temp');
                        $immeuble->nom=$column['immeuble'];
                        $immeuble->projet_id=$projet_id;
                        $immeuble->tranche_id=$tranche->id;
                        $immeuble->bloc_id=$bloc->id;
                        if($immeuble->save()){
                            Bien_Helper::checkAndCreateBien($tranche, $projet_id, $bloc, $immeuble, $column);
                        }
                    }
                    
                }

            }
            else{
                log::info('messing  column bloc  where  tranche  exist');
                
                $immeuble = Immeuble::on('temp')
                ->where('nom', $column['immeuble'])
                ->where('tranche_id',  $tranche->id)
                ->where('projet_id', $projet_id)->first();

               
                if($immeuble )
                {
                    Log::info('immeuble from db here  e where  column  tranche    exist ');
                //    foreach($immeuble as $immeubles)
                //    {
                    Log::info($immeuble->id);
                    Bien_Helper::checkAndCreateBien($tranche, $projet_id, null, $immeuble, $column);
                //    }
                }
                //  immeuble else

                else{

                log::info('im  in  else immeu  where column  tranche exist');
                   $immeuble=new Immeuble();
                   $immeuble->setConnection('temp');
                   $immeuble->nom=$column['immeuble'];
                   $immeuble->projet_id=$projet_id;
                   $immeuble->tranche_id=$tranche->id;
                   
                //    $immeuble->bloc_id=$bloc->id;
                   if($immeuble->save() ){
                    $nv=0;
                    Bien_Helper::checkAndCreateBien($tranche, $projet_id, null, $immeuble, $column);
                    }
                }

            }
            
        }else{
            $new_tranche=new Tranche();
            $new_tranche->setConnection('temp');
            $new_tranche->nom=$column['tranche'];
            $new_tranche->projet_id=$projet_id;
            if($new_tranche->save()){
            
                if(array_key_exists('Bloc',$column)){
                    $new_bloc=new Bloc();
                    $new_bloc->setConnection('temp');
                    $new_bloc->nom=$column['Bloc'];
                    $new_bloc->projet_id=$projet_id;
                    $new_bloc->tranche_id=$new_tranche->id;
                    if($new_bloc->save() && $column['immeuble']!=null){
                        $new_immeuble=new Immeuble();
                        $new_immeuble->setConnection('temp');
                        $new_immeuble->nom=$column['immeuble'];
                        $new_immeuble->projet_id=$projet_id;
                        $new_immeuble->tranche_id=$new_tranche->id;
                        $new_immeuble->bloc_id=$new_bloc->id;
                    }
                    if($new_immeuble->save()){
                        Bien_Helper::checkAndCreateBien($new_tranche, $projet_id, $new_bloc, $new_immeuble, $column);
                    }
                        
                }
        
                else{
                    $new_immeuble=new Immeuble();
                    $new_immeuble->setConnection('temp');
                    $new_immeuble->nom=$column['immeuble'];
                    $new_immeuble->projet_id=$projet_id;
                    $new_immeuble->tranche_id=$new_tranche->id;

                    if($new_immeuble->save()){
                        Bien_Helper::checkAndCreateBien($new_tranche, $projet_id, null, $new_immeuble, $column);
                    }

                }   
        

            }
        }
    }



   
}
