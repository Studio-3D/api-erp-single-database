<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTypologieRequest;
use App\Http\Requests\UpdateTypologieRequest;
use App\Models\Appel;
use App\Models\TraitementAppel;
use App\Models\Frein;
use App\Models\HistoriqueBien;
use App\Models\PreReservation;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreAppelRequest;
use Carbon\Carbon;
use App\Enum\InteretEnumAppel;
use App\Http\Helpers\NotificationHelper;
use App\Events\NotificationEvent;
use App\Models\Relance_Rdv_Appel;
use App\Http\Requests\StoreProspectRequest;
use Illuminate\Support\Facades\Config;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Models\TypeBienAppel;
use App\Http\Requests\UpdateAppelRequest;
use App\Http\Requests\UpdateDate_relance_Rdv;
use App\Events\NotifMenuEvent;
use App\Models\StatutProspect;
use App\Http\Controllers\NotificationController;




class AppelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function indexByProjet(Request $request, $projet_id)
{
    if (Auth::guard('api')->check()) {
        // Default values for pagination
        $size = $request->input('size', null);
        $page = $request->input('page', null);

        DatabaseHelper::Config();
        $query = Appel::on('temp')->with('projet', 'prospect', 'last_traitement_appel')
            ->where('projet_id', $projet_id);

        // Filter by CIN
        if ($request->filled('cin')) {
            $query->whereHas('prospect', function ($q) use ($request) {
                $q->where('cin', 'like', '%' . $request->input('cin') . '%');
            });
        }

        // Filter by nom
        if ($request->filled('nom')) {
            $query->whereHas('prospect', function ($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->input('nom') . '%');
            });
        }

        // Filter by prenom
        if ($request->filled('prenom')) {
            $query->whereHas('prospect', function ($q) use ($request) {
                $q->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            });
        }

        // Filter by date - corrected to use proper date comparison
        if ($request->filled('date')) {
            $date = Carbon::parse($request->input('date'))->format('Y-m-d');
            $query->whereHas('last_traitement_appel', function ($q) use ($date) {
                $q->whereDate('date', $date);
            });
        }

