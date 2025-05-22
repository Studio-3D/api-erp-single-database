<?php

namespace  App\Http\Helpers;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\HistoriqueBienHelper;
use Illuminate\Support\Facades\Auth;
use App\Models\Bien;
use App\Models\Frein_Bien;
use App\Http\Helpers\FreinBienHelper;
use App\Models\Frein;
use App\Models\Notification;
use App\Models\Relance_Rdv_Visite;
use App\Enum\EtatBien;
use App\Models\Visite;
use App\Enum\StatutVisiteEnum;
use App\Enum\InteretEnum;
use App\Models\User;
use Carbon\Carbon;
use App\Models\TypeBien;
use App\Models\CompositionBien;
use Illuminate\Support\Facades\Log;

use App\Models\TraitementFrein;






class Bien_Helper
{


    public static function checkAndCreateBienByExcel($projet_id,$tranche_id,$bloc_id,$immeuble_id,$row){

       // DatabaseHelper::Config(); on desactive si on fait import sans cron version ancien
        $bien_count = Bien::on('temp')->where(function ($query) use ($row, $projet_id, $tranche_id, $bloc_id, $immeuble_id) {
            if (!empty($row['Numero'])) {
                $query->where('propriete_dite_bien', $row['Numero']);
            }
            if ($immeuble_id !== null) {
                $query->where('immeuble_id', $immeuble_id);
            }
            if ($bloc_id !== null) {
                    $query->where('bloc_id', $bloc_id);
            }
            if ($tranche_id !== null) {
                $query->where('tranche_id', $tranche_id);
            }
            $query->where('projet_id', $projet_id);
        })->count();

        if ($bien_count == 0) {
            $bien= new  Bien();
            $bien->setConnection('temp');
            $bien->immeuble_id = $immeuble_id ?? null;
            $bien->bloc_id=$bloc_id ?? null;
            $bien->tranche_id = $tranche_id ?? null;
            $bien->projet_id = $projet_id;

            if (array_key_exists("Numero",$row) && $row['Numero']!=null ){
                    $bien->propriete_dite_bien=$row['Numero'];
            }
            if (str_contains($row['Numero'], 'Appt')) {
                $explode_numero = explode("Appt", $row['Numero']);
                $num=$explode_numero[1];

              }elseif(str_contains($row['Numero'], 'APP')){
                $explode_numero = explode("APP", $row['Numero']);
                $num=$explode_numero[1];
              }else{
                 $num=$row['Numero'];
              }
            $bien->numero=$num;

            if (str_contains($row['Etage'], 'er etage')) {
                $explode_niveau_1 = explode("er etage", $row['Etage']);
                $nv=$explode_niveau_1[0];
            }elseif(str_contains($row['Etage'], 'er étage')){
                            $explode_niveau_5 = explode("er étage", $row['Etage']);
                            $nv=$explode_niveau_5[0];
            }elseif(str_contains($row['Etage'], 'eme etage')){
                            $explode_niveau_2 = explode("eme etage", $row['Etage']);
                            $nv=$explode_niveau_2[0];

            }elseif(str_contains($row['Etage'], 'ème etage')){
                            $explode_niveau_3 = explode("ème etage", $row['Etage']);
                            $nv=$explode_niveau_3[0];
            }
            elseif(str_contains($row['Etage'], 'ème étage')){
                            $explode_niveau_4 = explode("ème étage", $row['Etage']);
                            $nv=$explode_niveau_4[0];
            }
            elseif(str_contains($row['Etage'], 'RDC')){
                        $nv=0;
            }else{
            $nv=0;
            }
              $bien->niveau=$nv;

            if (array_key_exists("Type bien",$row) && $row['Type bien']!=null){
                $type=TypeBien::on('temp')->where('projet_id',$projet_id)->get();
                foreach ($type as $key => $value) {
                    if ($value->id ==intval($row['Type bien'])) {
                        $bien->type_id=$value->id;
                    }
                }
            }
            if (array_key_exists("Prix parking",$row) && $row['Prix parking'] != NULL) {
                    $bien->prix_parking = $row['Prix parking'];
            }else{
                $bien->prix_parking = 0;
            }
            if (array_key_exists("Prix box",$row) && $row['Prix box']!= NULL){
                $bien->prix_box = $row['Prix box'];
            } else {
                $bien->prix_box = 0;
            }
            if (array_key_exists("Balcon",$row)){
                if ($row['Balcon'] == NULL || $row['Balcon'] == 'SYNDIC PROPOSE'||$row['Balcon']=='SYNDIC PLAN') {
                    $bien->superficie_balcon = 0;
                    $bien-> superficie_balcon_calculer=0;
                } else {
                    $bien->superficie_balcon = $row['Balcon'];
                    $bien-> superficie_balcon_calculer=$row['Balcon'];
                }
            }
            if (array_key_exists("Terrasse",$row) && $row['Terrasse'] != NULL) {
                    $bien->superficie_terrasse = $row['Terrasse'];
                    $bien->superficie_terrasse_calculer= $row['Terrasse'];
            }else{
                $bien->superficie_terrasse = 0;
                $bien->superficie_terrasse_calculer=0;
            }
            if (array_key_exists("Superficie architect",$row) && $row['Superficie architect'] != NULL) {
            $bien->superficie_architecte =$row['Superficie architect'];
            }else{
                $bien->superficie_architecte = 0;
            }
            if(array_key_exists("Superficie habitable",$row) && $row['Superficie habitable'] != NULL) {
                $bien->superficie_habitable =$row['Superficie habitable'];
            }else{
                $bien->superficie_habitable = 0;
            }
            if (array_key_exists("pu",$row) && $row['pu'] != NULL) {
                $bien->prix_unitaire=$row['pu'];
            }else{
                $bien->prix_unitaire=0;
            }
            if (array_key_exists("Orientation",$row) && $row['Orientation'] != NULL) {
                $bien->orientation=$row['Orientation'];
            }else{
                $bien->orientation='N';
            }
            if (array_key_exists("Avance minimale",$row) && $row['Avance minimale'] != NULL) {
                $bien->avance_minimale=$row['Avance minimale'];
            }else{
                $bien->avance_minimale=0;
            }
            if (array_key_exists("Nombre facades",$row) && $row['Nombre facades'] != NULL) {
                $bien->nbre_facades=$row['Nombre facades'];
            }else{
                $bien->nbre_facades=0;
            }
            if (array_key_exists("Superficie totale",$row) && $row['Superficie totale'] != NULL) {
                $bien->superficie_total =$row['Superficie totale'];
            }else{
                $bien->superficie_total=$bien->superficie_habitable+$bien->superficie_balcon+$bien->superficie_terrasse;
            }

            $bien->superficie_vendable=$bien->superficie_habitable+$bien->superficie_balcon_calculer+$bien->superficie_terrasse_calculer;
            $bien->prix=$bien->prix_unitaire*$bien->superficie_total+$bien->prix_parking+ $bien->prix_box;
            $bien->etat='disponible';
            if($bien->save()){
                $nb_chambre=0;
                $nb_salon=0;
                $nb_cuisine=0;
                $nb_sdb=0;
                $nb_hall=0;
                $nb_placard=0;
                $nb_balcon=0;
                $nb_terasse=0;
                $nb_buanderie=0;
                $nb_reception=0;
                if (array_key_exists("Nombre chambre",$row) && $row['Nombre chambre'] != NULL) {
                    $nb_chambre =$row['Nombre chambre'];
                }
                if (array_key_exists("Nombre salon",$row) && $row['Nombre salon'] != NULL) {
                    $nb_salon =$row['Nombre salon'];
                }
                if (array_key_exists("Nombre cuisine",$row) && $row['Nombre cuisine'] != NULL) {
                    $nb_cuisine =$row['Nombre cuisine'];
                }
                if (array_key_exists("Nombre sdb",$row) && $row['Nombre sdb'] != NULL) {
                    $nb_sdb =$row['Nombre sdb'];
                }
                if (array_key_exists("Nombre hall",$row) && $row['Nombre hall'] != NULL) {
                    $nb_hall=$row['Nombre hall'];
                }
                if (array_key_exists("Nombre terasse",$row) && $row['Nombre terasse'] != NULL) {
                    $nb_terasse=$row['Nombre terasse'];
                }
                if (array_key_exists("Nombre balcon",$row) && $row['Nombre balcon'] != NULL) {
                    $nb_balcon=$row['Nombre balcon'];
                }
                if (array_key_exists("Nombre buanderie",$row) && $row['Nombre buanderie'] != NULL) {
                    $nb_buanderie=$row['Nombre buanderie'];
                }
                if (array_key_exists("Nombre placard",$row) && $row['Nombre placard'] != NULL) {
                    $nb_placard=$row['Nombre placard'];
                }
                if (array_key_exists("Nombre reception",$row) && $row['Nombre reception'] != NULL) {
                    $nb_reception=$row['Nombre reception'];
                }
                if($nb_chambre!=0||$nb_salon!=0||$nb_cuisine!=0||$nb_sdb!=0||$nb_hall!=0||$nb_placard!=0||$nb_balcon!=0||$nb_terasse!=0||$nb_buanderie!=0||$nb_reception!=0){
                    $compo=new CompositionBien();
                                    $compo->setConnection('temp');
                                    $compo->bien_id=$bien->id;
                                    $compo->nbre_chambres=$nb_chambre;
                                    $compo->nbre_salons=$nb_salon;
                                    $compo->nbre_sdb=$nb_sdb;
                                    $compo->nbre_cuisines=$nb_cuisine;
                                    $compo->nbre_balcons=$nb_balcon;
                                    $compo->nbre_terasses=$nb_terasse;
                                    $compo->nbre_placards=$nb_placard;
                                    $compo->nbre_halls=$nb_hall;
                                    $compo->nbre_buanderies=$nb_buanderie;
                                    $compo->nbre_receptions=$nb_reception;
                                    $compo->save();
                }


                Bien_Helper::store_bien_frein($bien->id,'import');

                /*if (array_key_exists("composition",$row)){
                    $pattern = "/[,\s.]/";
                    $exp=preg_split($pattern, $row['composition']);
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

                }*/
            }
        }
    }

