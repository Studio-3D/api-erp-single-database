<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Tranche;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\CompositionBien; 
use App\Models\TypeBien;
use App\Models\Immeuble;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingcolumn;
use Illuminate\Support\Str;
use App\Http\Helpers\DatabaseHelper;




class ExcelDataController extends Controller
{

    //  public function  __construct( $projet_id,$tranche_id)
    //  {
    //      $this->projet_id= $request->projet
    //      $this->tranche_id= $tranche_id;
 
    //  }

   

    
    public function UploadDataExcel(Request $request)
    {
        $projet_id = $request->projetId;

        DatabaseHelper::Config();
        set_time_limit(0);
        ini_set('memory_limit', '-1');
    
         $data = $request->input('data');
        //  $blocs = [];
        // Initialize an array to store blocs
    
        // Iterate through each element in the $data array
        // loping data from  data intm  column  from front end 
        foreach ($data as $column) {


            // if(array_key_exists('tranche',$column))
            // {

            // }
            $tranche =Tranche::on('temp')
            ->where('nom', $column['tranche'])
            ->where('projet_id', $projet_id)
            ->get();

            log::info($tranche);

            //
            if($tranche)
            {
                Log::info('tranche exist ');

                foreach($tranche as $tranches)
                {
                    
                    Log::info(' loop tranche');
                    
                    
                    Log::info($tranches->id);
                    
                    $bloc = Bloc::on('temp')
                    ->where('nom', $column['Bloc'])
                    ->where('tranche_id', $tranches->id)
                    ->where('projet_id', $projet_id)
                    ->get();

               Log::info($bloc);
               

                    if($bloc)
                    {
                        Log::info('bloc exist ');
           
                        foreach($bloc as $blocs)
                        {
                            
                            Log::info('loop bloc ');
                            
                            
                            Log::info($blocs->id);
                            
                            $immeuble = Immeuble::on('temp')
                            ->where('nom', $column['immeuble'])
                            ->where('tranche_id',  $tranches->id)
                            ->where('projet_id', $projet_id)
                            ->where('bloc_id', $blocs->id)->get();
                            
                              
                            
                            Log::info($immeuble);
                            
                            if($immeuble)
                            {
                                Log::info('immeuble exist ');
                               foreach($immeuble as $immeubles)
                               {
                                
                                Log::info(' lop immeu');
                                
                                
                                Log::info($immeubles->id);
                                

                                $nv=0;
                               
                                // $bien_exist = Bien::on('temp')->where(function ($query) use ($column) {
                                //     $query->where('propriete_dite_bien', $column['Appt_Num'])
                                //           ->orWhere('propriete_dite_bien', $column['magasin_num']);
                                // })->where('tranche_id', $tranches->id)
                                //   ->where('projet_id', $projet_id)
                                //   ->where('bloc_id', $blocs->id)
                                //   ->count();

                                // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                                

                                $bien_exist = Bien::on('temp')->where(function ($query) use ($column) {
                                    if (!empty($column['Appt_Num'])) {
                                        log::info('appt num  not empty  ');
                                        $query->where('propriete_dite_bien', $column['Appt_Num']);
                                        
                                    } elseif (!empty($column['magasin_num'])) { 
                                        log::info('magasin num  not empty  ');

                                        $query->where('propriete_dite_bien', $column['magasin_num']);
                                    }
                                })->where('tranche_id', $tranches->id)
                                  ->where('projet_id', $projet_id)
                                  ->where('bloc_id', $blocs->id)
                                  ->count();
                                
                                log::info('uts done here 1');
                                if( $bien_exist == 0 )
                                {
                                    log::info('uts done here 2');

                                    $bien= new  Bien();
                                    $bien->setConnection('temp');
                                    $bien->bloc_id=$blocs->id;
                                    $bien->immeuble_id = $immeubles->id;

                                    if (array_key_exists("Appt_Num",$column) && $column['Appt_Num']!=null ){
                                        log::info('uts done here 3');
                                            $explode_numero = explode("Appt", $column['Appt_Num']);
                                            $bien->numero=$explode_numero[1];
                                            $bien->propriete_dite_bien=$column['Appt_Num'];
                                       
;
                                    }
                                    if (array_key_exists("magasin_num",$column) && $column['magasin_num']!=null){
                                       
                                             $bien->numero=$column['magasin_num'];
                                             $bien->propriete_dite_bien=$column['magasin_num'];

                                        
                                    }

                                        log::info('its done here 4');

                                    if (array_key_exists("Niveau",$column) && array_key_exists("etage",$column)){

                                        if($column['Niveau']!=null){


                                             if (str_contains($column['Niveau'], 'er etage')) {
                                                  $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                                  $bien->Niveau=$explode_Niveau_1[0];
                                                  $nv=$explode_Niveau_1[0];
                                             }elseif(str_contains($column['Niveau'], 'eme etage')){
                                                  $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                                  $bien->Niveau=$explode_Niveau_2[0];
                                                  $nv=$explode_Niveau_2[0];

                                             }elseif(str_contains($column['Niveau'], 'ème etage')){
                                                  $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                                  $bien->Niveau=$explode_Niveau_3[0];
                                                  $nv=$explode_Niveau_2[0];
                                             }
                                             elseif(str_contains($column['Niveau'], 'RDC')){
                                                $bien->Niveau=0;
                                                $nv=0;
                                             }


                                        }elseif($column['etage']!=null){
                                            $bien->Niveau=$column['etage'];
                                             $nv=$column['etage'];

                                        }
                                    }else{
                                        if($column['Niveau']!=null){
                                            if (str_contains($column['Niveau'], 'er etage')) {
                                                     $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                                     $bien->Niveau=$explode_Niveau_1[0];
                                                     $nv=$explode_Niveau_1[0];
                                                }elseif(str_contains($column['Niveau'], 'eme etage')){
                                                     $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                                     $bien->Niveau=$explode_Niveau_2[0];
                                                     $nv=$explode_Niveau_2[0];

                                                }elseif(str_contains($column['Niveau'], 'ème etage')){
                                                     $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                                     $bien->Niveau=$explode_Niveau_3[0];
                                                     $nv=$explode_Niveau_2[0];
                                                }
                                                elseif(str_contains($column['Niveau'], 'RDC')){
                                                   $bien->Niveau=0;
                                                   $nv=0;
                                                }
                                       }

                                    }

                                    log::info('uts done here 5');

                                    if ($column['type_local']=='APPARTEMENT'){
                                        $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                        $bien->type_id=$type->id;

                                    }

                                    elseif ($column['type_local']=='LOCAL COMMERCIAL') {
                                        $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                        $bien->type_id=$type->id;

                                    }

                                    // $bien->partie_p = $column['partie_p'];

                                    if (array_key_exists("parking",$column)){
                                        if ($column['parking'] == NULL) {

                                             $bien->prix_parking = 0;
                                        } else {
                                             $bien->prix_parking = $column['parking'];
                                        }
                                    }

                                    else{
                                        $bien->superficie_balcon = 0;
                                   }

                                   if (array_key_exists("balcon",$column)){
                                    if ($column['balcon'] == NULL || $column['balcon'] == 'SYNDIC PROPOSE'||$column['balcon']=='SYNDIC PLAN') {
                                        $sup_balcon=0;
                                         $bien->superficie_balcon = 0;

                                    } else {
                                        $bien->superficie_balcon = $column['balcon'];

                                    }
                                    
                                  }else{
                                    $bien->superficie_balcon = 0;

                                  }
                                  if (array_key_exists("terrasse",$column)){
                                    if ($column['terrasse'] == NULL) {
                                        log::info('uts done here 6');

                                        $bien->superficie_terrasse = 0;
                                        

                                    } else {

                                        $bien->superficie_terrasse = $column['terrasse'];

                                    }
                                }else{

                                    $bien->superficie_terrasse = 0;

                                }
                                $bien->superficie_terrasse_calculer=$bien->superficie_terrasse;
                                $bien-> superficie_balcon_calculer=$bien->superficie_balcon;

                                if (array_key_exists("superficie_architect",$column)){
                                    if ($column['superficie_architect'] == NULL) {
                                        $bien-> superficie_architecte =0;

                                    } else {
                                        $bien->superficie_architecte =$column['superficie_architect']-$bien->superficie_terrasse_calculer-$bien-> superficie_balcon_calculer;

                                    }
                                }else{

                                    $bien->superficie_architecte = 0;

                                }
                                $bien->superficie_architecte = 0;

                                if (array_key_exists("pu",$column)){
                                    if ($column['pu'] == NULL) {
                                    
                                        if($nv==0){
                                            $bien->prix_unitaire=11500;
                                        }else{
                                        
                                            $bien->prix_unitaire=12000;
                                        }

                                    }
                                    else{
                                        $bien->prix_unitaire=$column['pu'];
                                    }
                                }else{
                                    if($column['type_local']=='LOCAL COMMERCIAL'){
                                        $bien->prix_unitaire=25000;

                                    }else{
                                        $bien->prix_unitaire=0;
                                    }
                                }
                                if (array_key_exists("prix_box",$column)){
                                    if ($column['prix_box'] == NULL) {
                                        $bien->prix_box = 0;

                                    } else {
                                        $bien->prix_box = $column['prix_box'];

                                    }
                                }else{
                                     $bien->prix_box = 0;

                                }

                                                    $sup=$bien->superficie;
                                                    $bien->superficie_total=$sup+$bien->superficie_balcon+$bien->superficie_terrasse;
                                                    $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                                    $bien->etat='disponible';
                                                    $bien->orientation = 'N';
                                                    $bien->conventionne = 0;
                                                    $bien->tranche_id = $tranches->id;
                                                    $bien->projet_id = $projet_id;
                                                    $bien->avance_minimale = 0;
                                                    $bien->nbre_facades=0;
                                                    $bien->superficie_vendable=0;

                                                    
                                                    Log::info('bien  before save');
                                                    
                                                    if($bien->save()){
                                                        Log::info('bien   save  Succ');



                                                        if (array_key_exists("Categorie",$column)){
                                                            log::info('category here');
                                                            $pattern = "/[,\s.]/";
                                                            $exp=preg_split($pattern, $column['Categorie']);

                                                            $balcon=0;
                                                            $chambre=0;
                                                            $salon=0;
                                                            $cuisin=0;
                                                            $sdb=0;
                                                            $placard=0;
                                                            $terasse=0;
                                                            for($i=0;$i<=count($exp)-1;$i++){

                                                                if (str_contains($exp[$i], 'CHAMBRES')) {
                                                                     $chambre=explode("CHAMBRES",$exp[$i]);
                                                                    $chambre=$chambre[0];
                                                                }elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                                     $placard=explode("PLACARDS",$exp[$i]);
                                                                    $placard=$placard[0];
                                                                }
                                                                elseif (str_contains($exp[$i], 'SALON')) {
                                                                     $salon=explode("SALON",$exp[$i]);
                                                                     $salon=$salon[0];
                                                                }
                                                                elseif (str_contains($exp[$i], ' ')) {
                                                                     $cuisin=explode("CUISINE",$exp[$i]);
                                                                     $cuisin=$cuisin[0];
                                                                }
                                                                elseif (str_contains($exp[$i], 'SDB')) {
                                                                     $sdb=explode("SDB",$exp[$i]);
                                                                    $sdb=$sdb[0];
                                                                }
                                                                 elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                                     $placard=explode("PLACARDS",$exp[$i]);
                                                                    $placard=$placard[0];
                                                                }
                                                                 elseif (str_contains($exp[$i], 'TERRASSE')) {
                                                                     $terassse=explode("TERRASSE",$exp[$i]);
                                                                    $terassse=$terassse[0];
                                                                }
                                                                elseif (str_contains($exp[$i], 'BALCON')) {
                                                                     $balcon=explode("BALCON",$exp[$i]);
                                                                    $balcon=$balcon[0];
                                                                }

                                                            }
                                                            $compo=new CompositionBien();
                                                            $compo->setConnection('temp');
                                                            $compo->bien_id=$bien->id;
                                                            $compo->nbre_chambres=$chambre;
                                                            $compo->nbre_salons=$salon;
                                                            $compo->nbre_sdb=$sdb;
                                                            $compo->nbre_cuisines=$cuisin;
                                                            $compo->nbre_balcons=$balcon;
                                                            $compo->nbre_terasses=$terasse;
                                                            $compo->nbre_placards=$placard;
                                                            $compo->save();

                                                        }

                                                    }

                                }
                                log::info(' bien  exist ');

                                
                               }
                               
                            }
                            //  immeuble else

                            else{

                                log::info('im  in  else imme');
                               $immeuble=new Immeuble();
                               $immeuble->setConnection('temp');
                               $immeuble->nom=$column['immeuble'];
                               $immeuble->projet_id=$projet_id;
                               $immeuble->tranche_id=$tranches->id;
                               $immeuble->bloc_id=$blocs->id;
                               if($immeuble->save()){
                                $nv=0;
                                // $bien_exist=Bien::on('temp')->where(function ($query) use ($column){
                                //     $query->where('propriete_dite_bien',$column['Appt_Num'])->orwhere('propriete_dite_bien',$column['magasin_num']);
                                // })->where('tranche_id', $tranches->id)->where('projet_id', $projet_id)->where('bloc_id', $blocs->id)->where('immeuble_id', $immeubles->id)->count();
                                
                                // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                                

                                $bien_exist = Bien::on('temp')->where(function ($query) use ($column) {
                                    if (!empty($column['Appt_Num'])) {
                                        log::info('appt num  not empty  ');
                                        $query->where('propriete_dite_bien', $column['Appt_Num']);
                                        
                                    } elseif (!empty($column['magasin_num'])) { 
                                        log::info('magasin num  not empty  ');

                                        $query->where('propriete_dite_bien', $column['magasin_num']);
                                    }
                                })->where('tranche_id', $tranches->id)
                                  ->where('projet_id', $projet_id)
                                  ->where('bloc_id', $blocs->id)
                                  ->count();
                                if($bien_exist==0)
                                {
                                    $bien = new Bien();
                                    $bien->setConnection('temp');
                                    $bien->bloc_id = $blocs->id;
                                    $bien->immeuble_id = $immeubles->id;
                                }
                                if (array_key_exists("Appt_Num",$column)){
                                    if($column['Appt_Num']!=null){
                                    $explode_numero = explode("Appt", $column['Appt_Num']);
                                    $bien->numero=$explode_numero[1];
                                     $bien->propriete_dite_bien=$column['Appt_Num'];
                                    }
                                }
                                if (array_key_exists("magasin_num",$column)){
                                    if($column['magasin_num']!=null){
                                         $bien->numero=$column['magasin_num'];
                                        $bien->propriete_dite_bien=$column['magasin_num'];

                                    }
                                }

                                if (array_key_exists("Niveau",$column) && array_key_exists("etage",$column)){

                                    if($column['Niveau']!=null){


                                         if (str_contains($column['Niveau'], 'er etage')) {
                                              $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_1[0];
                                              $nv=$explode_Niveau_1[0];
                                         }elseif(str_contains($column['Niveau'], 'eme etage')){
                                              $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_2[0];
                                              $nv=$explode_Niveau_2[0];

                                         }elseif(str_contains($column['Niveau'], 'ème etage')){
                                              $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_3[0];
                                              $nv=$explode_Niveau_2[0];
                                         }
                                         elseif(str_contains($column['Niveau'], 'RDC')){
                                            $bien->Niveau=0;
                                            $nv=0;
                                         }


                                    }elseif($column['etage']!=null){
                                        $bien->Niveau=$column['etage'];
                                         $nv=$column['etage'];

                                    }
                                }
                                else{
                                    if($column['Niveau']!=null){
                                        if (str_contains($column['Niveau'], 'er etage')) {
                                                 $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                                 $bien->Niveau=$explode_Niveau_1[0];
                                                 $nv=$explode_Niveau_1[0];
                                            }elseif(str_contains($column['Niveau'], 'eme etage')){
                                                 $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                                 $bien->Niveau=$explode_Niveau_2[0];
                                                 $nv=$explode_Niveau_2[0];

                                            }elseif(str_contains($column['Niveau'], 'ème etage')){
                                                 $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                                 $bien->Niveau=$explode_Niveau_3[0];
                                                 $nv=$explode_Niveau_2[0];
                                            }
                                            elseif(str_contains($column['Niveau'], 'RDC')){
                                               $bien->Niveau=0;
                                               $nv=0;
                                            }
                                   }
                                }
                                if ($column['type_local']=='APPARTEMENT'){
                                    $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();

                                    $bien->type_id=$type->id;

                                }
                                elseif ($column['type_local']=='LOCAL COMMERCIAL') {
                                    $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                // $bien->partie_p = $column['partie_p'];

                                if (array_key_exists("parking",$column)){
                                    if ($column['parking'] == NULL) {

                                         $bien->prix_parking = 0;
                                    } else {
                                         $bien->prix_parking = $column['parking'];
                                    }
                                }
                                else{
                                    $bien->prix_parking = 0;
                                    }
                                    if (array_key_exists("balcon",$column)){
                                        if ($column['balcon'] == NULL||$column['balcon'] == 'SYNDIC PROPOSE'||$column['balcon']=='SYNDIC PLAN') {
                                            $sup_balcon=0;
                                             $bien->superficie_balcon = 0;

                                        } else {
                                            $bien->superficie_balcon = $column['balcon'];

                                        }
                                    }else{
                                        $bien->superficie_balcon = 0;
                                    }

                                    if (array_key_exists("terrasse",$column)){
                                        if ($column['terrasse'] == NULL) {
                                            $bien->superficie_terrasse = 0;

                                        } else {

                                            $bien->superficie_terrasse = $column['terrasse'];

                                        }
                                    }else{
                                        $bien->superficie_terrasse = 0;


                                    }
                                    $bien->superficie_terrasse_calculer=$bien->superficie_terrasse;
                                    $bien-> superficie_balcon_calculer= $bien->superficie_balcon;

                                    if (array_key_exists("superficie_architect",$column)){
                                        if ($column['superficie_architect'] == NULL) {
                                            $bien-> superficie_architecte =0;

                                        } else {
                                            $bien->superficie_architecte =$column['superficie_architect']-$bien->superficie_terrasse_calculer-$bien-> superficie_balcon_calculer;

                                        }
                                    }else{
                                        $bien->superficie_architecte = 0;

                                    }
                                    $bien->superficie_architecte = 0;

                                    if (array_key_exists("pu",$column)){
                                        if ($column['pu'] == NULL) {
                                            //rdc
                                            if($nv==0){
                                                $bien->prix_unitaire=11500;
                                            }else{
                                                //etage
                                                $bien->prix_unitaire=12000;
                                            }

                                        }
                                        else{
                                            $bien->prix_unitaire=$column['pu'];
                                        }
                                    }else{
                                        if($column['type_local']=='LOCAL COMMERCIAL'){
                                            $bien->prix_unitaire=25000;

                                        }else{
                                            $bien->prix_unitaire=0;
                                        }

                                    }

                                    if (array_key_exists("prix_box",$column)){
                                        if ($column['prix_box'] == NULL) {
                                            $bien->prix_box = 0;

                                        } else {
                                            $bien->prix_box = $column['prix_box'];

                                        }
                                    }
                                    else{
                                        $bien->prix_box = 0;

                                   }

                                       $sup=$bien->superficie;
                                        $bien->superficie_total=$sup+$bien->superficie_balcon+$bien->superficie_terrasse;
                                        $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                        $bien->etat='disponible';
                                        $bien->orientation = null;
                                        $bien->conventionne = 0;
                                        $bien->tranche_id = $tranches->id;
                                        $bien->projet_id = $projet_id;
                                        $bien->avance_minimale = 0;
                                        if($bien->save()){

                                            if (array_key_exists("Categorie",$column)){
                                                $pattern = "/[,\s.]/";
                                                $exp=preg_split($pattern, $column['Categorie']);

                                                $balcon=0;
                                                $chambre=0;
                                                $salon=0;
                                                $cuisin=0;
                                                $sdb=0;
                                                $placard=0;
                                                $terasse=0;
                                                for($i=0;$i<=count($exp)-1;$i++){

                                                    if (str_contains($exp[$i], 'CHAMBRES')) {
                                                         $chambre=explode("CHAMBRES",$exp[$i]);
                                                        $chambre=$chambre[0];
                                                    }elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                         $placard=explode("PLACARDS",$exp[$i]);
                                                        $placard=$placard[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'SALON')) {
                                                         $salon=explode("SALON",$exp[$i]);
                                                        $salon=$salon[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'CUISINE')) {
                                                         $cuisin=explode("CUISINE",$exp[$i]);
                                                        $cuisin=$cuisin[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'SDB')) {
                                                         $sdb=explode("SDB",$exp[$i]);
                                                        $sdb=$sdb[0];
                                                    }
                                                     elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                         $placard=explode("PLACARDS",$exp[$i]);
                                                        $placard=$placard[0];
                                                    }
                                                     elseif (str_contains($exp[$i], 'TERRASSE')) {
                                                         $terassse=explode("TERRASSE",$exp[$i]);
                                                        $terassse=$terassse[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'BALCON')) {
                                                         $balcon=explode("BALCON",$exp[$i]);
                                                        $balcon=$balcon[0];
                                                    }

                                                }
                                                $compo=new CompositionBien();
                                                $compo->setConnetion('temp');
                                                $compo->bien_id=$bien->id;
                                                $compo->nbre_chambres=$chambre;
                                                $compo->nbre_salons=$salon;
                                                $compo->nbre_sdb=$sdb;
                                                $compo->nbre_cuisiness=$cuisin;
                                                $compo->nbre_balconss=$balcon;
                                                $compo->nbre_terasses=$terasse;
                                                $compo->nbre_placardss=$placard;
                                                $compo->save();

                                            }

                                        }
                                



                                }

                            }


                        }
                    }
                    // bloc else 
                    else{
                        
                        Log::info('im  i   else bloc');
                        
                        $nv=null;
                        // bloc not exist 
                      $bloc=new Bloc();
                      $bloc->setConnetion('temp');
                      $bloc->nom=$column['bloc'];
                      $bloc->projet_id=$projet_id;
                      $bloc->tranche_id=$tranches->id;
                      if($bloc->save()){
                        $immeuble=new Immeuble();
                        $immeuble->setConnetion('temp');
                        $immeuble->nom=$column['immeuble'];
                        $immeuble->projet_id=$projet_id;
                        $immeuble->tranche_id=$tranches->id;
                        $immeuble->bloc_id=$bloc->id;
                        if($immeuble->save()){
                            // $bien_exist=Bien::on('temp')->where(function ($query ) use ($column){
                            //     $query->where('propriete_dite_bien',$column['Appt_Num'])->orwhere('propriete_dite_bien',$column['magasin_num']);
                            // })->where('tranche_id', $tranches->id)->where('projet_id', $projet_id)->where('bloc_id', $bloc->id)->where('immeuble_id',$immeuble->id)->count();

                            // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                                

                            $bien_exist = Bien::on('temp')->where(function ($query) use ($column) {
                                if (!empty($column['Appt_Num'])) {
                                    log::info('appt num  not empty  ');
                                    $query->where('propriete_dite_bien', $column['Appt_Num']);
                                    
                                } elseif (!empty($column['magasin_num'])) { 
                                    log::info('magasin num  not empty  ');

                                    $query->where('propriete_dite_bien', $column['magasin_num']);
                                }
                            })->where('tranche_id', $tranches->id)
                              ->where('projet_id', $projet_id)
                              ->where('bloc_id', $blocs->id)
                              ->count();

                            if($bien_exist==0)
                            {
                                $bien = new Bien();
                                $bien->setConnetion('temp');
                                $bien->bloc_id = $bloc->id;
                                $bien->immeuble_id = $immeuble->id;
                                if (array_key_exists("Appt_Num",$column)){
                                    if($column['Appt_Num']!=null){
                                    $explode_numero = explode("Appt", $column['Appt_Num']);
                                    $bien->numero=$explode_numero[1];
                                     $bien->propriete_dite_bien=$column['Appt_Num'];
                                    }
                                }
                                if (array_key_exists("magasin_num",$column)){
                                    if($column['magasin_num']!=null){
                                         $bien->numero=$column['magasin_num'];
                                        $bien->propriete_dite_bien=$column['magasin_num'];

                                    }
                                }

                                if (array_key_exists("Niveau",$column) && array_key_exists("etage",$column)){
                                    if($column['Niveau']!=null){
                                       if (str_contains($column['Niveau'], 'er etage')) {
                                              $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_1[0];
                                              $nv=$explode_Niveau_1[0];
                                         }elseif(str_contains($column['Niveau'], 'eme etage')){
                                              $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_2[0];
                                              $nv=$explode_Niveau_2[0];

                                         }elseif(str_contains($column['Niveau'], 'ème etage')){
                                              $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                              $bien->Niveau=$explode_Niveau_3[0];
                                              $nv=$explode_Niveau_2[0];
                                         }
                                         elseif(str_contains($column['Niveau'], 'RDC')){
                                            $bien->Niveau=0;
                                            $nv=0;
                                         }
                                    }elseif($column['etage']!=null){
                                        $bien->Niveau=$column['etage'];
                                         $nv=$column['etage'];

                                    }else{
                                        if($column['Niveau']!=null){
                                            if (str_contains($column['Niveau'], 'er etage')) {
                                                       $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                                       $bien->Niveau=$explode_Niveau_1[0];
                                                       $nv=$explode_Niveau_1[0];
                                                  }elseif(str_contains($column['Niveau'], 'eme etage')){
                                                       $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                                       $bien->Niveau=$explode_Niveau_2[0];
                                                       $nv=$explode_Niveau_2[0];

                                                  }elseif(str_contains($column['Niveau'], 'ème etage')){
                                                       $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                                       $bien->Niveau=$explode_Niveau_3[0];
                                                       $nv=$explode_Niveau_2[0];
                                                  }
                                                  elseif(str_contains($column['Niveau'], 'RDC')){
                                                     $bien->Niveau=0;
                                                     $nv=0;
                                                  }
                                         }

                                         if ($column['type_local']=='APPARTEMENT'){
                                            $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        elseif ($column['type_local']=='LOCAL COMMERCIAL') {
                                            $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        // $bien->partie_p = $column['partie_p'];

                                        if (array_key_exists("balcon",$column)){
                                            if ($column['balcon'] == NULL||$column['balcon'] == 'SYNDIC PROPOSE'||$column['balcon']=='SYNDIC PLAN') {

                                                 $bien->superficie_balcon = 0;

                                            } else {
                                                $bien->superficie_balcon = $column['balcon'];

                                            }
                                        }else{
                                            $bien->superficie_terrasse = 0;
                                        }

                                        if (array_key_exists("parking",$column)){
                                            if ($column['parking'] == NULL) {

                                                 $bien->prix_parking = 0;
                                            } else {
                                                 $bien->prix_parking = $column['parking'];
                                            }
                                        }
                                        else{
                                            $bien->prix_parking = 0;
                                        }

                                        
                                        $bien->superficie_terrasse_calculer=$bien->superficie_terrasse;
                                        $bien-> superficie_balcon_calculer= $bien->superficie_balcon;
                                        if (array_key_exists("superficie_architect",$column)){
                                            if ($column['superficie_architect'] == NULL) {
                                                $bien-> superficie_architecte =0;

                                            } else {
                                                $bien->superficie_architecte =$column['superficie_architect']-$bien->superficie_terrasse_calculer-$bien-> superficie_balcon_calculer;

                                            }
                                        }else{

                                            $bien->superficie_architecte = 0;

                                        }
                                        $bien->superficie_architecte = 0;

                                        if (array_key_exists("pu",$column)){
                                            if ($column['pu'] == NULL) {
                                                //rdc
                                                if($nv==0){
                                                    $bien->prix_unitaire=11500;
                                                }else{
                                                    //etage
                                                    $bien->prix_unitaire=12000;
                                                }

                                            }
                                            else{
                                                $bien->prix_unitaire=$column['pu'];
                                            }
                                        }else{
                                            if($column['type_local']=='LOCAL COMMERCIAL'){
                                                $bien->prix_unitaire=25000;

                                            }else{
                                                $bien->prix_unitaire=0;
                                            }
                                        }
                                        if (array_key_exists("prix_box",$column)){
                                            if ($column['prix_box'] == NULL) {
                                                $bien->prix_box = 0;

                                            } else {
                                                $bien->prix_box = $column['prix_box'];

                                            }
                                        }else{
                                            $bien->prix_box = $column['prix_box'];

                                        }


                                        $sup=$bien->superficie_architecte ;
                                        $bien->superficie_total=$sup+$bien->superficie_balcon+$bien->superficie_terrasse;
                                        $bien->prix=$bien->prix_unitaire*($sup+$bien->superficie_balcon+$bien->superficie_terrasse)+$bien->prix_parking+ $bien->prix_box;
                                        $bien->etat='disponible';
                                        $bien->orientation = null;
                                        $bien->conventionne = 0;
                                        $bien->tranche_id = $tranches->id;
                                        $bien->projet_id = $projet_id;
                                        $bien->avance_minimale = 0;

                                        if($bien->save())
                                        {
                                            if (array_key_exists("Categorie",$column)){
                                                $pattern = "/[,\s.]/";
                                                $exp=preg_split($pattern, $column['Categorie']);

                                                $balcon=0;
                                                $chambre=0;
                                                $salon=0;
                                                $cuisin=0;
                                                $sdb=0;
                                                $placard=0;
                                                $terasse=0;
                                                for($i=0;$i<=count($exp)-1;$i++){

                                                    if (str_contains($exp[$i], 'CHAMBRES')) {
                                                         $chambre=explode("CHAMBRES",$exp[$i]);
                                                        $chambre=$chambre[0];
                                                    }elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                         $placard=explode("PLACARDS",$exp[$i]);
                                                        $placard=$placard[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'SALON')) {
                                                         $salon=explode("SALON",$exp[$i]);
                                                        $salon=$salon[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'CUISINE')) {
                                                         $cuisin=explode("CUISINE",$exp[$i]);
                                                        $cuisin=$cuisin[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'SDB')) {
                                                         $sdb=explode("SDB",$exp[$i]);
                                                        $sdb=$sdb[0];
                                                    }
                                                     elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                         $placard=explode("PLACARDS",$exp[$i]);
                                                        $placard=$placard[0];
                                                    }
                                                     elseif (str_contains($exp[$i], 'TERRASSE')) {
                                                         $terassse=explode("TERRASSE",$exp[$i]);
                                                        $terassse=$terassse[0];
                                                    }
                                                    elseif (str_contains($exp[$i], 'BALCON')) {
                                                         $balcon=explode("BALCON",$exp[$i]);
                                                        $balcon=$balcon[0];
                                                    }

                                                }
                                                $compo=new CompositionBien();
                                                $compo->on('temp');
                                                $compo->bien_id=$bien->id;
                                                $compo->nbre_chambres=$chambre;
                                                $compo->nbre_salons=$salon;
                                                $compo->nbre_sdb=$sdb;
                                                $compo->nbre_cuisines=$cuisin;
                                                $compo->nbre_balcons=$balcon;
                                                $compo->nbre_terasses=$terasse;
                                                $compo->nbre_placards=$placard;
                                                $compo->save();

                                            }
                                        }


                                    }
                                }

                            }
                            
                        }

                      }

                    }
                }
            }
            // tranche else
            else{

                // tranche not exist 

                
                Log::info('tranche else');
                
              $nv=null;
              $tranche=new Tranche();
              $tranche->setConnection('temp');
              $tranche->nom=$column['tranche'];
              $tranche->projet_id=$projet_id;
              if($tranche->save){

                $bloc=new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom=$column['bloc'];
                $bloc->projet_id=$projet_id;
                $bloc->tranche_id=$tranche->id;
                if($bloc->save())
                {
                    $immeuble=new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom=$row['immeuble'];
                    $immeuble->projet_id=$projet_id;
                    $immeuble->tranche_id=$tranche->id;
                    $immeuble->bloc_id=$bloc->id;
                }
                if($immeuble->save()){
                    // $bien_exist=Bien::on('temp')->where(function ($query ) use ($column){
                    //     $query->where('propriete_dite_bien',$column['Appt_Num'])->orwhere('propriete_dite_bien',$column['magasin_num']);
                    // })->where('tranche_id', $tranche->id)->where('projet_id', $projet_id)->where('bloc_id', $bloc->id)->where('immeuble_id',$immeuble->id)->count();

                    // in this case we  check if the  one of thatt columns  is  empty  not  have check itt   (med)
                                

                    $bien_exist = Bien::on('temp')->where(function ($query) use ($column) {
                        if (!empty($column['Appt_Num'])) {
                            log::info('appt num  not empty  ');
                            $query->where('propriete_dite_bien', $column['Appt_Num']);
                            
                        } elseif (!empty($column['magasin_num'])) { 
                            log::info('magasin num  not empty  ');

                            $query->where('propriete_dite_bien', $column['magasin_num']);
                        }
                    })->where('tranche_id', $tranches->id)
                      ->where('projet_id', $projet_id)
                      ->where('bloc_id', $blocs->id)
                      ->count();
                    if($bien_exist==0)
                    {
                        $bien = new Bien();
                        $bien->setConnection('temp');
                        $bien->bloc_id = $bloc->id;
                        $bien->immeuble_id = $immeuble->id;
                        if (array_key_exists("Appt_Num",$column)){
                            if($column['Appt_Num']!=null){
                            $explode_numero = explode("Appt", $column['Appt_Num']);
                            $bien->numero=$explode_numero[1];
                             $bien->propriete_dite_bien=$column['Appt_Num'];
                            }
                        }
                        if (array_key_exists("magasin_num",$column)){
                            if($column['magasin_num']!=null){
                                 $bien->numero=$column['magasin_num'];
                                $bien->propriete_dite_bien=$column['magasin_num'];

                            }
                        }

                        if (array_key_exists("Niveau",$column) && array_key_exists("etage",$column)){
                            if($column['Niveau']!=null){
                               if (str_contains($column['Niveau'], 'er etage')) {
                                      $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                      $bien->Niveau=$explode_Niveau_1[0];
                                      $nv=$explode_Niveau_1[0];
                                 }elseif(str_contains($column['Niveau'], 'eme etage')){
                                      $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                      $bien->Niveau=$explode_Niveau_2[0];
                                      $nv=$explode_Niveau_2[0];

                                 }elseif(str_contains($column['Niveau'], 'ème etage')){
                                      $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                      $bien->Niveau=$explode_Niveau_3[0];
                                      $nv=$explode_Niveau_2[0];
                                 }
                                 elseif(str_contains($column['Niveau'], 'RDC')){
                                    $bien->Niveau=0;
                                    $nv=0;
                                 }
                            }elseif($column['etage']!=null){
                                $bien->Niveau=$column['etage'];
                                 $nv=$column['etage'];

                            }else{
                                if($column['Niveau']!=null){
                                    if (str_contains($column['Niveau'], 'er etage')) {
                                               $explode_Niveau_1 = explode("er etage", $column['Niveau']);
                                               $bien->Niveau=$explode_Niveau_1[0];
                                               $nv=$explode_Niveau_1[0];
                                          }elseif(str_contains($column['Niveau'], 'eme etage')){
                                               $explode_Niveau_2 = explode("eme etage", $column['Niveau']);
                                               $bien->Niveau=$explode_Niveau_2[0];
                                               $nv=$explode_Niveau_2[0];

                                          }elseif(str_contains($column['Niveau'], 'ème etage')){
                                               $explode_Niveau_3 = explode("ème etage", $column['Niveau']);
                                               $bien->Niveau=$explode_Niveau_3[0];
                                               $nv=$explode_Niveau_2[0];
                                          }
                                          elseif(str_contains($column['Niveau'], 'RDC')){
                                             $bien->Niveau=0;
                                             $nv=0;
                                          }
                                 }

                                 if ($column['type_local']=='APPARTEMENT'){
                                    $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                elseif ($column['type_local']=='LOCAL COMMERCIAL') {
                                    $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                // $bien->partie_p = $column['partie_p'];

                                if (array_key_exists("balcon",$column)){
                                    if ($column['balcon'] == NULL||$column['balcon'] == 'SYNDIC PROPOSE'||$column['balcon']=='SYNDIC PLAN') {

                                         $bien->superficie_balcon = 0;

                                    } else {
                                        $bien->superficie_balcon = $column['balcon'];

                                    }
                                }else{
                                    $bien->superficie_terrasse = 0;
                                }

                                if (array_key_exists("parking",$column)){
                                    if ($column['parking'] == NULL) {

                                         $bien->prix_parking = 0;
                                    } else {
                                         $bien->prix_parking = $column['parking'];
                                    }
                                }
                                else{
                                    $bien->prix_parking = 0;
                                }

                                
                                $bien->superficie_terrasse_calculer=$bien->superficie_terrasse;
                                $bien-> superficie_balcon_calculer= $bien->superficie_balcon;
                                if (array_key_exists("superficie_architect",$column)){
                                    if ($column['superficie_architect'] == NULL) {
                                        $bien-> superficie_architecte =0;

                                    } else {
                                        $bien->superficie_architecte =$column['superficie_architect']-$bien->superficie_terrasse_calculer-$bien-> superficie_balcon_calculer;

                                    }
                                }else{

                                    $bien->superficie_architecte = 0;

                                }
                                $bien->superficie_architecte = 0;

                                if (array_key_exists("pu",$column)){
                                    if ($column['pu'] == NULL) {
                                        //rdc
                                        if($nv==0){
                                            $bien->prix_unitaire=11500;
                                        }else{
                                            //etage
                                            $bien->prix_unitaire=12000;
                                        }

                                    }
                                    else{
                                        $bien->prix_unitaire=$column['pu'];
                                    }
                                }else{
                                    if($column['type_local']=='LOCAL COMMERCIAL'){
                                        $bien->prix_unitaire=25000;

                                    }else{
                                        $bien->prix_unitaire=0;
                                    }
                                }
                                if (array_key_exists("prix_box",$column)){
                                    if ($column['prix_box'] == NULL) {
                                        $bien->prix_box = 0;

                                    } else {
                                        $bien->prix_box = $column['prix_box'];

                                    }
                                }else{
                                    $bien->prix_box = $column['prix_box'];

                                }


                                $sup=$bien->superficie_architecte ;
                                $bien->superficie_total=$sup+$bien->superficie_balcon+$bien->superficie_terrasse;
                                $bien->prix=$bien->prix_unitaire*($sup+$bien->superficie_balcon+$bien->superficie_terrasse)+$bien->prix_parking+ $bien->prix_box;
                                $bien->etat='disponible';
                                $bien->orientation = null;
                                $bien->conventionne = 0;
                                $bien->tranche_id = $tranches->id;
                                $bien->projet_id = $projet_id;
                                $bien->avance_minimale = 0;

                                if($bien->save())
                                {
                                    if (array_key_exists("Categorie",$column)){
                                        $pattern = "/[,\s.]/";
                                        $exp=preg_split($pattern, $column['Categorie']);

                                        $balcon=0;
                                        $chambre=0;
                                        $salon=0;
                                        $cuisin=0;
                                        $sdb=0;
                                        $placard=0;
                                        $terasse=0;
                                        for($i=0;$i<=count($exp)-1;$i++){

                                            if (str_contains($exp[$i], 'CHAMBRES')) {
                                                 $chambre=explode("CHAMBRES",$exp[$i]);
                                                $chambre=$chambre[0];
                                            }elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                 $placard=explode("PLACARDS",$exp[$i]);
                                                $placard=$placard[0];
                                            }
                                            elseif (str_contains($exp[$i], 'SALON')) {
                                                 $salon=explode("SALON",$exp[$i]);
                                                $salon=$salon[0];
                                            }
                                            elseif (str_contains($exp[$i], 'CUISINE')) {
                                                 $cuisin=explode("CUISINE",$exp[$i]);
                                                $cuisin=$cuisin[0];
                                            }
                                            elseif (str_contains($exp[$i], 'SDB')) {
                                                 $sdb=explode("SDB",$exp[$i]);
                                                $sdb=$sdb[0];
                                            }
                                             elseif (str_contains($exp[$i], 'PLACARDS')) {
                                                 $placard=explode("PLACARDS",$exp[$i]);
                                                $placard=$placard[0];
                                            }
                                             elseif (str_contains($exp[$i], 'TERRASSE')) {
                                                 $terassse=explode("TERRASSE",$exp[$i]);
                                                $terassse=$terassse[0];
                                            }
                                            elseif (str_contains($exp[$i], 'BALCON')) {
                                                 $balcon=explode("BALCON",$exp[$i]);
                                                $balcon=$balcon[0];
                                            }

                                        }
                                        $compo=new CompositionBien();
                                        $compo->setConnection('temp');
                                        $compo->bien_id=$bien->id;
                                        $compo->nbre_chambres=$chambre;
                                        $compo->nbre_salons=$salon;
                                        $compo->nbre_sdb=$sdb;
                                        $compo->nbre_cuisines=$cuisin;
                                        $compo->nbre_balcons=$balcon;
                                        $compo->nbre_terasses=$terasse;
                                        $compo->nbre_placards=$placard;
                                        $compo->save();

                                    }
                                }


                            }
                        }

                    }
                    
                }

              }


            }
           
    
 
    
        }
    
        Log::info('blocs: ',$blocs);
    }

    private function get_imoo()
    {
        
    }
    

    public function testfunction( Request $request)
    {
        $data = $request->input('data');
        foreach($data as $column)
        {
            if(array_key_exists('NUM',$column))
            
            // $explode_numero = explode("Appt", $column['Appt_Num']);
            
            Log::info($column);
            
            // log::info($explode_numero);

        }
        
        // $explode_numero = explode("Appt", $data['Appt_Num']);
       
    }
 
//     public function UploadDataExcel(Request $request)
// {
//     DatabaseHelper::Config();
//     $tranche_id = Tranche::on('temp')->where('projet_Id', $request->projetId)
//         ->orderBy('id', 'desc')
//         ->pluck('id')
//         ->first();
//     $projet_id = $request->projetId;

//     set_time_limit(0);
//     ini_set('memory_limit', '-1');

//     $data = $request->input('data');
//     $blocs = []; // Initialize an array to store blocs

//     // Iterate through each element in the $data array
//     foreach ($data as $column) {
//         // Retrieve blocs from the 'temp' database connection where 'nom', 'tranche_id', and 'projet_id' match the column's values
//         $bloc = Bloc::on('temp')
//             ->where('nom', $column['Bloc'])
//             ->where('tranche_id', $tranche_id)
//             ->where('projet_id', $projet_id)
//             ->get();

//         // Merge the retrieved blocs into the $blocs array
//         $blocs = array_merge($blocs, $bloc);

//         // Log the current bloc's name
//         Log::info($column['Bloc']);
//     }

//     // Log the retrieved blocs
//     Log::info('blocs: ' . json_encode($blocs));
// }


}
