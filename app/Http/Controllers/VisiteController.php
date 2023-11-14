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
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\StoreVisiteRequest;
use App\Http\Requests\Store_n_VisiteRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Http\Requests\UpdateVisiteRequest;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class VisiteController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     public static function paginate_array($items, $perPage, $page ,$url)
        {
            $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
            $total = count($items);
            $currentpage = $page;
            $offset = ($currentpage * $perPage) - $perPage ;
            $itemstoshow = array_slice($items , $offset , $perPage);
            return new LengthAwarePaginator($itemstoshow, $total, $perPage, $page,
                ['path'=> $url]);
        }

    public function index(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5); // Get the number of items per page
            $page = $request->input('page', 1);

            $visites = Visite::on('temp')->latest('created_at')->where('projet_id',$projet_id)
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
                'visit_count' => count($visite)

            ];});

          $data = $this->paginate_array($visites->toArray(),$perPage,$page,$request->url());
            return response()->json(['visites' => $data]);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
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
                convert lead to visite
            ****/

        $user = Auth::user();
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            //test si le user connecte celui qui a  fait la proposition
            if($request->bien_id!=NULL){
                $bien_prop=Bien::on('temp')->findorfail($request->bien_id);
                if($bien_prop->etat=='ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id!=Auth::guard('api')->user()->id){
                    return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
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
                $visite_exist=Visite::on('temp')->where('prospect_id',$request->prospect_id)->where('projet_id',$request->selectedProjet)->orderBy('created_at', 'DESC')->first();
                if($visite_exist!=null){
                     //store n visite in this projet
                    $visite->origin_id =$visite_exist->origin_id;
                }
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

            if($request->interet==InteretEnum::INTERESSE->value){
                $visite->bien_id = $request->bien_id;
                $visite->interet = $request->interet;
                if($request->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $visite->statut= $request->statut;
                    $visite->rdv = $request->rdv;
                    $visite->mode_relance=$request->mode_relance;
                    $visite->date_relance = $request->date_relance;
                }
                elseif($request->statut==StatutVisiteEnum::VENDU->value){
                    $visite->statut=$request->statut;
                }

            }

            elseif($request->interet==InteretEnum::RECEPTIF->value){
                $visite->interet = $request->interet;
                $visite->mode_relance=$request->mode_relance;
                $visite->date_relance = $request->date_relance;
            }
            elseif($request->interet==InteretEnum::PERDU->value){
                $visite->interet = $request->interet;
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
                   //store les notification relance / rdv
                if ($visite->date_relance != null) {
                    NotificationHelper::storeNotification(
                        'lien_relance', $request->date_relance,1,'RELANCE DU VISITE',Auth::guard('api')->user()->id,$visite->getAttribute('id'),$visite->prospect_id
                    );
                }
                if ($visite->rdv != null) {
                    NotificationHelper::storeNotification(
                        'lien_rdv', $request->rdv,2,'RDV DU VISITE',Auth::guard('api')->user()->id,$visite->getAttribute('id'),$visite->prospect_id
                    );

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
                    $freinRequest['list_att']=1;
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
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $frein=new FreinController();
            $visite = Visite::on('temp')->findOrfail($id);
                if($visite->interet==InteretEnum::PERDU->value) {
                    $visite['frein']=$frein->searchFreinByVisiteId($visite->id);
                }
                $relatedVisites=Visite::on('temp')->where('origin_id',$visite->id)->orderby('created_at', 'DESC')->get();
                foreach ($relatedVisites as $relatedVisite) {
                    if ($relatedVisite->interet == InteretEnum::PERDU->value) {
                        $frein = $frein->searchFreinByVisiteId($relatedVisite->id);
                        $relatedVisite['frein'] = $frein;
                    }
                }

            return response()->json(['visite' => $visite,'relatedVistes'=>$relatedVisites], 200);
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
            $visite = Visite::on('temp')->findOrFail($id);

             //test si le user connecte celui qui a  fait la proposition
             if($request->bien_id!=NULL){
                $bien_prop=Bien::on('temp')->findorfail($request->bien_id);
                if($bien_prop->etat=='ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id!=Auth::guard('api')->user()->id ){
                    return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
                }
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
                                $old_bien=new BienController();
                                $old_bien->libererBien($visite->get()->value('bien_id'));
                            }
                    }
                }

            }

                    //changement d'interet (receptif ou perdu)
            if($visite->get()->value('bien_id') && $request->interet != InteretEnum::INTERESSE->value){

                $bienEncoursPropo=new BienController();
                $bienEncoursPropo->libererBien($visite->get()->value('bien_id'));

            }

            $visite->user_id = $userAuth->value('id');
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

            //interesse
            if($request->interet==InteretEnum::INTERESSE->value){
                $visite->bien_id = $request->bien_id;
                $visite->interet = $request->interet;
                if($request->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $visite->statut= $request->statut;
                    $visite->rdv = $request->rdv;
                    $visite->mode_relance=$request->mode_relance;
                    $visite->date_relance = $request->date_relance;
                }
                elseif($request->statut==StatutVisiteEnum::VENDU->value){
                    $visite->statut=$request->statut;
                    $visite->rdv = null;
                    $visite->mode_relance=null;
                    $visite->date_relance = null;
                }

            }

            elseif($request->interet==InteretEnum::RECEPTIF->value){
                $visite->interet = $request->interet;
                $visite->mode_relance=$request->mode_relance;
                $visite->date_relance = $request->date_relance;
                $visite->statut= null;
                $visite->rdv =null;
                $visite->bien_id =null;
            }
            elseif($request->interet==InteretEnum::PERDU->value){
                $visite->interet = $request->interet;
                $visite->mode_relance=null;
                $visite->date_relance = null;
                $visite->statut= null;
                $visite->rdv =null;
                $visite->bien_id =null;
            }
            $visite->save();

            /**store historique du visite si il a chnge interet ou statut**/
            /** store relances et rdv **/
            /**si ancien perdu avec notif des bien dispo on supprime la notif**/
            //STORE HISTORIQUE DU BIEN
            if($visite->bien_id!=null){
                if($visite->statut==StatutVisiteEnum::VENDU->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite vendu du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
                else if($visite->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    HistoriqueBienHelper::createHistoriqueBien(5, "Modification visite pré reservé du client :".$prospect->cin .' '.$prospect->nom .' '.$prospect->prenom, $visite->bien_id, Auth::guard('api')->user()->id,$visite->id,NULL);
                }
            }
            //supprimer ancien notif
            $notif_exist_relance_rdv=Notification::on('temp')->whereIN('type',[1,2])->where('visite_id',$visite->id)->get();
            if(count($notif_exist_relance_rdv)>0){
                foreach($notif_exist_relance_rdv as $nt){
                    $nt->delete();
                }
            }

            //store les nouveaux notifications relance / rdv
               if ($visite->date_relance != null) {
                NotificationHelper::storeNotification(
                    'lien_relance', $request->date_relance,1,'RELANCE DU VISITE',Auth::guard('api')->user()->id,$visite->getAttribute('id'),$visite->prospect_id
                );
            }
            if ($visite->rdv != null) {
                NotificationHelper::storeNotification(
                    'lien_rdv', $request->rdv,2,'RDV DU VISITE',Auth::guard('api')->user()->id,$visite->getAttribute('id'),$visite->prospect_id
                );

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
                $frein_id=Frein::on('temp')->where('visite_id', $visite->id)->get();
                $freinRequest['prix_min']=$request->prix_min;
                $freinRequest['frein']=$request->frein;
                $freinRequest['prix_max']=$request->prix_max;
                $freinRequest['sup_min']=$request->sup_min;
                $freinRequest['sup_max']=$request->sup_max;
                $freinRequest['list_att']=1;
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
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $visite=Visite::on('temp')->findOrFail($id);
            if($visite->interet== InteretEnum::INTERESSE->value){
                if($visite->bien_id){
                    $bienEncoursPropo=new BienController();
                    $bienEncoursPropo->libererBien($visite->bien_id);
                }
            }
            if($visite->interet == InteretEnum::PERDU->name){
                $frein=Frein::on('temp')->where('visite_id',$visite->id)->get();
                $freinController= new FreinController();
                $freinController->destroy($frein->value('id'));
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
        $originalVisite=Visite::on('temp')->find($id);
        if (!$originalVisite){ return response()->json(['error'=>"L'original de la visite n'a pas été trouvé."]);}

        $user = Auth::user();
         //test si le user connecte celui qui a  fait la proposition
         if($request->bien_id!=NULL){
            $bien_prop=Bien::on('temp')->findorfail($request->bien_id);
            if($bien_prop->etat=='ENCOURS_DE_PROPOSITION' && $bien_prop->is_proposed->user_id!=$user->id){
                return response()->json(['error_33' => 'le bien choisi :'.$bien_prop->propriete_dite_bien.' est en cours de proposition  par : '.$bien_prop->is_proposed->user->name.' '.$bien_prop->is_proposed->user->prenom], 333);
            }
        }
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

             //store n visite

            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $newVisit=new Visite();
            $newVisit->setConnection('temp');
            $newVisit->user_id=$userAuth->value('id');
            $newVisit->prospect_id=$originalVisite->prospect_id;
            $newVisit->projet_id = $request->selectedProjet;
            $newVisit->origin_id=$id;
            $newVisit->source_id = $originalVisite->source_id;
            $newVisit->partenaire_id = $originalVisite->partenaire_id;
            $newVisit->notifie = $originalVisite->notifie;
            $newVisit->commentaire = $request->commentaire;
            if($request->interet==InteretEnum::INTERESSE->value){
                $newVisit->bien_id = $request->bien_id;
                $newVisit->interet = $request->interet;
                if($request->statut==StatutVisiteEnum::PRE_RESERVATION->value){
                    $newVisit->statut= $request->statut;
                    $newVisit->rdv = $request->rdv;
                    $newVisit->mode_relance=$request->mode_relance;
                    $newVisit->date_relance = $request->date_relance;
                }
                elseif($request->statut==StatutVisiteEnum::VENDU->value){
                    $newVisit->statut=$request->statut;
                }

            }

            elseif($request->interet==InteretEnum::RECEPTIF->value){
                $newVisit->interet = $request->interet;
                $newVisit->mode_relance=$request->mode_relance;
                $newVisit->date_relance = $request->date_relance;
            }
            elseif($request->interet==InteretEnum::PERDU->value){
                $newVisit->interet = $request->interet;
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
                //supprimer dernier notif

                //store les notification relance / rdv
                if ($newVisit->date_relance != null) {
                    NotificationHelper::storeNotification(
                        'lien_relance', $request->date_relance,1,'RELANCE DU VISITE',Auth::guard('api')->user()->id,$newVisit->getAttribute('id'),$newVisit->prospect_id
                    );
                }
                if ($newVisit->rdv != null) {
                    NotificationHelper::storeNotification(
                        'lien_rdv', $request->rdv,2,'RDV DU VISITE',Auth::guard('api')->user()->id,$newVisit->getAttribute('id'),$newVisit->prospect_id
                    );

                }
                $old_visite = Visite::on('temp')->where('origin_id', $id)->orderBy('created_at', 'DESC')->first();
                     //Si lors de l'ancienne visite le client a préreservé==>libérer le bien et supprimer les relances
                if ($old_visite->statut == StatutVisiteEnum::PRE_RESERVATION->value ) {
                    $oldBien = Bien::on('temp')->find($old_visite->bien_id);
                    if ($oldBien->etat == 'PRE_RESERVATION' && $oldBien->historique_bien_pre_reserve->visite_id==$old_visite->id) {
                        $old_bien->libererBien($old_visite->bien_id);
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
                    $freinRequest['list_att']=1;
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