        // Filter by telephone (either primary or secondary)
        if ($request->filled('telephone')) {
            $query->whereHas('prospect', function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                        ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                });
            });
        }

        // Filter by secondary phone
        if ($request->filled('telephone_num2')) {
            $query->whereHas('prospect', function ($q) use ($request) {
                $q->where('telephone_num2', 'like', '%' . $request->input('telephone_num2') . '%');
            });
        }

        // Filter by interet - corrected to properly check the value
       /* if ($request->filled('interet')) {
            $interet = $request->input('interet');
            $query->whereHas('last_traitement_appel', function ($q) use ($interet) {
                $q->where('interet', $interet);
            });
        }*/

        // Apply pagination if parameters are valid
        if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
            $appels = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $appels->currentPage(),
                'totalItems' => $appels->total(),
                'totalPages' => $appels->lastPage(),
            ];

            return response()->json([
                'data' => $appels->items(),
                'pagination' => $pagination,
            ], 200);
        }

        // Return all results if no pagination parameters
        $appels = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['appels' => $appels]);
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}
    /**
     * Show the form for creating a new resource.
     */
                     //store relance to table

    public function store_trait_appel(Request $request){
        DatabaseHelper::Config();
        $trait=new TraitementAppel();
        $trait->setConnection('temp');
        $trait->appel_id=$request->appel_id;
        $trait->user_id =$request->user_id;
        $trait->type_appel=$request->type_appel;
        $trait->date=$request->date;
        $trait->interet=$request->interet;
        $trait->etat=1;
        $trait->date_traitement=$request->date_traitement;
        $trait->commentaire=$request->commentaire;
        if($request->interet==InteretEnumAppel::Intéressé->value){
            $trait->tranche_id=$request->tranche_id;
            $trait->bloc_id=$request->bloc_id;
            $trait->immeuble_id=$request->immeuble_id;
            $trait->etage=$request->etage;
            $trait->orientation=$request->orientation;
        }
        if($trait->save()){
            $data = [
                'appel_id' => $request->appel_id,
                'traite_appel_id' => $trait->id,
                'prospect_id'=>$request->prospect_id,
                'project_id'=>$request->project_id,
                'date_relance'=>$request->date_relance,
                'mode_relance'=>$request->mode_relance,
                'rdv'=>$request->rdv,
            ];
            if($request->interet==InteretEnumAppel::Réceptif->value){
                //store relance to table
                   $this->store_relance_rdv($request->merge($data));
            }elseif($request->interet==InteretEnumAppel::Intéressé->value){
                    //store_type_bien by appel
                    if (!empty($request->type_biens)) {
                        $array_tp_b= explode(',', $request->type_biens); // $tranches_array sera ['5', '2']
                        foreach ($array_tp_b as $valeur) {
                            $this->store_types_bien_appel($valeur,$trait->id);
                        }
                    }
                            //store relance to table
                   $this->store_relance_rdv($request->merge($data));
            }
            elseif($request->interet==InteretEnumAppel::Perdu->value){
                        //stoore freeein

                        $freinRequest['traite_appel_id']=$trait->id;
                        $freinRequest['prix_min']=$request->prix_min;
                        $freinRequest['prix_max']=$request->prix_max;
                        $freinRequest['sup_min']=$request->sup_min;
                        $freinRequest['sup_max']=$request->sup_max;
                        $freinRequest['etat']=1;
                        $freinRequest['avance']=$request->avance;
                        $freinRequest['selectedTranches']=$request->tranches_id;
                             //-1 for 0 si on selection just le 0
                        $freinRequest['selectedEtages'] = ($request->etages == "0") ? -100 : $request->etages;
                        $freinRequest['selectedOrientations']=$request->orientations;
                        $freinRequest['selectedTypologies']=$request->typologies;
                        $freinRequest['selectedVues']=$request->vues;
                        $freinController = new FreinController();
                        $freinController->store(new StoreFreinRequest($freinRequest));

            }
        }
        return response()->json(['traite',$trait],200);


    }
    public function store_relance_rdv(Request $request){
                DatabaseHelper::Config();
                Config::set('broadcasting.default', 'pusher_3');
                if ($request->date_relance != null) {
                    $data_notif = [
                        'lien' => '/crm/appels/'.$request->appel_id,
                        'date' => $request->date_relance,
                        'type' => 27,
                        'description' => 'RELANCE APPEL',
                        'user_id' => Auth::guard('api')->user()->id,
                        'role'=>null,
                        'appel_id'=>$request->appel_id,
                        'prospect_id'=>$request->prospect_id,
                        'projet_id'=>$request->projet_id,
                        'traite_appel_id'=>$request->traite_appel_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($request->traite_appel_id));

                    $relance=new Relance_Rdv_Appel();
                    $relance->setConnection('temp');
                    $relance->type=1;//relance
                    $relance->mode_relance=$request->mode_relance;
                    $relance->date_relance=$request->date_relance;
                    $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                    $relance->traite_appel_id=$request->traite_appel_id;
                    $relance->save();
                    Config::set('broadcasting.default', 'pusher_5');
                    broadcast(new NotifMenuEvent('F'));
                }
                if ($request->rdv != null) {
                    $data_notif = [
                        'lien' => '/crm/appels/'.$request->appel_id,
                        'date' => $request->rdv,
                        'type' => 28,
                        'description' => 'RDV APPEL',
                        'user_id' => Auth::guard('api')->user()->id,
                        'role'=>null,
                        'appel_id'=>$request->appel_id,
                        'prospect_id'=>$request->prospect_id,
                        'projet_id'=>$request->projet_id,
                        'traite_appel_id'=>$request->traite_appel_id,

                    ];
                    $notif_helper = new NotificationHelper();
                    $notif_helper->storeNotification($request->merge($data_notif));
                    broadcast(new NotificationEvent($request->traite_appel_id));

                    $rdv=new Relance_Rdv_Appel();
                    $rdv->setConnection('temp');
                    $rdv->type=2;//rdv
                    $rdv->rdv=$request->rdv;
                    $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                    $rdv->traite_appel_id=$request->traite_appel_id;
                    $rdv->save();
                    Config::set('broadcasting.default', 'pusher_5');
                    broadcast(new NotifMenuEvent('E'));
                }
                return response()->json('success');

    }

    public function store_types_bien_appel($valeur,$t_appel_id){
        DatabaseHelper::Config();
        //store appel w traitement
        $tp_b = new TypeBienAppel();
        $tp_b->setConnection('temp');
        $tp_b->traite_appel_id=$t_appel_id;
        $tp_b->type_bien_id=$valeur;
        $tp_b->save();
        return response()->json('success');

    }


    public function store(StoreAppelRequest $request)
    {
            if(RoleHelper::ACSup()){

                $user = Auth::user();
                DatabaseHelper::Config();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $prospect_id=null;
                if($request->prospect_id=="null"||$request->prospect_id==null){

                    $validatedData = $request->validated();
                    $validatedData['cin']=$request->cin=="null"?null:$request->cin;
                    $validatedData['nom']=$request->nom=="null"?null:$request->nom;
                    $validatedData['prenom']=$request->prenom=="null"?null:$request->prenom;
                    $validatedData['source']=$request->source=="null"?null:$request->source;
                    if($request->source_txt == 'PARTENAIRE'|| $request->source_txt == 'Partenaire'){
                       $validatedData['partenaire_id']=$request->partenaire_id=="null"?null:$request->partenaire_id;
                    }else{
                       $validatedData['partenaire_id']=null;
                    }
                    $validatedData['telephone']=$request->telephone=="null"?null:$request->telephone;
                    $validatedData['telephone_num2']=$request->telephone_num2=="null"?null:$request->telephone_num2;
                    $validatedData['origin']='appel';
                    $validatedData['notifie']=$request->notifie=="null"?null:$request->notifie;
                    $validatedData['ville']=$request->ville=="null"?null:$request->ville;
                    $validatedData['projet_id']=$request->projet_id;
                    $prospectController = new ProspectController();
                    $prospect = $prospectController->store(new StoreProspectRequest($validatedData));
                }
                else{

                    //recupere le prospect //modifier info
                    $prospect= Prospect::on('temp')->findorfail($request->prospect_id);
                    //$prospect->cin=$request->cin;
                    if($request->cin!="null"){
                        $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$request->prospect_id)->count();
                        if($cin_exist==0){
                            $prospect->cin=$request->cin;
                        }
                    }
                    $prospect->projet_id=$request->projet_id;
                    $prospect->nom= $request->nom=="null"?null:$request->nom;
                    $prospect->prenom=$request->prenom=="null"?null:$request->prenom;
                    $prospect->telephone=  $request->telephone=="null"?null:$request->telephone;
                    $prospect->telephone_num2=$request->telephone_num2=="null"?null:$request->telephone_num2;
                    $prospect->ville= $request->ville=="null"?null:$request->ville;
                    $prospect->source=$request->source=="null"?null:$request->source;;
                    if ($request->source_txt == 'PARTENAIRE'||$request->source_txt == 'Partenaire') {
                    $prospect->partenaire_id = $request->partenaire_id=="null"?null:$request->partenaire_id;
                    }else{
                        $prospect->partenaire_id = null;
                    }
                    $prospect->save();
                }

                //fetch if prosect_id has already an appel

                $appels_prospect=Appel::on('temp')->where('prospect_id',$prospect->id)->first();
                if($appels_prospect!=null){
                    //store new traitement
                    $data = [
                        'appel_id' => $appels_prospect->id,
                        'user_id' =>$userAuth->value('id'),
                        'type_appel' => $request->type_appel,
                        'date' => Carbon::now(),
                        'interet' => $request->interet,
                        'etat' => 1,
                        'date_traitement' => Carbon::now(),
                        'tranche_id' => $request->tranche_id=="null"?null:$request->tranche_id,
                        'bloc_id' => $request->bloc_id=="null"?null:$request->bloc_id,
                        'immeuble_id' => $request->immeuble_id=="null"?null:$request->immeuble_id,
                        'type_biens'=>$request->type_biens,
                        'etage' => $request->etage,
                        'orientation' => $request->orientation=="null"?null:$request->orientation,
                        'projet_id'=>$request->projet_id,
                        'prospect_id'=>$prospect->id,
                        'date_relance'=>$request->date_relance=="null"?null:$request->date_relance,
                        'mode_relance'=>$request->mode_relance=="null"?null:$request->mode_relance,
                        'rdv'=>$request->rdv=="null"?null:$request->rdv,
                        'commentaire'=> $request->commentaire=="null"?null:$request->commentaire
                    ];
                    $this->store_trait_appel($request->merge($data));
                }else{
                    //store appel w traitement
                    $appel = new Appel();
                    $appel->setConnection('temp');
                    $appel->projet_id=$request->projet_id;
                    $appel->prospect_id=$prospect->id;
                    if($appel->save()){

                    $data = [
                        'appel_id' => $appel->id,
                        'user_id' =>$userAuth->value('id'),
                        'type_appel' => $request->type_appel,
                        'date' => Carbon::now(),
                        'interet' => $request->interet,
                        'etat' => 1,
                        'date_traitement' => Carbon::now(),
                        'tranche_id' => $request->tranche_id=="null"?null:$request->tranche_id,
                        'bloc_id' => $request->bloc_id=="null"?null:$request->bloc_id,
                        'immeuble_id' => $request->immeuble_id=="null"?null:$request->immeuble_id,
                        'type_biens'=>$request->type_biens,
                        'etage' => $request->etage,
                        'orientation' => $request->orientation=="null"?null:$request->orientation,
                        'projet_id'=>$request->projet_id,
                        'prospect_id'=>$prospect->id,
                        'date_relance'=>$request->date_relance=="null"?null:$request->date_relance,
                        'mode_relance'=>$request->mode_relance=="null"?null:$request->mode_relance,
                        'rdv'=>$request->rdv="null"?null:$request->rdv,
                        'commentaire'=> $request->commentaire=="null"?null:$request->commentaire
                    ];
                       $this->store_trait_appel($request->merge($data));
                    }
                     // store statut du  prospect
                    $statut_pro = new StatutProspect();
                    $statut_pro->setConnection('temp');
                    $statut_pro->prospect_id=$prospect->id;
                    $statut_pro->statut='5';
                    $statut_pro->date_traitement = Carbon::now();
                    $statut_pro->user_id_traite = $userAuth->value('id');
                    $statut_pro->appel_id = $appel->id;
                    $statut_pro->save();
                    }



                return response()->json(['message' => 'appel added.'], 200);


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }



    }
    public function show_t_appel($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $tr_appel = TraitementAppel::on('temp')->with('frein','relance','rdv','tranche','bloc','immeuble','type_biens')->findOrfail($id);
            $frein=new FreinController();
            if($tr_appel->interet==InteretEnumAppel::Perdu->value) {
                $tr_appel['frein']=$frein->searchFreinByAppelId($id,'without_row_deleted');
            }
            return response()->json(['tr_appel' => $tr_appel,'frein'=>$tr_appel['frein']], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update(Request $request,$id)
    {
            if(RoleHelper::ACSup()){
                $user = Auth::user();
                DatabaseHelper::Config();
                $traite_appel=TraitementAppel::on('temp')->findorfail($id);
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

                //update info proqspect
                //recupere le prospect //modifier info
                 $prospect= Prospect::on('temp')->findorfail($request->prospect_id);

                 //$prospect->cin=$request->cin;
                 if($request->cin!="null"){
                     $cin_exist=Prospect::on('temp')->where('cin',$request->cin)->where('id','!=',$request->prospect_id)->count();
                     if($cin_exist==0){
                         $prospect->cin=$request->cin;
                     }
                 }
                 $prospect->nom= $request->nom=="null"?null:$request->nom;
                 $prospect->prenom=$request->prenom=="null"?null:$request->prenom;
                 $prospect->telephone=  $request->telephone=="null"?null:$request->telephone;
                 $prospect->telephone_num2=$request->telephone_num2=="null"?null:$request->telephone_num2;
                 $prospect->ville= $request->ville=="null"?null:$request->ville;
                 $prospect->source=$request->source=="null"?null:$request->source;;
                 if ($request->source_txt == 'PARTENAIRE'|| 'Partenaire') {
                 $prospect->partenaire_id = $request->partenaire_id;
                 }else{
                     $prospect->partenaire_id = null;
                 }
                 $prospect->save();

                 $traite_appel->type_appel=$request->type_appel;
                 $traite_appel->date=Carbon::now();
                 $traite_appel->interet=$request->interet;
                 $traite_appel->etat=1;
                 $traite_appel->date_traitement=Carbon::now();
                 $traite_appel->commentaire=$request->commentaire;
                 if($request->interet==InteretEnumAppel::Intéressé->value){
                     $traite_appel->tranche_id=$request->tranche_id;
                     $traite_appel->bloc_id=$request->bloc_id;
                     $traite_appel->immeuble_id=$request->immeuble_id;
                     $traite_appel->etage=$request->etage;
                     $traite_appel->orientation=$request->orientation;
                 }else{
                    $traite_appel->tranche_id=null;
                    $traite_appel->bloc_id=null;
                    $traite_appel->immeuble_id=null;
                    $traite_appel->etage=null;
                    $traite_appel->orientation=null;
                 }
                 if($traite_appel->save()){
                    $data = [
                        'appel_id' => $traite_appel->appel_id,
                        'traite_appel_id' => $id,
                        'prospect_id'=>$request->prospect_id=="null"?null:$request->prospect_id,
                        'project_id'=>$request->project_id=="null"?null:$request->project_id,
                        'date_relance'=>$request->date_relance,
                        'mode_relance'=>$request->mode_relance=="null"?null:$request->mode_relance,
                        'rdv'=>$request->rdv=="null"?null:$request->rdv,
                        ];

                        if($request->interet==InteretEnumAppel::Réceptif->value){
                            //delete old notif + set old relance rdv traite automatique

                             //SUPPRIMER LES OLDS NOTIF
                                $notif_old_relance=Notification::on('temp')->where(function ($query){
                                    $query->where('type',27)
                                        ->orwhere('type',28);})
                                    ->where(function ($query_2) use($id){
                                            $query_2->where('traite_appel_id',$id);})
                                    ->get();
                                    if(($notif_old_relance->count())>0){
                                    foreach($notif_old_relance as $nt_r){
                                        $nt_r->delete();
                                    }
                                    }

                                  /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                                  $old_relances_rdv=Relance_Rdv_Appel::on('temp')->where('traite_appel_id',$id)->where('type_traitement',0)->get();
                                  if(count($old_relances_rdv)>0){
                                      foreach($old_relances_rdv as $old){
                                          $old->setConnection('temp');
                                          $old->type_traitement=2;//auto
                                          $old->date_traitement=Carbon::now();
                                          $old->user_id_traite=$userAuth->value('id');
                                          $old->save();
                                      }
                                  }
                                  //update t_appel
                                   // $this->store_relance_rdv($request->merge($data));
                                   Config::set('broadcasting.default', 'pusher_3');
                                   if ($request->date_relance != null) {

                                    $data_not = [
                                        'lien' => '/crm/appels/'.$request->appel_id,
                                        'date' => $request->date_relance,
                                        'type' => 27,
                                        'description' => 'RELANCE APPEL',
                                        'user_id' => Auth::guard('api')->user()->id,
                                        'role'=>null,
                                        'appel_id'=>$request->appel_id,
                                        'prospect_id'=>$request->prospect_id,
                                        'projet_id'=>$request->projet_id,
                                        'traite_appel_id'=>$id,

                                    ];

                                    $notif_helper = new NotificationHelper();
                                    $notif_helper->storeNotification($request->merge($data_not));
                                    broadcast(new NotificationEvent($id));

                                    $relance=new Relance_Rdv_Appel();
                                    $relance->setConnection('temp');
                                    $relance->type=1;//relance
                                    $relance->mode_relance=$request->mode_relance;
                                    $relance->date_relance=$request->date_relance;
                                    $relance->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                    $relance->traite_appel_id=$id;
                                    $relance->save();
                                    Config::set('broadcasting.default', 'pusher_5');
                                    broadcast(new NotifMenuEvent('F'));
                                }
                                if ($request->rdv != null) {
                                    $data_notif = [
                                        'lien' => '/appels/show/'.$request->appel_id,
                                        'date' => $request->rdv,
                                        'type' => 28,
                                        'description' => 'RDV APPEL',
                                        'user_id' => Auth::guard('api')->user()->id,
                                        'role'=>null,
                                        'appel_id'=>$request->appel_id,
                                        'prospect_id'=>$request->prospect_id,
                                        'projet_id'=>$request->projet_id,
                                        'traite_appel_id'=>$id,

                                    ];
                                    $notif_helper = new NotificationHelper();
                                    $notif_helper->storeNotification($request->merge($data_notif));
                                    broadcast(new NotificationEvent($id));

                                    $rdv=new Relance_Rdv_Appel();
                                    $rdv->setConnection('temp');
                                    $rdv->type=2;//rdv
                                    $rdv->rdv=$request->rdv;
                                    $rdv->type_traitement=0;//0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                    $rdv->traite_appel_id=$id;
                                    $rdv->save();
                                    Config::set('broadcasting.default', 'pusher_5');
                                    broadcast(new NotifMenuEvent('E'));
                                }

                        }
                        elseif($request->interet==InteretEnumAppel::Intéressé->value){

                             //SUPPRIMER LES OLDS NOTIF
                             $notif_old_relance=Notification::on('temp')->where(function ($query){
                                $query->where('type',27)
                                    ->orwhere('type',28);})
                                ->where(function ($query_2) use($id){
                                        $query_2->where('traite_appel_id',$id);})
                                ->get();
                                if(($notif_old_relance->count())>0){
                                foreach($notif_old_relance as $nt_r){
                                    $nt_r->delete();
                                }
                                }
                                //traitement
                                //destroy ancien type_biens
                                $old_type_biens=TypeBienAppel::on('temp')->where('traite_appel_id',$id)->get();
                                if(count($old_type_biens)>0){
                                    foreach($old_type_biens as $old){
                                        $old->forceDelete();
                                    }
                                }
                                //store_type_bien by appel
                                if (!empty($request->type_biens)) {

                                    $array_tp_b= explode(',', $request->type_biens); // $tranches_array sera ['5', '2']
                                    foreach ($array_tp_b as $valeur) {
                                        $this->store_types_bien_appel($valeur,$id);
                                    }
                                }
                                    //store relance to table
                           $this->store_relance_rdv($request->merge($data));


                        }
                        elseif ($traite_appel->interet == InteretEnumAppel::Perdu->value) {
                            $frein_id=Frein::on('temp')->where('traite_appel_id', $id)->get();
                            $freinRequest['traite_appel_id']=$id;
                            $freinRequest['prix_min']=$request->prix_min;
                            $freinRequest['prix_min']=$request->prix_min;
                            $freinRequest['freins']=$request->freins;
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

                                $freinRequest['traite_appel_id']=$id;
                                $freinController->store(new StoreFreinRequest($freinRequest));
                            }
                        }

                        else {

                                $frein=Frein::on('temp')->where('traite_appel_id', $id)->get();
                                if(!$frein->isEmpty()){
                                    $freinController=new FreinController();
                                    $freinController->destroy($frein->value('id'));
                                }
                        }

                 }
                return response()->json('c bon');


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }



    }
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $appel =Appel::on('temp')->with('prospect')->findOrfail($id);
            return response()->json(['appel' => $appel], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function get_nb_relances_appels($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){

            $nb_relances_appels =Relance_Rdv_Appel::on('temp')->with('traite_appel')
                ->whereHas('traite_appel.appel', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                })
                ->whereDate('date_relance', '<=', Carbon::now())->where('type_traitement', 0)->where('type', 1)->count();
            }
            else{
                $nb_relances_appels =Relance_Rdv_Appel::on('temp')->with('traite_appel')
                ->whereHas('traite_appel.appel', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                })
                ->whereHas('traite_appel', function ($q) use ($userAuth) {
                        $q->where('user_id', $userAuth->value('id'));
                })
                ->whereDate('date_relance', '<=', Carbon::now())->where('type_traitement', 0)->where('type', 1)
                ->count();
            }

            return response()->json(['nb' => $nb_relances_appels], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function get_nb_rdv_appels($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            if(RoleHelper::AdminSup()){

            $nb_rdv_appels =Relance_Rdv_Appel::on('temp')->with('traite_appel')
                ->whereHas('traite_appel.appel', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                })->whereDate('rdv', '<=', Carbon::now())->where('type_traitement', 0)->where('type', 2)->count();
            }
            else{
            $nb_rdv_appels =Relance_Rdv_Appel::on('temp')->with('traite_appel')
                ->whereHas('traite_appel.appel', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                })->whereDate('rdv', '<=', Carbon::now())->where('type_traitement', 0)
                ->where('type', 2)
                ->whereHas('traite_appel', function ($q) use ($userAuth) {
                    $q->where('user_id', $userAuth->value('id'));
                })
                ->count();
            }

            return response()->json(['nb' => $nb_rdv_appels], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function get_relances_rdv_appels(Request $request, $projet_id)
    {

        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $query = Relance_Rdv_Appel::on('temp')->with('traite_appel')
                ->where('type_traitement', 0)->where('type', $request->type)
                ->whereHas('traite_appel.appel', function ($q) use ($projet_id) {
                    $q->where('projet_id', $projet_id);
                });
            if(!RoleHelper::AdminSup()){
                $query->whereHas('traite_appel', function ($q) use ($userAuth) {
                    $q->where('user_id', $userAuth->value('id'));
                });
            }
            if($request->type==1){
                $query->whereDate('date_relance', '<=', Carbon::now());
            }else{
                $query->whereDate('rdv', '<=', Carbon::now());
            }
            if ($request->filled('nom_prenom')){
                    $query->whereHas('traite_appel.appel.prospect', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->input('nom_prenom') . '%')
                    ->orWhere('prenom', 'like', '%' . $request->input('nom_prenom') . '%');});
            }
            if ($request->filled('cin')){
                $query->whereHas('traite_appel.appel.prospect', function ($q) use ($request) {
                $q->where('cin', 'like', '%' . $request->input('cin') . '%');});
            }
            if ($request->filled('telephone')){
                $query->whereHas('traite_appel.appel.prospect', function ($q) use ($request) {
                $q->where('telephone', 'like', '%' . $request->input('telephone') . '%');});
            }
            if ($request->filled('mode_relance')) {
                $query->where('mode_relance', $request->input('mode_relance'));
            }

            if ($request->filled('date_relance')) {
                $start = Carbon::parse($request->input('date_relance'));
                $query->whereDate('date_relance', $start);
            }
            if ($request->filled('rdv')) {
                $start = Carbon::parse($request->input('rdv'));
                $query->whereDate('rdv', $start);
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $relances = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $relances->currentPage(),
                    'totalItems' => $relances->total(),
                    'totalPages' => $relances->lastPage(),
                ];

                $relances = $relances->items();

                return response()->json([
                    'data' => $relances,
                    'pagination' => $pagination,
                ], 200);
            } else {
            $relances = $query->orderBy('created_at', 'desc')
            ->get();
            return response()->json(['relances' => $relances]);

            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }



    public function index_traitement_appel(Request $request, $id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = TraitementAppel::on('temp')->with('tranche','bloc','immeuble','type_biens','rdv','relance','frein')->where('appel_id', $id);

            if ($request->filled('date')) {
                $start = Carbon::parse($request->input('date'));
                $query->whereDate('date', $start);            }
            if ($request->filled('responsable')) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->input('responsable') . '%')
                          ->orWhere('prenom', 'like', '%' . $request->input('responsable') . '%');
                    });
                });
            }

            if ($request->filled('type_appel')) {
                $query->where('type_appel',  $request->input('type_appel'));
            }
            if ($request->filled('interet')) {
                $query->where('interet',  $request->input('interet'));
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $t_appels = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $t_appels->currentPage(),
                    'totalItems' => $t_appels->total(),
                    'totalPages' => $t_appels->lastPage(),
                ];

                $t_appels = $t_appels->items();

                return response()->json([
                    'data' => $t_appels,
                    'pagination' => $pagination,
                ], 200);
            } else {
            $t_appels = $query->orderBy('created_at', 'desc')
            ->get();
            return response()->json(['data' => $t_appels]);

            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $appel = Appel::on('temp')->findOrFail($id);
            $traitement_appels=TraitementAppel::on('temp')->where('appel_id',$id)->get();
            if(count($traitement_appels)>0){
                foreach($traitement_appels as $tr_ap){
                 $this->destroy_t_appel($tr_ap->id,0);
                }
            }
            if ($appel->delete()) {
                return response()->json(['message' => 'appel supprimée avec succès.'], 200);
            } else {
                return response()->json(['error' => "L appel n'a pas été supprimée."], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

//destroy traitement appel
    public function destroy_t_appel($id,$number)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $t_appel = TraitementAppel::on('temp')->findOrFail($id);
            $relances_rdv=Relance_Rdv_Appel::on('temp')->where('traite_appel_id',$id)->get();
            if(count($relances_rdv)>0){
                foreach($relances_rdv as $r){
                    $r->delete();
                }
            }
            $freins=Frein::on('temp')->where('traite_appel_id',$id)->get();
            if(count($freins)>0){
                foreach($freins as $fr){
                    $fr->setConnection('temp');
                    $fr->traite_appel_id=null;
                    $fr->save();
                }
            }
            $histo=HistoriqueBien::on('temp')->where('t_appel_id',$id)->get();
            if(count($histo)>0){
                foreach($histo as $hs){
                    $hs->setConnection('temp');
                    $hs->t_appel_id=null;
                    $hs->save();
                }
            }
            $pre=PreReservation::on('temp')->where('appel_id',$id)->get();
            if(count($pre)>0){
                foreach($pre as $pr){
                    $pr->delete();
                }
            }

            $notif=Notification::on('temp')->where('traite_appel_id',$id)->get();
            if(count($notif)>0){
                foreach($notif as $nt){
                    $nt->delete();
                }
            }
            $tp_b_appel=TypeBienAppel::on('temp')->where('traite_appel_id',$id)->get();
            if(count($tp_b_appel)>0){
                foreach($tp_b_appel as $t){
                    $t->delete();
                }
            }
            $notif = new NotificationController();
            $notif->destory_force_by_column_id('t_appel', $id);
            $app_id=$t_appel->appel_id;
            if ($t_appel->delete()) {
                if($number==1){
                    $traitement_appels=TraitementAppel::on('temp')->where('appel_id',$app_id)->get();
                    //ya aucun traitements appels
                    if(count($traitement_appels)==0){
                        $appel = Appel::on('temp')->findOrFail($t_appel->appel_id);
                        $appel->delete();
                    }
                }

                return response()->json(['message' => 'traitement appel supprimée avec succès.'], 200);
            } else {
                return response()->json(['error' => "Le traitement appel n'a pas été supprimée."], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public  function traiter_relance_rdv_appel($id,UpdateDate_relance_Rdv $request)
    {
        if(RoleHelper::ACSup()) {
           // Config::set('broadcasting.default', 'pusher_5');
            DatabaseHelper::Config();
            $user = Auth::user();
          //  $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $relance = Relance_Rdv_Appel::on('temp')->findOrFail($id);

            //if date !=null (nouvelle relance )
            if($request->date!=null ){
                        $traite_appel_id=$relance->traite_appel_id;
                        $prospect_id=$relance->traite_appel->appel->prospect_id;
                        $old_mode_relance=$relance->mode_relance;
                        $relance->type_traitement=3;//demi traitement
                        $relance->date_traitement=Carbon::now();
                        $relance->commentaire=$request->commentaire;
                        if($relance->save()){
                            //delete old notificcation
                            $type=0;
                            //si relance ou rdv
                            if($relance->type==1){
                                $type=27;
                            }else{
                                $type=28;
                            }
                            $notif_exist_relance=Notification::on('temp')->where('type',$type)->where('traite_appel_id',$traite_appel_id)->get();
                            if(count($notif_exist_relance)>0){
                                foreach($notif_exist_relance as $nt){
                                    $nt->delete();
                                }
                            }
                                //store new relance
                            $new_relance=new Relance_Rdv_Appel();
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
                            $new_relance->traite_appel_id=$traite_appel_id;
                            $new_relance->save();

                            if($relance->type==1){
                            //store new notification
                            Config::set('broadcasting.default', 'pusher_3');

                            $data_notif = [
                                'lien' => '/crm/appels/'.$new_relance->traite_appel->appel->id,
                                'date' => $request->date,
                                'type' => 27,
                                'description' => 'RELANCE APPEL',
                                'user_id' => Auth::guard('api')->user()->id,
                                'role'=>null,
                                'traite_appel_id'=>$traite_appel_id,
                                'prospect_id'=>$prospect_id,
                                'projet_id'=>$new_relance->traite_appel->appel->projet_id,

                            ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($new_relance->id));
                           Config::set('broadcasting.default', 'pusher_5');
                             broadcast(new NotifMenuEvent('F'));
                            }
                            else{
                                //store new notification
                                Config::set('broadcasting.default', 'pusher_3');
                                $data_notif = [
                                    'lien' => '/crm/appels/'.$new_relance->traite_appel->appel->id,
                                    'date' => $request->date,
                                    'type' => 28,
                                    'description' => 'RDV APPEL',
                                    'user_id' => Auth::guard('api')->user()->id,
                                    'role'=>null,
                                    'traite_appel_id'=>$traite_appel_id,
                                    'prospect_id'=>$prospect_id,
                                    'projet_id'=>$new_relance->traite_appel->appel->projet_id,

                                ];
                            $notif_helper = new NotificationHelper();
                            $notif_helper->storeNotification($request->merge($data_notif));
                            broadcast(new NotificationEvent($new_relance->id));
                            Config::set('broadcasting.default', 'pusher_5');
                            broadcast(new NotifMenuEvent('E'));

                            }
                            return response()->json(['message' => $new_relance], 200);
                        }
            }else{

                // si date ==null la relance /rdv  est traité


                        $relance->type_traitement=1;//manuelle
                        $relance->commentaire=$request->commentaire;
                        $relance->date_traitement=Carbon::now();
                        if($relance->save()){
                            $type=0;
                            //si relance ou rdv
                            if($relance->type==1){
                                $type=27;
                            }else{
                                $type=28;
                            }
                            //delete old notificcation
                            $notif_exist_relance=Notification::on('temp')->where('type',$type)->where('traite_appel_id',$relance->traite_appel_id)->get();
                            if(count($notif_exist_relance)>0){
                                foreach($notif_exist_relance as $nt){
                                    $nt->delete();
                                }
                            }

                           Config::set('broadcasting.default', 'pusher_5');
                           if($relance->type==1){
                            //relance
                           broadcast(new NotifMenuEvent('F'));
                           }else{
                            //rdv
                            broadcast(new NotifMenuEvent('E'));
                           }
                            return response()->json(['message' => 'Validé avec succès.'], 200);
                        }

            }


       } else {
           return response()->json(['error' => 'Unauthorized'], 401);
       }

    }

    /**
     * Display the specified resource.
     */


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


    /**
     * Remove the specified resource from storage.
     */


     public function get_info_cin_unique($id,$cin)
    {
            if(RoleHelper::ACSup()){
                DatabaseHelper::Config();
                //cin unique
                $prospect_count=Prospect::on('temp')->where('cin',$cin)->where('id','!=',$id)->count();
                return response()->json(['prospect_count' => $prospect_count]);


            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }



    }

}

