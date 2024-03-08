<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreDesistementRequest;
use App\Models\Desistement;
use App\Models\Reservation;
use App\Models\Remboursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Enum\TypeDesistement;
use App\Enum\TypeDesistementProfit;
use App\Http\Helpers\PaginationHelper;


use App\Enum\ModePaiement;
use App\Models\PiecesJointe;
use App\Models\HistoriqueDesistement;
use App\Models\Avance;
use App\Models\Client;
use App\Models\PenaliteDesistement;
use App\Models\FicheTransmission;


use App\Enum\EtatBien;
use App\Models\User;
use App\Models\Aquereur;
use App\Models\Bien;
use App\Enum\EtatReservationEnum;
use \NumberFormatter;
use Carbon\Carbon;
use App\Http\Helpers\NotificationHelper;
use App\Http\Requests\StoreAvanceRequest;
use App\Enum\RoleEnum;
use App\Models\AquereurDesistement;
use App\Models\NouvelAquereurDesistement;
use App\Models\Prospect;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\HistoriqueBienHelper;









class DesistementController extends Controller
{
    /**
     * Display a listing of the resource.
     */




    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDesistementRequest $request)

    {
        $user = Auth::user();
        if(RoleHelper::AC()){
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            //get code desistement partage =>pour stocker dans reservation
            $code_desist_reservation=0;
            $reservation=Reservation::on('temp')->findOrFail($request->reservation_id);
            if($reservation->code_desistement!=null){
              $code_desist_reservation=$reservation->code_desistement;
            }else{
                $last_code = Reservation::on('temp')->where('etat',1)->orderByRaw("CAST(code_desistement as UNSIGNED) DESC")
                ->get('code_desistement')->first();
                if ($last_code->code_desistement!=null) {
                    $code_desist_reservation= $last_code->code_desistement + 1;
                } else {
                    $code_desist_reservation = 1;
                }

            }
            $desistement = new Desistement();
            $desistement->setConnection('temp');
            $desistement->reservation_id=$request->reservation_id;
            $desistement->type=$request->type;

            if($request->type==TypeDesistement::Désistement_Définitif->value){
                $desistement->motif=$request->motif;
            }elseif($request->type==TypeDesistement::Désistement_Au_Profit->value){
                if($request->type_dp!=TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                    $desistement->lien_parente=$request->lien_parente;
                }
                $desistement->type_dp=$request->type_dp;

            }elseif($request->type==TypeDesistement::Changement_De_Bien->value){
                $desistement->bien_id_new=$request->bien_id_new;
                $desistement->montant_a_ajouter=$request->montant_a_ajouter;
                if($request->montant_a_ajouter>0){
                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                    $mont_lettre = $inWords->format($request->montant_a_ajouter);
                    $desistement->montant_a_ajouter_par_lettre=$request->mont_lettre;

                    $desistement->sr= (bool)$request->sr;
                    $desistement->mode_paiement=$request->mode_paiement;
                    //cheque cheque-banque cheque cetifice
                    if($request->mode_paiement==2||$request->mode_paiement==3||$request->mode_paiement==4){
                        $desistement->numero_paiement=$request->numero_paiement;
                        $desistement->banque_id=$request->banque_id;
                        $desistement->echeance=$request->echeance;
                    }
                    //virement versement
                    elseif($request->mode_paiement==5||$request->mode_paiement==6){
                        $desistement->numero_paiement=$request->numero_paiement;
                        $desistement->banque_id=$request->banque_id;
                    }
                }
            }

            $desistement->commentaire=$request->commentaire;
            $desistement->bien_id_ancien=$request->bien_id_ancien;
            $desistement->projet_id=$request->projet_id;
            $desistement->user_id = $userAuth->value('id');
            $last_num_recu = Desistement::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
            ->get('num_recu')->first();
            if ($last_num_recu!=null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $desistement->num_recu = '00' . $n_recu . '';
            } else {
                $desistement->num_recu = '001';
            }
            if (RoleHelper::AdminSup()) {
                //validé
                $desistement->statut = 1;
            }


            if($desistement->save()){

                //DD

                    if($request->type==TypeDesistement::Désistement_Définitif->value){
                            //store Remboursement
                        //si sum_avances >0
                        if($request->type_remb!=null){
                            //store Remboursement
                            $remboursement=new Remboursement();
                            $remboursement->setConnection('temp');
                            $remboursement->desistement_id=$desistement->id;
                            $remboursement->reservation_id=$request->reservation_id;
                            $remboursement->s_avances=$request->sum_avances_valides;
                            $remboursement->statut=0;

                            if($request->type_remb=='transfert'||$request->type_remb=='transfert_remb'){
                                $remboursement->dossier_id_transfert=$request->dossier_id;
                                $remboursement->montant_transfert=$request->sum_avances_valides;
                            }


                            if($request->type_remb=='transfert'){
                                $remboursement->mode_rembourse=$request->type_remb;
                                if (RoleHelper::AdminSup()) {
                                    $remboursement->statut=1;
                                }
                                $remboursement->etat=1;
                            }
                            elseif($request->type_remb=='transfert_remb'){
                                $remboursement->date_rembourse=$request->date_remboursement;
                                $remboursement->mode_rembourse_client=$request->mode_remboursement_transfere;
                                $remboursement->pour_le_compte=$request->pour_le_compte_transfere;
                                $remboursement->num_paiement=$request->num_paiement;
                                /*if($request->fichier_autorisation_transfere!=null){
                                    //store_fichier
                                }
                                if($request->cheque_recu_transferer!=null){
                                    //store_cheque_recu
                                    }
                                */
                                //montant rembourse lettre
                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                $mont_remb_lettre = $inWords->format($request->reste_a_rembourse);

                                if($request->type_remb_transfere=='immediat'){
                                    $remboursement->mode_rembourse='transfert_rem_direct';
                                    if (RoleHelper::AdminSup()) {
                                        $remboursement->statut=1;
                                    }
                                    $remboursement->etat=1;
                                    $remboursement->user_id_valider=$userAuth->value('id');
                                    $remboursement->montant_a_rembourser=$request->reste_a_rembourse;
                                    $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;
                                    $remboursement->montant_transfert=$request->montant_transferer;
                                }else{
                                    $remboursement->mode_rembourse='transfert_rem_apres_vente';
                                    $remboursement->statut=0;
                                    $remboursement->etat=0;
                                    $remboursement->montant_transfert=$request->montant_transferer;
                                    $remboursement->montant_a_rembourser=$request->reste_a_rembourse;
                                    $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;
                                }

                            }elseif($request->type_remb=='apres_vente'){
                                $remboursement->mode_rembourse=$request->type_remb;
                                $remboursement->statut=0;
                                $remboursement->etat=0;
                                $remboursement->montant_a_rembourser=$request->sum_avances_valides-$request->penalite_montant;
                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                $mont_remb_lettre = $inWords->format($request->sum_avances_valides-$request->penalite_montant);
                                $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;

                            }
                            elseif($request->type_remb=='direct'){
                                $remboursement->date_rembourse=$request->date_remboursement;
                                $remboursement->mode_rembourse=$request->type_remb;
                                $remboursement->mode_rembourse_client=$request->mode_remboursement;
                                $remboursement->pour_le_compte=$request->pour_le_compte;
                                $remboursement->num_paiement=$request->num_paiement_transfere;
                                if (RoleHelper::AdminSup()) {
                                    $remboursement->statut=1;
                                }
                                $remboursement->user_id_valider=$userAuth->value('id');
                                $remboursement->etat =1;
                                $remboursement->montant_a_rembourser=$request->sum_avances_valides-$request->penalite_montant;
                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                $mont_remb_lettre = $inWords->format($request->sum_avances_valides-$request->penalite_montant);
                                $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;
                                /*if($request->fichier_autorisation!=null){
                                    //store_fichier
                                    }
                                    if($request->cheque_recu!=null){
                                    //store_cheque
                                    }

                                */
                            }

                                if($remboursement->save()){
                                    $bien = Bien::on('temp')->findOrFail($request->bien_id_ancien);
                                    $bien->setConnection('temp');
                                    $bien->remboursement_id=$remboursement->id;
                                    $bien->save();
                                }

                        }

                    }
                    elseif($request->type==TypeDesistement::Désistement_Au_Profit->value){

                        if($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                            //push les aqu_id=>pour storer les non desiteurs
                             $array_aq_id=array();
                            //store les aquerures desisteur
                            $aqu_desit_Controller = new AquereurController();
                            if ($request->desisteur_dp_proche_co) {
                                foreach ($request->desisteur_dp_proche_co as $aq_nfo) {
                                    array_push($array_aq_id,$aq_nfo['id']);
                                    $dataAquereur = [
                                        'pourcentage' => $aq_nfo['pourcentage'],
                                        'client_id' => $aq_nfo['cl_id'],
                                        'aq_id' => $aq_nfo['id'],
                                        'desistement_id' => $desistement->id,
                                        'type_desisteur' => 'desisteur',
                                    ];
                                    $aqu_desit_Controller->store_aquereurs_desistement($request->merge($dataAquereur));
                                }
                            }
                            //store les non desisteurs
                            $aquereurs_non_desisteurs=Aquereur::on('temp')->where('reservation_id',$request->reservation_id)->whereNotIn('id', $array_aq_id)->get();
                            if(count($aquereurs_non_desisteurs)>0){
                                $non_desist_controller = new AquereurController();
                                    foreach ($aquereurs_non_desisteurs as $aq_nfo) {
                                        $dataAquereur_t = [
                                            'pourcentage' => $aq_nfo->pourcentage,
                                            'client_id' => $aq_nfo->client->id,
                                            'aq_id' => $aq_nfo->id,
                                            'desistement_id' => $desistement->id,
                                            'type_desisteur' => 'non_desisteur',
                                        ];
                                        $non_desist_controller->store_aquereurs_desistement($request->merge($dataAquereur_t));
                                    }

                            }

                            //store les nouveau_aqu_desistement
                            $nv_aqu_desit_Controller = new AquereurController();
                            if ($request->new_clients_dp_proche) {
                                foreach ($request->new_clients_dp_proche as $nv_aq_nfo) {
                                    $dataAquereur_nv = [
                                        'cin' => $nv_aq_nfo['cin'],
                                        'nom' => $nv_aq_nfo['nom'],
                                        'prenom' => $nv_aq_nfo['prenom'],
                                        'telephone' => $nv_aq_nfo['telephone_num1'],
                                        'pourcentage' => $nv_aq_nfo['pourcentage'],

                                    ];
                                    $nv_aqu_desit_Controller->store_new_aquereurs_desistement($request->merge($dataAquereur_nv));
                                }
                            }


                        }elseif($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){


                            //push les aqu_id=>pour storer les non desiteurs
                            $array_aq_id=array();
                            //store les aquerures desisteur
                            $aqu_desit_Controller = new AquereurController();
                            if ($request->desisteur_dp_proche_co) {
                                foreach ($request->desisteur_dp_proche_co as $aq_nfo) {
                                    array_push($array_aq_id,$aq_nfo['id']);
                                    $dataAquereur = [
                                        'pourcentage' => $aq_nfo['pourcentage'],
                                        'client_id' => $aq_nfo['cl_id'],
                                        'aq_id' => $aq_nfo['id'],
                                        'desistement_id' => $desistement->id,
                                        'type_desisteur' => 'desisteur',
                                    ];
                                    $aqu_desit_Controller->store_aquereurs_desistement($request->merge($dataAquereur));
                                }
                            }
                            //store au profit
                            $profit_Controller = new AquereurController();
                            if ($request->profit_dp_co_reser) {
                                foreach ($request->profit_dp_co_reser as $prof) {
                                    array_push($array_aq_id,$prof['id']);
                                    $dataAquereur = [
                                        'pourcentage' => $prof['new_pourcentage'],
                                        'client_id' => $prof['cl_id'],
                                        'aq_id' => $prof['id'],
                                        'desistement_id' => $desistement->id,
                                        'type_desisteur' => 'au_profit',
                                    ];
                                    $profit_Controller->store_aquereurs_desistement($request->merge($dataAquereur));
                                }
                            }
                            //store les non desisteurs
                            $aquereurs_non_desisteurs=Aquereur::on('temp')->where('reservation_id',$request->reservation_id)->whereNotIn('id', $array_aq_id)->get();
                            if(count($aquereurs_non_desisteurs)>0){
                                $non_desist_controller = new AquereurController();
                                    foreach ($aquereurs_non_desisteurs as $aq_nfo) {
                                        $dataAquereur_t = [
                                            'pourcentage' => $aq_nfo->pourcentage,
                                            'client_id' => $aq_nfo->client->id,
                                            'aq_id' => $aq_nfo->id,
                                            'desistement_id' => $desistement->id,
                                            'type_desisteur' => 'non_desisteur',
                                        ];
                                        $non_desist_controller->store_aquereurs_desistement($request->merge($dataAquereur_t));
                                    }

                            }


                        }elseif($request->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                            //store les desisteur partiel
                            $aqu_desit_part_Controller = new AquereurController();
                            if ($request->desisteutrs_profit_dp_partiel) {
                                foreach ($request->desisteutrs_profit_dp_partiel as $aq_nfo) {
                                    $dataAquereur = [
                                        'pourcentage' => $aq_nfo['pourcentage_'],
                                        'client_id' => $aq_nfo['cl_id'],
                                        'aq_id' => $aq_nfo['id'],
                                        'desistement_id' => $desistement->id,
                                        'type_desisteur' => 'partiel',
                                    ];
                                    $aqu_desit_part_Controller->store_aquereurs_desistement($request->merge($dataAquereur));
                                }
                            }
                            //store les nouvel_aq_partiel
                             $nv_aqu_part_Controller = new AquereurController();
                             if ($request->new_clients_dp_partiel) {
                                 foreach ($request->new_clients_dp_partiel as $nv_aq_nfo) {
                                     $dataAquereur_nv = [
                                         'cin' => $nv_aq_nfo['cin'],
                                         'nom' => $nv_aq_nfo['nom'],
                                         'prenom' => $nv_aq_nfo['prenom'],
                                         'telephone' => $nv_aq_nfo['telephone_num1'],
                                         'pourcentage' => $nv_aq_nfo['pourcentage'],

                                     ];
                                     $nv_aqu_part_Controller->store_new_aquereurs_desistement($request->merge($dataAquereur_nv));
                                 }
                             }
                        }
                    }

                    //store Pénalité
                        if($request->checked_penalite==true){
                            $num_recu=null;
                            $last_num_recu = PenaliteDesistement::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                            ->get('num_recu')->first();
                            if ($last_num_recu!=null) {
                                $n_recu = $last_num_recu->num_recu + 1;
                                $num_recu = '00' . $n_recu . '';
                            } else {
                                $num_recu = '001';
                            }
                            $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                            $pen_mnt_lettre = $inWords->format($request->penalite_montant);
                            $pen = new PenaliteDesistement();
                            $pen->setConnection('temp');
                            $pen->desistement_id=$desistement->id;
                            $pen->num_recu=$num_recu;
                            if (RoleHelper::AdminSup()) {
                                //validé
                                $pen->statut = 1;
                            }else{
                                $pen->statut=0;
                            }
                            $pen->montant=$request->penalite_montant;
                            $pen->montant_par_lettre=$pen_mnt_lettre;
                            $pen->penalite_par=$request->penalite_par;
                            $pen->mode_penalite=$request->mode_penalite;
                            $pen->sr= (bool)$request->sr_pen;
                            $pen->mode_paiement=$request->mode_paiement_pen;
                            //cheque cheque-banque cheque cetifice
                            if($request->mode_paiement_pen==2||$request->mode_paiement_pen==3||$request->mode_paiement_pen==4){
                                $pen->numero_paiement=$request->numero_paiement_pen;
                                $pen->banque_id=$request->banque_id_pen;
                                $pen->echeance=$request->echeance_pen;
                            }
                            //virement versement
                            elseif($request->mode_paiement_pen==5||$request->mode_paiement_pen==6){
                                $pen->numero_paiement=$request->numero_paiement_pen;
                                $pen->banque_id=$request->banque_id_pen;
                            }
                            //les pices jointes des penalité a jouter

                            if( $pen->save()){
                                //store notification echeance
                                if($request->penalite_par=='prix'){
                                   if($pen->echeance!=null){
                                    NotificationHelper::storeNotification(
                                        '/desistements/show/'.$desistement->id, $pen->echeance,5,'ECHEANCE',Auth::guard('api')->user()->id,null,null,null,$desistement->projet_id,null,$desistement->reservation_id
                                    );
                                   }
                                }
                                //store pice_jointe_penalite

                                 //store penalite to fiche transmission
                                    $fiche= new FicheTransmission();
                                    $fiche->setConnection('temp');
                                    //num recu cree aujourdhui
                                    $recu_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', Carbon::now())
                                    ->get('num_recu')->first();
                                    if ($recu_now!=null) {
                                        $fiche->num_recu= $recu_now->num_recu;

                                    } else {
                                        //num recu cree != aujourdhui
                                        $rec_not_now= FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at','!=', Carbon::now())
                                        ->get('num_recu')->first();
                                        if($rec_not_now!=null){
                                            $pp = $rec_not_now->num_recu + 1;
                                            $fiche->num_recu = '00' . $pp . '';
                                        }
                                        else{
                                            $fiche->num_recu = '001';
                                        }

                                    }
                                    $fiche->avance_id=null;
                                    $fiche->penalite_id=$pen->id;
                                    $fiche->user_id=$userAuth->value('id');
                                    if($request->mode_paiement_pen==2||$request->mode_paiement_pen==3||$request->mode_paiement_pen==4){
                                        $fiche->date = $pen->echeance;
                                    }
                                    else{
                                        $fiche->date = Carbon::now();
                                    }
                                    if($fiche->save()){
                                        if (RoleHelper::Com()) {
                                              //notifiction to admin de valider penalite
                                                    NotificationHelper::storeNotification(
                                                        '/penalites/show/' . $pen->id, null, 6, 'DEMANDE VALIDATION Pénlite', null, RoleEnum::ADMIN->value, null, null, $desistement->projet_id, null, $desistement->reservation_id
                                                    );
                                         }
                                    }

                            }

                        }

                    //store piece jointe
                     //store archive si rejete et re store nouveau desistement



                    //Validation /Notification
                    if (RoleHelper::AdminSup()) {

                        if($request->type==TypeDesistement::Désistement_Définitif->value){
                                //update eta de reservation
                                $reservation->setConnection('temp');
                                $reservation->etat=EtatReservationEnum::desist_definitif->value ;
                                $reservation->code_desistement=$code_desist_reservation;
                                if($reservation->save()){
                                    //soft_delete_avances
                                    $avanceController = new AvanceController();
                                    $avanceController->soft_destroy_avances_by_reservationId($request->reservation_id);
                                    //soft delete aquereurs
                                    $aquController = new AquereurController();
                                    $aquController->soft_destroy_aqueureurs_by_reservationId($request->reservation_id);
                                    //set piece jointe etat=0
                                    $pjController = new PiecesJointeController();
                                    $pjController->soft_destroy_pj_by_reservationId($request->reservation_id);
                                    //set bien disponible et desistement_id
                                    Bien_Helper::libererBien($request->bien_id_ancien,null,$desistement->id);
                                    //si sum_avances >0
                                    if($request->type_remb!=null){
                                            if($remboursement->mode_rembourse=='transfert' || $remboursement->mode_rembourse=='transfert_rem_direct'||$remboursement->mode_rembourse=='transfert_rem_apres_vente' ){
                                                //store avance
                                                $avanceController = new AvanceController();
                                                $avanceRequest = new StoreAvanceRequest();

                                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                                if($remboursement->mode_rembourse=='transfert_rem_direct'||$remboursement->mode_rembourse=='transfert_rem_apres_vente'){
                                                    $montant=$request->montant_transferer;
                                                    $mnt_lettre = $inWords->format($montant);
                                                }else{
                                                    $montant=$request->sum_avances_valides;
                                                    $mnt_lettre = $inWords->format($montant);
                                                }

                                               /* if($remboursement->mode_rembourse=='transfert_rem_apres_vente'){
                                                    $mode_transfert='transfert & Remboursement aprés vente depuis le dossier :'.$reservation->code_reservation;
                                                }elseif($remboursement->mode_rembourse=='transfert_rem_direct'){
                                                     $mode_transfert='transfert & Remboursement Immédiat depuis le dossier: '.$reservation->code_reservation;
                                                }
                                                else{
                                                     $mode_transfert='transfert depuis le dossier :'.$reservation->code_reservation;
                                                }*/
                                                $dataAvance = [
                                                    //addedd
                                                    'desistement_id'=>$desistement->id,
                                                    'dossier_id_transfert'=>$request->reservation_id,
                                                    'reservation_id'=>$request->dossier_id,
                                                    /////
                                                    'sr' => false,
                                                    'type_encaissement' => 1,
                                                    'montant' => $montant,
                                                   // 'mode_transfert' => $mode_transfert,
                                                    'mode_paiement' => ModePaiement::transfert_dossier->value,
                                                    'numero_paiement' => null,
                                                    'date_reglement' => Carbon::now(),
                                                    'echeance' => null,
                                                    'banque_id' => null,
                                                    'montant_par_lettre' => $mnt_lettre,
                                                    'commentaireAvance' => null,
                                                    'num_remise' => null,
                                                    'date_encaissement' =>null,

                                                ];
                                                $avanceRequest->merge($dataAvance);
                                                $avanceController->store($avanceRequest);
                                                 //send piece jointes
                                            }
                                    }
                                     //validation desistement
                                    $desistement->reservation_id_new=$request->reservation_id;
                                    if($desistement->save()){
                                        //store Historique Désistement
                                            //test si res_id exist deja en table historique
                                            $histo_count=HistoriqueDesistement::on('temp')->where('reservation_id',$request->reservation_id)->count();
                                            if($histo_count==0){
                                                $this->store_historique_desistement($request->reservation_id,null,$request->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                            }
                                            // store histo desi
                                            $this->store_historique_desistement(null,$desistement->id,$request->bien_id_ancien,$code_desist_reservation,Carbon::now());
                                    }
                                }
                        }

                        //DP
                        elseif($request->type==TypeDesistement::Désistement_Au_Profit->value){

                                //dp_proche//dp_co
                                if($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value||$request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                    $aq_non_desisteur=AquereurDesistement::on('temp')->where('desistement_id',$desistement->id)->where('type','non_desisteur')->get();
                                    $nouvel_aqu=NouvelAquereurDesistement::on('temp')->where('desistement_id',$desistement->id)->get();
                                    $les_au_profit=AquereurDesistement::on('temp')->where('desistement_id',$desistement->id)->where('type','au_profit')->get();

                                }else{
                                    //partiel
                                    $les_partiel=AquereurDesistement::on('temp')->where('desistement_id',$desistement->id)->where('type','partiel')->get();
                                    $nouvel_aqu=NouvelAquereurDesistement::on('temp')->where('desistement_id',$desistement->id)->get();
                                }


                                $resv_ancien = Reservation::on('temp')->findOrFail($request->reservation_id);
                                //coppier ancien reservation meme code _reservation
                                $resv_new = $resv_ancien->replicate();
                                $resv_new->setConnection('temp');
                                $resv_new->ancien_id = $resv_ancien->id;
                                $resv_new->code_reservation= $resv_ancien->code_reservation;
                                $resv_new->code_desistement=$code_desist_reservation;
                                $resv_new->etat= 1;
                                if($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                                    $resv_new->nb_acquereurs = count($aq_non_desisteur)+count($nouvel_aqu);
                                }elseif($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                    $resv_new->nb_acquereurs = count($aq_non_desisteur)+count($les_au_profit);
                                }
                                elseif($request->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                                    $resv_new->nb_acquereurs = count($les_partiel)+count($nouvel_aqu);
                                }

                                $resv_new->created_at = Carbon::now();
                                $resv_new->updated_at = Carbon::now();
                                if($resv_new->save()){
                                    //set resv ancien etat
                                    $resv_ancien->setConnection('temp');
                                    if($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                                        $resv_ancien->etat=EtatReservationEnum::desist_profit_proche->value;
                                    }elseif($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                        $resv_ancien->etat=EtatReservationEnum::desist_profit_co->value;

                                    }elseif($request->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                                        $resv_ancien->etat=EtatReservationEnum::desist_partiel->value;
                                    }
                                    $resv_ancien->code_desistement=$code_desist_reservation;

                                    if($resv_ancien->save()){
                                        //replicate piece jointe
                                        $pj_ancien = PiecesJointe::on('temp')->where('reservation_id',$request->reservation_id)->get();
                                        if(count($pj_ancien)>0){
                                            foreach($pj_ancien as $pj_old){
                                                $pj_new = $pj_old->replicate();
                                                $pj_new->setConnection('temp');
                                                $pj_new->reservation_id = $resv_new->id;
                                                $pj_new->created_at = Carbon::now();
                                                $pj_new->updated_at = Carbon::now();
                                                if($pj_new->save()){
                                                    $pj_old->delete();
                                                }
                                            }
                                        }

                                        //replicate avance
                                        $av_ancien = Avance::on('temp')->where('reservation_id',$request->reservation_id)->get();
                                        if(count($av_ancien)>0){
                                            foreach($av_ancien as $av_old){
                                                $av_new = $av_old->replicate();
                                                $av_new->setConnection('temp');
                                                $av_new->reservation_id = $resv_new->id;
                                                $av_new->reservation_id_ancien = $request->reservation_id;
                                                $av_new->desistement_id = $desistement->id;
                                                $av_new->ancien_recu = $av_old->num_recu;
                                                $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                                                ->get('num_recu')->first();
                                                if ($last_num_recu!=null) {
                                                    $n_recu = $last_num_recu->num_recu + 1;
                                                    $av_new->num_recu = '00' . $n_recu . '';
                                                } else {
                                                    $av_new->num_recu = '001';
                                                }
                                                $av_new->created_at = Carbon::now();
                                                $av_new->updated_at = Carbon::now();
                                                if($av_new->save()){
                                                    $av_old->delete();
                                                }
                                            }
                                        }
                                        //soft delete ancien aquereurs
                                        $aquController = new AquereurController();
                                        $aquController->soft_destroy_aqueureurs_by_reservationId($request->reservation_id);

                                    }
                                    if($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                                        //store les non desisteur
                                        $aqu_non_desist_Controller = new AquereurController();
                                        $aquRequest = new StoreAquereurRequest();
                                        if (count($aq_non_desisteur)>0) {
                                            foreach ($aq_non_desisteur as $aq_nfo) {
                                                $dataAquereur = [
                                                    'pourcentage' => $aq_nfo->pourcentage,
                                                    'client_id' => $aq_nfo->client_id,
                                                    'reservation_id' => $resv_new->id,
                                                ];
                                                $aquRequest->merge($dataAquereur);
                                                $aqu_non_desist_Controller->store($aquRequest);
                                            }
                                        }
                                        //store les new clients
                                        $clientController = new ClientController();
                                        $clientRequest = new StoreClientRequest();
                                        $aquereurController = new AquereurController();
                                        $aquereurRequest = new StoreAquereurRequest();
                                            if(count($nouvel_aqu)>0){
                                            foreach ($nouvel_aqu as $info) {
                                                $client_exist=Client::on('temp')->where('cin',$info->cin)->orderBy('created_at', 'DESC')->get()->first();

                                                    if($client_exist!=null){
                                                        $clientData =$client_exist;
                                                    }else{
                                                        // si est un prospect
                                                        $prospect_exist = Prospect::on('temp')->where(function($query) use ($info) {
                                                        $query->where('telephone',$info->telephone)
                                                            ->orwhere('telephone_num2',$info->telephone)
                                                            ->orwhere('cin',$info->cin)
                                                            ;})
                                                            ->get()->first();
                                                        if($prospect_exist!=null){
                                                            $dataClient= [
                                                                'cin'=>$info->cin,
                                                                'nom'=>$info->nom,
                                                                'prenom'=>$info->prenom,
                                                                'telephone_num1'=>$info->telephone,
                                                                'telephone_num2'=>$prospect_exist->telephone_num2,
                                                                'notifie'=>$prospect_exist->notifie,
                                                                'civilite'=>'Mr',
                                                                'situation_familliale'=>'Célibataire',
                                                                'type_client'=>1,
                                                            ];
                                                        }else{
                                                            //new client
                                                            $dataClient= [
                                                                'cin'=>$info->cin,
                                                                'nom'=>$info->nom,
                                                                'prenom'=>$info->prenom,
                                                                'telephone_num1'=>$info->telephone,
                                                                'telephone_num2'=>null,
                                                                'notifie'=>0,
                                                                'civilite'=>'Mr',
                                                                'situation_familliale'=>'Célibataire',
                                                                'type_client'=>1,
                                                            ];
                                                        }

                                                        $clientRequest->merge($dataClient);
                                                        $clientData = $clientController->store($clientRequest);
                                                    }
                                                    $dataAquereur = [
                                                        'pourcentage' => $info->pourcentage,
                                                        'client_id' => $clientData->id,
                                                        'reservation_id' => $resv_new->id,
                                                    ];
                                                    $aquereurRequest->merge($dataAquereur);
                                                    $aquereurController->store($aquereurRequest);
                                            }
                                        }
                                    }
                                    elseif($request->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                        //store les non desisteur
                                        $aqu_non_desist_Controller = new AquereurController();
                                        $aquRequest = new StoreAquereurRequest();
                                        if (count($aq_non_desisteur)>0) {
                                            foreach ($aq_non_desisteur as $aq_nfo) {
                                                $dataAquereur = [
                                                    'pourcentage' => $aq_nfo->pourcentage,
                                                    'client_id' => $aq_nfo->client_id,
                                                    'reservation_id' => $resv_new->id,
                                                ];
                                                $aquRequest->merge($dataAquereur);
                                                $aqu_non_desist_Controller->store($aquRequest);
                                            }
                                        }
                                        //store les au profit + new pourcenage
                                        $aqu_profit_Controller = new AquereurController();
                                        $aquRequest_pr = new StoreAquereurRequest();
                                        if (count($les_au_profit)>0) {
                                            foreach ($les_au_profit as $aq_nfo) {
                                                //get old pourcentage
                                                $aquer_old_percent=Aquereur::on('temp')->onlyTrashed()->findorfail($aq_nfo->aq_id);
                                                $dataAquereur = [
                                                    'pourcentage' => $aq_nfo->pourcentage+$aquer_old_percent->pourcentage,
                                                    'client_id' => $aq_nfo->client_id,
                                                    'reservation_id' => $resv_new->id,
                                                ];
                                                $aquRequest_pr->merge($dataAquereur);
                                                $aqu_profit_Controller->store($aquRequest_pr);
                                            }
                                        }
                                    }
                                    elseif($request->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                                        //store les desisteur partiel
                                        $les_partiel_Controller = new AquereurController();
                                        $aquRequest = new StoreAquereurRequest();
                                        if (count($les_partiel)>0) {
                                            foreach ($les_partiel as $aq_nfo) {
                                                $dataAquereur = [
                                                    'pourcentage' => $aq_nfo->pourcentage,
                                                    'client_id' => $aq_nfo->client_id,
                                                    'reservation_id' => $resv_new->id,
                                                ];
                                                $aquRequest->merge($dataAquereur);
                                                $les_partiel_Controller->store($aquRequest);
                                            }
                                        }
                                        //store les new clients partiel
                                        $clientController = new ClientController();
                                        $clientRequest = new StoreClientRequest();
                                        $aquereurController = new AquereurController();
                                        $aquereurRequest = new StoreAquereurRequest();
                                            if(count($nouvel_aqu)>0){
                                            foreach ($nouvel_aqu as $info) {
                                                $client_exist=Client::on('temp')->where('cin',$info->cin)->orderBy('created_at', 'DESC')->get()->first();

                                                    if($client_exist!=null){
                                                        $clientData =$client_exist;
                                                    }else{
                                                        // si est un prospect
                                                        $prospect_exist = Prospect::on('temp')->where(function($query) use ($info) {
                                                        $query->where('telephone',$info->telephone)
                                                            ->orwhere('telephone_num2',$info->telephone)
                                                            ->orwhere('cin',$info->cin)
                                                            ;})
                                                            ->get()->first();
                                                        if($prospect_exist!=null){
                                                            $dataClient= [
                                                                'cin'=>$info->cin,
                                                                'nom'=>$info->nom,
                                                                'prenom'=>$info->prenom,
                                                                'telephone_num1'=>$info->telephone,
                                                                'telephone_num2'=>$prospect_exist->telephone_num2,
                                                                'notifie'=>$prospect_exist->notifie,
                                                                'civilite'=>'Mr',
                                                                'situation_familliale'=>'Célibataire',
                                                                'type_client'=>1,
                                                            ];
                                                        }else{
                                                            //new client
                                                            $dataClient= [
                                                                'cin'=>$info->cin,
                                                                'nom'=>$info->nom,
                                                                'prenom'=>$info->prenom,
                                                                'telephone_num1'=>$info->telephone,
                                                                'telephone_num2'=>null,
                                                                'notifie'=>0,
                                                                'civilite'=>'Mr',
                                                                'situation_familliale'=>'Célibataire',
                                                                'type_client'=>1,
                                                            ];
                                                        }

                                                        $clientRequest->merge($dataClient);
                                                        $clientData = $clientController->store($clientRequest);
                                                    }
                                                    $dataAquereur = [
                                                        'pourcentage' => $info->pourcentage,
                                                        'client_id' => $clientData->id,
                                                        'reservation_id' => $resv_new->id,
                                                    ];
                                                    $aquereurRequest->merge($dataAquereur);
                                                    $aquereurController->store($aquereurRequest);
                                            }
                                        }
                                    }
                                    //validation du desistement et add reservation_id_new
                                        $desistement->reservation_id_new=$resv_new->id;
                                        if($desistement->save()){

                                            /**store Historique */
                                              //test si res_id exist deja en table historique
                                              $histo_count_res_ancien=HistoriqueDesistement::on('temp')->where('reservation_id',$request->reservation_id)->count();
                                              if($histo_count_res_ancien==0){
                                                  $this->store_historique_desistement($request->reservation_id,null,$request->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                              }
                                              // store histo desi
                                              $this->store_historique_desistement(null,$desistement->id,$request->bien_id_ancien,$code_desist_reservation,Carbon::now());
                                              //store histo res_new
                                              $this->store_historique_desistement($resv_new->id,null,$request->bien_id_ancien,$code_desist_reservation,Carbon::now());

                                        }
                                }

                        }
                        //CHANGEMENT DE BIEN

                        elseif($request->type==TypeDesistement::Changement_De_Bien->value){
                            //set bien Pré-Réservé
                            $bien_c=new BienController();
                            $bien_c->prereserverBien($request->bien_id_new,null,null);
                            //replicate reservation
                            $resv_ancien = Reservation::on('temp')->findOrFail($request->reservation_id);
                            //coppier ancien reservation meme code _reservation
                            $resv_new = $resv_ancien->replicate();
                            $resv_new->setConnection('temp');
                            $resv_new->ancien_id = $resv_ancien->id;
                            $resv_new->bien_id = $request->bien_id_new;
                            $resv_new->code_reservation= $resv_ancien->code_reservation;
                            $resv_new->etat= 1;
                            $resv_new->created_at = Carbon::now();
                            $resv_new->updated_at = Carbon::now();
                            $resv_new->code_desistement=$code_desist_reservation;

                            if($resv_new->save()){
                                //set resv ancien etat
                                $resv_ancien->setConnection('temp');
                                $resv_ancien->etat=EtatReservationEnum::desist_change_bien->value;
                                $resv_ancien->code_desistement=$code_desist_reservation;

                                if($resv_ancien->save()){
                                    //replicate piece jointe
                                    $pj_ancien = PiecesJointe::on('temp')->where('reservation_id',$request->reservation_id)->get();
                                    if(count($pj_ancien)>0){
                                        foreach($pj_ancien as $pj_old){
                                            $pj_new = $pj_old->replicate();
                                            $pj_new->setConnection('temp');
                                            $pj_new->reservation_id = $resv_new->id;
                                            $pj_new->created_at = Carbon::now();
                                            $pj_new->updated_at = Carbon::now();
                                            if($pj_new->save()){
                                                $pj_old->delete();
                                            }
                                        }
                                    }

                                    //replicate avance
                                    $av_ancien = Avance::on('temp')->where('reservation_id',$request->reservation_id)->get();
                                    if(count($av_ancien)>0){
                                        foreach($av_ancien as $av_old){
                                            $av_new = $av_old->replicate();
                                            $av_new->setConnection('temp');
                                            $av_new->reservation_id = $resv_new->id;
                                            $av_new->reservation_id_ancien = $request->reservation_id;
                                            $av_new->desistement_id = $desistement->id;
                                            $av_new->ancien_recu = $av_old->num_recu;
                                            $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                                            ->get('num_recu')->first();
                                            if ($last_num_recu!=null) {
                                                $n_recu = $last_num_recu->num_recu + 1;
                                                $av_new->num_recu = '00' . $n_recu . '';
                                            } else {
                                                $av_new->num_recu = '001';
                                            }
                                            $av_new->created_at = Carbon::now();
                                            $av_new->updated_at = Carbon::now();
                                            if($av_new->save()){
                                                $av_old->delete();
                                            }
                                        }
                                    }
                                  //replicate piece jointe
                                  $old_aqu_s = Aquereur::on('temp')->where('reservation_id',$request->reservation_id)->get();
                                  if(count($old_aqu_s)>0){
                                      foreach($old_aqu_s as $aq_old){
                                          $aq_new = $aq_old->replicate();
                                          $aq_new->setConnection('temp');
                                          $aq_new->reservation_id = $resv_new->id;
                                          $aq_new->created_at = Carbon::now();
                                          $aq_new->updated_at = Carbon::now();
                                          if($aq_new->save()){
                                              $aq_old->delete();
                                          }
                                      }
                                  }

                                }
                                //if(montant_a_ajouter >0)
                                if($request->montant_a_ajouter>0){

                                    //store avance
                                    $avanceController = new AvanceController();
                                    $avanceRequest = new StoreAvanceRequest();

                                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                    $montant=$request->montant_a_ajouter;
                                    $mnt_lettre = $inWords->format($montant);
                                    $dataAvance = [
                                        //addedd
                                        'desistement_id'=>$desistement->id,
                                        'dossier_id_transfert'=>null,
                                        'reservation_id'=>$resv_new->id,
                                        /////
                                        'sr' => (bool)$request->sr,
                                        'type_encaissement' => 1,
                                        'montant' => $montant,
                                        'mode_paiement' => $request->mode_paiement,
                                        'numero_paiement' => $request->numero_paiement,
                                        'date_reglement' => Carbon::now(),
                                        'echeance' => $request->echeance,
                                        'banque_id' => $request->banque_id,
                                        'montant_par_lettre' => $mnt_lettre,
                                        'commentaireAvance' => null,
                                        'num_remise' => null,
                                        'date_encaissement' =>null,

                                    ];
                                    $avanceRequest->merge($dataAvance);
                                    $avanceController->store($avanceRequest);
                                    }
                                    //send piece jointes
                                }

                                //libration de l'ancien bien
                                Bien_Helper::libererBien($request->bien_id_ancien,null,$desistement->id);



                         //validation du desistement et add reservation_id_new
                             $desistement->reservation_id_new=$resv_new->id;
                            if($desistement->save()){
                                            /**store Historique */
                                              //test si res_id exist deja en table historique
                                              $histo_count_res_ancien=HistoriqueDesistement::on('temp')->where('reservation_id',$request->reservation_id)->count();
                                              if($histo_count_res_ancien==0){
                                                  $this->store_historique_desistement($request->reservation_id,null,$request->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                              }
                                              // store histo desi
                                              $this->store_historique_desistement(null,$desistement->id,$request->bien_id_ancien,$code_desist_reservation,Carbon::now());
                                              //store histo res_new
                                              $this->store_historique_desistement($resv_new->id,null,$request->bien_id_ancien,$code_desist_reservation,Carbon::now());

                            }
                        }


                    }
                    else{
                        //VALIDATION DU CHANGEMENT DE BIEN
                        //notif to admin pour valider desistement
                        NotificationHelper::storeNotification(
                            '/desistements/show/'.$desistement->id, Carbon::now(),9,'Demande Validation desistement',null,RoleEnum::ADMIN->value,null,null,$desistement->projet_id,null,$desistement->reservation_id
                        );
                    }
            }





            return response()->json(['desistement'=>$desistement],200);
        }
        else  return response()->json(['error' => 'Unauthorized'], 401);

    }

    public function store_historique_desistement($res_id,$des_id,$bien_id,$code_des,$date){
        if(RoleHelper::ACSup()){
        DatabaseHelper::Config();
        $histo = new HistoriqueDesistement();
        $histo->setConnection('temp');
        $histo->reservation_id=$res_id;
        $histo->desistement_id=$des_id;
        $histo->bien_id=$bien_id;
        $histo->code_desistement=$code_des;
        $histo->date=$date;
        $histo->save();
        }

    }

    public function get_historiques_desistement_by_reservation(Request $request, $code_desistement)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $array=array();

                $data=HistoriqueDesistement::on('temp')->where('code_desistement',$code_desistement)->orderBy('date','asc')->get();
                $data_s = $data->map(function ($dt) {
                    $desisteurs=null;
                    $au_profits=null;
                    $data_nv_aq=null;
                    $bien_new_propriete=null;
                    $sum_avances=null;
                    $penalite_montant=null;
                        if($dt->desistement_id!=null){
                            //si ligne desistement

                            $aquereur_desisteurs=AquereurDesistement::on('temp')->where('desistement_id',$dt->desistement_id)->where('type','desisteur')->get();
                            $aquereur_profit=AquereurDesistement::on('temp')->where('desistement_id',$dt->desistement_id)->where('type','au_profit')->get();

                            if(count($aquereur_desisteurs)>0){
                                    $desisteurs= $aquereur_desisteurs->map(function ($aq_dt) {
                                        return [
                                        'client_nom' => $aq_dt->client->nom,
                                        'client_prenom' => $aq_dt->client->prenom,
                                        'client_percent' => $aq_dt->aquereur->pourcentage,
                                        'type' => $aq_dt->type,
                                    ];});
                            }

                            if(count($aquereur_profit)>0){

                                $au_profits= $aquereur_profit->map(function ($aq_dt) {
                                    return [
                                        'client_nom' => $aq_dt->client->nom,
                                        'client_prenom' => $aq_dt->client->prenom,
                                        'client_percent' => $aq_dt->aquereur->pourcentage,
                                        'type' => $aq_dt->type,
                                    ];});
                            }

                            $nv_aquereur_desistement=NouvelAquereurDesistement::on('temp')->where('desistement_id',$dt->desistement_id)->get();
                            if(count($nv_aquereur_desistement)>0){
                                $data_nv_aq = $nv_aquereur_desistement->map(function ($aq_nv) {
                                    return [
                                        'nv_client_cin' => $aq_nv->cin,
                                        'nv_client_nom' => $aq_nv->nom,
                                        'nv_client_prenom' => $aq_nv->prenom,
                                        'nv_client_telephone' => $aq_nv->telephone,
                                        'nv_client_percent' => $aq_nv->pourcentage,
                                    ];});
                            }else{
                                $data_nv_aq=null;
                            }
                            if($dt->desistement->bien_id_new!=null){
                                $bien=Bien::on('temp')->findorfail($dt->desistement->bien_id_new);
                                $bien_new_propriete=$bien->propriete_dite_bien;
                            }

                            //penalite
                            $penalite=PenaliteDesistement::on('temp')->where('desistement_id',$dt->desistement_id)->get()->first();
                            if($penalite!=null){
                                $penalite_montant=$penalite->montant;

                            }


                        }
                        if($dt->reservation_id!=null){
                            $sum_avances = Avance::on('temp')->where('reservation_id',$dt->reservation_id)->where('statut',1)->withTrashed()->sum('montant');
                        }

                      //array
                    return [
                        'histo' => $dt,
                        'desisteurs'=>$desisteurs,
                        'au_profits'=>$au_profits,
                        'new_aquereur_desistement'=>$data_nv_aq,
                        'bien_new_propriete'=>$bien_new_propriete,
                        'sum_avances'=>$sum_avances,
                        'penalite_montant'=>$penalite_montant,
                    ];});

                $data_mm = PaginationHelper::paginate_array($data_s->toArray(),$perPage,$page,$request->url());

            return response()->json(['historiques' => $data_mm], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }
    public function index(Request $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);
            $sources = Desistement::on('temp')->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['sources' => $sources],200);
        }
       else  return response()->json(['error'=>'Unauthorized'], 401);
    }
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            return response()->json([], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVueRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();

            return response()->json([],200);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $vue=Vue::on('temp')->findOrFail($id);
            if($vue->delete())
            {
                return response()->json(['message'=>'Vue supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La vue n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
