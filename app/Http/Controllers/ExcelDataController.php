<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Tranche;
use App\Models\Bien;
use App\Models\Bloc;
use App\CompositionBien;
use App\Models\TypeBien;
use App\Models\Immeuble;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingitem;
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
        $blocs = []; // Initialize an array to store blocs
    
        // Iterate through each element in the $data array
        // loping data from  data intm  item  from front end 
        foreach ($data as $item) {


            $tranche =Tranche::on('temp')
            ->where('nom', $item['tranche'])
            ->where('projet_id', $projet_id)
            ->get();

            //
            if($tranche)
            {
                Log::info('tranche exist ');

                foreach($tranche as $tranches)
                {
                    $bloc = Bloc::on('temp')
                    ->where('nom', $item['Bloc'])
                    ->where('tranche_id', $tranches->id)
                    ->where('projet_id', $projet_id)
                    ->get();

                    if($bloc)
                    {
                        Log::info('bloc exist ');
           
                        foreach($bloc as $blocs)
                        {
                            $immeuble = Immeuble::where('nom', $item['immeuble'])->where('tranche_id',  $tranches->id)->where('project_id', $projet_id)->where('bloc_id', $blocs->id)->get(['id']);
                            if($immeuble)
                            {
                                Log::info('immeuble exist ');
                               foreach($immeuble as $immeubles)
                               {

                                $nv=0;
                                $bien_exist=Bien::on('temp')->where(function ($query) use ($item){
                                    $query->where('propriete_dite_bien',$item['appt_num'])->orwhere('propriete_dite_bien',$item['magasin_num']);
                                })->where('tranche_id', $tranches->id)->where('project_id', $projet_id)->where('bloc_id', $blocs->id)->get();


                                if($bien_exist==0)
                                {
                                    $bien= new  Bien();
                                    $bien->setConnection('temp');
                                    $bien->bloc_id=$blocs->id;
                                    $bien->immeuble_id = $immeubles->id;

                                    if (array_key_exists("appt_num",$item->toArray()) && $item['appt_num']!=null ){
                                      
                                            $explode_numero = explode("Appt", $item['appt_num']);
                                            $bien->numero=$explode_numero[1];
                                            $bien->propriete_dite_bien=$item['appt_num'];
                                       

                                    }
                                    if (array_key_exists("magasin_num",$item->toArray()) && $item['magasin_num']!=null){
                                       
                                             $bien->numero=$item['magasin_num'];
                                             $bien->propriete_dite_bien=$item['magasin_num'];

                                        
                                    }

                                    if (array_key_exists("niveau",$item->toArray()) && array_key_exists("etage",$item->toArray())){

                                        if($item['niveau']!=null){


                                             if (str_contains($item['niveau'], 'er etage')) {
                                                  $explode_niveau_1 = explode("er etage", $item['niveau']);
                                                  $bien->niveau=$explode_niveau_1[0];
                                                  $nv=$explode_niveau_1[0];
                                             }elseif(str_contains($item['niveau'], 'eme etage')){
                                                  $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                                  $bien->niveau=$explode_niveau_2[0];
                                                  $nv=$explode_niveau_2[0];

                                             }elseif(str_contains($item['niveau'], 'ème etage')){
                                                  $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                                  $bien->niveau=$explode_niveau_3[0];
                                                  $nv=$explode_niveau_2[0];
                                             }
                                             elseif(str_contains($item['niveau'], 'RDC')){
                                                $bien->niveau=0;
                                                $nv=0;
                                             }


                                        }elseif($item['etage']!=null){
                                            $bien->niveau=$item['etage'];
                                             $nv=$item['etage'];

                                        }
                                    }else{
                                        if($item['niveau']!=null){
                                            if (str_contains($item['niveau'], 'er etage')) {
                                                     $explode_niveau_1 = explode("er etage", $item['niveau']);
                                                     $bien->niveau=$explode_niveau_1[0];
                                                     $nv=$explode_niveau_1[0];
                                                }elseif(str_contains($item['niveau'], 'eme etage')){
                                                     $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                                     $bien->niveau=$explode_niveau_2[0];
                                                     $nv=$explode_niveau_2[0];

                                                }elseif(str_contains($item['niveau'], 'ème etage')){
                                                     $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                                     $bien->niveau=$explode_niveau_3[0];
                                                     $nv=$explode_niveau_2[0];
                                                }
                                                elseif(str_contains($item['niveau'], 'RDC')){
                                                   $bien->niveau=0;
                                                   $nv=0;
                                                }
                                       }

                                    }



                                    if ($item['type_local']=='APPARTEMENT'){
                                        $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                        $bien->type_id=$type->id;

                                    }
                                    elseif ($item['type_local']=='LOCAL COMMERCIAL') {
                                        $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                        $bien->type_id=$type->id;

                                    }

                                    $bien->partie_p = $item['partie_p'];

                                    if (array_key_exists("parking",$item->toArray())){
                                        if ($item['parking'] == NULL) {

                                             $bien->prix_parking = 0;
                                        } else {
                                             $bien->prix_parking = $item['parking'];
                                        }
                                    }
                                    else{
                                        $bien->superficie_balcon = 0;
                                   }

                                   if (array_key_exists("balcon",$item->toArray())){
                                    if ($item['balcon'] == NULL || $item['balcon'] == 'SYNDIC PROPOSE'||$item['balcon']=='SYNDIC PLAN') {
                                        $sup_balcon=0;
                                         $bien->superficie_balcon = 0;

                                    } else {
                                        $bien->superficie_balcon = $item['balcon'];

                                    }
                                  }else{
                                    $bien->superficie_balcon = 0;

                                  }
                                  if (array_key_exists("terrasse",$item->toArray())){
                                    if ($item['terrasse'] == NULL) {
                                        $bien->superficie_terasse = 0;

                                    } else {

                                        $bien->superficie_terasse = $item['terrasse'];

                                    }
                                }else{

                                    $bien->superficie_terasse = 0;

                                }
                                $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                $bien->superficie_balcon_calculee= $bien->superficie_balcon;

                                if (array_key_exists("superficie_architect",$item->toArray())){
                                    if ($item['superficie_architect'] == NULL) {
                                        $bien->superficie =0;

                                    } else {
                                        $bien->superficie =$item['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                    }
                                }else{

                                    $bien->superficie = 0;

                                }
                                $bien->superficie_architecte = 0;

                                if (array_key_exists("pu",$item->toArray())){
                                    if ($item['pu'] == NULL) {
                                    
                                        if($nv==0){
                                            $bien->prix_unitaire=11500;
                                        }else{
                                        
                                            $bien->prix_unitaire=12000;
                                        }

                                    }
                                    else{
                                        $bien->prix_unitaire=$item['pu'];
                                    }
                                }else{
                                    if($item['type_local']=='LOCAL COMMERCIAL'){
                                        $bien->prix_unitaire=25000;

                                    }else{
                                        $bien->prix_unitaire=0;
                                    }
                                }
                                if (array_key_exists("prix_box",$item->toArray())){
                                    if ($item['prix_box'] == NULL) {
                                        $bien->prix_box = 0;

                                    } else {
                                        $bien->prix_box = $item['prix_box'];

                                    }
                                }else{
                                     $bien->prix_box = 0;

                                }

                                                    $sup=$bien->superficie;
                                                    $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                                    $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                                    $bien->etat='disponible';
                                                    $bien->orientation = null;
                                                    $bien->conventionne = 0;
                                                    $bien->tranche_id = $tranches->id;
                                                    $bien->project_id = $projet_id;
                                                    $bien->avance_min = 0;
                                                    if($bien->save()){
                                                        if (array_key_exists("categorie",$item->toArray())){
                                                            $pattern = "/[,\s.]/";
                                                            $exp=preg_split($pattern, $item['categorie']);

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
                                                            $compou->setConnection('temp');
                                                            $compo->bien_id=$bien->id;
                                                            $compo->nbre_chambre=$chambre;
                                                            $compo->nbre_salon=$salon;
                                                            $compo->nbre_sdb=$sdb;
                                                            $compo->nbre_cuisine=$cuisin;
                                                            $compo->nbre_balcon=$balcon;
                                                            $compo->nbre_terasse=$terasse;
                                                            $compo->nbre_placard=$placard;
                                                            $compo->save();

                                                        }

                                                    }

                                }
                                
                               }
                            }
                            //  immeuble else

                            else{
                               $immeuble=new Immeuble();
                               $immeuble->setConnection('temp');
                               $immeuble->nom=$item['immeuble'];
                               $immeuble->project_id=$projet_id;
                               $immeuble->tranche_id=$tranches->id;
                               $immeuble->bloc_id=$blocs->id;
                               if($immeuble->save()){
                                $nv=0;
                                $bien_exist=Bien::on('temp')->where(function ($query) use ($item){
                                    $query->where('propriete_dite_bien',$item['appt_num'])->orwhere('propriete_dite_bien',$item['magasin_num']);
                                })->where('tranche_id', $tranches->id)->where('project_id', $projet_id)->where('bloc_id', $blocs->id)->where('immeuble_id', $immeubles->id)->count();
                                if($bien_exist==0)
                                {
                                    $bien = new Bien();
                                    $bien->setConnection('temp');
                                    $bien->bloc_id = $blocs->id;
                                    $bien->immeuble_id = $immeubles->id;
                                }
                                if (array_key_exists("appt_num",$item->toArray())){
                                    if($item['appt_num']!=null){
                                    $explode_numero = explode("Appt", $item['appt_num']);
                                    $bien->numero=$explode_numero[1];
                                     $bien->propriete_dite_bien=$item['appt_num'];
                                    }
                                }
                                if (array_key_exists("magasin_num",$item->toArray())){
                                    if($item['magasin_num']!=null){
                                         $bien->numero=$item['magasin_num'];
                                        $bien->propriete_dite_bien=$item['magasin_num'];

                                    }
                                }

                                if (array_key_exists("niveau",$item->toArray()) && array_key_exists("etage",$item->toArray())){

                                    if($item['niveau']!=null){


                                         if (str_contains($item['niveau'], 'er etage')) {
                                              $explode_niveau_1 = explode("er etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_1[0];
                                              $nv=$explode_niveau_1[0];
                                         }elseif(str_contains($item['niveau'], 'eme etage')){
                                              $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_2[0];
                                              $nv=$explode_niveau_2[0];

                                         }elseif(str_contains($item['niveau'], 'ème etage')){
                                              $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_3[0];
                                              $nv=$explode_niveau_2[0];
                                         }
                                         elseif(str_contains($item['niveau'], 'RDC')){
                                            $bien->niveau=0;
                                            $nv=0;
                                         }


                                    }elseif($item['etage']!=null){
                                        $bien->niveau=$item['etage'];
                                         $nv=$item['etage'];

                                    }
                                }
                                else{
                                    if($item['niveau']!=null){
                                        if (str_contains($item['niveau'], 'er etage')) {
                                                 $explode_niveau_1 = explode("er etage", $item['niveau']);
                                                 $bien->niveau=$explode_niveau_1[0];
                                                 $nv=$explode_niveau_1[0];
                                            }elseif(str_contains($item['niveau'], 'eme etage')){
                                                 $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                                 $bien->niveau=$explode_niveau_2[0];
                                                 $nv=$explode_niveau_2[0];

                                            }elseif(str_contains($item['niveau'], 'ème etage')){
                                                 $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                                 $bien->niveau=$explode_niveau_3[0];
                                                 $nv=$explode_niveau_2[0];
                                            }
                                            elseif(str_contains($item['niveau'], 'RDC')){
                                               $bien->niveau=0;
                                               $nv=0;
                                            }
                                   }
                                }
                                if ($item['type_local']=='APPARTEMENT'){
                                    $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();

                                    $bien->type_id=$type->id;

                                }
                                elseif ($item['type_local']=='LOCAL COMMERCIAL') {
                                    $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                $bien->partie_p = $item['partie_p'];

                                if (array_key_exists("parking",$item->toArray())){
                                    if ($item['parking'] == NULL) {

                                         $bien->prix_parking = 0;
                                    } else {
                                         $bien->prix_parking = $item['parking'];
                                    }
                                }
                                else{
                                    $bien->prix_parking = 0;
                                    }
                                    if (array_key_exists("balcon",$item->toArray())){
                                        if ($item['balcon'] == NULL||$item['balcon'] == 'SYNDIC PROPOSE'||$item['balcon']=='SYNDIC PLAN') {
                                            $sup_balcon=0;
                                             $bien->superficie_balcon = 0;

                                        } else {
                                            $bien->superficie_balcon = $item['balcon'];

                                        }
                                    }else{
                                        $bien->superficie_balcon = 0;
                                    }

                                    if (array_key_exists("terrasse",$item->toArray())){
                                        if ($item['terrasse'] == NULL) {
                                            $bien->superficie_terasse = 0;

                                        } else {

                                            $bien->superficie_terasse = $item['terrasse'];

                                        }
                                    }else{
                                        $bien->superficie_terasse = 0;


                                    }
                                    $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                    $bien->superficie_balcon_calculee= $bien->superficie_balcon;

                                    if (array_key_exists("superficie_architect",$item->toArray())){
                                        if ($item['superficie_architect'] == NULL) {
                                            $bien->superficie =0;

                                        } else {
                                            $bien->superficie =$item['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                        }
                                    }else{
                                        $bien->superficie = 0;

                                    }
                                    $bien->superficie_architecte = 0;

                                    if (array_key_exists("pu",$item->toArray())){
                                        if ($item['pu'] == NULL) {
                                            //rdc
                                            if($nv==0){
                                                $bien->prix_unitaire=11500;
                                            }else{
                                                //etage
                                                $bien->prix_unitaire=12000;
                                            }

                                        }
                                        else{
                                            $bien->prix_unitaire=$item['pu'];
                                        }
                                    }else{
                                        if($item['type_local']=='LOCAL COMMERCIAL'){
                                            $bien->prix_unitaire=25000;

                                        }else{
                                            $bien->prix_unitaire=0;
                                        }

                                    }

                                    if (array_key_exists("prix_box",$item->toArray())){
                                        if ($item['prix_box'] == NULL) {
                                            $bien->prix_box = 0;

                                        } else {
                                            $bien->prix_box = $item['prix_box'];

                                        }
                                    }
                                    else{
                                        $bien->prix_box = 0;

                                   }

                                       $sup=$bien->superficie;
                                        $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                        $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                        $bien->etat='disponible';
                                        $bien->orientation = null;
                                        $bien->conventionne = 0;
                                        $bien->tranche_id = $tranches->id;
                                        $bien->project_id = $projet_id;
                                        $bien->avance_min = 0;
                                        if($bien->save()){

                                            if (array_key_exists("categorie",$item->toArray())){
                                                $pattern = "/[,\s.]/";
                                                $exp=preg_split($pattern, $item['categorie']);

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
                                                $compo->nbre_chambre=$chambre;
                                                $compo->nbre_salon=$salon;
                                                $compo->nbre_sdb=$sdb;
                                                $compo->nbre_cuisine=$cuisin;
                                                $compo->nbre_balcon=$balcon;
                                                $compo->nbre_terasse=$terasse;
                                                $compo->nbre_placard=$placard;
                                                $compo->save();

                                            }

                                        }
                                



                                }

                            }


                        }
                    }
                    // bloc else 
                    else{
                        $nv=null;
                        // bloc not exist 
                      $blocc=new Bloc();
                      $blocc->setConnetion('temp');
                      $blocc->nom=$item['bloc'];
                      $blocc->project_id=$projet_id;
                      $blocc->tranche_id=$tranches->id;
                      if($bloc->id){

                        $immeuble=new Immeuble();
                        $immeuble->setConnetion('temp');
                        $immeuble->nom=$item['immeuble'];
                        $immeuble->project_id=$projet_id;
                        $immeuble->tranche_id=$tranches->id;
                        $immeuble->bloc_id=$blocc->id;
                        if($immeuble->save()){
                            $bien_exist=Bien::on('temp')->where(function ($query ) use ($item){
                                $query->where('propriete_dite_bien',$item['appt_num'])->orwhere('propriete_dite_bien',$item['magasin_num']);
                            })->where('tranche_id', $tranches->id)->where('project_id', $projet_id)->where('bloc_id', $blocc->id)->where('immeuble_id',$immeuble->id)->count();

                            if($bien_exist==0)
                            {
                                $bien = new Bien();
                                $bien->setConnetion('temp');
                                $bien->bloc_id = $blocc->id;
                                $bien->immeuble_id = $immeuble->id;
                                if (array_key_exists("appt_num",$item->toArray())){
                                    if($item['appt_num']!=null){
                                    $explode_numero = explode("Appt", $item['appt_num']);
                                    $bien->numero=$explode_numero[1];
                                     $bien->propriete_dite_bien=$item['appt_num'];
                                    }
                                }
                                if (array_key_exists("magasin_num",$item->toArray())){
                                    if($item['magasin_num']!=null){
                                         $bien->numero=$item['magasin_num'];
                                        $bien->propriete_dite_bien=$item['magasin_num'];

                                    }
                                }

                                if (array_key_exists("niveau",$item->toArray()) && array_key_exists("etage",$item->toArray())){
                                    if($item['niveau']!=null){
                                       if (str_contains($item['niveau'], 'er etage')) {
                                              $explode_niveau_1 = explode("er etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_1[0];
                                              $nv=$explode_niveau_1[0];
                                         }elseif(str_contains($item['niveau'], 'eme etage')){
                                              $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_2[0];
                                              $nv=$explode_niveau_2[0];

                                         }elseif(str_contains($item['niveau'], 'ème etage')){
                                              $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                              $bien->niveau=$explode_niveau_3[0];
                                              $nv=$explode_niveau_2[0];
                                         }
                                         elseif(str_contains($item['niveau'], 'RDC')){
                                            $bien->niveau=0;
                                            $nv=0;
                                         }
                                    }elseif($item['etage']!=null){
                                        $bien->niveau=$item['etage'];
                                         $nv=$item['etage'];

                                    }else{
                                        if($item['niveau']!=null){
                                            if (str_contains($item['niveau'], 'er etage')) {
                                                       $explode_niveau_1 = explode("er etage", $item['niveau']);
                                                       $bien->niveau=$explode_niveau_1[0];
                                                       $nv=$explode_niveau_1[0];
                                                  }elseif(str_contains($item['niveau'], 'eme etage')){
                                                       $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                                       $bien->niveau=$explode_niveau_2[0];
                                                       $nv=$explode_niveau_2[0];

                                                  }elseif(str_contains($item['niveau'], 'ème etage')){
                                                       $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                                       $bien->niveau=$explode_niveau_3[0];
                                                       $nv=$explode_niveau_2[0];
                                                  }
                                                  elseif(str_contains($item['niveau'], 'RDC')){
                                                     $bien->niveau=0;
                                                     $nv=0;
                                                  }
                                         }

                                         if ($item['type_local']=='APPARTEMENT'){
                                            $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        elseif ($item['type_local']=='LOCAL COMMERCIAL') {
                                            $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        $bien->partie_p = $item['partie_p'];

                                        if (array_key_exists("balcon",$item->toArray())){
                                            if ($item['balcon'] == NULL||$item['balcon'] == 'SYNDIC PROPOSE'||$item['balcon']=='SYNDIC PLAN') {

                                                 $bien->superficie_balcon = 0;

                                            } else {
                                                $bien->superficie_balcon = $item['balcon'];

                                            }
                                        }else{
                                            $bien->superficie_terasse = 0;
                                        }

                                        if (array_key_exists("parking",$item->toArray())){
                                            if ($item['parking'] == NULL) {

                                                 $bien->prix_parking = 0;
                                            } else {
                                                 $bien->prix_parking = $item['parking'];
                                            }
                                        }
                                        else{
                                            $bien->prix_parking = 0;
                                        }

                                        
                                        $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                        $bien->superficie_balcon_calculee= $bien->superficie_balcon;
                                        if (array_key_exists("superficie_architect",$item->toArray())){
                                            if ($item['superficie_architect'] == NULL) {
                                                $bien->superficie =0;

                                            } else {
                                                $bien->superficie =$item['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                            }
                                        }else{

                                            $bien->superficie = 0;

                                        }
                                        $bien->superficie_architecte = 0;

                                        if (array_key_exists("pu",$item->toArray())){
                                            if ($item['pu'] == NULL) {
                                                //rdc
                                                if($nv==0){
                                                    $bien->prix_unitaire=11500;
                                                }else{
                                                    //etage
                                                    $bien->prix_unitaire=12000;
                                                }

                                            }
                                            else{
                                                $bien->prix_unitaire=$item['pu'];
                                            }
                                        }else{
                                            if($item['type_local']=='LOCAL COMMERCIAL'){
                                                $bien->prix_unitaire=25000;

                                            }else{
                                                $bien->prix_unitaire=0;
                                            }
                                        }
                                        if (array_key_exists("prix_box",$item->toArray())){
                                            if ($item['prix_box'] == NULL) {
                                                $bien->prix_box = 0;

                                            } else {
                                                $bien->prix_box = $item['prix_box'];

                                            }
                                        }else{
                                            $bien->prix_box = $item['prix_box'];

                                        }


                                        $sup=$bien->superficie ;
                                        $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                        $bien->prix=$bien->prix_unitaire*($sup+$bien->superficie_balcon+$bien->superficie_terasse)+$bien->prix_parking+ $bien->prix_box;
                                        $bien->etat='disponible';
                                        $bien->orientation = null;
                                        $bien->conventionne = 0;
                                        $bien->tranche_id = $tranches->id;
                                        $bien->project_id = $projet_id;
                                        $bien->avance_min = 0;

                                        if($bien->save())
                                        {
                                            if (array_key_exists("categorie",$item->toArray())){
                                                $pattern = "/[,\s.]/";
                                                $exp=preg_split($pattern, $item['categorie']);

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
                                                $compo->nbre_chambre=$chambre;
                                                $compo->nbre_salon=$salon;
                                                $compo->nbre_sdb=$sdb;
                                                $compo->nbre_cuisine=$cuisin;
                                                $compo->nbre_balcon=$balcon;
                                                $compo->nbre_terasse=$terasse;
                                                $compo->nbre_placard=$placard;
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

                
                $nv=null;
              $tranche=new Tranche();
              $tranche->setConnection('temp');
              $tranche->nom=$item['tranche'];
              $tranche->project_id=$projet_id;
              if($tranche->save){

                $bloc=new Bloc();
                $bloc->setConnection('temp');
                $bloc->nom=$item['bloc'];
                $bloc->project_id=$projet_id;
                $bloc->tranche_id=$tranche->id;
                if($bloc->save())
                {
                    $immeuble=new Immeuble();
                    $immeuble->setConnection('temp');
                    $immeuble->nom=$row['immeuble'];
                    $immeuble->project_id=$projet_id;
                    $immeuble->tranche_id=$tranche->id;
                    $immeuble->bloc_id=$bloc->id;
                }
                if($immeuble->save()){
                    $bien_exist=Bien::on('temp')->where(function ($query ) use ($item){
                        $query->where('propriete_dite_bien',$item['appt_num'])->orwhere('propriete_dite_bien',$item['magasin_num']);
                    })->where('tranche_id', $tranche->id)->where('project_id', $projet_id)->where('bloc_id', $bloc->id)->where('immeuble_id',$immeuble->id)->count();
                    if($bien_exist==0)
                    {
                        $bien = new Bien();
                        $bien->setConnection('temp');
                        $bien->bloc_id = $bloc->id;
                        $bien->immeuble_id = $immeuble->id;
                        if (array_key_exists("appt_num",$item->toArray())){
                            if($item['appt_num']!=null){
                            $explode_numero = explode("Appt", $item['appt_num']);
                            $bien->numero=$explode_numero[1];
                             $bien->propriete_dite_bien=$item['appt_num'];
                            }
                        }
                        if (array_key_exists("magasin_num",$item->toArray())){
                            if($item['magasin_num']!=null){
                                 $bien->numero=$item['magasin_num'];
                                $bien->propriete_dite_bien=$item['magasin_num'];

                            }
                        }

                        if (array_key_exists("niveau",$item->toArray()) && array_key_exists("etage",$item->toArray())){
                            if($item['niveau']!=null){
                               if (str_contains($item['niveau'], 'er etage')) {
                                      $explode_niveau_1 = explode("er etage", $item['niveau']);
                                      $bien->niveau=$explode_niveau_1[0];
                                      $nv=$explode_niveau_1[0];
                                 }elseif(str_contains($item['niveau'], 'eme etage')){
                                      $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                      $bien->niveau=$explode_niveau_2[0];
                                      $nv=$explode_niveau_2[0];

                                 }elseif(str_contains($item['niveau'], 'ème etage')){
                                      $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                      $bien->niveau=$explode_niveau_3[0];
                                      $nv=$explode_niveau_2[0];
                                 }
                                 elseif(str_contains($item['niveau'], 'RDC')){
                                    $bien->niveau=0;
                                    $nv=0;
                                 }
                            }elseif($item['etage']!=null){
                                $bien->niveau=$item['etage'];
                                 $nv=$item['etage'];

                            }else{
                                if($item['niveau']!=null){
                                    if (str_contains($item['niveau'], 'er etage')) {
                                               $explode_niveau_1 = explode("er etage", $item['niveau']);
                                               $bien->niveau=$explode_niveau_1[0];
                                               $nv=$explode_niveau_1[0];
                                          }elseif(str_contains($item['niveau'], 'eme etage')){
                                               $explode_niveau_2 = explode("eme etage", $item['niveau']);
                                               $bien->niveau=$explode_niveau_2[0];
                                               $nv=$explode_niveau_2[0];

                                          }elseif(str_contains($item['niveau'], 'ème etage')){
                                               $explode_niveau_3 = explode("ème etage", $item['niveau']);
                                               $bien->niveau=$explode_niveau_3[0];
                                               $nv=$explode_niveau_2[0];
                                          }
                                          elseif(str_contains($item['niveau'], 'RDC')){
                                             $bien->niveau=0;
                                             $nv=0;
                                          }
                                 }

                                 if ($item['type_local']=='APPARTEMENT'){
                                    $type=TypeBien::on('temp')->where('type','Appartement')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                elseif ($item['type_local']=='LOCAL COMMERCIAL') {
                                    $type=TypeBien::on('temp')->where('type','Magasin')->get()->first();
                                    $bien->type_id=$type->id;

                                }
                                $bien->partie_p = $item['partie_p'];

                                if (array_key_exists("balcon",$item->toArray())){
                                    if ($item['balcon'] == NULL||$item['balcon'] == 'SYNDIC PROPOSE'||$item['balcon']=='SYNDIC PLAN') {

                                         $bien->superficie_balcon = 0;

                                    } else {
                                        $bien->superficie_balcon = $item['balcon'];

                                    }
                                }else{
                                    $bien->superficie_terasse = 0;
                                }

                                if (array_key_exists("parking",$item->toArray())){
                                    if ($item['parking'] == NULL) {

                                         $bien->prix_parking = 0;
                                    } else {
                                         $bien->prix_parking = $item['parking'];
                                    }
                                }
                                else{
                                    $bien->prix_parking = 0;
                                }

                                
                                $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                $bien->superficie_balcon_calculee= $bien->superficie_balcon;
                                if (array_key_exists("superficie_architect",$item->toArray())){
                                    if ($item['superficie_architect'] == NULL) {
                                        $bien->superficie =0;

                                    } else {
                                        $bien->superficie =$item['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                    }
                                }else{

                                    $bien->superficie = 0;

                                }
                                $bien->superficie_architecte = 0;

                                if (array_key_exists("pu",$item->toArray())){
                                    if ($item['pu'] == NULL) {
                                        //rdc
                                        if($nv==0){
                                            $bien->prix_unitaire=11500;
                                        }else{
                                            //etage
                                            $bien->prix_unitaire=12000;
                                        }

                                    }
                                    else{
                                        $bien->prix_unitaire=$item['pu'];
                                    }
                                }else{
                                    if($item['type_local']=='LOCAL COMMERCIAL'){
                                        $bien->prix_unitaire=25000;

                                    }else{
                                        $bien->prix_unitaire=0;
                                    }
                                }
                                if (array_key_exists("prix_box",$item->toArray())){
                                    if ($item['prix_box'] == NULL) {
                                        $bien->prix_box = 0;

                                    } else {
                                        $bien->prix_box = $item['prix_box'];

                                    }
                                }else{
                                    $bien->prix_box = $item['prix_box'];

                                }


                                $sup=$bien->superficie ;
                                $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                $bien->prix=$bien->prix_unitaire*($sup+$bien->superficie_balcon+$bien->superficie_terasse)+$bien->prix_parking+ $bien->prix_box;
                                $bien->etat='disponible';
                                $bien->orientation = null;
                                $bien->conventionne = 0;
                                $bien->tranche_id = $tranches->id;
                                $bien->project_id = $projet_id;
                                $bien->avance_min = 0;

                                if($bien->save())
                                {
                                    if (array_key_exists("categorie",$item->toArray())){
                                        $pattern = "/[,\s.]/";
                                        $exp=preg_split($pattern, $item['categorie']);

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
                                        $compo->nbre_chambre=$chambre;
                                        $compo->nbre_salon=$salon;
                                        $compo->nbre_sdb=$sdb;
                                        $compo->nbre_cuisine=$cuisin;
                                        $compo->nbre_balcon=$balcon;
                                        $compo->nbre_terasse=$terasse;
                                        $compo->nbre_placard=$placard;
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
    

    public function testfunction( Request $request)
    {
        $data = $request->input('data');
        foreach($data as $item)
        {
            $explode_numero = explode("Appt", $item['Appt_Num']);
            
            Log::info($item['Appt_Num']);
            
            // log::info($explode_numero);

        }
        
        // $explode_numero = explode("Appt", $data['appt_Num']);
       
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
//     foreach ($data as $item) {
//         // Retrieve blocs from the 'temp' database connection where 'nom', 'tranche_id', and 'projet_id' match the item's values
//         $bloc = Bloc::on('temp')
//             ->where('nom', $item['Bloc'])
//             ->where('tranche_id', $tranche_id)
//             ->where('projet_id', $projet_id)
//             ->get();

//         // Merge the retrieved blocs into the $blocs array
//         $blocs = array_merge($blocs, $bloc);

//         // Log the current bloc's name
//         Log::info($item['Bloc']);
//     }

//     // Log the retrieved blocs
//     Log::info('blocs: ' . json_encode($blocs));
// }
}
