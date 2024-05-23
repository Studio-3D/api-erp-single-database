<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Enum\EtatBien;
use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;
use App\Enum\TypeNotificationEnum;
use App\Http\Helpers\HistoriqueBienHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Requests\Store_n_VisiteRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Http\Requests\UpdateVisiteRequest;
use App\Http\Requests\UpdateDate_relance_Rdv;
use App\Http\Requests\StoreReservationRequest;
use \NumberFormatter;
use App\Models\Bien;
use App\Models\Notification;
use App\Models\PreReservation;
use App\Models\Bloc;
use App\Models\Frein;
use App\Models\Immeuble;
use App\Models\Prospect;
use App\Models\Tranche;
use App\Models\Typologie;
use App\Models\User;
use App\Models\Visite;
use App\Models\Vue;
use App\Models\FreinTranche;
use App\Models\FreinEtage;
use App\Models\FreinOrientation;
use App\Models\FreinTypologie;
use App\Models\FreinVue;
use App\Models\Relance_Rdv_visite;
use App\Models\HistoriqueVisite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Helpers\Bien_Helper;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Events\NotifMenuEvent;




class VisiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);

            $visites = Visite::on('temp')->latest('created_at')->where('projet_id',$projet_id)->where('etat',1)
            ->get()
            ->groupby('origin_id');
          $visites = $visites->map(function ($visite) {
            return [
                'id' => $visite->first()->id,
                'origin_id' => $visite->first()->origin_id,
                'nom_cc' => $visite->first()->user->name,
                'prenom_cc' => $visite->first()->user->prenom,
                'date' => $visite->first()->created_at,
                'cin' => $visite->first()->prospect->cin,
                'nom' => $visite->first()->prospect->nom,
                'prenom' => $visite->first()->prospect->prenom,
                'telephone' => $visite->first()->prospect->telephone,
                'telephone2' => $visite->first()->prospect->telephone_num2,
                'prospect_id' => $visite->first()->prospect->id,
                'interet' => $visite->first()->interet,
                'statut' => $visite->first()->statut,
                'propriete_dite_bien' => $visite->first()->bien_id?$visite->first()->bien->propriete_dite_bien:'',
                'etat_bien' => $visite->first()->bien_id?$visite->first()->bien->etat:'',
                'bien_id' => $visite->first()->bien_id?$visite->first()->bien_id:'',
                'visit_count' => count($visite),
                'reservation' => $visite->first()->reservation,

            ];});

          $data = PaginationHelper::paginate_array($visites->toArray(),$perPage,$page,$request->url());
            return response()->json(['visites' => $data]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function get_historiques($origin_id){
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $frein_h=new FreinController();
            $historiques=Visite::on('temp')->with('relance_relation','rdv_relation')
            ->where('origin_id',$origin_id)->withTrashed()->orderby('created_at', 'desc')->get();
            foreach ($historiques as $histo) {
                if ($histo->interet == InteretEnum::Perdu->value) {
                    $frein_h_ = $frein_h->searchFreinByVisiteId($histo->id,'with_row_deleted_at');
                    $histo['frein'] = $frein_h_;
                }
            }
            return response()->json(['historiques' => $historiques], 200);
         }
    }


    public function get_oldBien_visite_pre_reserve($origin_id){
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $biens_visite=Visite::on('temp')->where('origin_id',$origin_id)->where('interet',InteretEnum::Intéressé->value)->where('statut',StatutVisiteEnum::Pré_Réservation->value)->orderby('created_at', 'desc')->get(['bien_id','id']);
            return response()->json(['biens_visite' => $biens_visite], 200);
         }
    }
    public  function update_visite_bien_pre_reserve($id,Request $request)
    {
        if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            foreach ($request->list_biens_visite as $key=>$list) {
                //Annuler pre reservation

                if($list['action']==2){
                    $bien=Bien::on('temp')->findorfail($list['bien_id']);
                    $bien->etat = EtatBien::DISPONIBLE->value;
                    if($bien->save()){
                        $visite=Visite::on('temp')->findorfail($list['visite_id']);
                        $visite->statut=StatutVisiteEnum::Pré_Réservation_Perdu->value;
                        $visite->save();
                    }

                }
            }
        return response()->json(['message' => 'suceees'], 200);
       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }
    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     */


     public function store(StoreVisiteRequest $request)


     {      /***liste des fonctions a ajouter
                 Chercher s'il y a appel du meme client==>le convertir en visite
                 convert
                 lead to visite
             ****/


             $user = Auth::user();
             if (RoleHelper::ACSup()) {
                 DatabaseHelper::Config();
                 Config::set('broadcasting.default', 'pusher_3');

                  //store prospect si client n'existe pas

             if($request->prospect_id==null){
                 $validatedData = $request->validated();
                 $validatedData['cin']=$request->cin;
                 $validatedData['email']=$request->email;
                 $validatedData['source']=$request->source_id;
                 if($request->source_txt == 'PARTENAIRE'){
                    $validatedData['partenaire_id']=$request->partenaire_id;
                 }else{
                    $validatedData['partenaire_id']=null;
                 }
                 $validatedData['telephone']=$request->telephone;
                 $validatedData['telephone_num2']=$request->telephone_num2;
                 $validatedData['origin']='visite';
                 $validatedData['notifie']=$request->notifie;
                 $prospectController = new ProspectController();
                 $prospect = $prospectController->store(new StoreProspectRequest($validatedData));
             }
             else{
                 //recupere le prospect //modifier info
                 $prospect= Prospect::on('temp')->findorfail($request->prospect_id);
                 //$prospect->cin=$request->cin;
                 if($request->cin!=null){
                     $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$request->prospect_id)->count();
                     if($cin_exist==0){
                         $prospect->cin=$request->cin;
                         $prospect->email=$request->email;
                     }
                 }
                 $prospect->nom=$request->nom;
                 $prospect->prenom=$request->prenom;
                 $prospect->telephone=$request->telephone;
                 $prospect->telephone_num2=$request->telephone_num2;
                 $prospect->source=$request->source_id;
                 if ($request->source_txt == 'PARTENAIRE') {
                 $prospect->partenaire_id = $request->partenaire_id;
                 }
                 $prospect->notifie = $request->notifie;
                 $prospect->save();
             }
             $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                //(storee first ou n visite )
                $origin_id=null;
                $last_number=null;
                if($request->prospect_id!=null){
                    $visite_exist=Visite::on('temp')->where('prospect_id',$request->prospect_id)->where('etat',1)->where('projet_id',$request->selectedProjet)->orderBy('created_at', 'DESC')->first();
                    if($visite_exist!=null){
                        $last_number=intval(explode(" ",$visite_exist->description)[2]);
                        $origin_id =$visite_exist->origin_id;
                    }
                }
             //si receptif ou perdu
             if($request->interet==InteretEnum::Réceptif->value||$request->interet==InteretEnum::Perdu->value){
                        //rendre bien disponible si interet!=Intéressé ==>Réceptif ou Perdu
                        if ($request->list_bien_interesse) {
                            foreach ($request->list_bien_interesse as $key => $list_biens) {
                                if($list_biens['bien_id']){
                                        Bien_Helper::libererBien($list_biens['bien_id'],null,null);
                                }
                            }
                            }
                         $visite = new Visite();
                         $visite->setConnection('temp');

                         if($last_number==null){
                             $visite->description ='CREATION VISITE 1';
                         }else{
                            $visite->description ='CREATION VISITE '.($last_number+1);
                         }
                         $visite->origin_id=$origin_id;
                         $visite->user_id =  $userAuth->value('id');
                         $visite->prospect_id =$prospect->id;
                         $visite->projet_id = $request->selectedProjet;
                         $visite->commentaire = $request->commentaire;
                         $visite->interet = $request->interet;
                         //first visite bien==>show=1 et related_sho meme id du visite
                         $visite->show = 1;
                         if($visite->save()){
                            $visite->related_show_id = $visite->id;
                             if($visite->origin_id==null){
                                 $visite->origin_id = $visite->id;
                             }
                             if($visite->save()){
                                        //store relances et rdv et notifications
                                    if($visite->interet==InteretEnum::Réceptif->value){
                                        if ($request->date_relance != null) {
                                            NotificationHelper::storeNotification(
                                                '/visites/show/'.$visite->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                            );
                                            broadcast(new NotificationEvent($visite->id));

                                            $relance=new Relance_Rdv_visite();
                                            $relance->setConnection('temp');
                                            $relance->type=1;//relance
                                            $relance->mode_relance=$request->mode_relance;
                                            $relance->date_relance=$request->date_relance;
                                            $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                            $relance->user_id=$userAuth->value('id');
                                            $relance->visite_id=$visite->id;
                                            $relance->save();
                                        }
                                        if($request->rdv != null){
                                            NotificationHelper::storeNotification(
                                                '/visites/show/'.$visite->origin_id, $request->rdv,2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                            );
                                            broadcast(new NotificationEvent($visite->id));
                                            $rdv=new Relance_Rdv_visite();
                                            $rdv->setConnection('temp');
                                            $rdv->type=2;//rdv
                                            $rdv->rdv=$request->rdv;
                                            $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                            $rdv->user_id=$userAuth->value('id');
                                            $rdv->visite_id=$visite->id;
                                            $rdv->save();
                                        }
                                    }
                                    if ($visite->interet == InteretEnum::Perdu->value) {

                                        $freinRequest['visite_id']=$visite->getAttribute('id');
                                        $freinRequest['prix_min']=$request->prix_min;
                                        $freinRequest['prix_max']=$request->prix_max;
                                        $freinRequest['sup_min']=$request->sup_min;
                                        $freinRequest['sup_max']=$request->sup_max;
                                        $freinRequest['etat']=1;
                                        $freinRequest['avance']=$request->avance;
                                        $freinRequest['selectedTranches']=$request->tranches_id;
                                        $freinRequest['selectedEtages']=$request->etages;
                                        $freinRequest['selectedOrientations']=$request->orientations;
                                        $freinRequest['selectedTypologies']=$request->typologies;
                                        $freinRequest['selectedVues']=$request->vues;

                                        $freinController = new FreinController();
                                        $freinController->store(new StoreFreinRequest($freinRequest));
                                    }
                                    //traite/supprimer les relances rdv des old visite=>automatique
                                    $old_visites = Visite::on('temp')->where('origin_id', $visite->origin_id)->where('id', '!=',$visite->id)->orderBy('created_at', 'DESC')->get();
                                    if(count($old_visites)>0){
                                        foreach($old_visites as $old_visite){
                                            if($visite->interet==InteretEnum::Réceptif->value||$visite->interet==InteretEnum::Perdu->value){
                                                //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                                                if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value ) {
                                                    $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                                                    if ($oldBien->etat == 'Pré_Réservation' && $oldBien->historique_bien_pre_reserve->visite_id==$old_visite->id) {
                                                        Bien_Helper::libererBien($old_visite->bien_id,null,null);
                                                        if($old_visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                            $old_visite->statut=StatutVisiteEnum::Pré_Réservation_Perdu->value;
                                                        }elseif($old_visite->statut==StatutVisiteEnum::Vendu->value){
                                                            $old_visite->statut=StatutVisiteEnum::Réservation_Perdu->value;
                                                        }
                                                        $old_visite->save();
                                                    }
                                                }
                                            }

                                            //SUPPRIMER LES OLDS NOTIF
                                            $notif_old_relance=Notification::on('temp')->where(function ($query){
                                                $query->where('type',1)
                                                    ->orwhere('type',2);})
                                                ->where(function ($query_2) use($old_visite){
                                                        $query_2->where('visite_id',$old_visite->id);})
                                                ->get();
                                                if(($notif_old_relance->count())>0){
                                                foreach($notif_old_relance as $nt_r){
                                                    $nt_r->delete();
                                                }
                                                }
                                                /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                                    $old_relances_rdv=Relance_Rdv_visite::on('temp')->where('visite_id',$old_visite->id)->where('type_traitement',0)->get();
                                                    if(count($old_relances_rdv)>0){
                                                        foreach($old_relances_rdv as $old){
                                                            $old->type_traitement=2;//auto
                                                            $old->date_traitement=Carbon::now();
                                                            //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                                            if($old->visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                                if($visite->statut==StatutVisiteEnum::Vendu->value){
                                                                    $old->user_id_traite=$old_visite->user_id;
                                                                }
                                                                else{
                                                                    $old->user_id_traite=$userAuth->value('id');
                                                                }
                                                            }
                                                            else{
                                                                $old->user_id_traite=$userAuth->value('id');
                                                            }
                                                            $old->save();
                                                        }

                                                    }
                                        }
                                    }
                            }


                         }

             }else{
                   //store interesse
                   //list_interesse
                      $array_v_id=array();
                      $first_v_id=0;
                      $first_v_origin_id=0;
                    if ($request->list_bien_interesse) {

                     foreach ($request->list_bien_interesse as $key => $list_biens) {

                                    //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
                                    if($list_biens['bien_id']!=NULL&& $request->interet==InteretEnum::Intéressé->value){
                                        $bien_prop=Bien::on('temp')->findorfail($list_biens['bien_id']);
                                        if($bien_prop->etat!='DISPONIBLE'){
                                            if($bien_prop->etat=='ENCOURS_DE_PROPOSITION'){
                                                //test si le user connecte celui qui a  fait la proposition
                                                if($bien_prop->is_proposed->user_id!=Auth::guard('api')->user()->id){
                                                    return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
                                                }
                                            }else{
                                                //bien !=encours proposition ==>pre reserve
                                                return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est '.$bien_prop->etat], 333);
                                            }
                                        }
                                    }

                                 $visite = new Visite();
                                 $visite->setConnection('temp');
                                 if($last_number==null){
                                    $visite->description ='CREATION VISITE 1';
                                 }else{
                                   $visite->description ='CREATION VISITE '.intval($last_number)+1;
                                 }
                                 $visite->origin_id =$origin_id;
                                 $visite->user_id =  $userAuth->value('id');
                                 $visite->prospect_id =$prospect->id;
                                 $visite->projet_id = $request->selectedProjet;
                                 $visite->commentaire = $list_biens['commentaire'];
                                 $visite->interet = $request->interet;
                                 $visite->bien_id =$list_biens['bien_id'];
                                 $visite->statut= $list_biens['statut'];


                                 if($visite->save()){
                                    //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                                    array_push($array_v_id,$visite->id);
                                    //first visite bien==>show=1 et related_sho meme id du visite
                                    if($key==0){
                                        if($visite->origin_id==null){
                                            $visite->origin_id = $visite->id;
                                        }
                                        $visite->related_show_id=$visite->id;
                                        $first_v_id=$visite->id;
                                        $first_v_origin_id=$visite->origin_id;
                                        $visite->show=1;
                                    }
                                    else{
                                        $visite->related_show_id=$first_v_id;
                                        $visite->origin_id=$first_v_origin_id;
                                    }


                                     if($visite->save()){
                                            //STORE HISTORIQUE DU BIEN
                                            if($list_biens['bien_id']!=null){
                                                if($visite->statut==StatutVisiteEnum::Vendu->value){
                                                    HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                                                }
                                                else if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                    HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                                                }
                                            }
                                            //store relances et rdv et notifications
                                            if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                if ($list_biens['date_relance']!= null) {
                                                    NotificationHelper::storeNotification(
                                                        '/visites/show/'.$visite->origin_id,$list_biens['date_relance'],1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                                    );
                                                    broadcast(new NotificationEvent($visite->id));
                                                    $relance=new Relance_Rdv_visite();
                                                    $relance->setConnection('temp');
                                                    $relance->type=1;//relance
                                                    $relance->mode_relance=$list_biens['mode_relance'];
                                                    $relance->date_relance=$list_biens['date_relance'];
                                                    $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                                    $relance->user_id=$userAuth->value('id');
                                                    $relance->visite_id=$visite->id;
                                                    $relance->save();
                                                }
                                                if($list_biens['rdv']!= null){
                                                    NotificationHelper::storeNotification(
                                                        '/visites/show/'.$visite->origin_id,$list_biens['rdv'],2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                                    );
                                                    broadcast(new NotificationEvent($visite->id));
                                                    $rdv=new Relance_Rdv_visite();
                                                    $rdv->setConnection('temp');
                                                    $rdv->type=2;//rdv
                                                    $rdv->rdv=$list_biens['rdv'];
                                                    $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                                    $rdv->user_id=$userAuth->value('id');
                                                    $rdv->visite_id=$visite->id;
                                                    $rdv->save();
                                                }
                                            }


                                            //store code pre reserve to table ==>PreReservation
                                            if($visite->interet==InteretEnum::Intéressé->value && $visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                $bien_c=new BienController();
                                                $bien_c->prereserverBien($visite->bien_id,$visite->id,null);

                                            }
                                            elseif ($visite->interet == InteretEnum::Intéressé->value && $visite->statut ==StatutVisiteEnum::Vendu->value) {
                                                //set visite pre reserve

                                                    $reservationController = new ReservationController();
                                                    $reservationRequest = new StoreReservationRequest();

                                                    $dataReservation = [
                                                        'nb_acquereurs'=>1,
                                                        'code_reservation'=>$list_biens['code_reservation'],
                                                        'prix'=>$list_biens['prix'],
                                                        'mode_financement'=>$list_biens['mode_financement'],
                                                        'date_reservation'=>$list_biens['date_reservation'],
                                                        'commentaire'=>$list_biens['commentaire_res'],
                                                        'visite_id'=>$visite->id,
                                                        'prix_remise'=>$list_biens['prix_remise'],
                                                        'prix_forfetaire'=>$list_biens['prix_forfetaire'],
                                                        'bien_id'=>$list_biens['bien_id'],
                                                        'projet_id'=>$request->selectedProjet,
                                                        'verifierPourcentages'=>true,
                                                        'origin'=>'visite',
                                                        'cin'=>$request->cin,
                                                        'nom'=>$request->nom,
                                                        'prenom'=>$request->prenom,
                                                        'telephone_num1'=>$request->telephone,
                                                        'telephone_num2'=>$request->telephone_num2,
                                                        'notifie'=>$prospect->notifie,
                                                        'prospect_id'=>$prospect->id,
                                                        'civilite'=>'Mr',
                                                        'type_client'=>1,
                                                        'situation_familliale'=>1,
                                                        'sr' => $list_biens['sr'],
                                                        'type_encaissement' => 1,
                                                        'avance' => $list_biens['avance_res'],
                                                        'mode_paiement' => $list_biens['mode_paiement'],
                                                        'numero_paiement' => $list_biens['numero_paiement'],
                                                        'date_reglement' => $list_biens['date_reglement'],
                                                        'echeance' => $list_biens['echeance'],
                                                        'banque_id' =>  $list_biens['banque_id'],
                                                        'commentaireAvance' =>$list_biens['commentaireAvance'],
                                                        'num_remise' => $list_biens['num_remise'],
                                                        'date_encaissement' => $list_biens['date_encaissement'],

                                                    ];
                                                    $reservationRequest->merge($dataReservation);
                                                    $reservationController->store($reservationRequest);


                                            }
                                     }
                                 }








                     }
                     }


                     //list des bien transfere vendu
                if ($request->list_bien_transfere_vendu!=null) {
                    //list des biens interesse
                    foreach ($request->list_bien_transfere_vendu as $key => $list_biens) {
                        $visite = new Visite();
                        $visite->setConnection('temp');
                        if($last_number==null){
                           $visite->description ='CREATION VISITE 1';
                        }else{
                          $visite->description ='CREATION VISITE '.intval($last_number)+1;
                        }
                        $visite->origin_id =$origin_id;
                        $visite->user_id =  $userAuth->value('id');
                        $visite->prospect_id =$prospect->id;
                        $visite->projet_id = $request->selectedProjet;
                        $visite->commentaire = $list_biens['commentaire'];
                        $visite->interet = $request->interet;
                        $visite->bien_id =$list_biens['bien_id'];
                        $visite->statut= $list_biens['statut'];


                        if($visite->save()){
                           //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                           array_push($array_v_id,$visite->id);
                           //first visite bien==>show=1 et related_sho meme id du visite
                           if($request->list_bien_interesse==null){
                                if($key==0){
                                    if($visite->origin_id==null){
                                        $visite->origin_id = $visite->id;
                                    }
                                    $visite->related_show_id=$visite->id;
                                    $first_v_id=$visite->id;
                                    $first_v_origin_id=$visite->origin_id;
                                    $visite->show=1;
                                }
                                else{
                                    $visite->related_show_id=$first_v_id;
                                    $visite->origin_id=$first_v_origin_id;
                                }
                           }else{
                            $visite->related_show_id=$first_v_id;
                            $visite->origin_id=$first_v_origin_id;
                           }



                            if($visite->save()){
                                   //STORE HISTORIQUE DU BIEN
                                   if($list_biens['bien_id']!=null){
                                       if($visite->statut==StatutVisiteEnum::Vendu->value){
                                           HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                                       }
                                       else if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                           HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                                       }
                                   }
                                   //store relances et rdv et notifications
                                   if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                       if ($list_biens['date_relance']!= null) {
                                           NotificationHelper::storeNotification(
                                               '/visites/show/'.$visite->origin_id,$list_biens['date_relance'],1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                           );
                                           broadcast(new NotificationEvent($visite->id));
                                           $relance=new Relance_Rdv_visite();
                                           $relance->setConnection('temp');
                                           $relance->type=1;//relance
                                           $relance->mode_relance=$list_biens['mode_relance'];
                                           $relance->date_relance=$list_biens['date_relance'];
                                           $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                           $relance->user_id=$userAuth->value('id');
                                           $relance->visite_id=$visite->id;
                                           $relance->save();
                                       }
                                       if($list_biens['rdv']!= null){
                                           NotificationHelper::storeNotification(
                                               '/visites/show/'.$visite->origin_id,$list_biens['rdv'],2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                                           );
                                           broadcast(new NotificationEvent($visite->id));
                                           $rdv=new Relance_Rdv_visite();
                                           $rdv->setConnection('temp');
                                           $rdv->type=2;//rdv
                                           $rdv->rdv=$list_biens['rdv'];
                                           $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                           $rdv->user_id=$userAuth->value('id');
                                           $rdv->visite_id=$visite->id;
                                           $rdv->save();
                                       }
                                   }


                                   //store code pre reserve to table ==>PreReservation
                                   if($visite->interet==InteretEnum::Intéressé->value && $visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                       $bien_c=new BienController();
                                       $bien_c->prereserverBien($visite->bien_id,$visite->id,null);

                                   }
                                   elseif ($visite->interet == InteretEnum::Intéressé->value && $visite->statut ==StatutVisiteEnum::Vendu->value) {
                                       //set visite pre reserve

                                           $reservationController = new ReservationController();
                                           $reservationRequest = new StoreReservationRequest();

                                           $dataReservation = [
                                               'nb_acquereurs'=>1,
                                               'code_reservation'=>$list_biens['code_reservation'],
                                               'prix'=>$list_biens['prix'],
                                               'mode_financement'=>$list_biens['mode_financement'],
                                               'date_reservation'=>$list_biens['date_reservation'],
                                               'commentaire'=>$list_biens['commentaire_res'],
                                               'visite_id'=>$visite->id,
                                               'prix_remise'=>$list_biens['prix_remise'],
                                               'prix_forfetaire'=>$list_biens['prix_forfetaire'],
                                               'bien_id'=>$list_biens['bien_id'],
                                               'projet_id'=>$request->selectedProjet,
                                               'verifierPourcentages'=>true,
                                               'origin'=>'visite',
                                               'cin'=>$request->cin,
                                               'nom'=>$request->nom,
                                               'prenom'=>$request->prenom,
                                               'telephone_num1'=>$request->telephone,
                                               'telephone_num2'=>$request->telephone_num2,
                                               'notifie'=>$prospect->notifie,
                                               'prospect_id'=>$prospect->id,
                                               'civilite'=>'Mr',
                                               'type_client'=>1,
                                               'situation_familliale'=>1,
                                               'sr' => $list_biens['sr'],
                                               'type_encaissement' => 1,
                                               'avance' => $list_biens['avance_res'],
                                               'mode_paiement' => $list_biens['mode_paiement'],
                                               'numero_paiement' => $list_biens['numero_paiement'],
                                               'date_reglement' => $list_biens['date_reglement'],
                                               'echeance' => $list_biens['echeance'],
                                               'banque_id' =>  $list_biens['banque_id'],
                                               'commentaireAvance' =>$list_biens['commentaireAvance'],
                                               'num_remise' => $list_biens['num_remise'],
                                               'date_encaissement' => $list_biens['date_encaissement'],

                                           ];
                                           $reservationRequest->merge($dataReservation);
                                           $reservationController->store($reservationRequest);


                                   }

                                    //set old visite to pre _reservation_vendu

                                    $old_visite_transfere=Visite::on('temp')->findorfail($list_biens['visite_id']);
                                    $old_visite_transfere->statut=StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $old_visite_transfere->save();
                            }
                        }
                    }
                }

                      //traite/supprimer les relances rdv des old visite=>automatique
                      $old_visites = Visite::on('temp')->where('origin_id', $origin_id)->whereNotIn('id', $array_v_id)->orderBy('created_at', 'DESC')->get();
                      if(count($old_visites)>0){
                          foreach($old_visites as $old_visite){

                              //SUPPRIMER LES OLDS NOTIF
                              $notif_old_relance=Notification::on('temp')->where(function ($query){
                                  $query->where('type',1)
                                      ->orwhere('type',2);})
                                  ->where(function ($query_2) use($old_visite){
                                          $query_2->where('visite_id',$old_visite->id);})
                                  ->get();
                                  if(($notif_old_relance->count())>0){
                                  foreach($notif_old_relance as $nt_r){
                                      $nt_r->delete();
                                  }
                                  }
                                  /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                      $old_relances_rdv=Relance_Rdv_visite::on('temp')->where('visite_id',$old_visite->id)->where('type_traitement',0)->get();
                                      if(count($old_relances_rdv)>0){
                                          foreach($old_relances_rdv as $old){
                                              $old->type_traitement=2;//auto
                                              $old->date_traitement=Carbon::now();
                                              //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                              if($old->visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                                  if($visite->statut==StatutVisiteEnum::Vendu->value){
                                                      $old->user_id_traite=$old_visite->user_id;
                                                  }
                                                  else{
                                                      $old->user_id_traite=$userAuth->value('id');
                                                  }
                                              }
                                              else{
                                                  $old->user_id_traite=$userAuth->value('id');
                                              }
                                              $old->save();
                                          }

                                      }
                          }
                      }


             }
              }else{
                     return response()->json(['error' => 'Unauthorized'], 401);
                 }


     }

    /**
     * Display the specified resource.
     */
    public function get_propriete_bien_concat($id){
        DatabaseHelper::Config();
        $b_pr=Bien::on('temp')->findorfail($id);

            //tranches bloc w immeuble
            if($b_pr->tranche_id!=null && $b_pr->bloc_id!=null && $b_pr->immeuble_id!=null){
                $propriete=$propriete=$b_pr->tranche->nom.'-'.$b_pr->bloc->nom.'-'.$b_pr->immeuble->nom.'-'.$b_pr->propriete_dite_bien;

              }
            //tranche bloc
           elseif($b_pr->tranche_id!=null && $b_pr->bloc_id!=null && $b_pr->immeuble_id==null){
            $propriete=$b_pr->tranche->nom.'-'.$b_pr->bloc->nom.'-'.$b_pr->propriete_dite_bien;
            }
             //tranche immeuble
           elseif($b_pr->tranche_id!=null && $b_pr->bloc_id==null && $b_pr->immeuble_id!=null){
            $propriete=$b_pr->tranche->nom.'-'.$b_pr->immeuble->nom.'-'.$b_pr->propriete_dite_bien;
            }
           //bloc immeuble
           elseif ($b_pr->tranche_id==null && $b_pr->bloc_id!=null && $b_pr->immeuble_id!=null){
            $propriete=$b_pr->bloc->nom.'-'.$b_pr->immeuble->nom.'-'.$b_pr->propriete_dite_bien;
             }
            //bloc
           elseif($b_pr->tranche_id==null && $b_pr->bloc_id!=null && $b_pr->immeuble_id==null){
            $propriete=$b_pr->bloc->nom.'-'.$b_pr->propriete_dite_bien;
             }
           //immeuble
           elseif($b_pr->tranche_id==null && $b_pr->bloc_id==null && $b_pr->immeuble_id!=null){
            $propriete=$b_pr->immeuble->nom.'-'.$b_pr->propriete_dite_bien;
               }
            //tranche
            elseif($b_pr->tranche_id!=null && $b_pr->bloc_id==null && $b_pr->immeuble_id==null){
                $propriete=$b_pr->tranche->nom.'-'.$b_pr->propriete_dite_bien;
                }

            return response()->json($propriete);


    }

    public function relance_rdv_by_visite($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
                $histo=Visite::on('temp')->with('historique_relances_rdvs')->where('origin_id',$id)->where('etat',1)->orderby('created_at', 'DESC')->get();
            return response()->json(['histo'=>$histo], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function show($id)
    {

        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();


            $visite = Visite::on('temp')->with('relance_relation','rdv_relation','reservation')->findOrfail($id);
            $frein=new FreinController();
                if($visite->interet==InteretEnum::Perdu->value) {
                    $visite['frein']=$frein->searchFreinByVisiteId($visite->id,'without_row_deleted');
                }

                $relatedVisites=Visite::on('temp')->with('pre_reservation_visite','relance_relation','rdv_relation','reservation')->where('origin_id',$visite->id)->where('etat',1)->orderby('created_at', 'DESC')->get();

                foreach ($relatedVisites as $relatedVisite) {
                    if ($relatedVisite->interet == InteretEnum::Perdu->value) {
                        $frein_v = $frein->searchFreinByVisiteId($relatedVisite->id,'without_row_deleted');
                        $relatedVisite['frein'] = $frein_v;
                    }
                }
                $relatedVisites_show=Visite::on('temp')->with('pre_reservation_visite','relance_relation','rdv_relation','reservation')->where('origin_id',$visite->id)->where('etat',1)->where('show',1)->orderby('created_at', 'DESC')->get();
                //get nom propriete _dite_bien concat utilisé dans edit visite
                $propriete=null;
                if($visite->bien_id!=null){
                    $propriete= $this->get_propriete_bien_concat($visite->bien_id);
                }

            return response()->json(['visite' => $visite,'propriete_dite_bien' => $propriete,'visites'=>$relatedVisites,'visites_show'=>$relatedVisites_show], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

 public static function traiter_relance_rdv_visite($id,UpdateDate_relance_Rdv $request)
    {
        if(RoleHelper::ACSup()) {
           Config::set('broadcasting.default', 'pusher_3');
           // Config::set('broadcasting.default', 'pusher_5');
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $relance = Relance_Rdv_visite::on('temp')->findOrFail($id);

            //if date !=null (nouvelle relance )
            if($request->date!=null){

                        $visite_id=$relance->visite_id;
                        $prospect_id=$relance->visite->prospect_id;
                        $old_mode_relance=$relance->mode_relance;
                        $relance->type_traitement=3;//demi traitement
                        $relance->date_traitement=Carbon::now();
                        $relance->commentaire=$request->commentaire;
                        $relance->user_id_traite=$userAuth->value('id');
                        if($relance->save()){
                            //delete old notificcation
                            $notif_exist_relance=Notification::on('temp')->where('type',$relance->type)->where('visite_id',$visite_id)->get();
                            if(count($notif_exist_relance)>0){
                                foreach($notif_exist_relance as $nt){
                                    $nt->delete();
                                }
                            }
                                //store new relance
                            $new_relance=new Relance_Rdv_visite();
                            $new_relance->setConnection('temp');
                            if($relance->type==1){
                                $new_relance->type=1;//relance
                                $new_relance->mode_relance=$old_mode_relance;
                                $new_relance->date_relance=$request->date;

                            }
                            else{
                                //rdv
                                $new_relance->type=2;//rdv
                                $new_relance->rdv=$request->date;
                            }
                            $new_relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                            $new_relance->user_id=$userAuth->value('id');;
                            $new_relance->visite_id=$visite_id;
                            $new_relance->save();

                            if($relance->type==1){
                            //store new notification
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$new_relance->visite->origin_id, $request->date,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite_id,$prospect_id,$new_relance->visite->projet_id,null,null
                            );
                            broadcast(new NotificationEvent($new_relance->id));
                           // broadcast(new NotifMenuEvent(10));

                            }
                            else{
                                //store new notification
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$new_relance->visite->origin_id, $request->date,2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite_id,$prospect_id,$new_relance->visite->projet_id,null,null
                            );
                            broadcast(new NotificationEvent($new_relance->id));
                          //  broadcast(new NotifMenuEvent(10));

                            }
                            return response()->json(['message' => $new_relance], 200);
                        }
            }else{
                // si date ==null la relance /rdv  est traité


                        $relance->type_traitement=1;//manuelle
                        $relance->commentaire=$request->commentaire;
                        $relance->date_traitement=Carbon::now();
                        $relance->user_id_traite=$userAuth->value('id');
                        if($relance->save()){
                            //delete old notificcation
                            $notif_exist_relance=Notification::on('temp')->where('type',$relance->type)->where('visite_id',$relance->visite_id)->get();
                            if(count($notif_exist_relance)>0){
                                foreach($notif_exist_relance as $nt){
                                    $nt->delete();
                                }
                            }
                           // broadcast(new NotificationEvent($relance->id));

                            return response()->json(['message' => 'Validé avec succès.'], 200);
                        }

            }


       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Visite $visite)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVisiteRequest $request,$id)
    {


        $user = Auth::user();
        if(RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            Config::set('broadcasting.default', 'pusher_3');
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $old_visite = Visite::on('temp')->findOrFail($id);
            $old_description=$old_visite->description;

             //test si le user connecte celui qui a  fait la proposition
             if($request->bien_id!=NULL && $request->interet==InteretEnum::Intéressé->value){
                $bien_prop=Bien::on('temp')->findorfail($request->bien_id);

                    if($bien_prop->etat=='ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id!=Auth::guard('api')->user()->id){
                        return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
                    }
            }

                //copier ancien visite et mettre new visite
                $visite = $old_visite->replicate();
                $visite->setConnection('temp');
                if($visite->save()){
                    $old_visite->etat=0;
                    $old_visite->save();
                }
                //calcul nb visite
                /*$description=null;
                $visites_count=Visite::on('temp')->where('origin_id',$visite->origin_id)->where('etat',1)->orderBy('created_at', 'DESC')->count();
                if($visites_count>0){
                    $description='MODIFICATION VISITE '.$visites_count;
                }*/

            //recupere le prospect //modifier info
            $prospect= Prospect::on('temp')->findorfail($visite->prospect_id);
            if($request->cin!=null){
                $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$visite->prospect_id)->count();
                if($cin_exist==0){
                    $prospect->cin=$request->cin;
                    $prospect->email=$request->email;
                }
            }
            $prospect->nom=$request->nom;
            $prospect->prenom=$request->prenom;
            $prospect->telephone=$request->telephone;
            $prospect->telephone_num2=$request->telephone_num2;
            $prospect->source=$request->source_id;
            if ($request->source_txt == 'PARTENAIRE') {
                $prospect->partenaire_id = $request->partenaire_id;
            }
            $prospect->notifie = $request->notifie;
            $prospect->save();



             /****Libérer le bien de l'ancienne visite**/
                    //chngement de bien
            if($request->interet==InteretEnum::Intéressé->value){
                if (($visite->statut == StatutVisiteEnum::Pré_Réservation->value|| $visite->statut == StatutVisiteEnum::Vendu->value) && $visite->bien_id!=null) {
                    if($visite->bien_id!=$request->bien_id){
                        $oldBien = Bien::on('temp')->find($visite->bien_id);
                            if ($oldBien->etat == 'Pré_Réservation' || $oldBien->etat == 'Vendu') {
                                Bien_Helper::libererBien($visite->bien_id,null,null);

                            }
                    }
                }

            }

                    //changement d'interet (Réceptif ou Perdu)
            if($visite->bien_id!=null && $request->interet != InteretEnum::Intéressé->value){
                Bien_Helper::libererBien($visite->bien_id,null,null);

            }

            $visite->user_id = $userAuth->value('id');
            $visite->commentaire = $request->commentaire;
            if (str_contains($old_description, 'CREATION')==true) {
                $visite->description = str_replace('CREATION', 'MODIFICATION', $old_description);
            }else{
                $visite->description = $old_description;

            }
            $visite->old_v_id = $id;
            $visite->interet = $request->interet;

            //Intéressé
            if($request->interet==InteretEnum::Intéressé->value){
                $visite->bien_id = $request->bien_id;
                $visite->statut= $request->statut;
            }

            elseif($request->interet==InteretEnum::Réceptif->value){
                $visite->statut= null;
                $visite->bien_id =null;
            }
            elseif($request->interet==InteretEnum::Perdu->value){
                $visite->statut= null;
                $visite->bien_id =null;
            }
            $visite->save();


            /** store relances et rdv **/
            //STORE HISTORIQUE DU BIEN
            if($visite->bien_id!=null){
                if($visite->statut==StatutVisiteEnum::Vendu->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
                else if($visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
            }
            /**si ancien Perdu avec notif des bien dispo on supprime la notif** pas encours */
            //supprimer ancien notif relance rdv
            $notif_exist_relance_rdv=Notification::on('temp')->whereIN('type',[1,2])->where('visite_id',$old_visite->id)->get();
            if(count($notif_exist_relance_rdv)>0){
                foreach($notif_exist_relance_rdv as $nt){
                    $nt->delete();
                }
            }

             //store relances et rdv et notifications
             if($visite->statut==StatutVisiteEnum::Pré_Réservation->value ||$visite->interet==InteretEnum::Réceptif->value){
                if ($request->date_relance != null) {
                    if($old_visite->relance_relation!=null){
                        $old_visite->relance_relation->delete();
                    }
                    NotificationHelper::storeNotification(
                        '/visites/show/'.$visite->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->id,$visite->prospect_id,$visite->projet_id,null,null
                    );
                    broadcast(new NotificationEvent($visite->id));
                    $relance=new Relance_Rdv_visite();
                    $relance->setConnection('temp');
                    $relance->type=1;//relance
                    $relance->mode_relance=$request->mode_relance;
                    $relance->date_relance=$request->date_relance;
                    $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                    $relance->user_id=$userAuth->value('id');
                    $relance->visite_id=$visite->id;
                    $relance->save();
                }
                if($request->rdv != null){
                    if($old_visite->rdv_relation!=null){
                        $old_visite->rdv_relation->delete();
                    }
                    NotificationHelper::storeNotification(
                        '/visites/show/'.$visite->origin_id, $request->rdv,2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                    );
                    broadcast(new NotificationEvent($visite->id));
                    $rdv=new Relance_Rdv_visite();
                    $rdv->setConnection('temp');
                    $rdv->type=2;//rdv
                    $rdv->rdv=$request->rdv;
                    $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                    $rdv->user_id=$userAuth->value('id');
                    $rdv->visite_id=$visite->id;
                    $rdv->save();
                }
            }
            //store code pre reserve to table ==>PreReservation
            if($old_visite->statut!=StatutVisiteEnum::Pré_Réservation->value ){
                if($visite->interet==InteretEnum::Intéressé->value && $visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                    $bien_c=new BienController();
                    $bien_c->prereserverBien($visite->bien_id,$visite->id,null);
                }
            }

            //if($old_visite->statut!=StatutVisiteEnum::Vendu->value ){
            if ($visite->interet == InteretEnum::Intéressé->value && $visite->statut ==StatutVisiteEnum::Vendu->value) {

                    $reservationController = new ReservationController();
                    $reservationRequest = new StoreReservationRequest();

                    $numberToWords = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
                    $prix_remise_lettre = $numberToWords->format($request->prix_remise);
                    $prix_forfetaire_lettre = $numberToWords->format($request->prix_forfetaire);

                    $dataReservation = [
                        'nb_acquereurs'=>1,
                        'code_reservation'=>$request->code_reservation,
                        'prix'=>$request->prix,
                        'mode_financement'=>$request->mode_financement,
                        'date_reservation'=>$request->date_reservation,
                        'commentaire'=>$request->commentaire_res,
                        'visite_id'=>$visite->id,
                        'prix_remise'=>$request->prix_remise,
                        'prix_remise_lettre'=>$request->prix_remise_lettre,
                        'prix_forfetaire'=>$request->prix_forfetaire,
                        'prix_forfetaire_lettre'=>$request->prix_forfetaire_lettre,
                        'bien_id'=>$request->bien_id,
                        'projet_id'=>$visite->projet_id,
                        'verifierPourcentages'=>true,
                        'origin'=>'visite',
                        'cin'=>$prospect->cin,
                        'nom'=>$prospect->nom,
                        'prenom'=>$prospect->prenom,
                        'telephone_num1'=>$prospect->telephone,
                        'telephone_num2'=>$prospect->telephone_num2,
                        'notifie'=>$prospect->notifie,
                        'prospect_id'=>$prospect->id,
                        'civilite'=>'Mr',
                        'type_client'=>1,
                        'situation_familliale'=>1,
                        'sr' => $request->sr,
                        'check_montant' => $request->check_montant,
                        'type_encaissement' => 1,
                        'avance' => $request->avance_res,
                        'mode_paiement' => $request->mode_paiement,
                        'numero_paiement' => $request->numero_paiement,
                        'date_reglement' => $request->date_reglement,
                        'echeance' => $request->echeance,
                        'banque_id' => $request->banque_id,
                        'commentaireAvance' => $request->commentaireAvance,
                        'num_remise' => $request->num_remise,
                        'date_encaissement' => $request->date_encaissement,

                    ];
                    $reservationRequest->merge($dataReservation);
                    $reservationController->store($reservationRequest);


            }
            // }

            if ($visite->interet == InteretEnum::Perdu->value) {
                $frein_id=Frein::on('temp')->where('visite_id', $visite->id)->get();
                $freinRequest['prix_min']=$request->prix_min;
                $freinRequest['frein']=$request->frein;
                $freinRequest['prix_max']=$request->prix_max;
                $freinRequest['sup_min']=$request->sup_min;
                $freinRequest['sup_max']=$request->sup_max;
                $freinRequest['etat']=1;
                $freinRequest['avance']=$request->avance;
                $freinRequest['selectedTranches']=$request->tranches_id;
                $freinRequest['selectedEtages']=$request->etages;
                $freinRequest['selectedOrientations']=$request->orientations;
                $freinRequest['selectedTypologies']=$request->typologies;
                $freinRequest['selectedVues']=$request->vues;
                $freinController = new FreinController();
                if(!$frein_id->isEmpty()){
                    $freinController->update(new UpdateFreinRequest($freinRequest),$frein_id->value('id'));
                }
                else{

                    $freinRequest['visite_id']=$visite->id;
                    $freinController->store(new StoreFreinRequest($freinRequest));
                }
            }
            else {

                $frein=Frein::on('temp')->where('visite_id', $id)->get();
                if(!$frein->isEmpty()){
                    $freinController=new FreinController();
                    $freinController->destroy($frein->value('id'));
                }
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        DatabaseHelper::Config();
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $visite=Visite::on('temp')->findOrFail($id);
            if($visite->interet== InteretEnum::Intéressé->value){
                if($visite->bien_id){
                    Bien_Helper::libererBien($visite->bien_id,null,null);
                }
            }
            if($visite->interet == InteretEnum::Perdu->name){
                $frein=Frein::on('temp')->where('visite_id',$visite->id)->get();
                $freinController= new FreinController();
                $freinController->destroy($frein->id);
            }
            //relance_rdv
            $relance_rdv=Relance_Rdv_visite::on('temp')->where('visite_id',$id)->get();
            if(count($relance_rdv)>0){
                foreach($relance_rdv as $r){
                    $r->delete();
                }
            }
            //notifications
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('visite',$id);

            //set show=1 to first related visite
            if($visite->show==1){
                $related_visites=Visite::on('temp')->where('show',null)->where('related_show_id',$visite->related_show_id)->orderBy('created_at', 'ASC')->get();
                if(count($related_visites)>0){
                    foreach($related_visites as $key=>$rel_v){
                        if($key==0){
                            $rel_v->show=1;
                            $rel_v->save();
                        }
                    }
                }
            }

            if($visite->delete()){
                return response()->json(['message'=>'Visite supprimée avec succès.'],200);
            }
            else return response()->json(['error'=>"La visite n'a pas été supprimée."],404);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }
    //store n visite
    public function store_n_visite($id,Store_n_VisiteRequest $request){

        DatabaseHelper::Config();
        Config::set('broadcasting.default', 'pusher_3');
        $originalVisite=Visite::on('temp')->find($id);
        if (!$originalVisite){ return response()->json(['error'=>"L'original de la visite n'a pas été trouvé."]);}

        $user = Auth::user();

        if(RoleHelper::ACSup()){
            //si interet on store cin du client
             $prospect= Prospect::on('temp')->findorfail($originalVisite->prospect_id);
             if($request->cin!=null){
                 $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$originalVisite->prospect_id)->count();
                 if($cin_exist==0){
                     $prospect->cin=$request->cin;
                     $prospect->save();
                 }
             }
             $last_number=null;
                $visite_exist=Visite::on('temp')->where('origin_id',$id)->where('etat',1)->orderBy('created_at', 'DESC')->first();
                if($visite_exist!=null){
                    $last_number=intval(explode(" ",$visite_exist->description)[2]);
                }
             //receptif ou perdu
            if($request->interet==InteretEnum::Réceptif->value||$request->interet==InteretEnum::Perdu->value){
                   //rendre bien disponible si interet!=Intéressé ==>Réceptif ou Perdu
                   if ($request->list_bien_interesse) {
                    foreach ($request->list_bien_interesse as $key => $list_biens) {
                        if($list_biens['bien_id']){
                                Bien_Helper::libererBien($list_biens['bien_id'],null,null);
                        }
                    }
                    }
                    if ($request->list_bien_transfere_vendu) {
                        foreach ($request->list_bien_transfere_vendu as $key => $list_biens_ve) {
                            if($list_biens['bien_id']){
                                    Bien_Helper::libererBien($list_biens_ve['bien_id'],null,null);
                            }
                        }
                    }
                //store n visite
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $newVisit=new Visite();
                $newVisit->setConnection('temp');
                $newVisit->user_id=$userAuth->value('id');
                $newVisit->prospect_id=$originalVisite->prospect_id;
                $newVisit->projet_id = $request->selectedProjet;
                $newVisit->description='CREATION VISITE '.($last_number+1);
                $newVisit->origin_id=$id;
                $newVisit->commentaire = $request->commentaire;
                $newVisit->interet = $request->interet;
                $newVisit->show = 1;
                if($newVisit->save()){
                    $newVisit->related_show_id = $newVisit->id;
                    //store relances et rdv et notifications du new visite
                    if($newVisit->interet==InteretEnum::Réceptif->value){
                        if ($request->date_relance != null) {
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$newVisit->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$newVisit->getAttribute('id'),$newVisit->prospect_id,$newVisit->projet_id,null,null
                            );
                            broadcast(new NotificationEvent($newVisit->id));


                            $relance=new Relance_Rdv_visite();
                            $relance->setConnection('temp');
                            $relance->type=1;//relance
                            $relance->mode_relance=$request->mode_relance;
                            $relance->date_relance=$request->date_relance;
                            $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                            $relance->user_id=$userAuth->value('id') ;
                            $relance->visite_id=$newVisit->id;
                            $relance->save();
                        }
                        if($request->rdv != null){
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$newVisit->origin_id, $request->rdv,2,'RDV VISITE',Auth::guard('api')->user()->id,null,$newVisit->id,$newVisit->prospect_id,$newVisit->projet_id,null,null
                            );
                            broadcast(new NotificationEvent($newVisit->id));

                            $rdv=new Relance_Rdv_visite();
                            $rdv->setConnection('temp');
                            $rdv->type=2;//rdv
                            $rdv->rdv=$request->rdv;
                            $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                            $rdv->user_id=$userAuth->value('id');
                            $rdv->visite_id=$newVisit->id;
                            $rdv->save();
                        }
                    }
                    $old_visites = Visite::on('temp')->where('origin_id', $id)->where('id','!=',$newVisit->id)->orderBy('created_at', 'DESC')->get();
                    if(count($old_visites)>0){
                        foreach($old_visites as $old_visite){
                            //si le n visite receptif || perdu
                            if($newVisit->interet==InteretEnum::Réceptif->value||$newVisit->interet==InteretEnum::Perdu->value){
                                //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                                if ($old_visite->statut == StatutVisiteEnum::Pré_Réservation->value ) {
                                    $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                                    if ($oldBien->etat == 'Pré_Réservation' && $oldBien->historique_bien_pre_reserve->visite_id==$old_visite->id) {
                                        Bien_Helper::libererBien($old_visite->bien_id,null,null);
                                        if($old_visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                            $old_visite->statut=StatutVisiteEnum::Pré_Réservation_Perdu->value;
                                        }elseif($old_visite->statut==StatutVisiteEnum::Vendu->value){
                                            $old_visite->statut=StatutVisiteEnum::Réservation_Perdu->value;
                                        }
                                        $old_visite->save();
                                    }
                                }
                            }

                            //SUPPRIMER LES OLDS NOTIF
                             $notif_old_relance=Notification::on('temp')->where(function ($query){
                                $query->where('type',1)
                                    ->orwhere('type',2);})
                                ->where(function ($query_2) use($old_visite){
                                        $query_2->where('visite_id',$old_visite->id);})
                                ->get();
                                if(($notif_old_relance->count())>0){
                                foreach($notif_old_relance as $nt_r){
                                    $nt_r->delete();
                                }
                                }
                            /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                $old_relances_rdv=Relance_Rdv_visite::on('temp')->where('visite_id',$old_visite->id)->where('type_traitement',0)->get();
                                if(count($old_relances_rdv)>0){
                                    foreach($old_relances_rdv as $old){
                                        $old->type_traitement=2;//auto
                                        $old->date_traitement=Carbon::now();
                                        //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                        if($old->visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                            if($newVisit->statut==StatutVisiteEnum::Vendu->value){
                                                $old->user_id_traite=$old_visite->user_id;
                                            }
                                            else{
                                                $old->user_id_traite=$userAuth->value('id');
                                            }
                                        }
                                        else{
                                            $old->user_id_traite=$userAuth->value('id');
                                        }
                                        $old->save();
                                    }

                                }
                          }
                    }
                    if ($newVisit->interet == InteretEnum::Perdu->value) {
                        $freinRequest['visite_id']=$newVisit->getAttribute('id');
                        $freinRequest['prix_min']=$request->prix_min;
                        $freinRequest['prix_max']=$request->prix_max;
                        $freinRequest['sup_min']=$request->sup_min;
                        $freinRequest['sup_max']=$request->sup_max;
                        $freinRequest['etat']=1;
                        $freinRequest['avance']=$request->avance;
                        $freinRequest['selectedTranches']=$request->tranches_id;
                        $freinRequest['selectedEtages']=$request->etages;
                        $freinRequest['selectedOrientations']=$request->orientations;
                        $freinRequest['selectedTypologies']=$request->typologies;
                        $freinRequest['selectedVues']=$request->vues;
                        $freinController = new FreinController();
                        $freinController->store(new StoreFreinRequest($freinRequest));
                    }
                }
            }
            else{
                //interesse
                $first_v_id=0;
                $array_v_id=array();

                if ($request->list_bien_interesse!=null) {
                 //list des biens interesse
                foreach ($request->list_bien_interesse as $key => $list_biens) {
                    //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
                    if($list_biens['bien_id']!=NULL&& $request->interet==InteretEnum::Intéressé->value){
                        $bien_prop=Bien::on('temp')->findorfail($list_biens['bien_id']);
                        if($bien_prop->etat!='DISPONIBLE'){
                            if($bien_prop->etat=='ENCOURS_DE_PROPOSITION'){
                                //test si le user connecte celui qui a  fait la proposition
                                if($bien_prop->is_proposed->user_id!=Auth::guard('api')->user()->id){
                                    return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
                                }
                            }else{
                                //bien !=encours proposition ==>pre reserve
                                return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est '.$bien_prop->etat], 333);
                            }
                        }
                    }
                    //store n visite
                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                    $newVisit=new Visite();
                    $newVisit->setConnection('temp');
                    $newVisit->user_id=$userAuth->value('id');
                    $newVisit->prospect_id=$originalVisite->prospect_id;
                    $newVisit->projet_id = $request->selectedProjet;
                    $newVisit->description='CREATION VISITE '.($last_number+1);
                    $newVisit->origin_id=$id;
                    $newVisit->commentaire = $request->commentaire;
                    $newVisit->interet = $request->interet;
                    $newVisit->bien_id =$list_biens['bien_id'];
                    $newVisit->statut= $list_biens['statut'];


                    if($newVisit->save()){
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id,$newVisit->id);
                            //first visite bien==>show=1 et related_sho meme id du visite
                                if($key==0){
                                    $newVisit->related_show_id=$newVisit->id;
                                    $first_v_id=$newVisit->id;
                                    $newVisit->show=1;
                                }
                                else{
                                    $newVisit->related_show_id=$first_v_id;
                                }

                        if($newVisit->save()){
                                //STORE HISTORIQUE DU BIEN
                                if($list_biens['bien_id']!=null){
                                    if($newVisit->statut==StatutVisiteEnum::Vendu->value){
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$newVisit->bien_id, Auth::guard('api')->user()->id,$newVisit->id,NULL);
                                    }
                                    else if($newVisit->statut==StatutVisiteEnum::Pré_Réservation->value){
                                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $newVisit->bien_id, Auth::guard('api')->user()->id,$newVisit->id,NULL);
                                    }
                                }
                                //store relances et rdv et notifications
                                if($newVisit->statut==StatutVisiteEnum::Pré_Réservation->value){
                                    if ($list_biens['date_relance']!= null) {
                                        NotificationHelper::storeNotification(
                                            '/visites/show/'.$newVisit->origin_id,$list_biens['date_relance'],1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$newVisit->getAttribute('id'),$newVisit->prospect_id,$newVisit->projet_id,null,null
                                        );
                                        broadcast(new NotificationEvent($newVisit->id));
                                        $relance=new Relance_Rdv_visite();
                                        $relance->setConnection('temp');
                                        $relance->type=1;//relance
                                        $relance->mode_relance=$list_biens['mode_relance'];
                                        $relance->date_relance=$list_biens['date_relance'];
                                        $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $relance->user_id=$userAuth->value('id');
                                        $relance->visite_id=$newVisit->id;
                                        $relance->save();
                                    }
                                    if($list_biens['rdv']!= null){
                                        NotificationHelper::storeNotification(
                                            '/visites/show/'.$newVisit->origin_id,$list_biens['rdv'],2,'RDV VISITE',Auth::guard('api')->user()->id,null,$newVisit->getAttribute('id'),$newVisit->prospect_id,$newVisit->projet_id,null,null
                                        );
                                        broadcast(new NotificationEvent($newVisit->id));

                                        $rdv=new Relance_Rdv_visite();
                                        $rdv->setConnection('temp');
                                        $rdv->type=2;//rdv
                                        $rdv->rdv=$list_biens['rdv'];
                                        $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                        $rdv->user_id=$userAuth->value('id');
                                        $rdv->visite_id=$newVisit->id;
                                        $rdv->save();
                                    }
                                }


                                //store code pre reserve to table ==>PreReservation
                                if($newVisit->interet==InteretEnum::Intéressé->value && $newVisit->statut==StatutVisiteEnum::Pré_Réservation->value){
                                    $bien_c=new BienController();
                                    $bien_c->prereserverBien($newVisit->bien_id,$newVisit->id,null);

                                }
                                elseif ($newVisit->interet == InteretEnum::Intéressé->value && $newVisit->statut ==StatutVisiteEnum::Vendu->value) {
                                    //store visite vendu

                                        $reservationController = new ReservationController();
                                        $reservationRequest = new StoreReservationRequest();

                                        $dataReservation = [
                                            'nb_acquereurs'=>1,
                                            'code_reservation'=>$list_biens['code_reservation'],
                                            'prix'=>$list_biens['prix'],
                                            'mode_financement'=>$list_biens['mode_financement'],
                                            'date_reservation'=>$list_biens['date_reservation'],
                                            'commentaire'=>$list_biens['commentaire_res'],
                                            'visite_id'=>$newVisit->id,
                                            'prix_remise'=>$list_biens['prix_remise'],
                                            'prix_forfetaire'=>$list_biens['prix_forfetaire'],
                                            'bien_id'=>$list_biens['bien_id'],
                                            'projet_id'=>$request->selectedProjet,
                                            'verifierPourcentages'=>true,
                                            'cin'=>$prospect->cin,
                                            'nom'=>$prospect->nom,
                                            'prenom'=>$prospect->prenom,
                                            'telephone_num1'=>$prospect->telephone,
                                            'telephone_num2'=>$prospect->telephone_num2,
                                            'notifie'=>$prospect->notifie,
                                            'prospect_id'=>$prospect->id,
                                            'civilite'=>'Mr',
                                            'type_client'=>1,
                                            'situation_familliale'=>1,
                                            'sr' => $list_biens['sr'],
                                            'type_encaissement' => 1,
                                            'avance' => $list_biens['avance_res'],
                                            'mode_paiement' => $list_biens['mode_paiement'],
                                            'numero_paiement' => $list_biens['numero_paiement'],
                                            'date_reglement' => $list_biens['date_reglement'],
                                            'echeance' => $list_biens['echeance'],
                                            'banque_id' =>  $list_biens['banque_id'],
                                            'commentaireAvance' =>$list_biens['commentaireAvance'],
                                            'num_remise' => $list_biens['num_remise'],
                                            'date_encaissement' => $list_biens['date_encaissement'],

                                        ];
                                        $reservationRequest->merge($dataReservation);
                                        $reservationController->store($reservationRequest);


                                }
                        }
                    }
                }}

                //list des bien transfere vendu
                if ($request->list_bien_transfere_vendu!=null) {
                    //list des biens interesse
                    foreach ($request->list_bien_transfere_vendu as $key => $list_biens) {
                         //store n visite
                        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                        $newVisit=new Visite();
                        $newVisit->setConnection('temp');
                        $newVisit->user_id=$userAuth->value('id');
                        $newVisit->prospect_id=$originalVisite->prospect_id;
                        $newVisit->projet_id = $request->selectedProjet;
                        $newVisit->description='CREATION VISITE '.($last_number+1);
                        $newVisit->origin_id=$id;
                        $newVisit->commentaire = $request->commentaire;
                        $newVisit->interet = $request->interet;
                        $newVisit->bien_id =$list_biens['bien_id'];
                        $newVisit->statut= $list_biens['statut'];


                        if($newVisit->save()){
                            //push les vistes_id to array pour supprimer les relances where id not int array_v_id
                            array_push($array_v_id,$newVisit->id);
                            // related_sho meme id du visite
                            if($request->list_bien_interesse==null){
                                if($key==0){
                                    $newVisit->related_show_id=$newVisit->id;
                                    $first_v_id=$newVisit->id;
                                    $newVisit->show=1;
                                }
                                else{
                                    $newVisit->related_show_id=$first_v_id;
                                }
                            }
                            else{
                                $newVisit->related_show_id=$first_v_id;
                            }

                            if($newVisit->save()){
                                    //STORE HISTORIQUE DU BIEN
                                    if($list_biens['bien_id']!=null){
                                        if($newVisit->statut==StatutVisiteEnum::Vendu->value){
                                            HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite Vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$newVisit->bien_id, Auth::guard('api')->user()->id,$newVisit->id,NULL);
                                        }
                                    }

                                    if ($newVisit->interet == InteretEnum::Intéressé->value && $newVisit->statut ==StatutVisiteEnum::Vendu->value) {
                                        //store visite vendu

                                            $reservationController = new ReservationController();
                                            $reservationRequest = new StoreReservationRequest();

                                            $dataReservation = [
                                                'nb_acquereurs'=>1,
                                                'code_reservation'=>$list_biens['code_reservation'],
                                                'prix'=>$list_biens['prix'],
                                                'mode_financement'=>$list_biens['mode_financement'],
                                                'date_reservation'=>$list_biens['date_reservation'],
                                                'commentaire'=>$list_biens['commentaire_res'],
                                                'visite_id'=>$newVisit->id,
                                                'prix_remise'=>$list_biens['prix_remise'],
                                                'prix_forfetaire'=>$list_biens['prix_forfetaire'],
                                                'bien_id'=>$list_biens['bien_id'],
                                                'projet_id'=>$request->selectedProjet,
                                                'verifierPourcentages'=>true,
                                                'origin'=>'visite',
                                                'cin'=>$prospect->cin,
                                                'nom'=>$prospect->nom,
                                                'prenom'=>$prospect->prenom,
                                                'telephone_num1'=>$prospect->telephone,
                                                'telephone_num2'=>$prospect->telephone_num2,
                                                'notifie'=>$prospect->notifie,
                                                'prospect_id'=>$prospect->id,
                                                'civilite'=>'Mr',
                                                'type_client'=>1,
                                                'situation_familliale'=>1,
                                                'sr' => $list_biens['sr'],
                                                'type_encaissement' => 1,
                                                'avance' => $list_biens['avance_res'],
                                                'mode_paiement' => $list_biens['mode_paiement'],
                                                'numero_paiement' => $list_biens['numero_paiement'],
                                                'date_reglement' => $list_biens['date_reglement'],
                                                'echeance' => $list_biens['echeance'],
                                                'banque_id' =>  $list_biens['banque_id'],
                                                'commentaireAvance' =>$list_biens['commentaireAvance'],
                                                'num_remise' => $list_biens['num_remise'],
                                                'date_encaissement' => $list_biens['date_encaissement'],

                                            ];
                                            $reservationRequest->merge($dataReservation);
                                            $reservationController->store($reservationRequest);


                                    }
                                    //set old visite to pre _reservation_vendu

                                    $old_visite_transfere=Visite::on('temp')->findorfail($list_biens['visite_id']);
                                    $old_visite_transfere->statut=StatutVisiteEnum::Pré_Réservation_Vendu->value;
                                    $old_visite_transfere->save();
                            }
                        }
                    }
                }
                  //traite/supprimer les relances rdv des old visite=>automatique
                  $old_visites = Visite::on('temp')->where('origin_id', $id)->whereNotIn('id', $array_v_id)->orderBy('created_at', 'DESC')->get();
                  if(count($old_visites)>0){
                      foreach($old_visites as $old_visite){

                          //SUPPRIMER LES OLDS NOTIF
                          $notif_old_relance=Notification::on('temp')->where(function ($query){
                              $query->where('type',1)
                                  ->orwhere('type',2);})
                              ->where(function ($query_2) use($old_visite){
                                      $query_2->where('visite_id',$old_visite->id);})
                              ->get();
                              if(($notif_old_relance->count())>0){
                              foreach($notif_old_relance as $nt_r){
                                  $nt_r->delete();
                              }
                              }
                              /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                  $old_relances_rdv=Relance_Rdv_visite::on('temp')->where('visite_id',$old_visite->id)->where('type_traitement',0)->get();
                                  if(count($old_relances_rdv)>0){
                                      foreach($old_relances_rdv as $old){
                                          $old->type_traitement=2;//auto
                                          $old->date_traitement=Carbon::now();
                                          //si old visite pre reserve en suite n visite Vendu ==>user_id_traite(l'ancien user)
                                          if($old->visite->statut==StatutVisiteEnum::Pré_Réservation->value){
                                              if($visite->statut==StatutVisiteEnum::Vendu->value){
                                                  $old->user_id_traite=$old_visite->user_id;
                                              }
                                              else{
                                                  $old->user_id_traite=$userAuth->value('id');
                                              }
                                          }
                                          else{
                                              $old->user_id_traite=$userAuth->value('id');
                                          }
                                          $old->save();
                                      }

                                  }
                      }
                  }
            }

            return response()->json(['visite' => 'success'], 200);

        }else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public  function getAllAttributes(){
        $tranches = Tranche::where('projet_id', Session::get('projet_id'))->get();
        $etages= Tranche::where('projet_id', Session::get('projet_id'))->max('niveau_etage')->value();;
        $blocs = Bloc::where('projet_id', Session::get('projet_id'))->get();
        $immeubles = Immeuble::where('projet_id', Session::get('projet_id'))->get();
        $biens = Bien::where([['projet_id', Session::get('projet_id')],['etat',EtatBien::DISPONIBLE->name]])->get();
        $typologies=Typologie::where('projet_id', Session::get('projet_id'))->get();
        $vues=Vue::where('projet_id', Session::get('projet_id'))->get();
        $formData = [
            'tranches' => $tranches,
            'etages' => $etages,
            'blocs' => $blocs,
            'immeubles' => $immeubles,
            'biens' => $biens,
            'typologies' => $typologies,
            'vues' => $vues
        ];

        return response()->json($formData);
    }
}
