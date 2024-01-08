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
                'interet' => $visite->first()->interet,
                'statut' => $visite->first()->statut,
                'propriete_dite_bien' => $visite->first()->bien_id?$visite->first()->bien->propriete_dite_bien:'',
                'etat_bien' => $visite->first()->bien_id?$visite->first()->bien->etat:'',
                'visit_count' => count($visite)

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
            ->where('origin_id',$origin_id)->withTrashed()->orderby('created_at', 'asc')->get();
            foreach ($historiques as $histo) {
                if ($histo->interet == InteretEnum::PERDU->value) {
                    $frein_h_ = $frein_h->searchFreinByVisiteId($histo->id,'with_row_deleted_at');
                    $histo['frein'] = $frein_h_;
                }
            }
            return response()->json(['historiques' => $historiques], 200);
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
             //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
            if($request->bien_id!=NULL && $request->interet==InteretEnum::INTERESSE->value){
                $bien_prop=Bien::on('temp')->findorfail($request->bien_id);
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
            //rendre bien disponible si interet!=interesse ==>receptif ou perdu
            if($request->bien_id){
                if($request->interet!=InteretEnum::INTERESSE->value){
                    Bien_Helper::libererBien($request->bien_id,null);

                }
            }



            //store prospect si client n'existe pas

            if($request->prospect_id==null){
                $validatedData = $request->validated();
                $validatedData['cin']=$request->cin;
                $validatedData['email']=$request->email;
                $validatedData['source']=$request->source_id;
                $validatedData['telephone']=$request->telephone;
                $validatedData['telephone_num2']=$request->telephone_num2;
                $validatedData['origin']='visite';
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
                    }
                }
                $prospect->nom=$request->nom;
                $prospect->prenom=$request->prenom;
                $prospect->email=$request->email;
                $prospect->telephone=$request->telephone;
                $prospect->telephone_num2=$request->telephone_num2;
                $prospect->source=$request->source_id;
                $prospect->save();
            }
            //(storee first ou n visite )
            $origin_id=null;
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $visite = new Visite();
            $visite->setConnection('temp');
            if($request->prospect_id!=null){
                $visite_exist=Visite::on('temp')->where('prospect_id',$request->prospect_id)->where('projet_id',$request->selectedProjet)->orderBy('created_at', 'DESC')->get();
                if(count($visite_exist)>0){
                    foreach ($visite_exist as $key => $value){
                        if($key==0){
                            //store n visite in this projet
                        $visite->origin_id =$value->origin_id;
                        }
                    }

                }
                $visite->description ='CREATION VISITE '.count($visite_exist)+1;
            }
            if($visite->description==null){
                $visite->description ='CREATION VISITE 1';
            }
            $visite->user_id =  $userAuth->value('id');
            $visite->prospect_id =$prospect->id;
            $visite->projet_id = $request->selectedProjet;
            $visite->commentaire = $request->commentaire;
            $visite->source_id = $request->source_id;
            if ($request->source_txt == 'PARTENAIRE') {
            $visite->partenaire_id = $request->partenaire_id;
            }
            else{
                $visite->partenaire_id = null;
            }
            $visite->notifie = $request->notifie;
            $visite->interet = $request->interet;
            if($request->interet==InteretEnum::INTERESSE->value){
                $visite->bien_id = $request->bien_id;
                $visite->statut= $request->statut;
            }

            if($visite->save()){
                if($visite->origin_id==null){
                    $visite->origin_id = $visite->id;
                    $visite->save();
                }


                //STORE HISTORIQUE DU BIEN
                if($visite->bien_id!=null){
                    if($visite->statut==StatutVisiteEnum::VENDU->value){
                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                    }
                    else if($visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                    }
                }
                    //store relances et rdv et notifications
                    if($visite->statut==StatutVisiteEnum::PRE_RESERVATION->value ||$visite->interet==InteretEnum::RECEPTIF->value){
                        if ($request->date_relance != null) {
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$visite->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->getAttribute('id'),$visite->prospect_id,$visite->projet_id,null,null
                            );

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
                if($visite->interet==InteretEnum::INTERESSE->value && $visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $bien_c=new BienController();
                    $bien_c->prereserverBien($visite->bien_id,$visite->id,null);

                }
                /*elseif ($visite->interet == 'INTERESSE' && $visite->statut == 'vendu') {
                    store reservation
                }*/


                if ($visite->interet == InteretEnum::PERDU->value) {

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
             }
            return response()->json(['visite' => $visite->id], 200);


        }
        else
        {
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

    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();


            $visite = Visite::on('temp')->with('relance_relation','rdv_relation')->findOrfail($id);
            $frein=new FreinController();
                if($visite->interet==InteretEnum::PERDU->value) {
                    $visite['frein']=$frein->searchFreinByVisiteId($visite->id,'without_row_deleted');
                }

                $relatedVisites=Visite::on('temp')->with('pre_reservation_visite','relance_relation','rdv_relation','historique_relances_rdvs')->where('origin_id',$visite->id)->where('etat',1)->orderby('created_at', 'DESC')->get();

                foreach ($relatedVisites as $relatedVisite) {
                    if ($relatedVisite->interet == InteretEnum::PERDU->value) {
                        $frein_v = $frein->searchFreinByVisiteId($relatedVisite->id,'without_row_deleted');
                        $relatedVisite['frein'] = $frein_v;
                    }
                }

                //get nom propriete _dite_bien concat utilisé dans edit visite
                $propriete=null;
                if($visite->bien_id!=null){
                    $propriete= $this->get_propriete_bien_concat($visite->bien_id);
                }

            return response()->json(['visite' => $visite,'propriete_dite_bien' => $propriete,'visites'=>$relatedVisites], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

 public static function traiter_relance_rdv_visite($id,UpdateDate_relance_Rdv $request)
    {
        if(RoleHelper::ACSup()) {
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
                                $new_relance->commentaire=$request->commentaire;

                            }
                            else{
                                //rdv
                                $new_relance->type=2;//rdv
                                $new_relance->rdv=$request->date;
                                $new_relance->commentaire=$request->commentaire;

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
                            }
                            else{
                                //store new notification
                            NotificationHelper::storeNotification(
                                '/visites/show/'.$new_relance->visite->origin_id, $request->date,2,'RDV VISITE',Auth::guard('api')->user()->id,null,$visite_id,$prospect_id,$new_relance->visite->projet_id,null,null
                            );

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

            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $old_visite = Visite::on('temp')->findOrFail($id);

             //test si le user connecte celui qui a  fait la proposition
             if($request->bien_id!=NULL && $request->interet==InteretEnum::INTERESSE->value){
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
                $description=null;
                $visites_count=Visite::on('temp')->where('origin_id',$visite->origin_id)->where('etat',1)->orderBy('created_at', 'DESC')->count();
                if($visites_count>0){
                    $description='MODIFICATION VISITE '.$visites_count;
                }

            //recupere le prospect //modifier info
            $prospect= Prospect::on('temp')->findorfail($visite->prospect_id);
            if($request->cin!=null){
                $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$visite->prospect_id)->count();
                if($cin_exist==0){
                    $prospect->cin=$request->cin;
                }
            }
            $prospect->nom=$request->nom;
            $prospect->prenom=$request->prenom;
            $prospect->email=$request->email;
            $prospect->telephone=$request->telephone;
            $prospect->telephone_num2=$request->telephone_num2;
            $prospect->source=$request->source_id;
            $prospect->save();


             /****Libérer le bien de l'ancienne visite**/
                    //chngement de bien
            if($request->interet==InteretEnum::INTERESSE->value){
                if (($visite->statut == StatutVisiteEnum::PRE_RESERVATION->value|| $visite->statut == StatutVisiteEnum::VENDU->value) && $visite->bien_id!=null) {
                    if($visite->bien_id!=$request->bien_id){
                        $oldBien = Bien::on('temp')->find($visite->bien_id);
                            if ($oldBien->etat == 'PRE_RESERVATION' || $oldBien->etat == 'VENDU') {
                                Bien_Helper::libererBien($visite->bien_id,null);

                            }
                    }
                }

            }

                    //changement d'interet (receptif ou perdu)
            if($visite->bien_id!=null && $request->interet != InteretEnum::INTERESSE->value){
                Bien_Helper::libererBien($visite->bien_id,null);

            }

            $visite->user_id = $userAuth->value('id');
            $visite->commentaire = $request->commentaire;
            $visite->description = $description;
            $visite->old_v_id = $id;
            $visite->source_id = $request->source_id;
            if ($request->source_txt == 'PARTENAIRE') {
                $visite->partenaire_id = $request->partenaire_id;
                }
            else{
                    $visite->partenaire_id = null;
                }
            $visite->notifie = $request->notifie;
            $visite->interet = $request->interet;

            //interesse
            if($request->interet==InteretEnum::INTERESSE->value){
                $visite->bien_id = $request->bien_id;
                $visite->statut= $request->statut;
            }

            elseif($request->interet==InteretEnum::RECEPTIF->value){
                $visite->statut= null;
                $visite->bien_id =null;
            }
            elseif($request->interet==InteretEnum::PERDU->value){
                $visite->statut= null;
                $visite->bien_id =null;
            }
            $visite->save();


            /** store relances et rdv **/
            //STORE HISTORIQUE DU BIEN
            if($visite->bien_id!=null){
                if($visite->statut==StatutVisiteEnum::VENDU->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
                else if($visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
            }
            /**si ancien perdu avec notif des bien dispo on supprime la notif** pas encours */
            //supprimer ancien notif relance rdv
            $notif_exist_relance_rdv=Notification::on('temp')->whereIN('type',[1,2])->where('visite_id',$old_visite->id)->get();
            if(count($notif_exist_relance_rdv)>0){
                foreach($notif_exist_relance_rdv as $nt){
                    $nt->delete();
                }
            }

             //store relances et rdv et notifications
             if($visite->statut==StatutVisiteEnum::PRE_RESERVATION->value ||$visite->interet==InteretEnum::RECEPTIF->value){
                if ($request->date_relance != null) {
                    if($old_visite->relance_relation!=null){
                        $old_visite->relance_relation->delete();
                    }
                    NotificationHelper::storeNotification(
                        '/visites/show/'.$visite->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$visite->id,$visite->prospect_id,$visite->projet_id,null,null
                    );

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
            if($old_visite->statut!=StatutVisiteEnum::PRE_RESERVATION->value ){
                if($visite->interet==InteretEnum::INTERESSE->value && $visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $bien_c=new BienController();
                    $bien_c->prereserverBien($visite->bien_id,$visite->id,null);
                }
            }


             /*elseif ($visite->interet == 'INTERESSE' && $visite->statut == 'vendu') {
                                        store reservation
                                    }*/

            if ($visite->interet == InteretEnum::PERDU->value) {
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
            if($visite->interet== InteretEnum::INTERESSE->value){
                if($visite->bien_id){
                    Bien_Helper::libererBien($visite->bien_id,null);
                }
            }
            if($visite->interet == InteretEnum::PERDU->name){
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
        $originalVisite=Visite::on('temp')->find($id);
        if (!$originalVisite){ return response()->json(['error'=>"L'original de la visite n'a pas été trouvé."]);}

        $user = Auth::user();
         //test si le user connecte celui qui a  fait la proposition // bien pre reserve par autre
         if($request->bien_id!=NULL && $request->interet==InteretEnum::INTERESSE->value){
            $bien_prop=Bien::on('temp')->findorfail($request->bien_id);
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
            //rendre bien disponible si interet!=interesse ==>si receptif ou perdu
            if($request->bien_id){
                if($request->interet!=InteretEnum::INTERESSE->value){
                   Bien_Helper::libererBien($request->bien_id,null);
                }
            }
        if(RoleHelper::ACSup()){
            $old_visite = Visite::on('temp')->where('origin_id', $id)->orderBy('created_at', 'DESC')->first();
            //si interet on store cin du client
             $prospect= Prospect::on('temp')->findorfail($originalVisite->prospect_id);
             if($request->cin!=null){
                 $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$originalVisite->prospect_id)->count();
                 if($cin_exist==0){
                     $prospect->cin=$request->cin;
                     $prospect->save();
                 }
             }

             $description=null;
             $visites_count=Visite::on('temp')->where('origin_id',$id)->where('etat',1)->orderBy('created_at', 'DESC')->count();
             if($visites_count>0){
                 $description='CREATION VISITE '.$visites_count+1;
             }
             //store n visite
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $newVisit=new Visite();
            $newVisit->setConnection('temp');
            $newVisit->user_id=$userAuth->value('id');
            $newVisit->prospect_id=$originalVisite->prospect_id;
            $newVisit->projet_id = $request->selectedProjet;
            $newVisit->description=$description;
            $newVisit->origin_id=$id;
            $newVisit->source_id = $originalVisite->source_id;
            $newVisit->partenaire_id = $originalVisite->partenaire_id;
            $newVisit->notifie = $originalVisite->notifie;
            $newVisit->commentaire = $request->commentaire;
            $newVisit->interet = $request->interet;
            if($request->interet==InteretEnum::INTERESSE->value){
                $newVisit->bien_id = $request->bien_id;
                $newVisit->statut= $request->statut;
            }
            if($newVisit->save()){
                 //STORE HISTORIQUE DU BIEN
                if($newVisit->bien_id!=null){
                    if($newVisit->statut==StatutVisiteEnum::VENDU->value){
                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation nouvelle visite vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom,$newVisit->bien_id, Auth::guard('api')->user()->id,$newVisit->id,NULL);
                    }
                    else if($newVisit->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                        HistoriqueBienHelper::createHistoriqueBien(5, "Creation nouvelle visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $newVisit->bien_id, Auth::guard('api')->user()->id,$newVisit->id,NULL);
                    }
                }

                //store relances et rdv et notifications du new visite
                if($newVisit->statut==StatutVisiteEnum::PRE_RESERVATION->value ||$newVisit->interet==InteretEnum::RECEPTIF->value){
                    if ($request->date_relance != null) {
                        NotificationHelper::storeNotification(
                            '/visites/show/'.$newVisit->origin_id, $request->date_relance,1,'RELANCE VISITE',Auth::guard('api')->user()->id,null,$newVisit->getAttribute('id'),$newVisit->prospect_id,$newVisit->projet_id,null,null
                        );

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

                     //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                if ($old_visite->statut == StatutVisiteEnum::PRE_RESERVATION->value ) {
                    $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                    if ($oldBien->etat == 'PRE_RESERVATION' && $oldBien->historique_bien_pre_reserve->visite_id==$old_visite->id) {
                        Bien_Helper::libererBien($old_visite->bien_id,null);
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
                            //si old visite pre reserve en suite n visite vendu ==>user_id_traite(l'ancien user)
                            if($old->visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                                if($newVisit->statut==StatutVisiteEnum::VENDU->value){
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

                  //store code pre reserve to table ==>PreReservation
                if($newVisit->interet==InteretEnum::INTERESSE->value && $newVisit->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $bien_c=new BienController();
                    $bien_c->prereserverBien($newVisit->bien_id,$newVisit->id,null);
                }
                  /*elseif ($newVisit->interet == 'INTERESSE' && $newVisit->statut == 'vendu') {
                    store reservation
                  }*/

                  if ($newVisit->interet == InteretEnum::PERDU->value) {
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

            return response()->json(['visite' => $newVisit], 200);
        }
        else
        {
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
