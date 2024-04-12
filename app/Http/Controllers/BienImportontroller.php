<?php

namespace App\Imports;

use App\Bien;

use App\Bloc;
use App\CompositionBien;
use App\TypeBien;
use App\Immeuble;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
class BiensImport implements ToCollection,WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function  __construct($projet_id,$tranche_id)
    {
        $this->projet_id= $projet_id;
        $this->tranche_id= $tranche_id;

    }
    public function collection(Collection $rows)
    {

        set_time_limit(0);
        ini_set('memory_limit', '-1');


        foreach ($rows as $row) {
            $bloc='';

            $explode_bloc=explode(" ",$row['bloc']);

            $bloc=Bloc::where('description', $row['bloc'])->where('tranche_id',$this->tranche_id)->where('project_id',$this->projet_id)->get();
           if(count($bloc)>0) {

             //bloc exist
             foreach ($bloc as $blc_id) {
                $immeuble = Immeuble::where('description', $row['immeuble'])->where('tranche_id', $this->tranche_id)->where('project_id', $this->projet_id)->where('bloc_id', $blc_id->id)->get(['id']);
                if (count($immeuble) > 0) {
                    foreach ($immeuble as $imm_id) {

                                $nv=0;
                                    //if le bien formuler  $bien_propriete_dite_1 exist dans la BD
                                $bien_count=Bien::where(function ($query) use ($row){
                                    $query->where('propriete_dite_bien',$row['appt_num'])->orwhere('propriete_dite_bien',$row['magasin_num']);
                                })->where('tranche_id', $this->tranche_id)->where('project_id', $this->projet_id)->where('bloc_id', $blc_id->id)->count();
                                                if($bien_count==0) {
                                                    $bien = new Bien();
                                                    $bien->bloc_id = $blc_id->id;
                                                    $bien->immeuble_id = $imm_id->id;
                                                        //if colonne exist    if (array_key_exists("appt_num",$row->toArray())){
                                                    if (array_key_exists("appt_num",$row->toArray())){
                                                        if($row['appt_num']!=null){
                                                        $explode_numero = explode("Appt", $row['appt_num']);
                                                        $bien->numero=$explode_numero[1];
                                                         $bien->propriete_dite_bien=$row['appt_num'];
                                                        }
                                                    }
                                                    if (array_key_exists("magasin_num",$row->toArray())){
                                                        if($row['magasin_num']!=null){
                                                             $bien->numero=$row['magasin_num'];
                                                            $bien->propriete_dite_bien=$row['magasin_num'];

                                                        }
                                                    }



                                                        //fichie_appt niveau et etage //fichier_magazin =>niveau
                                                    if (array_key_exists("niveau",$row->toArray()) && array_key_exists("etage",$row->toArray())){

                                                            if($row['niveau']!=null){


                                                                 if (str_contains($row['niveau'], 'er etage')) {
                                                                      $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_1[0];
                                                                      $nv=$explode_niveau_1[0];
                                                                 }elseif(str_contains($row['niveau'], 'eme etage')){
                                                                      $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_2[0];
                                                                      $nv=$explode_niveau_2[0];

                                                                 }elseif(str_contains($row['niveau'], 'ème etage')){
                                                                      $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_3[0];
                                                                      $nv=$explode_niveau_2[0];
                                                                 }
                                                                 elseif(str_contains($row['niveau'], 'RDC')){
                                                                    $bien->niveau=0;
                                                                    $nv=0;
                                                                 }


                                                            }elseif($row['etage']!=null){
                                                                $bien->niveau=$row['etage'];
                                                                 $nv=$row['etage'];

                                                            }
                                                        }
                                                    else{
                                                        if($row['niveau']!=null){
                                                             if (str_contains($row['niveau'], 'er etage')) {
                                                                      $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_1[0];
                                                                      $nv=$explode_niveau_1[0];
                                                                 }elseif(str_contains($row['niveau'], 'eme etage')){
                                                                      $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_2[0];
                                                                      $nv=$explode_niveau_2[0];

                                                                 }elseif(str_contains($row['niveau'], 'ème etage')){
                                                                      $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_3[0];
                                                                      $nv=$explode_niveau_2[0];
                                                                 }
                                                                 elseif(str_contains($row['niveau'], 'RDC')){
                                                                    $bien->niveau=0;
                                                                    $nv=0;
                                                                 }
                                                        }
                                                    }



                                                    if ($row['type_local']=='APPARTEMENT'){
                                                        $type=TypeBien::where('type','Appartement')->get()->first();
                                                        $bien->type_id=$type->id;

                                                    }
                                                    elseif ($row['type_local']=='LOCAL COMMERCIAL') {
                                                        $type=TypeBien::where('type','Magasin')->get()->first();
                                                        $bien->type_id=$type->id;

                                                    }
                                                    $bien->partie_p = $row['partie_p'];

                                                    if (array_key_exists("parking",$row->toArray())){
                                                        if ($row['parking'] == NULL) {

                                                             $bien->prix_parking = 0;
                                                        } else {
                                                             $bien->prix_parking = $row['parking'];
                                                        }
                                                    }else{
                                                        $bien->prix_parking = 0;
                                                        }



                                                    if (array_key_exists("balcon",$row->toArray())){
                                                        if ($row['balcon'] == NULL||$row['balcon'] == 'SYNDIC PROPOSE'||$row['balcon']=='SYNDIC PLAN') {
                                                            $sup_balcon=0;
                                                             $bien->superficie_balcon = 0;

                                                        } else {
                                                            $bien->superficie_balcon = $row['balcon'];

                                                        }
                                                    }else{
                                                         $bien->superficie_balcon = 0;
                                                    }
                                                    if (array_key_exists("terrasse",$row->toArray())){
                                                        if ($row['terrasse'] == NULL) {
                                                            $bien->superficie_terasse = 0;

                                                        } else {

                                                            $bien->superficie_terasse = $row['terrasse'];

                                                        }
                                                    }else{
                                                        $bien->superficie_terasse = 0;

                                                    }
                                                    $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                                    $bien->superficie_balcon_calculee= $bien->superficie_balcon;

                                                    if (array_key_exists("superficie_architect",$row->toArray())){
                                                        if ($row['superficie_architect'] == NULL) {
                                                            $bien->superficie =0;

                                                        } else {
                                                            $bien->superficie =$row['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                                        }
                                                    }else{

                                                        $bien->superficie = 0;

                                                    }
                                                     $bien->superficie_architecte = 0;

                                                    if (array_key_exists("pu",$row->toArray())){
                                                        if ($row['pu'] == NULL) {
                                                            //rdc
                                                            if($nv==0){
                                                                $bien->prix_unitaire=11500;
                                                            }else{
                                                                //etage
                                                                $bien->prix_unitaire=12000;
                                                            }

                                                        }
                                                        else{
                                                            $bien->prix_unitaire=$row['pu'];
                                                        }
                                                    }else{
                                                        if($row['type_local']=='LOCAL COMMERCIAL'){
                                                            $bien->prix_unitaire=25000;

                                                        }else{
                                                            $bien->prix_unitaire=0;
                                                        }
                                                    }
                                                    if (array_key_exists("prix_box",$row->toArray())){
                                                        if ($row['prix_box'] == NULL) {
                                                            $bien->prix_box = 0;

                                                        } else {
                                                            $bien->prix_box = $row['prix_box'];

                                                        }
                                                    }else{
                                                         $bien->prix_box = 0;

                                                    }
                                                    /*if($row['type_local']=='LOCAL COMMERCIAL'){
                                                        $sup=$bien->superficie;
                                                    }else{
                                                       $sup=$bien->superficie_architecte ;
                                                    }*/
                                                    $sup=$bien->superficie;
                                                    $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                                    $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                                    $bien->etat='disponible';
                                                    $bien->orientation = null;
                                                    $bien->conventionne = 0;
                                                    $bien->tranche_id = $this->tranche_id;
                                                    $bien->project_id = $this->projet_id;
                                                    $bien->avance_min = 0;
                                                    if($bien->save()){

                                                       //store composition
                                                       if (array_key_exists("categorie",$row->toArray())){
                                                                $pattern = "/[,\s.]/";
                                                                $exp=preg_split($pattern, $row['categorie']);

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
                else
                {
                        //immeuble non exist
                    $immeuble=new Immeuble();
                    $immeuble->description=$row['immeuble'];
                    $immeuble->project_id=$this->projet_id;
                    $immeuble->tranche_id=$this->tranche_id;
                    $immeuble->bloc_id=$blc_id->id;
                    if($immeuble->save()){
                        $nv=0;
                        //if le bien formuler  $bien_propriete_dite_1 exist dans la BD
                    $bien_count=Bien::where(function ($query) use ($row){
                        $query->where('propriete_dite_bien',$row['appt_num'])->orwhere('propriete_dite_bien',$row['magasin_num']);
                    })->where('tranche_id', $this->tranche_id)->where('project_id', $this->projet_id)->where('bloc_id', $blc_id->id)->where('immeuble_id', $immeuble->id)->count();
                                    if($bien_count==0) {
                                        $bien = new Bien();
                                        $bien->bloc_id = $blc_id->id;
                                        $bien->immeuble_id = $immeuble->id;
                                            //if colonne exist    if (array_key_exists("appt_num",$row->toArray())){
                                        if (array_key_exists("appt_num",$row->toArray())){
                                            if($row['appt_num']!=null){
                                            $explode_numero = explode("Appt", $row['appt_num']);
                                            $bien->numero=$explode_numero[1];
                                             $bien->propriete_dite_bien=$row['appt_num'];
                                            }
                                        }
                                        if (array_key_exists("magasin_num",$row->toArray())){
                                            if($row['magasin_num']!=null){
                                                 $bien->numero=$row['magasin_num'];
                                                $bien->propriete_dite_bien=$row['magasin_num'];

                                            }
                                        }



                                            //fichie_appt niveau et etage //fichier_magazin =>niveau
                                        if (array_key_exists("niveau",$row->toArray()) && array_key_exists("etage",$row->toArray())){

                                                if($row['niveau']!=null){


                                                     if (str_contains($row['niveau'], 'er etage')) {
                                                          $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_1[0];
                                                          $nv=$explode_niveau_1[0];
                                                     }elseif(str_contains($row['niveau'], 'eme etage')){
                                                          $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_2[0];
                                                          $nv=$explode_niveau_2[0];

                                                     }elseif(str_contains($row['niveau'], 'ème etage')){
                                                          $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_3[0];
                                                          $nv=$explode_niveau_2[0];
                                                     }
                                                     elseif(str_contains($row['niveau'], 'RDC')){
                                                        $bien->niveau=0;
                                                        $nv=0;
                                                     }


                                                }elseif($row['etage']!=null){
                                                    $bien->niveau=$row['etage'];
                                                     $nv=$row['etage'];

                                                }
                                            }
                                        else{
                                            if($row['niveau']!=null){
                                                 if (str_contains($row['niveau'], 'er etage')) {
                                                          $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_1[0];
                                                          $nv=$explode_niveau_1[0];
                                                     }elseif(str_contains($row['niveau'], 'eme etage')){
                                                          $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_2[0];
                                                          $nv=$explode_niveau_2[0];

                                                     }elseif(str_contains($row['niveau'], 'ème etage')){
                                                          $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                          $bien->niveau=$explode_niveau_3[0];
                                                          $nv=$explode_niveau_2[0];
                                                     }
                                                     elseif(str_contains($row['niveau'], 'RDC')){
                                                        $bien->niveau=0;
                                                        $nv=0;
                                                     }
                                            }
                                        }



                                        if ($row['type_local']=='APPARTEMENT'){
                                            $type=TypeBien::where('type','Appartement')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        elseif ($row['type_local']=='LOCAL COMMERCIAL') {
                                            $type=TypeBien::where('type','Magasin')->get()->first();
                                            $bien->type_id=$type->id;

                                        }
                                        $bien->partie_p = $row['partie_p'];


                                        if (array_key_exists("parking",$row->toArray())){
                                            if ($row['parking'] == NULL) {

                                                 $bien->prix_parking = 0;
                                            } else {
                                                 $bien->prix_parking = $row['parking'];
                                            }
                                        }else{
                                            $bien->prix_parking = 0;
                                            }



                                        if (array_key_exists("balcon",$row->toArray())){
                                            if ($row['balcon'] == NULL||$row['balcon'] == 'SYNDIC PROPOSE'||$row['balcon']=='SYNDIC PLAN') {
                                                $sup_balcon=0;
                                                 $bien->superficie_balcon = 0;

                                            } else {
                                                $bien->superficie_balcon = $row['balcon'];

                                            }
                                        }else{
                                             $bien->superficie_balcon = 0;
                                        }
                                        if (array_key_exists("terrasse",$row->toArray())){
                                            if ($row['terrasse'] == NULL) {
                                                $bien->superficie_terasse = 0;

                                            } else {

                                                $bien->superficie_terasse = $row['terrasse'];

                                            }
                                        }else{
                                            $bien->superficie_terasse = 0;

                                        }

                                        $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                        $bien->superficie_balcon_calculee= $bien->superficie_balcon;

                                        if (array_key_exists("superficie_architect",$row->toArray())){
                                            if ($row['superficie_architect'] == NULL) {
                                                $bien->superficie =0;

                                            } else {
                                                $bien->superficie =$row['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                            }
                                        }else{

                                            $bien->superficie = 0;

                                        }
                                         $bien->superficie_architecte = 0;

                                        if (array_key_exists("pu",$row->toArray())){
                                            if ($row['pu'] == NULL) {
                                                //rdc
                                                if($nv==0){
                                                    $bien->prix_unitaire=11500;
                                                }else{
                                                    //etage
                                                    $bien->prix_unitaire=12000;
                                                }

                                            }
                                            else{
                                                $bien->prix_unitaire=$row['pu'];
                                            }
                                        }else{
                                            if($row['type_local']=='LOCAL COMMERCIAL'){
                                                $bien->prix_unitaire=25000;

                                            }else{
                                                $bien->prix_unitaire=0;
                                            }
                                        }
                                        if (array_key_exists("prix_box",$row->toArray())){
                                            if ($row['prix_box'] == NULL) {
                                                $bien->prix_box = 0;

                                            } else {
                                                $bien->prix_box = $row['prix_box'];

                                            }
                                        }else{
                                             $bien->prix_box = 0;

                                        }
                                        /*if($row['type_local']=='LOCAL COMMERCIAL'){
                                            $sup=$bien->superficie;
                                        }else{
                                           $sup=$bien->superficie_architecte ;
                                        }*/
                                        $sup=$bien->superficie;
                                        $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                        $bien->prix=$bien->prix_unitaire*($sup)+$bien->prix_parking+ $bien->prix_box;
                                        $bien->etat='disponible';
                                        $bien->orientation = null;
                                        $bien->conventionne = 0;
                                        $bien->tranche_id = $this->tranche_id;
                                        $bien->project_id = $this->projet_id;
                                        $bien->avance_min = 0;
                                        if($bien->save()){

                                           //store composition
                                           if (array_key_exists("categorie",$row->toArray())){
                                                    $pattern = "/[,\s.]/";
                                                    $exp=preg_split($pattern, $row['categorie']);

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
           
           else{
                $nv=null;
               //bloc non exist
             $blocc=new Bloc();
             $blocc->description=$row['bloc'];
             $blocc->project_id=$this->projet_id;
             $blocc->tranche_id=$this->tranche_id;
              if($blocc->save()){
                //store immeuble
                $immeuble=new Immeuble();
                $immeuble->description=$row['immeuble'];
                $immeuble->project_id=$this->projet_id;
                $immeuble->tranche_id=$this->tranche_id;
                $immeuble->bloc_id=$blocc->id;
                if($immeuble->save()){
                            $bien_count_3=Bien::where(function ($query ) use ($row){
                                $query->where('propriete_dite_bien',$row['appt_num'])->orwhere('propriete_dite_bien',$row['magasin_num']);
                            })->where('tranche_id', $this->tranche_id)->where('project_id', $this->projet_id)->where('bloc_id', $blocc->id)->where('immeuble_id',$immeuble->id)->count();
                                 if($bien_count_3==0){
                                                     $bien = new Bien();
                                                    $bien->bloc_id = $blocc->id;
                                                    $bien->immeuble_id = $immeuble->id;
                                                        //if colonne exist    if (array_key_exists("appt_num",$row->toArray())){
                                                    if (array_key_exists("appt_num",$row->toArray())){
                                                        if($row['appt_num']!=null){
                                                        $explode_numero = explode("Appt", $row['appt_num']);
                                                        $bien->numero=$explode_numero[1];
                                                         $bien->propriete_dite_bien=$row['appt_num'];
                                                        }
                                                    }
                                                    if (array_key_exists("magasin_num",$row->toArray())){
                                                        if($row['magasin_num']!=null){
                                                             $bien->numero=$row['magasin_num'];
                                                            $bien->propriete_dite_bien=$row['magasin_num'];

                                                        }
                                                    }



                                                        //fichie_appt niveau et etage //fichier_magazin =>niveau
                                                    if (array_key_exists("niveau",$row->toArray()) && array_key_exists("etage",$row->toArray())){
                                                            if($row['niveau']!=null){
                                                               if (str_contains($row['niveau'], 'er etage')) {
                                                                      $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_1[0];
                                                                      $nv=$explode_niveau_1[0];
                                                                 }elseif(str_contains($row['niveau'], 'eme etage')){
                                                                      $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_2[0];
                                                                      $nv=$explode_niveau_2[0];

                                                                 }elseif(str_contains($row['niveau'], 'ème etage')){
                                                                      $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_3[0];
                                                                      $nv=$explode_niveau_2[0];
                                                                 }
                                                                 elseif(str_contains($row['niveau'], 'RDC')){
                                                                    $bien->niveau=0;
                                                                    $nv=0;
                                                                 }
                                                            }elseif($row['etage']!=null){
                                                                $bien->niveau=$row['etage'];
                                                                 $nv=$row['etage'];

                                                            }
                                                        }
                                                    else{
                                                        if($row['niveau']!=null){
                                                           if (str_contains($row['niveau'], 'er etage')) {
                                                                      $explode_niveau_1 = explode("er etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_1[0];
                                                                      $nv=$explode_niveau_1[0];
                                                                 }elseif(str_contains($row['niveau'], 'eme etage')){
                                                                      $explode_niveau_2 = explode("eme etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_2[0];
                                                                      $nv=$explode_niveau_2[0];

                                                                 }elseif(str_contains($row['niveau'], 'ème etage')){
                                                                      $explode_niveau_3 = explode("ème etage", $row['niveau']);
                                                                      $bien->niveau=$explode_niveau_3[0];
                                                                      $nv=$explode_niveau_2[0];
                                                                 }
                                                                 elseif(str_contains($row['niveau'], 'RDC')){
                                                                    $bien->niveau=0;
                                                                    $nv=0;
                                                                 }
                                                        }
                                                    }



                                                    if ($row['type_local']=='APPARTEMENT'){
                                                        $type=TypeBien::where('type','Appartement')->get()->first();
                                                        $bien->type_id=$type->id;

                                                    }
                                                    elseif ($row['type_local']=='LOCAL COMMERCIAL') {
                                                        $type=TypeBien::where('type','Magasin')->get()->first();
                                                        $bien->type_id=$type->id;

                                                    }
                                                    $bien->partie_p = $row['partie_p'];

                                                      if (array_key_exists("balcon",$row->toArray())){
                                                        if ($row['balcon'] == NULL||$row['balcon'] == 'SYNDIC PROPOSE'||$row['balcon']=='SYNDIC PLAN') {

                                                             $bien->superficie_balcon = 0;

                                                        } else {
                                                            $bien->superficie_balcon = $row['balcon'];

                                                        }
                                                    }else{
                                                         $bien->superficie_balcon = 0;
                                                    }
                                                    if (array_key_exists("terrasse",$row->toArray())){
                                                        if ($row['terrasse'] == NULL) {
                                                            $bien->superficie_terasse = 0;

                                                        } else {

                                                            $bien->superficie_terasse = $row['terrasse'];

                                                        }
                                                    }else{
                                                        $bien->superficie_terasse = 0;

                                                    }



                                                    if (array_key_exists("parking",$row->toArray())){
                                                        if ($row['parking'] == NULL) {

                                                             $bien->prix_parking = 0;
                                                        } else {
                                                             $bien->prix_parking = $row['parking'];
                                                        }
                                                    }else{
                                                        $bien->prix_parking = 0;
                                                        }


                                                    $bien->superficie_terasse_calculee=$bien->superficie_terasse;
                                                    $bien->superficie_balcon_calculee= $bien->superficie_balcon;

                                                    if (array_key_exists("superficie_architect",$row->toArray())){
                                                        if ($row['superficie_architect'] == NULL) {
                                                            $bien->superficie =0;

                                                        } else {
                                                            $bien->superficie =$row['superficie_architect']-$bien->superficie_terasse_calculee-$bien->superficie_balcon_calculee;

                                                        }
                                                    }else{

                                                        $bien->superficie = 0;

                                                    }
                                                    $bien->superficie_architecte = 0;

                                                    if (array_key_exists("pu",$row->toArray())){
                                                        if ($row['pu'] == NULL) {
                                                            //rdc
                                                            if($nv==0){
                                                                $bien->prix_unitaire=11500;
                                                            }else{
                                                                //etage
                                                                $bien->prix_unitaire=12000;
                                                            }

                                                        }
                                                        else{
                                                            $bien->prix_unitaire=$row['pu'];
                                                        }
                                                    }else{
                                                        if($row['type_local']=='LOCAL COMMERCIAL'){
                                                            $bien->prix_unitaire=25000;

                                                        }else{
                                                            $bien->prix_unitaire=0;
                                                        }
                                                    }
                                                    if (array_key_exists("prix_box",$row->toArray())){
                                                        if ($row['prix_box'] == NULL) {
                                                            $bien->prix_box = 0;

                                                        } else {
                                                            $bien->prix_box = $row['prix_box'];

                                                        }
                                                    }else{
                                                         $bien->prix_box = 0;

                                                    }
                                                   /* if($row['type_local']=='LOCAL COMMERCIAL'){
                                                        $sup=$bien->superficie;
                                                    }else{
                                                       $sup=$bien->superficie ;
                                                    }*/
                                                    $sup=$bien->superficie ;
                                                    $bien->superficie_totale=$sup+$bien->superficie_balcon+$bien->superficie_terasse;
                                                    $bien->prix=$bien->prix_unitaire*($sup+$bien->superficie_balcon+$bien->superficie_terasse)+$bien->prix_parking+ $bien->prix_box;
                                                    $bien->etat='disponible';
                                                    $bien->orientation = null;
                                                    $bien->conventionne = 0;
                                                    $bien->tranche_id = $this->tranche_id;
                                                    $bien->project_id = $this->projet_id;
                                                    $bien->avance_min = 0;
                                                    if($bien->save()){

                                                       //store composition
                                                       if (array_key_exists("categorie",$row->toArray())){
                                                                $pattern = "/[,\s.]/";
                                                                $exp=preg_split($pattern, $row['categorie']);

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
