<?php
namespace App\Http\Controllers;
use App\Enum\EtatReservationEnum;
use App\Enum\ModePaiement;
use App\Enum\RoleEnum;
use App\Enum\StatutReservationEnum;
use App\Enum\TypeDesistement;
use App\Enum\TypeDesistementProfit;
use App\Events\NotificationEvent;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreAquereurRequest;
use App\Http\Requests\StoreAvanceRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\StoreDesistementRequest;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Models\Aquereur;
use App\Models\AquereurDesistement;
use App\Models\Avance;
use App\Models\Bien;
use App\Models\Client;
use App\Models\Desistement;
use App\Models\Encaissement;
use App\Models\FicheTransmission;
use App\Models\HistoriqueDesistement;
use App\Models\NouvelAquereurDesistement;
use App\Models\PenaliteDesistement;
use App\Models\PiecesJointe;
use App\Models\Prospect;
use App\Models\Remboursement;
use App\Models\Reservation;
use App\Models\Societe;
use App\Models\StatutAvancePenalite;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use \NumberFormatter;

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
        Config::set('broadcasting.default', 'pusher_3');
        if (RoleHelper::AC()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

            //get code desistement partage =>pour stocker dans reservation
            $code_desist_reservation = 0;
            $reservation = Reservation::on('temp')->findOrFail($request->reservation_id);
            if ($reservation->code_desistement != null) {
                $code_desist_reservation = $reservation->code_desistement;
            } else {
                $last_code = Reservation::on('temp')->orderByRaw("CAST(code_desistement as UNSIGNED) DESC")
                    ->get('code_desistement')->first();
                if ($last_code->code_desistement != null) {
                    $code_desist_reservation = $last_code->code_desistement + 1;
                } else {
                    $code_desist_reservation = 1;
                }

            }

            $desistement = new Desistement();
            $desistement->setConnection('temp');
            $desistement->reservation_id = $request->reservation_id;
            $desistement->type = $request->type;

            if ($request->type == TypeDesistement::Désistement_Définitif->value) {
                $desistement->motif = $request->motif;
            } elseif ($request->type == TypeDesistement::Désistement_Au_Profit->value) {
                if ($request->type_dp != TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {
                    $desistement->lien_parente = $request->lien_parente;
                }
                $desistement->type_dp = $request->type_dp;

            } elseif ($request->type == TypeDesistement::Changement_De_Bien->value) {
                $desistement->bien_id_new = $request->bien_id_new;
                $desistement->montant_a_ajouter = $request->montant_a_ajouter;
                if ($request->montant_a_ajouter > 0) {
                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                    $mont_lettre = $inWords->format($request->montant_a_ajouter);
                    $desistement->montant_a_ajouter_par_lettre = $request->mont_lettre;
                    $desistement->sr = (bool) $request->sr;
                    $desistement->mode_paiement = $request->input("mode_paiement");
                    //cheque cheque-banque cheque cetifice
                    if ($request->mode_paiement == 2 || $request->mode_paiement == 3 || $request->mode_paiement == 4) {
                        $desistement->numero_paiement = $request->numero_paiement;
                        $desistement->banque_id = $request->banque_id;
                        $desistement->echeance = $request->echeance;
                    }
                    //virement versement
                    elseif ($request->mode_paiement == 5 || $request->mode_paiement == 6) {
                        $desistement->numero_paiement = $request->numero_paiement;
                        $desistement->banque_id = $request->banque_id;
                    }
                }
            }

            $desistement->commentaire = $request->commentaire;
            $desistement->bien_id_ancien = $request->bien_id_ancien;
            $desistement->projet_id = $request->projet_id;

            $desistement->user_id = intval($userAuth->value('id'));

            $last_num_recu = Desistement::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                ->get('num_recu')->first();
            if ($last_num_recu != null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $desistement->num_recu = '00' . $n_recu . '';
            } else {
                $desistement->num_recu = '001';
            }
            if (RoleHelper::AdminSup()) {
                //validé
                $desistement->statut = 1;
                $desistement->date_validation = Carbon::now();
            }

            if ($desistement->save()) {

                //DD
                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);

                if ($request->type == TypeDesistement::Désistement_Définitif->value) {
                    //store Remboursement
                    //si sum_avances >0
                    if ($request->type_remb != null) {
                        //store one  Remboursement si transfert vers un autre dossier
                        if ($request->type_remb == 'transfert') {
                            $remboursement = new Remboursement();
                            $remboursement->setConnection('temp');
                            $remboursement->desistement_id = $desistement->id;
                            $remboursement->reservation_id = $request->reservation_id;
                            $remboursement->s_avances = $request->sum_avances_valides;
                            $remboursement->statut = 1;
                            $remboursement->etat = 1;
                            $remboursement->mode_rembourse = $request->type_remb;
                            $remboursement->dossier_id_transfert = $request->dossier_id;
                            $remboursement->montant_transfert = $request->sum_avances_valides;
                            $remboursement->save();
                        } else {
                            // multiple remnboursement
                            $data_inputlist_remb = $request->input('inputlist_remb', '[]');
                            $dataArray_inputlist_remb = json_decode($data_inputlist_remb, true); // Ensure it's an array

                            if ($dataArray_inputlist_remb) {

                                foreach ($dataArray_inputlist_remb as $index => $cl_remb) {
                                    $remboursement=new Remboursement();
                                    $remboursement->setConnection('temp');
                                    $remboursement->desistement_id=$desistement->id;
                                    $remboursement->reservation_id=$request->reservation_id;
                                    $remboursement->s_avances=$request->sum_avances_valides;
                                    $remboursement->statut=0;
                                    $remboursement->aquereur_id=$cl_remb['aq_id'];
                                    $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                                    $societe = Societe::findOrfail($user_societes->societe_id);
                                    if ($request->hasFile('fichier_autorisation_' . $index)) {
                                        $remboursement->fichier_autorisation= $request->file('fichier_autorisation_' . $index)->getClientOriginalName();
                                        File::makeDirectory(public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements'.'/'.'fichier_autorisations'), 0755, true, true);
                                        $request->file('fichier_autorisation_' . $index)->move(public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements'.'/'.'fichier_autorisations'), $request->file('fichier_autorisation_' . $index)->getClientOriginalName());

                                    }
                                    if ($request->hasFile('cheque_recu_' . $index)) {

                                        $remboursement->cheque = $request->file('cheque_recu_' . $index)->getClientOriginalName();
                                        File::makeDirectory(public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements'.'/'.'cheques'), 0755, true, true);
                                        $request->file('cheque_recu_' . $index)->move(public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements'.'/'.'cheques'),  $request->file('cheque_recu_' . $index)->getClientOriginalName());

                                    }
                                    //montant rembourse lettre

                                    if($request->penalite_montant!=null){
                                        $sum=$request->reste_a_rembourse-(double)$request->penalite_montant;
                                        $mont_a_rembourser=$sum*((double)$cl_remb['pourcentage'])/100;
                                        }else{
                                            $mont_a_rembourser=($request->reste_a_rembourse*((double)($cl_remb['pourcentage'])/100));
                                        }
                                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                        $mont_remb_lettre = $inWords->format($mont_a_rembourser);

                                    if($request->type_remb=='transfert_remb'){
                                        $remboursement->dossier_id_transfert=$request->dossier_id;
                                        $remboursement->date_rembourse=$cl_remb['date_rembourse'];
                                        $remboursement->mode_rembourse_client=$cl_remb['mode_rembourse'];
                                        $remboursement->pour_le_compte=$cl_remb['pour_le_compte'];
                                        $remboursement->num_paiement=$cl_remb['num_paiement'];


                                        /*if ($request->hasFile($cl_remb['fichier_autorisation']) ) {
                                            $remboursement->fichier_autorisation =$request->file('fichier_autorisation')->getClientOriginalName();
                                            $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/fichiers_autorisation');
                                            File::makeDirectory($directory, 0755, true, true);
                                            $request->file('fichier_autorisation')->move($directory,$request->file('fichier_autorisation')->getClientOriginalName());
                                        }
                                        if ($request->hasFile($cl_remb['cheque_recu'])) {
                                            $remboursement->cheque =$request->file('cheque_recu')->getClientOriginalName();
                                            $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remboursements/cheques_reçus');
                                            File::makeDirectory($directory, 0755, true, true);
                                            $request->file('cheque_recu')->move($directory,$request->file('cheque_recu')->getClientOriginalName());
                                        }*/
                                        $remboursement->montant_transfert=$request->montant_transferer;
                                        $remboursement->montant_a_rembourser=$mont_a_rembourser;
                                        $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;
                                        if($request->type_remb_transfere=='immediat'){
                                            $remboursement->mode_rembourse='transfert_rem_direct';
                                            $remboursement->statut=1;
                                            $remboursement->etat=1;
                                            $remboursement->user_id_valider=$userAuth->value('id');

                                        }else{
                                            $remboursement->mode_rembourse='transfert_rem_apres_vente';
                                            $remboursement->statut=0;
                                            $remboursement->etat=0;
                                        }

                                    }elseif($request->type_remb=='apres_vente'){
                                        $remboursement->mode_rembourse=$request->type_remb;
                                        $remboursement->statut=0;
                                        $remboursement->etat=0;
                                        $remboursement->montant_a_rembourser=$mont_a_rembourser;
                                        $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;

                                    }
                                    elseif($request->type_remb=='direct'){
                                        $remboursement->mode_rembourse=$request->type_remb;
                                        $remboursement->date_rembourse=$cl_remb['date_rembourse'];
                                        $remboursement->mode_rembourse_client=$cl_remb['mode_rembourse'];
                                        $remboursement->pour_le_compte=$cl_remb['pour_le_compte'];
                                        $remboursement->num_paiement=$cl_remb['num_paiement'];
                                        $remboursement->statut=1;
                                        $remboursement->user_id_valider=$userAuth->value('id');
                                        $remboursement->etat =1;
                                        $remboursement->montant_a_rembourser=$mont_a_rembourser;
                                        $remboursement->montant_a_rembourser_par_lettre=$mont_remb_lettre;
                                    }
                                    $remboursement->save();
                                }
                            }
                        }

                        $bien = Bien::on('temp')->findOrFail($request->bien_id_ancien);
                        $bien->setConnection('temp');
                        $bien->desistement_id = $desistement->id;
                        $bien->save();
                    }

                } elseif ($request->type == TypeDesistement::Désistement_Au_Profit->value) {

                    if ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value) {
                        //push les aqu_id=>pour storer les non desiteurs

                        $array_aq_id = array();
                        //store les aquerures desisteur
                        $aqu_desit_Controller = new AquereurController();
                        $data_desisteur_dp_proche_co = $request->input('desisteur_dp_proche_co', '[]');

                        $dataArray_desisteur_dp_proche_co = json_decode($data_desisteur_dp_proche_co, true); // Ensure it's an array

                        if ($dataArray_desisteur_dp_proche_co) {
                            foreach ($dataArray_desisteur_dp_proche_co as $aq_nfo) {
                                array_push($array_aq_id, $aq_nfo['id']);
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
                        $aquereurs_non_desisteurs = Aquereur::on('temp')->where('reservation_id', $request->reservation_id)->whereNotIn('id', $array_aq_id)->get();
                        if (count($aquereurs_non_desisteurs) > 0) {
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
                        $data_new_clients_dp_proche = $request->input('new_clients_dp_proche', '[]');

                        $dataArray_new_clients_dp_proche = json_decode($data_new_clients_dp_proche, true); // Ensure it's an array

                        if ($dataArray_new_clients_dp_proche) {
                            foreach ($dataArray_new_clients_dp_proche as $nv_aq_nfo) {
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

                    } elseif ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {

                        //push les aqu_id=>pour storer les non desiteurs
                        $array_aq_id = array();
                        //store les aquerures desisteur
                        $aqu_desit_Controller = new AquereurController();
                        $data_desisteur_dp_proche_co = $request->input('desisteur_dp_proche_co', '[]');
                        $dataArray_desisteur_dp_proche_co = json_decode($data_desisteur_dp_proche_co, true); // Ensure it's an array

                        if ($dataArray_desisteur_dp_proche_co) {
                            foreach ($dataArray_desisteur_dp_proche_co as $aq_nfo) {
                                array_push($array_aq_id, $aq_nfo['id']);
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
                        $data_profit_dp_co_reser = $request->input('profit_dp_co_reser', '[]');

                        $dataArray_profit_dp_co_reser = json_decode($data_profit_dp_co_reser, true); // Ensure it's an array

                        if ($dataArray_profit_dp_co_reser) {
                            foreach ($dataArray_profit_dp_co_reser as $prof) {
                                array_push($array_aq_id, $prof['id']);
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
                        $aquereurs_non_desisteurs = Aquereur::on('temp')->where('reservation_id', $request->reservation_id)->whereNotIn('id', $array_aq_id)->get();
                        if (count($aquereurs_non_desisteurs) > 0) {
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

                    } elseif ($request->type_dp == TypeDesistementProfit::Désistement_Partiel->value) {
                        //store les desisteur partiel
                        $aqu_desit_part_Controller = new AquereurController();
                        $data_desisteutrs_profit_dp_partiel = $request->input('desisteutrs_profit_dp_partiel', '[]');
                        $dataArray_desisteutrs_profit_dp_partiel = json_decode($data_desisteutrs_profit_dp_partiel, true); // Ensure it's an array

                        if ($dataArray_desisteutrs_profit_dp_partiel) {
                            foreach ($dataArray_desisteutrs_profit_dp_partiel as $aq_nfo) {
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
                        $data_new_clients_dp_partiel = $request->input('new_clients_dp_partiel', '[]');
                        $dataArray_new_clients_dp_partiel = json_decode($data_new_clients_dp_partiel, true); // Ensure it's an array

                        if ($dataArray_new_clients_dp_partiel) {
                            foreach ($dataArray_new_clients_dp_partiel as $nv_aq_nfo) {
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

                if ($request->input('checked_penalite') == 'true') {

                    $num_recu = null;
                    $last_num_recu = PenaliteDesistement::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                        ->get('num_recu')->first();
                    if ($last_num_recu != null) {
                        $n_recu = $last_num_recu->num_recu + 1;
                        $num_recu = '00' . $n_recu . '';
                    } else {
                        $num_recu = '001';
                    }
                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                    $pen_mnt_lettre = $inWords->format($request->penalite_montant);
                    $pen = new PenaliteDesistement();
                    $pen->setConnection('temp');
                    $pen->desistement_id = $desistement->id;
                    $pen->num_recu = $num_recu;
                    $pen->statut = 0;
                    /*if (RoleHelper::AdminSup()) {
                    //validé
                    $pen->statut = 1;
                    }else{
                    $pen->statut=0;
                    }*/
                    $pen->montant = $request->penalite_montant;
                    $pen->montant_par_lettre = $pen_mnt_lettre;
                    $pen->penalite_par = $request->penalite_par;
                    $pen->mode_penalite = $request->mode_penalite;
                    //$pen->sr= (bool)$request->sr_pen;
                    if ($request->sr_pen == 'false') {
                        $pen->sr = 0;
                    } else {
                        $pen->sr = 1;
                    }
                    $pen->mode_paiement = $request->input("mode_paiement_pen");
                    //cheque cheque-banque cheque cetifice
                    if ($request->mode_paiement_pen == 2 || $request->mode_paiement_pen == 3 || $request->mode_paiement_pen == 4) {
                        $pen->numero_paiement = $request->numero_paiement_pen;
                        $pen->banque_id = $request->banque_id_pen;
                        $pen->echeance = $request->echeance_pen;
                    }
                    //virement versement
                    elseif ($request->mode_paiement_pen == 5 || $request->mode_paiement_pen == 6) {
                        $pen->numero_paiement = $request->numero_paiement_pen;
                        $pen->banque_id = $request->banque_id_pen;
                    }
                    //les pices jointes des penalité a jouter

                    if ($pen->save()) {
                        //store notification echeance
                        if ($request->penalite_par == 'prix') {
                            if ($pen->echeance != null) {
                                NotificationHelper::storeNotification(
                                    '/desistements/penalite/show/' . $pen->id, $pen->echeance, 5, 'ECHEANCE Pénalité', Auth::guard('api')->user()->id, null, null, null, $desistement->projet_id, null, $desistement->reservation_id
                                );
                                broadcast(new NotificationEvent($pen->id));
                            }
                        }

                        //store pice_jointe_penalite
                        if ($request->file('files_penalite')) {
                            foreach ($request->file('files_penalite') as $file) {
                                $piecesJointeController = new PiecesJointeController();
                                $pieceJointeRequest = new StorePiecesJointeRequest();

                                // Récupérer le nom du fichier
                                $fileName = $file->getClientOriginalName();
                                $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/penalites');
                                File::makeDirectory($directory, 0755, true, true);
                                $file->move($directory, $fileName);
                                $fileType = $file->getClientOriginalExtension();
                                $datapieceJointe = [
                                    'fichier' => $fileName,
                                    'type' => $fileType,
                                    'penalite_id' => $pen->id,

                                ];

                                $pieceJointeRequest->merge($datapieceJointe);
                                $piecesJointeController->store($pieceJointeRequest);
                            }
                        }
                        //store penalite to fiche transmission
                        $fiche = new FicheTransmission();
                        $fiche->setConnection('temp');
                        //num recu cree aujourdhui
                        $recu_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', Carbon::now())
                            ->get('num_recu')->first();
                        if ($recu_now != null) {
                            $fiche->num_recu = $recu_now->num_recu;

                        } else {
                            //num recu cree != aujourdhui
                            $rec_not_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', '!=', Carbon::now())
                                ->get('num_recu')->first();
                            if ($rec_not_now != null) {
                                $pp = $rec_not_now->num_recu + 1;
                                $fiche->num_recu = '00' . $pp . '';
                            } else {
                                $fiche->num_recu = '001';
                            }

                        }
                        $fiche->avance_id = null;
                        $fiche->penalite_id = $pen->id;
                        $fiche->user_id = $userAuth->value('id');
                        if ($request->mode_paiement_pen == 2 || $request->mode_paiement_pen == 3 || $request->mode_paiement_pen == 4) {
                            $fiche->date = $pen->echeance;
                        } else {
                            $fiche->date = Carbon::now();
                        }
                        if ($fiche->save()) {
                            //if (RoleHelper::Com()) {
                            //notifiction to admin de valider penalite
                            NotificationHelper::storeNotification(
                                'desistements/penalites/show/' . $pen->id, null, 6, 'DEMANDE VALIDATION Pénalité', null, RoleEnum::ADMIN->value, null, null, $desistement->projet_id, null, $desistement->reservation_id
                            );
                            broadcast(new NotificationEvent($pen->id));
                            // }
                        }

                    }

                }

                //store piece jointe
                if ($request->file('files_desistement')) {
                    foreach ($request->file('files_desistement') as $file) {
                        $piecesJointeController = new PiecesJointeController();
                        $pieceJointeRequest = new StorePiecesJointeRequest();

                        // Récupérer le nom du fichier
                        $fileName = $file->getClientOriginalName();
                        $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/desistement');
                        File::makeDirectory($directory, 0755, true, true);
                        $file->move($directory, $fileName);
                        $fileType = $file->getClientOriginalExtension();
                        $datapieceJointe = [
                            'fichier' => $fileName,
                            'type' => $fileType,
                            'desistement_id' => $desistement->id,

                        ];

                        $pieceJointeRequest->merge($datapieceJointe);
                        $piecesJointeController->store($pieceJointeRequest);
                    }
                }

                //Validation /Notification
                if (RoleHelper::AdminSup()) {

                    if ($request->type == TypeDesistement::Désistement_Définitif->value) {

                        //update eta de reservation
                        $reservation->setConnection('temp');
                        $reservation->etat = EtatReservationEnum::desist_definitif->value;
                        $reservation->code_desistement = $code_desist_reservation;
                        if ($reservation->save()) {

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

                            Bien_Helper::libererBien($request->bien_id_ancien, null, $desistement->id);

                            //si sum_avances >0
                            if ($request->type_remb != null) {
                                if ($remboursement->mode_rembourse == 'transfert' || $remboursement->mode_rembourse == 'transfert_rem_direct' || $remboursement->mode_rembourse == 'transfert_rem_apres_vente') {
                                    //store avance
                                    $avanceController = new AvanceController();
                                    $avanceRequest = new StoreAvanceRequest();

                                    $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                    if ($remboursement->mode_rembourse == 'transfert_rem_direct' || $remboursement->mode_rembourse == 'transfert_rem_apres_vente') {
                                        $montant = $request->montant_transferer;
                                        $mnt_lettre = $inWords->format($montant);
                                    } else {
                                        $montant = $request->sum_avances_valides;
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
                                        'desistement_id' => $desistement->id,
                                        'dossier_id_transfert' => $request->reservation_id,
                                        'reservation_id' => $request->dossier_id,
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
                                        'date_encaissement' => null,
                                        //send piece jointes

                                        'files_avance' => $request->file('files_avance'),

                                    ];
                                    $avanceRequest->merge($dataAvance);
                                    $avanceController->store($avanceRequest);

                                }
                            }
                            //validation desistement
                            $desistement->reservation_id_new = $request->reservation_id;
                            if ($desistement->save()) {
                                //store Historique Désistement
                                //test si res_id exist deja en table historique
                                $histo_count = HistoriqueDesistement::on('temp')->where('reservation_id', $request->reservation_id)->count();
                                if ($histo_count == 0) {
                                    $this->store_historique_desistement($request->reservation_id, null, $request->bien_id_ancien, $code_desist_reservation, $reservation->created_at);
                                }
                                // store histo desi
                                $this->store_historique_desistement(null, $desistement->id, $request->bien_id_ancien, $code_desist_reservation, Carbon::now());
                            }
                        }
                    }

                    //DP
                    elseif ($request->type == TypeDesistement::Désistement_Au_Profit->value) {

                        //dp_proche//dp_co
                        if ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value || $request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {
                            $aq_non_desisteur = AquereurDesistement::on('temp')->where('desistement_id', $desistement->id)->where('type', 'non_desisteur')->get();
                            $nouvel_aqu = NouvelAquereurDesistement::on('temp')->where('desistement_id', $desistement->id)->get();
                            $les_au_profit = AquereurDesistement::on('temp')->where('desistement_id', $desistement->id)->where('type', 'au_profit')->get();

                        } else {
                            //partiel
                            $les_partiel = AquereurDesistement::on('temp')->where('desistement_id', $desistement->id)->where('type', 'partiel')->get();
                            $nouvel_aqu = NouvelAquereurDesistement::on('temp')->where('desistement_id', $desistement->id)->get();
                        }

                        $resv_ancien = Reservation::on('temp')->findOrFail($request->reservation_id);
                        //coppier ancien reservation meme code _reservation
                        $resv_new = $resv_ancien->replicate();
                        $resv_new->setConnection('temp');
                        $resv_new->ancien_id = $resv_ancien->id;
                        $resv_new->code_reservation = $resv_ancien->code_reservation;
                        $resv_new->code_desistement = $code_desist_reservation;
                        $resv_new->etat = 1;
                        if ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value) {
                            $resv_new->nb_acquereurs = count($aq_non_desisteur) + count($nouvel_aqu);
                        } elseif ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {
                            $resv_new->nb_acquereurs = count($aq_non_desisteur) + count($les_au_profit);
                        } elseif ($request->type_dp == TypeDesistementProfit::Désistement_Partiel->value) {
                            $resv_new->nb_acquereurs = count($les_partiel) + count($nouvel_aqu);
                        }

                        $resv_new->created_at = Carbon::now();
                        $resv_new->updated_at = Carbon::now();
                        if ($resv_new->save()) {
                            //set resv ancien etat
                            $resv_ancien->setConnection('temp');
                            if ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value) {
                                $resv_ancien->etat = EtatReservationEnum::desist_profit_proche->value;
                            } elseif ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {
                                $resv_ancien->etat = EtatReservationEnum::desist_profit_co->value;

                            } elseif ($request->type_dp == TypeDesistementProfit::Désistement_Partiel->value) {
                                $resv_ancien->etat = EtatReservationEnum::desist_partiel->value;
                            }
                            $resv_ancien->code_desistement = $code_desist_reservation;

                            if ($resv_ancien->save()) {
                                //replicate piece jointe
                                $pj_ancien = PiecesJointe::on('temp')->where('reservation_id', $request->reservation_id)->get();
                                if (count($pj_ancien) > 0) {
                                    foreach ($pj_ancien as $pj_old) {
                                        $pj_new = $pj_old->replicate();
                                        $pj_new->setConnection('temp');
                                        $pj_new->reservation_id = $resv_new->id;
                                        $pj_new->created_at = Carbon::now();
                                        $pj_new->updated_at = Carbon::now();
                                        if ($pj_new->save()) {
                                            $pj_old->delete();
                                        }
                                    }
                                }

                                //replicate avance
                                $av_ancien = Avance::on('temp')->where('reservation_id', $request->reservation_id)->get();
                                if (count($av_ancien) > 0) {
                                    foreach ($av_ancien as $av_old) {
                                        $av_new = $av_old->replicate();
                                        $av_new->setConnection('temp');
                                        $av_new->reservation_id = $resv_new->id;
                                        $av_new->reservation_id_ancien = $request->reservation_id;
                                        $av_new->desistement_id = $desistement->id;
                                        $av_new->ancien_recu = $av_old->num_recu;
                                        $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                                            ->get('num_recu')->first();
                                        if ($last_num_recu != null) {
                                            $n_recu = $last_num_recu->num_recu + 1;
                                            $av_new->num_recu = '00' . $n_recu . '';
                                        } else {
                                            $av_new->num_recu = '001';
                                        }
                                        $av_new->created_at = Carbon::now();
                                        $av_new->updated_at = Carbon::now();
                                        if ($av_new->save()) {
                                            $av_old->delete();
                                            //replicate statut
                                            $av_last_statut = StatutAvancePenalite::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($av_last_statut != null) {
                                                $st_av_new = $av_last_statut->replicate();
                                                $st_av_new->avance_id = $av_new->id;
                                                $st_av_new->setConnection('temp');
                                                if ($st_av_new->save()) {
                                                    $av_last_statut->delete();
                                                }
                                            }
                                            //replicate pice jointe

                                            $av_old_pj = PiecesJointe::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($av_old_pj != null) {
                                                $pj_new = $av_old_pj->replicate();
                                                $pj_new->avance_id = $av_new->id;
                                                $pj_new->setConnection('temp');
                                                if ($pj_new->save()) {
                                                    $av_old_pj->delete();
                                                }
                                            }

                                            //replicate fiche transmission

                                            $old_f = FicheTransmission::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($old_f != null) {
                                                $f_new = $old_f->replicate();
                                                $f_new->avance_id = $av_new->id;
                                                $f_new->setConnection('temp');
                                                if ($f_new->save()) {
                                                    $old_f->delete();
                                                }
                                            }
                                            //replicate encaissement

                                            $old_en = Encaissement::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($old_en != null) {
                                                $new_encais = $old_en->replicate();
                                                $new_encais->reservation_id = $resv_new->id;
                                                $new_encais->avance_id = $av_new->id;
                                                $new_encais->setConnection('temp');
                                                if ($new_encais->save()) {
                                                    $old_en->delete();
                                                }
                                            }

                                        }
                                    }
                                }
                                //soft delete ancien aquereurs
                                $aquController = new AquereurController();
                                $aquController->soft_destroy_aqueureurs_by_reservationId($request->reservation_id);

                            }
                            if ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value) {
                                //store les non desisteur
                                $aqu_non_desist_Controller = new AquereurController();
                                $aquRequest = new StoreAquereurRequest();
                                if (count($aq_non_desisteur) > 0) {
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
                                if (count($nouvel_aqu) > 0) {
                                    foreach ($nouvel_aqu as $info) {
                                        $client_exist = Client::on('temp')->where('cin', $info->cin)->orderBy('created_at', 'DESC')->get()->first();

                                        if ($client_exist != null) {
                                            $clientData = $client_exist;
                                        } else {
                                            // si est un prospect
                                            $prospect_exist = Prospect::on('temp')->where(function ($query) use ($info) {
                                                $query->where('telephone', $info->telephone)
                                                    ->orwhere('telephone_num2', $info->telephone)
                                                    ->orwhere('cin', $info->cin)
                                                ;
                                            })
                                                ->get()->first();
                                            if ($prospect_exist != null) {
                                                $dataClient = [
                                                    'cin' => $info->cin,
                                                    'nom' => $info->nom,
                                                    'prenom' => $info->prenom,
                                                    'telephone_num1' => $info->telephone,
                                                    'telephone_num2' => $prospect_exist->telephone_num2,
                                                    'notifie' => $prospect_exist->notifie,
                                                    'civilite' => 'Mr',
                                                    'situation_familliale' => 'Célibataire',
                                                    'type_client' => 1,
                                                ];
                                            } else {
                                                //new client
                                                $dataClient = [
                                                    'cin' => $info->cin,
                                                    'nom' => $info->nom,
                                                    'prenom' => $info->prenom,
                                                    'telephone_num1' => $info->telephone,
                                                    'telephone_num2' => null,
                                                    'notifie' => 0,
                                                    'civilite' => 'Mr',
                                                    'situation_familliale' => 'Célibataire',
                                                    'type_client' => 1,
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
                            } elseif ($request->type_dp == TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value) {
                                //store les non desisteur
                                $aqu_non_desist_Controller = new AquereurController();
                                $aquRequest = new StoreAquereurRequest();
                                if (count($aq_non_desisteur) > 0) {
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
                                if (count($les_au_profit) > 0) {
                                    foreach ($les_au_profit as $aq_nfo) {
                                        //get old pourcentage
                                        $aquer_old_percent = Aquereur::on('temp')->onlyTrashed()->findorfail($aq_nfo->aq_id);
                                        $dataAquereur = [
                                            'pourcentage' => $aq_nfo->pourcentage + $aquer_old_percent->pourcentage,
                                            'client_id' => $aq_nfo->client_id,
                                            'reservation_id' => $resv_new->id,
                                        ];
                                        $aquRequest_pr->merge($dataAquereur);
                                        $aqu_profit_Controller->store($aquRequest_pr);
                                    }
                                }
                            } elseif ($request->type_dp == TypeDesistementProfit::Désistement_Partiel->value) {
                                //store les desisteur partiel
                                $les_partiel_Controller = new AquereurController();
                                $aquRequest = new StoreAquereurRequest();
                                if (count($les_partiel) > 0) {
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
                                if (count($nouvel_aqu) > 0) {
                                    foreach ($nouvel_aqu as $info) {
                                        $client_exist = Client::on('temp')->where('cin', $info->cin)->orderBy('created_at', 'DESC')->get()->first();

                                        if ($client_exist != null) {
                                            $clientData = $client_exist;
                                        } else {
                                            // si est un prospect
                                            $prospect_exist = Prospect::on('temp')->where(function ($query) use ($info) {
                                                $query->where('telephone', $info->telephone)
                                                    ->orwhere('telephone_num2', $info->telephone)
                                                    ->orwhere('cin', $info->cin)
                                                ;
                                            })
                                                ->get()->first();
                                            if ($prospect_exist != null) {
                                                $dataClient = [
                                                    'cin' => $info->cin,
                                                    'nom' => $info->nom,
                                                    'prenom' => $info->prenom,
                                                    'telephone_num1' => $info->telephone,
                                                    'telephone_num2' => $prospect_exist->telephone_num2,
                                                    'notifie' => $prospect_exist->notifie,
                                                    'civilite' => 'Mr',
                                                    'situation_familliale' => 'Célibataire',
                                                    'type_client' => 1,
                                                ];
                                            } else {
                                                //new client
                                                $dataClient = [
                                                    'cin' => $info->cin,
                                                    'nom' => $info->nom,
                                                    'prenom' => $info->prenom,
                                                    'telephone_num1' => $info->telephone,
                                                    'telephone_num2' => null,
                                                    'notifie' => 0,
                                                    'civilite' => 'Mr',
                                                    'situation_familliale' => 'Célibataire',
                                                    'type_client' => 1,
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
                            $desistement->reservation_id_new = $resv_new->id;
                            if ($desistement->save()) {

                                /**store Historique */
                                //test si res_id exist deja en table historique
                                $histo_count_res_ancien = HistoriqueDesistement::on('temp')->where('reservation_id', $request->reservation_id)->count();
                                if ($histo_count_res_ancien == 0) {
                                    $this->store_historique_desistement($request->reservation_id, null, $request->bien_id_ancien, $code_desist_reservation, $reservation->created_at);
                                }
                                // store histo desi
                                $this->store_historique_desistement(null, $desistement->id, $request->bien_id_ancien, $code_desist_reservation, Carbon::now());
                                //store histo res_new
                                $this->store_historique_desistement($resv_new->id, null, $request->bien_id_ancien, $code_desist_reservation, Carbon::now());

                            }
                        }

                    }
                    //CHANGEMENT DE BIEN

                    elseif ($request->type == TypeDesistement::Changement_De_Bien->value) {
                        //set bien Pré-Réservé
                        $bien_c = new BienController();
                        $bien_c->prereserverBien($request->bien_id_new, null, null);
                        //replicate reservation
                        $resv_ancien = Reservation::on('temp')->findOrFail($request->reservation_id);
                        //coppier ancien reservation meme code _reservation
                        $resv_new = $resv_ancien->replicate();
                        $resv_new->setConnection('temp');
                        $resv_new->ancien_id = $resv_ancien->id;
                        $resv_new->bien_id = $request->bien_id_new;
                        $resv_new->code_reservation = $resv_ancien->code_reservation;
                        $resv_new->etat = 1;
                        $resv_new->created_at = Carbon::now();
                        $resv_new->updated_at = Carbon::now();
                        $resv_new->code_desistement = $code_desist_reservation;

                        if ($resv_new->save()) {
                            //set resv ancien etat
                            $resv_ancien->setConnection('temp');
                            $resv_ancien->etat = EtatReservationEnum::desist_change_bien->value;
                            $resv_ancien->code_desistement = $code_desist_reservation;

                            if ($resv_ancien->save()) {
                                //replicate piece jointe
                                $pj_ancien = PiecesJointe::on('temp')->where('reservation_id', $request->reservation_id)->get();
                                if (count($pj_ancien) > 0) {
                                    foreach ($pj_ancien as $pj_old) {
                                        $pj_new = $pj_old->replicate();
                                        $pj_new->setConnection('temp');
                                        $pj_new->reservation_id = $resv_new->id;
                                        $pj_new->created_at = Carbon::now();
                                        $pj_new->updated_at = Carbon::now();
                                        if ($pj_new->save()) {
                                            $pj_old->delete();
                                        }
                                    }
                                }

                                //replicate avance
                                $av_ancien = Avance::on('temp')->where('reservation_id', $request->reservation_id)->get();
                                if (count($av_ancien) > 0) {
                                    foreach ($av_ancien as $av_old) {
                                        $av_new = $av_old->replicate();
                                        $av_new->setConnection('temp');
                                        $av_new->reservation_id = $resv_new->id;
                                        $av_new->reservation_id_ancien = $request->reservation_id;
                                        $av_new->desistement_id = $desistement->id;
                                        $av_new->ancien_recu = $av_old->num_recu;
                                        $last_num_recu = Avance::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                                            ->get('num_recu')->first();
                                        if ($last_num_recu != null) {
                                            $n_recu = $last_num_recu->num_recu + 1;
                                            $av_new->num_recu = '00' . $n_recu . '';
                                        } else {
                                            $av_new->num_recu = '001';
                                        }
                                        $av_new->created_at = Carbon::now();
                                        $av_new->updated_at = Carbon::now();
                                        if ($av_new->save()) {
                                            $av_old->delete();
                                            //replicate statut
                                            $av_last_statut = StatutAvancePenalite::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($av_last_statut != null) {
                                                $st_av_new = $av_last_statut->replicate();
                                                $st_av_new->avance_id = $av_new->id;
                                                $st_av_new->setConnection('temp');
                                                if ($st_av_new->save()) {
                                                    $av_last_statut->delete();
                                                }
                                            }
                                            //replicate pice jointe

                                            $av_old_pj = PiecesJointe::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($av_old_pj != null) {
                                                $pj_new = $av_old_pj->replicate();
                                                $pj_new->avance_id = $av_new->id;
                                                $pj_new->setConnection('temp');
                                                if ($pj_new->save()) {
                                                    $av_old_pj->delete();
                                                }
                                            }
                                            //replicate fiche transmission

                                            $old_f = FicheTransmission::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($old_f != null) {
                                                $f_new = $old_f->replicate();
                                                $f_new->avance_id = $av_new->id;
                                                $f_new->setConnection('temp');
                                                if ($f_new->save()) {
                                                    $old_f->delete();
                                                }
                                            }
                                            //replicate encaissement

                                            $old_en = Encaissement::on('temp')->where('avance_id', $av_old->id)->orderby('created_at', 'desc')->first();
                                            if ($old_en != null) {
                                                $new_encais = $old_en->replicate();
                                                $new_encais->avance_id = $av_new->id;
                                                $new_encais->reservation_id = $resv_new->id;
                                                $new_encais->setConnection('temp');
                                                if ($new_encais->save()) {
                                                    $old_en->delete();
                                                }
                                            }

                                        }

                                    }

                                }
                                //replicate piece jointe
                                $old_aqu_s = Aquereur::on('temp')->where('reservation_id', $request->reservation_id)->get();
                                if (count($old_aqu_s) > 0) {
                                    foreach ($old_aqu_s as $aq_old) {
                                        $aq_new = $aq_old->replicate();
                                        $aq_new->setConnection('temp');
                                        $aq_new->reservation_id = $resv_new->id;
                                        $aq_new->created_at = Carbon::now();
                                        $aq_new->updated_at = Carbon::now();
                                        if ($aq_new->save()) {
                                            $aq_old->delete();
                                        }
                                    }
                                }

                            }
                            //if(montant_a_ajouter >0)
                            if ($request->montant_a_ajouter > 0) {

                                //store avance
                                $avanceController = new AvanceController();
                                $avanceRequest = new StoreAvanceRequest();

                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                $montant = $request->montant_a_ajouter;
                                $mnt_lettre = $inWords->format($montant);
                                $dataAvance = [
                                    //addedd
                                    'desistement_id' => $desistement->id,
                                    'dossier_id_transfert' => null,
                                    'reservation_id' => $resv_new->id,
                                    /////
                                    'sr' => (bool) $request->sr,
                                    'type_encaissement' => 1,
                                    'montant' => $montant,
                                    'mode_paiement' => $request->input("mode_paiement"),
                                    'numero_paiement' => $request->numero_paiement,
                                    'date_reglement' => Carbon::now(),
                                    'echeance' => $request->echeance,
                                    'banque_id' => $request->banque_id,
                                    'montant_par_lettre' => $mnt_lettre,
                                    'commentaireAvance' => null,
                                    'num_remise' => null,
                                    'date_encaissement' => null,
                                    'files_avance' => $request->file('files_avance'),

                                ];
                                $avanceRequest->merge($dataAvance);
                                $avanceController->store($avanceRequest);

                            }
                            //send piece jointes
                        }

                        //libration de l'ancien bien
                        Bien_Helper::libererBien($request->bien_id_ancien, null, $desistement->id);

                        //validation du desistement et add reservation_id_new
                        $desistement->reservation_id_new = $resv_new->id;
                        if ($desistement->save()) {
                            /**store Historique */
                            //test si res_id exist deja en table historique
                            $histo_count_res_ancien = HistoriqueDesistement::on('temp')->where('reservation_id', $request->reservation_id)->count();
                            if ($histo_count_res_ancien == 0) {
                                $this->store_historique_desistement($request->reservation_id, null, $request->bien_id_ancien, $code_desist_reservation, $reservation->created_at);
                            }
                            // store histo desi
                            $this->store_historique_desistement(null, $desistement->id, $request->bien_id_ancien, $code_desist_reservation, Carbon::now());
                            //store histo res_new
                            $this->store_historique_desistement($resv_new->id, null, $request->bien_id_ancien, $code_desist_reservation, Carbon::now());

                        }
                    }

                } else {
                    //VALIDATION DU CHANGEMENT DE BIEN
                    //notif to admin pour valider desistement
                    NotificationHelper::storeNotification(
                        '/desistements/show/' . $desistement->id, Carbon::now(), 9, 'Demande Validation desistement', null, RoleEnum::ADMIN->value, null, null, $desistement->projet_id, null, $desistement->reservation_id
                    );
                    broadcast(new NotificationEvent($desistement->id));

                }

            }

            //store archive si rejete et re store nouveau desistement
            if ($request->desistement_id_rejete != "null" && $request->desistement_id_rejete != null) {
                $old_desistement = Desistement::on('temp')->findorfail($request->desistement_id_rejete);
                $old_desistement->archive = 1;
                if ($old_desistement->save()) {
                    if ($old_desistement->penalite_desistement != null) {
                        $old_desistement->penalite_desistement->archive = 1;
                        $old_desistement->save();
                    }
                }
            }

            return response()->json(['desistement' => 'desistement créé'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function store_historique_desistement($res_id, $des_id, $bien_id, $code_des, $date)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $histo = new HistoriqueDesistement();
            $histo->setConnection('temp');
            $histo->reservation_id = $res_id;
            $histo->desistement_id = $des_id;
            $histo->bien_id = $bien_id;
            $histo->code_desistement = $code_des;
            $histo->date = $date;
            $histo->save();
        }

    }

    public function get_historiques_desistement_by_reservation(Request $request, $code_desistement)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $array = array();

            $data = HistoriqueDesistement::on('temp')->where('code_desistement', $code_desistement)->orderBy('date', 'asc')->get();
            $data_s = $data->map(function ($dt) {
                $desisteurs = null;
                $au_profits = null;
                $data_nv_aq = null;
                $bien_new_propriete = null;
                $sum_avances = null;
                $penalite_montant = null;
                if ($dt->desistement_id != null) {
                    //si ligne desistement

                    $aquereur_desisteurs = AquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->where('type', 'desisteur')->get();
                    $aquereur_profit = AquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->where('type', 'au_profit')->get();

                    if (count($aquereur_desisteurs) > 0) {
                        $desisteurs = $aquereur_desisteurs->map(function ($aq_dt) {
                            return [
                                'client_nom' => $aq_dt->client->nom,
                                'client_prenom' => $aq_dt->client->prenom,
                                'client_percent' => $aq_dt->aquereur->pourcentage,
                                'type' => $aq_dt->type,
                            ];
                        });
                    }

                    if (count($aquereur_profit) > 0) {

                        $au_profits = $aquereur_profit->map(function ($aq_dt) {
                            return [
                                'client_nom' => $aq_dt->client->nom,
                                'client_prenom' => $aq_dt->client->prenom,
                                'client_percent' => $aq_dt->aquereur->pourcentage,
                                'type' => $aq_dt->type,
                            ];
                        });
                    }

                    $nv_aquereur_desistement = NouvelAquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->get();
                    if (count($nv_aquereur_desistement) > 0) {
                        $data_nv_aq = $nv_aquereur_desistement->map(function ($aq_nv) {
                            return [
                                'nv_client_cin' => $aq_nv->cin,
                                'nv_client_nom' => $aq_nv->nom,
                                'nv_client_prenom' => $aq_nv->prenom,
                                'nv_client_telephone' => $aq_nv->telephone,
                                'nv_client_percent' => $aq_nv->pourcentage,
                            ];
                        });
                    } else {
                        $data_nv_aq = null;
                    }
                    if ($dt->desistement->bien_id_new != null) {
                        $bien = Bien::on('temp')->findorfail($dt->desistement->bien_id_new);
                        $bien_new_propriete = $bien->propriete_dite_bien;
                    }

                    //penalite
                    $penalite = PenaliteDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->get()->first();
                    if ($penalite != null) {
                        $penalite_montant = $penalite->montant;

                    }

                }
                if ($dt->reservation_id != null) {
                    $sum_avances = Avance::on('temp')->where('reservation_id', $dt->reservation_id)->where('statut', 1)->withTrashed()->sum('montant');
                }

                //array
                return [
                    'histo' => $dt,
                    'desisteurs' => $desisteurs,
                    'au_profits' => $au_profits,
                    'new_aquereur_desistement' => $data_nv_aq,
                    'bien_new_propriete' => $bien_new_propriete,
                    'sum_avances' => $sum_avances,
                    'penalite_montant' => $penalite_montant,
                ];
            });

            $data_mm = PaginationHelper::paginate_array($data_s->toArray(), $perPage, $page, $request->url());

            return response()->json(['historiques' => $data_mm], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function get_dossiers_by_bien(Request $request, $bien_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $array = array();

            $data = HistoriqueDesistement::on('temp')->where('bien_id', $bien_id)->orderBy('date', 'asc')->get();
            $data_s = $data->map(function ($dt) {
                $desisteurs = null;
                $au_profits = null;
                $data_nv_aq = null;
                $bien_new_propriete = null;
                $sum_avances = null;
                $penalite_montant = null;
                if ($dt->desistement_id != null) {
                    //si ligne desistement

                    $aquereur_desisteurs = AquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->where('type', 'desisteur')->get();
                    $aquereur_profit = AquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->where('type', 'au_profit')->get();

                    if (count($aquereur_desisteurs) > 0) {
                        $desisteurs = $aquereur_desisteurs->map(function ($aq_dt) {
                            return [
                                'client_nom' => $aq_dt->client->nom,
                                'client_prenom' => $aq_dt->client->prenom,
                                'client_percent' => $aq_dt->aquereur->pourcentage,
                                'type' => $aq_dt->type,
                            ];
                        });
                    }

                    if (count($aquereur_profit) > 0) {

                        $au_profits = $aquereur_profit->map(function ($aq_dt) {
                            return [
                                'client_nom' => $aq_dt->client->nom,
                                'client_prenom' => $aq_dt->client->prenom,
                                'client_percent' => $aq_dt->aquereur->pourcentage,
                                'type' => $aq_dt->type,
                            ];
                        });
                    }

                    $nv_aquereur_desistement = NouvelAquereurDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->get();
                    if (count($nv_aquereur_desistement) > 0) {
                        $data_nv_aq = $nv_aquereur_desistement->map(function ($aq_nv) {
                            return [
                                'nv_client_cin' => $aq_nv->cin,
                                'nv_client_nom' => $aq_nv->nom,
                                'nv_client_prenom' => $aq_nv->prenom,
                                'nv_client_telephone' => $aq_nv->telephone,
                                'nv_client_percent' => $aq_nv->pourcentage,
                            ];
                        });
                    } else {
                        $data_nv_aq = null;
                    }
                    if ($dt->desistement->bien_id_new != null) {
                        $bien = Bien::on('temp')->findorfail($dt->desistement->bien_id_new);
                        $bien_new_propriete = $bien->propriete_dite_bien;
                    }

                    //penalite
                    $penalite = PenaliteDesistement::on('temp')->where('desistement_id', $dt->desistement_id)->get()->first();
                    if ($penalite != null) {
                        $penalite_montant = $penalite->montant;

                    }

                }
                if ($dt->reservation_id != null) {
                    $sum_avances = Avance::on('temp')->where('reservation_id', $dt->reservation_id)->where('statut', 1)->withTrashed()->sum('montant');
                }

                //array
                return [
                    'histo' => $dt,
                    'desisteurs' => $desisteurs,
                    'au_profits' => $au_profits,
                    'new_aquereur_desistement' => $data_nv_aq,
                    'bien_new_propriete' => $bien_new_propriete,
                    'sum_avances' => $sum_avances,
                    'penalite_montant' => $penalite_montant,
                ];
            });

            $data_mm = PaginationHelper::paginate_array($data_s->toArray(), $perPage, $page, $request->url());

            return response()->json(['historiques' => $data_mm], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    /**
     * Display the specified resource.
     */

    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            Config::set('broadcasting.default', 'pusher_3');
            DatabaseHelper::Config();
            $desistement = Desistement::on('temp')->with('aquereurs_desisteurs', 'aquereurs_non_desisteurs', 'aquereurs_profits', 'remboursement', 'nouvel_aquereurs_desistements', 'aquereurs_partiel', 'Bien_nouveau', 'banque', 'penalite_desistement')->findOrFail($id);
            $reservation_ancien = Reservation::on('temp')->findorfail($desistement->reservation_id);
            $sum_avances_valides_ancien = 0;
            //si dossier desiste
            if ($reservation_ancien->etat > 1) {
                foreach ($reservation_ancien->avances_desist as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides_ancien += $av->montant;
                    }
                }

            } else {
                foreach ($reservation_ancien->avances as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides_ancien += $av->montant;
                    }
                }
            }
            //get nom propriete _dite_bien concat utilisé
            $propriete = null;
            if ($desistement->bien_id_new != null) {
                $bien = new VisiteController();
                $propriete = $bien->get_propriete_bien_concat($desistement->bien_id_new);
            }
            return response()->json(['desistement' => $desistement, 'sum_avances_valides_ancien' => $sum_avances_valides_ancien, 'propriete_dite_bien' => $propriete], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function validation_desitement($id,Request $request){
        if(RoleHelper::AdminSup()){
            Config::set('broadcasting.default', 'pusher_3');
            DatabaseHelper::Config();
            $desistement = Desistement::on('temp')->findOrFail($id);
            $reservation = Reservation::on('temp')->findOrFail($desistement->reservation_id);
            $remboursement=Remboursement::on('temp')->where('desistement_id',$id)->get()->first();

            //valider
            if($request->statut==1){
                //etape validation
                $code_desist_reservation=0;
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
                if($desistement->type==TypeDesistement::Désistement_Définitif->value){
                    //update eta de reservation
                    $reservation->setConnection('temp');
                    $reservation->etat=EtatReservationEnum::desist_definitif->value ;
                    $reservation->code_desistement=$code_desist_reservation;

                    if($reservation->save()){
                        //soft_delete_avances
                        $avanceController = new AvanceController();
                        $avanceController->soft_destroy_avances_by_reservationId($desistement->reservation_id);
                        //soft delete aquereurs
                        $aquController = new AquereurController();
                        $aquController->soft_destroy_aqueureurs_by_reservationId($desistement->reservation_id);
                        //set piece jointe etat=0
                        $pjController = new PiecesJointeController();
                        $pjController->soft_destroy_pj_by_reservationId($desistement->reservation_id);
                        //set bien disponible et desistement_id
                        Bien_Helper::libererBien($desistement->bien_id_ancien,null,$desistement->id);
                        //si sum_avances >0
                        if($remboursement!=null){

                                    if($remboursement->mode_rembourse=='transfert' || $remboursement->mode_rembourse=='transfert_rem_direct'||$remboursement->mode_rembourse=='transfert_rem_apres_vente' ){
                                        //store avance

                                        $avanceController = new AvanceController();
                                        $avanceRequest = new StoreAvanceRequest();

                                        $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                        if($remboursement->mode_rembourse=='transfert_rem_direct'||$remboursement->mode_rembourse=='transfert_rem_apres_vente'){
                                            $montant=$remboursement->montant_transfert;
                                            $mnt_lettre = $inWords->format($montant);
                                        }else{
                                            $montant=$remboursement->s_avances;
                                            $mnt_lettre = $inWords->format($montant);
                                        }
                                        $dataAvance = [
                                            //addedd
                                            'desistement_id'=>$id,
                                            'dossier_id_transfert'=>$desistement->reservation_id,
                                            'reservation_id'=> $remboursement->dossier_id_transfert,
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
                            //validation desistement
                            $desistement->reservation_id_new=$desistement->reservation_id;

                            if($desistement->save()){
                                //store Historique Désistement
                                    //test si res_id exist deja en table historique
                                    $histo_count=HistoriqueDesistement::on('temp')->where('reservation_id',$desistement->reservation_id)->count();
                                    if($histo_count==0){
                                        $this->store_historique_desistement($desistement->reservation_id,null,$desistement->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                    }
                                    // store histo desi
                                    $this->store_historique_desistement(null,$desistement->id,$desistement->bien_id_ancien,$code_desist_reservation,Carbon::now());
                            }
                        }
                    }
                }
                    //DP
                    elseif($desistement->type==TypeDesistement::Désistement_Au_Profit->value){

                            //dp_proche//dp_co
                            if($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value||$desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                $aq_non_desisteur=AquereurDesistement::on('temp')->where('desistement_id',$id)->where('type','non_desisteur')->get();
                                $nouvel_aqu=NouvelAquereurDesistement::on('temp')->where('desistement_id',$id)->get();
                                $les_au_profit=AquereurDesistement::on('temp')->where('desistement_id',$id)->where('type','au_profit')->get();

                            }else{
                                //partiel
                                $les_partiel=AquereurDesistement::on('temp')->where('desistement_id',$id)->where('type','partiel')->get();
                                $nouvel_aqu=NouvelAquereurDesistement::on('temp')->where('desistement_id',$id)->get();
                            }


                            $resv_ancien = Reservation::on('temp')->findOrFail($desistement->reservation_id);
                            //coppier ancien reservation meme code _reservation
                            $resv_new = $resv_ancien->replicate();
                            $resv_new->setConnection('temp');
                            $resv_new->ancien_id = $resv_ancien->id;
                            $resv_new->code_reservation= $resv_ancien->code_reservation;
                            $resv_new->code_desistement=$code_desist_reservation;
                            $resv_new->etat= 1;
                            if($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                                $resv_new->nb_acquereurs = count($aq_non_desisteur)+count($nouvel_aqu);
                            }elseif($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                $resv_new->nb_acquereurs = count($aq_non_desisteur)+count($les_au_profit);
                            }
                            elseif($desistement->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                                $resv_new->nb_acquereurs = count($les_partiel)+count($nouvel_aqu);
                            }

                            $resv_new->created_at = Carbon::now();
                            $resv_new->updated_at = Carbon::now();
                            if($resv_new->save()){
                                //set resv ancien etat
                                $resv_ancien->setConnection('temp');
                                if($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
                                    $resv_ancien->etat=EtatReservationEnum::desist_profit_proche->value;
                                }elseif($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
                                    $resv_ancien->etat=EtatReservationEnum::desist_profit_co->value;

                                }elseif($desistement->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
                                    $resv_ancien->etat=EtatReservationEnum::desist_partiel->value;
                                }
                                $resv_ancien->code_desistement=$code_desist_reservation;

                                if($resv_ancien->save()){
                                    //replicate piece jointe
                                    $pj_ancien = PiecesJointe::on('temp')->where('reservation_id',$desistement->reservation_id)->get();
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
                                    $av_ancien = Avance::on('temp')->where('reservation_id',$desistement->reservation_id)->get();
                                    if(count($av_ancien)>0){
                                        foreach($av_ancien as $av_old){
                                            $av_new = $av_old->replicate();
                                            $av_new->setConnection('temp');
                                            $av_new->reservation_id = $resv_new->id;
                                            $av_new->reservation_id_ancien = $desistement->reservation_id;
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
                                                //replicate statut
                                                $av_last_statut = StatutAvancePenalite::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                                if($av_last_statut!=null){
                                                    $st_av_new = $av_last_statut->replicate();
                                                    $st_av_new->avance_id=$av_new->id;
                                                    $st_av_new->setConnection('temp');
                                                    if($st_av_new->save()){
                                                        $av_last_statut->delete();
                                                    }
                                                }
                                                    //replicate pice jointe

                                                $av_old_pj = PiecesJointe::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                                if($av_old_pj!=null){
                                                    $pj_new = $av_old_pj->replicate();
                                                    $pj_new->avance_id=$av_new->id;
                                                    $pj_new->setConnection('temp');
                                                    if($pj_new->save()){
                                                        $av_old_pj->delete();
                                                    }
                                                }
                                                 //replicate fiche transmission

                                                 $old_f = FicheTransmission::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                                 if($old_f!=null){
                                                     $f_new = $old_f->replicate();
                                                     $f_new->avance_id=$av_new->id;
                                                     $f_new->setConnection('temp');
                                                     if($f_new->save()){
                                                         $old_f->delete();
                                                     }
                                                 }
                                                 //replicate encaissement

                                                  $old_en = Encaissement::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                                  if($old_en!=null){
                                                      $new_encais = $old_en->replicate();
                                                      $new_encais->avance_id=$av_new->id;
                                                      $new_encais->reservation_id=$resv_new->id;
                                                      $new_encais->setConnection('temp');
                                                      if($new_encais->save()){
                                                          $old_en->delete();
                                                      }
                                                  }
                                            }
                                        }
                                    }
                                    //soft delete ancien aquereurs
                                    $aquController = new AquereurController();
                                    $aquController->soft_destroy_aqueureurs_by_reservationId($desistement->reservation_id);

                                }
                                if($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value){
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
                                elseif($desistement->type_dp==TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value){
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
                                elseif($desistement->type_dp==TypeDesistementProfit::Désistement_Partiel->value){
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
                                        $histo_count_res_ancien=HistoriqueDesistement::on('temp')->where('reservation_id',$desistement->reservation_id)->count();
                                        if($histo_count_res_ancien==0){
                                            $this->store_historique_desistement($desistement->reservation_id,null,$desistement->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                        }
                                        // store histo desi
                                        $this->store_historique_desistement(null,$desistement->id,$desistement->bien_id_ancien,$code_desist_reservation,Carbon::now());
                                        //store histo res_new
                                        $this->store_historique_desistement($resv_new->id,null,$desistement->bien_id_ancien,$code_desist_reservation,Carbon::now());

                                    }
                            }

                    }
                    //CHANGEMENT DE BIEN

                    elseif($desistement->type==TypeDesistement::Changement_De_Bien->value){
                        //set bien Pré-Réservé
                        $bien_c=new BienController();
                        $bien_c->prereserverBien($desistement->bien_id_new,null,null);
                        //replicate reservation
                        $resv_ancien = Reservation::on('temp')->findOrFail($desistement->reservation_id);
                        //coppier ancien reservation meme code _reservation
                        $resv_new = $resv_ancien->replicate();
                        $resv_new->setConnection('temp');
                        $resv_new->ancien_id = $resv_ancien->id;
                        $resv_new->bien_id = $desistement->bien_id_new;
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
                                $pj_ancien = PiecesJointe::on('temp')->where('reservation_id',$desistement->reservation_id)->get();
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
                                $av_ancien = Avance::on('temp')->where('reservation_id',$desistement->reservation_id)->get();
                                if(count($av_ancien)>0){
                                    foreach($av_ancien as $av_old){
                                        $av_new = $av_old->replicate();
                                        $av_new->setConnection('temp');
                                        $av_new->reservation_id = $resv_new->id;
                                        $av_new->reservation_id_ancien = $desistement->reservation_id;
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
                                            //replicate statut
                                            $av_last_statut = StatutAvancePenalite::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                            if($av_last_statut!=null){
                                                $st_av_new = $av_last_statut->replicate();
                                                $st_av_new->avance_id=$av_new->id;
                                                $st_av_new->setConnection('temp');
                                                if($st_av_new->save()){
                                                    $av_last_statut->delete();
                                                }
                                            }
                                                //replicate pice jointe

                                            $av_old_pj = PiecesJointe::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                            if($av_old_pj!=null){
                                                $pj_new = $av_old_pj->replicate();
                                                $pj_new->avance_id=$av_new->id;
                                                $pj_new->setConnection('temp');
                                                if($pj_new->save()){
                                                    $av_old_pj->delete();
                                                }
                                            }
                                             //replicate fiche transmission

                                             $old_f = FicheTransmission::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                             if($old_f!=null){
                                                 $f_new = $old_f->replicate();
                                                 $f_new->avance_id=$av_new->id;
                                                 $f_new->setConnection('temp');
                                                 if($f_new->save()){
                                                     $old_f->delete();
                                                 }
                                             }
                                             //replicate encaissement

                                              $old_en = Encaissement::on('temp')->where('avance_id',$av_old->id)->orderby('created_at','desc')->first();
                                              if($old_en!=null){
                                                  $new_encais = $old_en->replicate();
                                                  $new_encais->avance_id=$av_new->id;
                                                  $new_encais->reservation_id=$resv_new->id;
                                                  $new_encais->setConnection('temp');
                                                  if($new_encais->save()){
                                                      $old_en->delete();
                                                  }
                                              }
                                        }
                                    }
                                }
                            //replicate piece jointe
                            $old_aqu_s = Aquereur::on('temp')->where('reservation_id',$desistement->reservation_id)->get();
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
                            if($desistement->montant_a_ajouter>0){

                                //store avance
                                $avanceController = new AvanceController();
                                $avanceRequest = new StoreAvanceRequest();

                                $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                                $montant=$desistement->montant_a_ajouter;
                                $mnt_lettre = $inWords->format($montant);
                                $dataAvance = [
                                    //addedd
                                    'desistement_id'=>$desistement->id,
                                    'dossier_id_transfert'=>null,
                                    'reservation_id'=>$resv_new->id,
                                    /////
                                    'sr' => (bool)$desistement->sr,
                                    'type_encaissement' => 1,
                                    'montant' => $montant,
                                    'mode_paiement' => $desistement->mode_paiement,
                                    'numero_paiement' => $desistement->numero_paiement,
                                    'date_reglement' => Carbon::now(),
                                    'echeance' => $desistement->echeance,
                                    'banque_id' => $desistement->banque_id,
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
                            Bien_Helper::libererBien($desistement->bien_id_ancien,null,$desistement->id);



                    //validation du desistement et add reservation_id_new
                        $desistement->reservation_id_new=$resv_new->id;
                        if($desistement->save()){
                                        /**store Historique */
                                        //test si res_id exist deja en table historique
                                        $histo_count_res_ancien=HistoriqueDesistement::on('temp')->where('reservation_id',$desistement->reservation_id)->count();
                                        if($histo_count_res_ancien==0){
                                            $this->store_historique_desistement($desistement->reservation_id,null,$desistement->bien_id_ancien,$code_desist_reservation,$reservation->created_at);
                                        }
                                        // store histo desi
                                        $this->store_historique_desistement(null,$desistement->id,$desistement->bien_id_ancien,$code_desist_reservation,Carbon::now());
                                        //store histo res_new
                                        $this->store_historique_desistement($resv_new->id,null,$desistement->bien_id_ancien,$code_desist_reservation,Carbon::now());

                        }
                    }
                    $desistement->statut=1;
                    $desistement->date_validation=Carbon::now();

                    if( $desistement->save()){
                            //desistement validé
                            NotificationHelper::storeNotification(
                                '/desistements/show/'.$desistement->id, Carbon::now(),11,'desistement validé',null,RoleEnum::ADMIN->value,null,null,$desistement->projet_id,null,$desistement->reservation_id
                            );
                            broadcast(new NotificationEvent($desistement->id));
                    }

            }else{
                //rejeter
                $desistement->statut=$request->statut;
                $desistement->commentaire_rejete=$request->commentaire;
                $desistement->date_validation=Carbon::now();

                if($desistement->save()){
                     //desistement validé
                     NotificationHelper::storeNotification(
                        '/desistements/corriger_desistement/'.$desistement->id, Carbon::now(),11,'desistement rejeté',null,RoleEnum::ADMIN->value,null,null,$desistement->projet_id,null,$desistement->reservation_id
                    );
                    broadcast(new NotificationEvent($desistement->id));
                }
            }

            return response()->json('données enregistrés avec success');
        } else  return response()->json(['error'=>'Unauthorized'], 401);
    }
    public function get_notif_dst_commercial($projet_id)
    {
        $user = Auth::user();
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $nb_desistement_valide = Desistement::on('temp')->where('archive', 0)->where('projet_id', $projet_id)->where('statut', 1)->where('user_id', $userAuth->value('id'))->whereDate('date_validation', Carbon::now())->count();
            $nb_desistement_rejete = Desistement::on('temp')->where('archive', 0)->where('projet_id', $projet_id)->where('statut', 2)->where('user_id', $userAuth->value('id'))->whereDate('date_validation', Carbon::now())->count();
            //$nb_desistement_encours = Desistement::on('temp')->where('projet_id',$projet_id)->where('statut',0)->where('user_id', $userAuth->value('id'))->count();

            //par type
            $nb_desistement_valide_par_type = Desistement::on('temp')->select(DB::raw('count(id) as nb_dst,type,type_dp'))
                ->where('projet_id', $projet_id)->where('archive', 0)->where('statut', 1)->where('user_id', $userAuth->value('id'))->whereDate('date_validation', Carbon::now())->groupBy('type', 'type_dp')->get();
            $nb_desistement_rejete_par_type = Desistement::on('temp')->select(DB::raw('count(id) as nb_dst,type,type_dp'))
                ->where('projet_id', $projet_id)->where('archive', 0)->where('statut', 2)->where('user_id', $userAuth->value('id'))->whereDate('date_validation', Carbon::now())->groupBy('type', 'type_dp')->get();
            /* $nb_desistement_encours_par_type =Desistement::on('temp')->select(DB::raw('count(id) as nb_dst,type,type_dp'))
            ->where('projet_id',$projet_id)->where('statut',0)->where('user_id', $userAuth->value('id'))->groupBy('type','type_dp')->get();*/

            return response()->json(['nb_dst_valide' => $nb_desistement_valide,
                'nb_dst_rejete' => $nb_desistement_rejete,
                //'nb_dst_encours'=>$nb_desistement_encours,
                'nb_desistement_valide_par_type' => $nb_desistement_valide_par_type,
                'nb_desistement_rejete_par_type' => $nb_desistement_rejete_par_type,
                //'nb_desistement_encours_par_type'=>$nb_desistement_encours_par_type
            ]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function get_notif_dst_admin($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $nb_desistement_att_valide = Desistement::on('temp')->where('archive', 0)->where('projet_id', $projet_id)->where('statut', 0)->count();
            //nb_des desistement par type
            $nb_desistement_att_valide_par_type = Desistement::on('temp')->select(DB::raw('count(id) as nb_dst,type,type_dp'))
                ->where('projet_id', $projet_id)->where('archive', 0)->where('statut', 0)->groupBy('type', 'type_dp')->get();

            return response()->json(['nb_dst_att_valide' => $nb_desistement_att_valide, 'nb_dst_att_valide_par_type' => $nb_desistement_att_valide_par_type]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function get_desistements(Request $request, $projet_id, $type, $statut)
    {
        $perPage = $request->input('pageSize', 5); // Get the number of items per page
        $page = $request->input('page', 1);
        $type_e = null;
        $type_e_dp = null;
        if ($type == 'dst_definitif') {
            $type_e = TypeDesistement::Désistement_Définitif->value;
        } elseif ($type == 'change_bien') {
            $type_e = TypeDesistement::Changement_De_Bien->value;
        } else {
            if ($type == 'dp_co') {
                $type_e_dp = TypeDesistementProfit::Désistement_AU_PROFIT_UN_CO_RESERVATAIRE->value;
                $type_e = TypeDesistement::Désistement_Au_Profit->value;
            } else if ($type == 'dp_proche') {
                $type_e_dp = TypeDesistementProfit::Désistement_AU_PROFIT_UN_PROCHE->value;
                $type_e = TypeDesistement::Désistement_Au_Profit->value;
            } else if ($type == 'dp_partiel') {
                $type_e_dp = TypeDesistementProfit::Désistement_Partiel->value;
                $type_e = TypeDesistement::Désistement_Au_Profit->value;
            }
        }

        //att de validation(admin)==>encours(commercial)
        if ($statut == 5) {
            $statut = 0;
        }

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $desistements = Desistement::on('temp')->with('penalite_desistement', 'remboursement', 'nouvel_aquereurs_desistements', 'aquereurs_desisteurs', 'aquereurs_profits', 'aquereurs_partiel', 'Bien_nouveau')->orderBy('created_at', 'desc')
                ->where('statut', $statut)
                ->where('type', $type_e)
                ->where('type_dp', $type_e_dp)
                ->where('projet_id', $projet_id)
                ->where('archive', 0)
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['desistements' => $desistements], 200);

        } elseif (RoleHelper::Com()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $desistements = Desistement::on('temp')->with('penalite_desistement', 'remboursement', 'nouvel_aquereurs_desistements', 'aquereurs_desisteurs', 'aquereurs_profits', 'aquereurs_partiel', 'Bien_nouveau')->orderBy('created_at', 'desc')
                ->where('statut', $statut)
                ->where('type', $type_e)
                ->where('user_id', $userAuth->value('id'))
                ->where('type_dp', $type_e_dp)
                ->where('projet_id', $projet_id)
                ->where('archive', 0)
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['desistements' => $desistements], 200);
        }

        // else  return response()->json(['error'=>'Unauthorized'], 401);
    }

    /**************************************Penalites***************************************/

    public function get_historiques_penalites_by_desId($desistement_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $penalites = PenaliteDesistement::on('temp')->with('banque')
            //->where('archive',0)
                ->where('desistement_id', $desistement_id)
                ->orderBy('created_at', 'desc')->get();
            return response()->json(['penalites' => $penalites]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function get_notif_pen_commercial($projet_id)
    {
        $user = Auth::user();
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $nb_pen_valide = PenaliteDesistement::on('temp')->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                ->where('penalites_desistements.archive', 0)
                ->where('desistements.archive', 0)
                ->where('desistements.projet_id', $projet_id)
                ->where('penalites_desistements.statut', 1)
                ->where('desistements.user_id', $userAuth->value('id'))
                ->whereDate('penalites_desistements.date_validation', Carbon::now())->count();

            $nb_pen_rejete = PenaliteDesistement::on('temp')->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                ->where('penalites_desistements.archive', 0)
                ->where('desistements.archive', 0)
                ->where('desistements.projet_id', $projet_id)
                ->where('penalites_desistements.statut', 2)
                ->where('desistements.user_id', $userAuth->value('id'))
                ->count();

            return response()->json(['nb_valide' => $nb_pen_valide,
                'nb_rejete' => $nb_pen_rejete,
            ]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function get_notif_pen_admin($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $nb_pen_att_valide = PenaliteDesistement::on('temp')->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                ->where('penalites_desistements.archive', 0)
                ->where('desistements.archive', 0)
                ->where('desistements.deleted_at', null)
                ->where('desistements.projet_id', $projet_id)->where('penalites_desistements.statut', 0)->count();
            return response()->json(['nb_att_valide' => $nb_pen_att_valide]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public function corriger_penalite(Request $request)
    {
        $user = Auth::user();
        Config::set('broadcasting.default', 'pusher_3');
        if (RoleHelper::AC()) {
            DatabaseHelper::Config();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $penalite = new PenaliteDesistement();
            $penalite->setConnection('temp');
            $num_recu = null;
            $last_num_recu = PenaliteDesistement::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")
                ->get('num_recu')->first();
            if ($last_num_recu != null) {
                $n_recu = $last_num_recu->num_recu + 1;
                $num_recu = '00' . $n_recu . '';
            } else {
                $num_recu = '001';
            }
            $inWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
            $pen_mnt_lettre = $inWords->format($request->penalite_montant);
            $pen = new PenaliteDesistement();
            $pen->setConnection('temp');
            $pen->desistement_id = $request->desistement_id;
            $pen->num_recu = $num_recu;
            $pen->statut = 0;
            /*if (RoleHelper::AdminSup()) {
            //validé
            $pen->statut = 1;
            }else{
            $pen->statut=0;
            }*/
            $pen->montant = $request->penalite_montant;
            $pen->montant_par_lettre = $pen_mnt_lettre;
            $pen->penalite_par = $request->penalite_par;
            $pen->mode_penalite = $request->mode_penalite;
            // $pen->sr= (bool)$request->sr_pen;
            if ($request->sr_pen == 'false') {
                $pen->sr = 0;
            } else {
                $pen->sr = 1;
            }
            $pen->mode_paiement = $request->input("mode_paiement_pen");
            //cheque cheque-banque cheque cetifice
            if ($request->mode_paiement_pen == 2 || $request->mode_paiement_pen == 3 || $request->mode_paiement_pen == 4) {
                $pen->numero_paiement = $request->numero_paiement_pen;
                $pen->banque_id = $request->banque_id_pen;
                $pen->echeance = $request->echeance_pen;
            }
            //virement versement
            elseif ($request->mode_paiement_pen == 5 || $request->mode_paiement_pen == 6) {
                $pen->numero_paiement = $request->numero_paiement_pen;
                $pen->banque_id = $request->banque_id_pen;
            }
            //les pices jointes des penalité a jouter

            if ($pen->save()) {
                //store notification echeance
                if ($request->penalite_par == 'prix') {
                    if ($pen->echeance != null) {
                        NotificationHelper::storeNotification(
                            '/desistements/penalite/show/' . $pen->id, $pen->echeance, 5, 'ECHEANCE Pénalité', Auth::guard('api')->user()->id, null, null, null, $desistement->projet_id, null, $desistement->reservation_id
                        );
                        broadcast(new NotificationEvent($pen->id));
                    }
                }
                //store pice_jointe_penalite

                //store penalite to fiche transmission
                $fiche = new FicheTransmission();
                $fiche->setConnection('temp');
                //num recu cree aujourdhui
                $recu_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', Carbon::now())
                    ->get('num_recu')->first();
                if ($recu_now != null) {
                    $fiche->num_recu = $recu_now->num_recu;

                } else {
                    //num recu cree != aujourdhui
                    $rec_not_now = FicheTransmission::on('temp')->orderByRaw("CAST(num_recu as UNSIGNED) DESC")->whereDate('created_at', '!=', Carbon::now())
                        ->get('num_recu')->first();
                    if ($rec_not_now != null) {
                        $pp = $rec_not_now->num_recu + 1;
                        $fiche->num_recu = '00' . $pp . '';
                    } else {
                        $fiche->num_recu = '001';
                    }

                }
                $fiche->avance_id = null;
                $fiche->penalite_id = $pen->id;
                $fiche->user_id = $userAuth->value('id');
                if ($request->mode_paiement_pen == 2 || $request->mode_paiement_pen == 3 || $request->mode_paiement_pen == 4) {
                    $fiche->date = $pen->echeance;
                } else {
                    $fiche->date = Carbon::now();
                }
                if ($fiche->save()) {
                    // if (RoleHelper::Com()) {
                    //notifiction to admin de valider penalite
                    NotificationHelper::storeNotification(
                        'desistements/penalites/show/' . $pen->id, null, 6, 'DEMANDE VALIDATION Pénalité', null, RoleEnum::ADMIN->value, null, null, $pen->desistement->projet_id, null, $pen->desistement->reservation_id
                    );
                    broadcast(new NotificationEvent($pen->id));
                    // }
                }

            }

            //archivé ancien penalité
            $old_penalite = PenaliteDesistement::on('temp')->findorfail($request->penalite_id);
            $old_penalite->archive = 1;
            $old_penalite->save();

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function get_all_penalites(Request $request, $projet_id, $statut)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            //att de validation(admin)==>encours(commercial)
            if ($statut == 5) {
                $statut = 0;
            }
            if (RoleHelper::AdminSup()) {
                DatabaseHelper::Config();
                $penalites = PenaliteDesistement::on('temp')->select('penalites_desistements.*')
                    ->with('banque', 'last_statut')
                    ->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                    ->orderBy('created_at', 'desc')
                    ->where('penalites_desistements.statut', $statut)
                    ->where('desistements.projet_id', $projet_id)
                    ->where('desistements.archive', 0)
                    ->where('penalites_desistements.archive', 0)
                    ->where('desistements.deleted_at', null)
                    ->paginate($perPage, ['*'], 'page', $page);
                return response()->json(['penalites' => $penalites], 200);

            } elseif (RoleHelper::Com()) {
                DatabaseHelper::Config();
                $user = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $penalites = PenaliteDesistement::on('temp')->select('penalites_desistements.*')
                    ->with('banque', 'last_statut')
                    ->join('desistements', 'desistements.id', '=', 'penalites_desistements.desistement_id')
                    ->orderBy('created_at', 'desc')
                    ->where('penalites_desistements.statut', $statut)
                    ->where('desistements.projet_id', $projet_id)
                    ->where('desistements.archive', 0)
                    ->where('penalites_desistements.archive', 0)
                    ->where('desistements.deleted_at', null)
                    ->where('user_id', $userAuth->value('id'))
                    ->paginate($perPage, ['*'], 'page', $page);
                return response()->json(['penalites' => $penalites], 200);
            }
            return response()->json(['penalites' => $penalites], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function show_penalite($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $penalite = PenaliteDesistement::on('temp')->with('banque', 'last_statut')->findOrFail($id);
            $reservation_ancien = Reservation::on('temp')->findorfail($penalite->desistement->reservation_id);
            $sum_avances_valides = 0;
            //si dossier desiste
            if ($reservation_ancien->etat > 1) {
                foreach ($reservation_ancien->avances_desist as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }

            } else {
                foreach ($reservation_ancien->avances as $av) {
                    //avance validé
                    if ($av->statut == StatutReservationEnum::Validé->value) {
                        $sum_avances_valides += $av->montant;
                    }
                }
            }
            return response()->json(['penalite' => $penalite, 'sum_avances_valides' => $sum_avances_valides], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function traiter_penalite($id, Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_3');
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $penalite = PenaliteDesistement::on('temp')->findOrFail($id);
            $penalite->statut = $request->etat;
            if ($penalite->save()) {
                //store statut_avances_penalites table=>si validé

                $st_pen = new StatutAvancePenalite();
                $st_pen->setConnection('temp');
                $st_pen->statut = $request->etat;
                if ($request->etat == 1) {
                    $st_pen->num_remise = $request->n_remise;
                    $st_pen->date_encaissement = $request->date_encaiss;

                } else {
                    $st_pen->commentaire = $request->commentaire;
                }

                $st_pen->penalite_id = $penalite->id;
                $st_pen->user_id_valider = $userAuth->value('id');
                $st_pen->date_validation = Carbon::now();
                $st_pen->save();
            }
            if ($request->etat == 1) {
                //store new notification validé
                $encaiss = new Encaissement();
                $encaiss->setConnection('temp');
                $encaiss->reservation_id = $penalite->desistement->reservation_id;
                $encaiss->type_encaissement = 6; //Penalités
                $encaiss->montant = $penalite->montant;
                $encaiss->penalite_id = $penalite->id;
                $encaiss->date_reglement = $penalite->created_at;
                $encaiss->date_encaissement = $request->date_encaiss;
                $encaiss->user_id_valider = $userAuth->value('id');
                $encaiss->save();

                NotificationHelper::storeNotification(
                    '/desistements/penalites/show/' . $penalite->id, Carbon::now(), 13, 'penalité validé', $penalite->desistement->user->user_id_origin, null, null, null, $penalite->desistement->projet_id, null, null
                );
                broadcast(new NotificationEvent($id));
            } else {
                //store new notification rejeté
                NotificationHelper::storeNotification(
                    '/desistements/penalites/show/' . $penalite->id, Carbon::now(), 14, 'penalité rejeté', $penalite->desistement->user->user_id_origin, null, null, null, $penalite->desistement->projet_id, null, null
                );
                broadcast(new NotificationEvent($id));
            }

            return response()->json(['message' => 'données enregistrés avec succès.'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function destroy(string $id)
    {
        if (RoleHelper::AdminSup()) {
            /*DatabaseHelper::Config();
        $vue=Vue::on('temp')->findOrFail($id);
        if($vue->delete())
        {
        return response()->json(['message'=>'Vue supprimée avec succès.'],200);
        }
        else{
        return response()->json(['error'=>"La vue n'a pas été supprimée."],404);
        }*/
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