    public static function libererBien($id,$text,$dst_id){
        if($text!='console'){
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        }
        $bien = Bien::on('temp')->findOrfail($id);
        $bien->etat = EtatBien::DISPONIBLE->value;
      //  $bien->desistement_id=$dst_id;
        if($bien->save()){
            Bien_Helper::store_bien_frein($bien->id,$text);
            //UPDATE DERNIER VISITE pre reserve=>pre reserve_perdu // vendu==>reservation_perdu
            $visite=Visite::on('temp')->where('bien_id',$id)->where('interet',InteretEnum::Intéressé->value)->orderBy('created_at', 'DESC')->first();
            if($visite!=null){
                if($text=='console'){
                //pre reserve
                if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                    $visite->statut=StatutVisiteEnum::Pré_Réservation_Perdu->value;
                }elseif($visite->statut==StatutVisiteEnum::Vendu->value){
                    $visite->statut=StatutVisiteEnum::Réservation_Perdu->value;
                }
                $visite->save();
                }

                //SUPPRIMER LES OLDS NOTIF
                $notif_old_relance=Notification::on('temp')->where(function ($query){
                    $query->where('type',1)
                        ->orwhere('type',2);})
                    ->where(function ($query_2) use($visite){
                            $query_2->where('visite_id',$visite->id);})
                    ->get();
                    if(($notif_old_relance->count())>0){
                       foreach($notif_old_relance as $nt_r){
                        $nt_r->delete();
                       }
                    }
                /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                    $old_relances_rdv=Relance_Rdv_Visite::on('temp')->where('visite_id',$visite->id)->where('type_traitement',0)->get();
                    if(count($old_relances_rdv)>0){
                        foreach($old_relances_rdv as $old){
                            $old->type_traitement=2;//auto
                            $old->date_traitement=Carbon::now();
                            //si old visite pre reserve en suite n visite vendu ==>user_id_traite(l'ancien user)
                            if($old->visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                if($visite->statut==StatutVisiteEnum::Vendu->value){
                                    $old->user_id_traite=$visite->user_id;
                                }
                                else{
                                    $old->user_id_traite=$text!='console'?$userAuth->value('id'):null;
                                }
                            }
                            else{
                                $old->user_id_traite=$text!='console'?$userAuth->value('id'):null;
                            }
                            $old->save();
                        }

                    }
            }
            //traitement Frein
            $traitement_frein=TraitementFrein::on('temp')->where('bien_id',$id)->where('interet',InteretEnum::Intéressé->value)->where('statut',1)->orderBy('created_at', 'DESC')->first();
            if($traitement_frein!=null){
                $traitement_frein->statut=StatutVisiteEnum::Pré_Réservation_Perdu->value;
                $traitement_frein->save();
                //SUPPRIMER LES OLDS NOTIF
                $notif_old_relance=Notification::on('temp')->where(function ($query){
                    $query->where('type',1)
                        ->orwhere('type',2);})
                    ->where(function ($query_2) use($traitement_frein){
                            $query_2->where('visite_id',$traitement_frein->visite_id);})
                    ->get();
                    if(($notif_old_relance->count())>0){
                       foreach($notif_old_relance as $nt_r){
                        $nt_r->delete();
                       }
                    }
                /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                    $old_relances_rdv=Relance_Rdv_Visite::on('temp')->where('visite_id',$traitement_frein->visite_id)->where('type_traitement',0)->get();
                    if(count($old_relances_rdv)>0){
                        foreach($old_relances_rdv as $old){
                            $old->type_traitement=2;//auto
                            $old->date_traitement=Carbon::now();
                            $old->user_id_traite=null;
                            $old->save();
                        }

                    }
            }
        }
        if($text=='console'){
            HistoriqueBienHelper::createHistoriqueBien(1, "liberation automatique",$id,NULL,NULL,NULL,null,null);
        }
        else{
            HistoriqueBienHelper::createHistoriqueBien(4, "liberer", $id, Auth::guard('api')->user()->id,NULL,NULL,null,null);

        }
    }
    public static function store_bien_frein($id,$text)
    {
        if($text!='console' && $text!='import'){
            DatabaseHelper::Config();
        }
        $bien=Bien::on('temp')->findorfail($id);
        $array_fr_id=array();
        $freins= Frein::on('temp')
        ->join('visites', 'visites.id', '=', 'freins.visite_id')
        ->leftjoin('frein_tranches', 'frein_tranches.frein_id', '=', 'freins.id')
        ->leftjoin('frein_etages', 'frein_etages.frein_id', '=', 'freins.id')
        ->leftjoin('frein_orientations', 'frein_orientations.frein_id', '=', 'freins.id')
        ->leftjoin('frein_typologies', 'frein_typologies.frein_id', '=', 'freins.id')
        ->leftjoin('frein_vues', 'frein_vues.frein_id', '=', 'freins.id')
        ->select('freins.id','freins.tranche as fr_tranche','freins.etage as fr_etage',
        'freins.orientation as fr_orientation','freins.typologie as fr_typologie',
       'freins.vue as fr_vue','freins.prix_min as fr_prix_min','freins.prix_max as fr_prix_max',
        'freins.superficie_min as fr_superficie_min','freins.superficie_max as fr_superficie_max',
        'frein_tranches.tranche_id','frein_etages.etage',
        'frein_orientations.orientation','frein_typologies.typologie_id','frein_vues.vue_id','freins.avance as fr_avance'
        )
        ->where('visites.projet_id', $bien->projet_id)
        ->whereIN('freins.etat', [1,2,6])
        ->where('visites.etat', 1)
        ->get();

        foreach($freins as $fr){
            if( ($fr->fr_tranche==1 && $fr->tranche_id==$bien->tranche_id)
           || ($fr->fr_etage==1 && $fr->etage==$bien->niveau)
           || ($fr->fr_orientation==1 && $fr->orientation==$bien->orientation)
           || ($fr->fr_typologie==1 && $fr->typologie_id==$bien->typologie_id)
           || ($fr->fr_vue==1 && $fr->vue_id==$bien->vue_id)
           || ($fr->fr_prix_min!=null && $fr->fr_prix_min<=$bien->prix)
           || ($fr->fr_prix_max!=null && $fr->fr_prix_max>=$bien->prix)
           || ($fr->fr_superficie_min!=null && $fr->fr_superficie_min<=$bien->superficie_habitable)
           || ($fr->fr_superficie_max!=null && $fr->fr_superficie_max>=$bien->superficie_habitable)
           ||(($fr->fr_avance!=null ||$fr->fr_avance!=0 ) && $fr->fr_avance<=$bien->avance_minimale)
            ){
                $exist=0;
                     //test si id du frein exist dans array
                    if(count($array_fr_id)==0){
                        array_push($array_fr_id,$fr->id);
                    }
                    else {
                        //si array.lenght!=0 test si id du frein exist dans array
                        for($i=0;$i<=sizeof($array_fr_id)-1;$i++){
                            if($array_fr_id[$i]==$fr->id){
                                $exist=1;
                            }
                        }
                        if($exist==0){
                            array_push($array_fr_id,$fr->id);
                        }
                    }

            }

        }
        //store to table frein_bien
        if(count($array_fr_id)>0){
            foreach($array_fr_id as $id_fr){
                //if bien_id already exist with this frein (en cas d update bien ->disponible)
                $count_exist_fr_bien=Frein_Bien::on('temp')->where('bien_id',$id)->where('frein_id',$id_fr)->count();
                if($count_exist_fr_bien==0){
                    FreinBienHelper::createFreinBien($bien->id,$id_fr);
                }
            }
        }
        return response()->json(['message' => $bien], 200);

    }

}
