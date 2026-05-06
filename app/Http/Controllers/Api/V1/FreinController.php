<?php

namespace App\Http\Controllers\Api\V1;
use Carbon\Carbon;
use App\Events\NotificationEvent;
use App\Events\NotifMenuEvent;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\FreinBienHelper;
use App\Http\Helpers\FreinEtageHelper;
use App\Http\Helpers\FreinOrientationHelper;
use App\Http\Helpers\FreinTrancheHelper;
use App\Http\Helpers\FreinTypologieHelper;
use App\Http\Helpers\FreinVueHelper;
use App\Http\Helpers\NotificationHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreFreinRequest;
use App\Http\Requests\Traite_Bien_freinRequest;
use App\Http\Requests\UpdateFreinRequest;
use App\Models\Frein;
use App\Models\TraitementFrein;
use App\Models\Visite;
use App\Models\FreinEtage;
use App\Models\FreinOrientation;
use App\Models\FreinTranche;
use App\Models\FreinTypologie;
use App\Models\FreinVue;
use App\Models\Frein_Bien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Enum\InteretEnum;
use App\Http\Helpers\Bien_Helper;
use App\Models\Relance_Rdv_Visite;


class FreinController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $freins = Frein::on('temp')->get();
            return response()->json(['freins' => $freins]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

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
    public function store(StoreFreinRequest $request)
    {

        // In FreinController store method
        \Log::info('Processing Frein:', [
            'freins' => $request->freins,
            'selectedEtages' => $request->selectedEtages,
            'contains_ETAGE' => str_contains(strtoupper($request->freins), 'ETAGE'),
            'has_selectedEtages' => $request->has('selectedEtages'),
            'selectedEtages_value' => $request->selectedEtages
        ]);
        if (RoleHelper::ACSup()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();
            $frein = new Frein();
            $frein->setConnection('temp');
            if (str_contains($request->freins, 'AUTRE')==true||str_contains($request->freins, 'autre')==true) {
                $frein->description_autre = $request->description_autre;
            }else{
                $frein->description_autre = null;
            }
            $frein->prix_min = $request->prix_min;
            $frein->prix_max = $request->prix_max;
            $frein->superficie_min = $request->sup_min;
            $frein->superficie_max = $request->sup_max;
            $frein->etat = $request->etat;
            $frein->avance = $request->avance;
            $frein->visite_id = $request->visite_id;
            $frein->traite_appel_id = $request->traite_appel_id;
            $frein->tranche = empty($request->selectedTranches) ? false : true;
            $frein->etage = empty($request->selectedEtages) ? false : true;
            $frein->orientation = empty($request->selectedOrientations) ? false : true;
            $frein->vue = empty($request->selectedVues) ? false : true;
            $frein->typologie = empty($request->selectedTypologies) ? false : true;
            if ($frein->save()) {
                if (!empty($request->selectedTranches)) {
                    $tranches_array = explode(',', $request->selectedTranches); // $tranches_array sera ['5', '2']
                    foreach ($tranches_array as $valeur) {
                        FreinTrancheHelper::createFreinTranche($valeur, $frein->id);
                    }
                }


                                // Use this simpler condition:
                    if (!empty($request->selectedEtages)) {
                        if ($request->has('selectedEtages') && $request->selectedEtages !== '') {
                            $array_etage = explode(',', $request->selectedEtages);
                            foreach ($array_etage as $valeur) {
                                $intValue = (int)$valeur;
                                if (is_numeric($valeur)) {
                                    $valueToStore = ($intValue === -100) ? 0 : $intValue;
                                    FreinEtageHelper::createFreinEtage($valueToStore, $frein->id);
                                }
                            }
                        }
                    }


                if (!empty($request->selectedOrientations)) {
                    $array_orientation = explode(',', $request->selectedOrientations); // $tranches_array sera ['5', '2']
                    foreach ($array_orientation as $valeur) {
                        FreinOrientationHelper::createFreinOrientation($valeur, $frein->id);
                    }
                }
                if (!empty($request->selectedTypologies)) {
                    $array_typologie = explode(',', $request->selectedTypologies); // $tranches_array sera ['5', '2']
                    foreach ($array_typologie as $valeur) {
                        FreinTypologieHelper::createFreinTypologie($valeur, $frein->id);
                    }
                }
                if (!empty($request->selectedVues)) {
                    $array_vue = explode(',', $request->selectedVues); // $tranches_array sera ['5', '2']
                    foreach ($array_vue as $valeur) {
                        FreinVueHelper::createFreinVue($valeur, $frein->id);
                    }
                }
                return response()->json(['frein' => $frein], 200);
            }
            return response()->json(['error' => "Cette visite n'est pas du type perdu."], 520);
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
            DatabaseHelper::Config();
            $frein = Frein::on('temp')->findOrfail($id);
            if ($frein->exists()) {
                if ($frein->value('tranche') == true) {
                    $frein_tranches = FreinTranche::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_tranches'] = $frein_tranches;
                }
                if ($frein->value('etage') == true) {
                    $frein_etages = FreinEtage::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_etages'] = $frein_etages;
                }
                if ($frein->value('vue') == true) {
                    $frein_vues = FreinVue::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_vues'] = $frein_vues;
                }
                if ($frein->value('typologie') == true) {
                    $frein_typologies = FreinVue::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_typologies'] = $frein_typologies;
                }
                if ($frein->value('orientation') == true) {
                    $frein_orientations = FreinOrientation::on('temp')->where('frein_id', $frein->id)->get();
                    $frein['frein_orientations'] = $frein_orientations;
                }
            }
            return response()->json(['frein' => $frein], 200);
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
    public function update(UpdateFreinRequest $request, $id)
    {
        if(RoleHelper::ACSup()||RoleHelper::RespoCommercial()){
            DatabaseHelper::Config();
            $frein=Frein::on('temp')->findOrFail($id);
            if (str_contains($request->freins, 'AUTRE')==true||str_contains($request->freins, 'autre')==true) {
                $frein->description_autre = $request->description_autre;
            }else{
                $frein->description_autre = null;
            }
            if (str_contains($request->freins, 'SUPERFICIE')==true) {
                $frein->superficie_min=$request->sup_min;
                $frein->superficie_max=$request->sup_max;
            }
            else{
                $frein->superficie_min=null;
                $frein->superficie_max=null;
            }

            if (str_contains($request->freins, 'PRIX')==true) {
                $frein->prix_min=$request->prix_min;
                $frein->prix_max=$request->prix_max;
            }
            else{
                $frein->prix_min=null;
                $frein->prix_max=null;
            }
            if (str_contains($request->freins, 'AVANCE')==true) {
                $frein->avance=$request->avance;
            }else{
                $frein->avance=null;
            }
            $frein->etat=$request->etat;
            $frein->tranche=str_contains($request->freins, 'TRANCHE')?true:false;
            $frein->etage = str_contains(strtoupper($request->freins), 'ETAGE') ? true : false;
            $frein->orientation= str_contains($request->freins, 'ORIENTATION')?true:false;
            $frein->vue=str_contains($request->freins, 'VUE')?true:false;
            $frein->typologie= str_contains($request->freins, 'TYPOLOGIE') ?true:false;
            $frein->save();
            FreinTrancheHelper::destroyFreinTranche($frein->id);
            if(!empty($request->selectedTranches) && str_contains($request->freins, 'TRANCHE') ){
                $tranches_array = explode(',', $request->selectedTranches); // $tranches_array sera ['5', '2']
                foreach($tranches_array as $valeur){
                        FreinTrancheHelper::createFreinTranche($valeur,$frein->id);
                }
            }



                    FreinEtageHelper::destroyFreinEtage($frein->id);



                     if (!empty($request->selectedEtages)) {
                        if ($request->has('selectedEtages') && $request->selectedEtages !== '') {
                            $array_etage = explode(',', $request->selectedEtages);
                            foreach ($array_etage as $valeur) {
                                $intValue = (int)$valeur;
                                if (is_numeric($valeur)) {
                                    $valueToStore = ($intValue === -100) ? 0 : $intValue;
                                    FreinEtageHelper::createFreinEtage($valueToStore, $frein->id);
                                }
                            }
                        }
                    }


            FreinOrientationHelper::destroyFreinOrientation($frein->id);
            if (!empty($request->selectedOrientations)&& str_contains($request->freins, 'ORIENTATION')) {
                $array_orientation = explode(',', $request->selectedOrientations); // $tranches_array sera ['5', '2']
                foreach ($array_orientation as $valeur) {
                    FreinOrientationHelper::createFreinOrientation($valeur, $frein->id);
                }
            }
            FreinTypologieHelper::destroyFreinTypologie($frein->id);
            if (!empty($request->selectedTypologies)&& str_contains($request->freins, 'TYPOLOGIE')) {
                $array_typologie = explode(',', $request->selectedTypologies); // $tranches_array sera ['5', '2']
                foreach ($array_typologie as $valeur) {
                    FreinTypologieHelper::createFreinTypologie($valeur, $frein->id);
                }
            }
            FreinVueHelper::destroyFreinVue($frein->id);
            if (!empty($request->selectedVues)&& str_contains($request->freins, 'VUE')) {
                $array_vue = explode(',', $request->selectedVues); // $tranches_array sera ['5', '2']
                foreach ($array_vue as $valeur) {
                    FreinVueHelper::createFreinVue($valeur, $frein->id);
                }
            }
             //destroy frein bien dispo
             FreinBienHelper::destroyFreinBien($frein->id);
             //notification des biens disponible pour ce frein
             if($frein->visite_id!=null){
                NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
             }
            return response()->json(['frein'=>$id]);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();
            $frein = Frein::on('temp')->findOrFail($id);
            if ($frein->tranche) {
                $freinTranches = FreinTranche::on('temp')->where('frein_id', $id)->get();
                foreach ($freinTranches as $freinTranche) {
                    $freinTranche->delete();
                }
            }
            if ($frein->etage) {
                $freinEtages = FreinEtage::on('temp')->where('frein_id', $id)->get();
                foreach ($freinEtages as $freinEtage) {
                    $freinEtage->delete();
                }
            }
            if ($frein->orientation) {
                $freinOrientations = FreinOrientation::on('temp')->where('frein_id', $id)->get();
                foreach ($freinOrientations as $freinOrientation) {
                    $freinOrientation->delete();
                }
            }
            if ($frein->typologie) {
                $freinTypologies = FreinTypologie::on('temp')->where('frein_id', $id)->get();
                foreach ($freinTypologies as $freinTypologie) {
                    $freinTypologie->delete();
                }
            }
            if ($frein->vue) {
                $freinVues = FreinVue::on('temp')->where('frein_id', $id)->get();
                foreach ($freinVues as $freinVue) {
                    $freinVue->delete();
                }

            }
            //destroy frein bien dispo
            FreinBienHelper::destroyFreinBien($id);
            //notification des biens disponible pour ce frein
            NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);

            $freinTraitement = TraitementFrein::on('temp')->where('frein_id', $id)->get();
            foreach ($freinTraitement as $freinTrai) {
                $freinTrai->delete();
            }
            //traitement frein
            if ($frein->delete()) {
                return response()->json(['message' => 'Frein supprimé avec succès.'], 200);
            } else {
                return response()->json(['error' => "Le frein n'a pas été supprimé."], 404);
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    private function syncRelationship($frein, $request, $relation, $modelClass, $pluckAtt)
    {
        if (!empty($frein->$relation)) {
            $frein->$relation()->sync($request);
        } else {
            $existingItems = $modelClass::on('temp')->where('frein_id', $frein->id)->pluck($pluckAtt)->toArray();
            if (!empty($existingItems)) {
                $frein->$relation()->detach($existingItems);
            }
        }
    }

/*public function searchFreinByVisiteId($id, $text)
    {
        if ($text == 'without_row_deleted') {
            $frein = Frein::on('temp')->where('visite_id', $id)->first();
            if ($frein) {

                $frein_tranches = FreinTranche::on('temp')->where('frein_id', $frein->id)->get();
                if (count($frein_tranches) > 0) {
                    $frein['frein_tranches'] = $frein_tranches;
                }

                $frein_etages = FreinEtage::on('temp')->where('frein_id', $frein->id)->get();
                if (count($frein_etages)) {
                    $frein['frein_etages'] = $frein_etages;
                }

                $frein_vues = FreinVue::on('temp')->where('frein_id', $frein->id)->get();
                if (count($frein_vues)) {
                    $frein['frein_vues'] = $frein_vues;
                }

                $frein_typologies = FreinTypologie::on('temp')->where('frein_id', $frein->id)->get();
                if (count($frein_typologies)) {
                    $frein['frein_typologies'] = $frein_typologies;
                }

                $frein_orientations = FreinOrientation::on('temp')->where('frein_id', $frein->id)->get();
                if (count($frein_orientations)) {
                    $frein['frein_orientations'] = $frein_orientations;
                }

                return $frein;
            } else {
                return null;
            }

        } else {

            $frein = Frein::on('temp')->withTrashed()->where('visite_id', $id)->first();
            if ($frein) {

                $frein_tranches = FreinTranche::on('temp')->withTrashed()->where('frein_id', $frein->id)->get();
                if (count($frein_tranches) > 0) {
                    $frein['frein_tranches'] = $frein_tranches;
                }

                $frein_etages = FreinEtage::on('temp')->withTrashed()->where('frein_id', $frein->id)->get();
                if (count($frein_etages)) {
                    $frein['frein_etages'] = $frein_etages;
                }

                $frein_vues = FreinVue::on('temp')->withTrashed()->where('frein_id', $frein->id)->get();
                if (count($frein_vues)) {
                    $frein['frein_vues'] = $frein_vues;
                }

                $frein_typologies = FreinTypologie::on('temp')->withTrashed()->where('frein_id', $frein->id)->get();
                if (count($frein_typologies)) {
                    $frein['frein_typologies'] = $frein_typologies;
                }

                $frein_orientations = FreinOrientation::on('temp')->withTrashed()->where('frein_id', $frein->id)->get();
                if (count($frein_orientations)) {
                    $frein['frein_orientations'] = $frein_orientations;
                }

                return $frein;
            } else {
                return null;
            }

        }
    }*/



   public function get_clients_freins(Request $request, $projet_id)
{
    if (!Auth::guard('api')->check()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Set default values for pagination
    $size = $request->input('size', 10); // Set a sensible default (e.g., 10)
    $page = $request->input('page', 1);  // Default to first page

    DatabaseHelper::Config();
    $user = Auth::user();
    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

    $query = Frein::on('temp')->with('visite','visite.prospect')
        ->where('etat', 2)
        ->whereHas('visite', function ($q) use ($projet_id) {
            $q->where('projet_id', $projet_id)->where('etat', 1);
        });

    /*if(!RoleHelper::AdminSup()) {
        $query->whereHas('visite', function ($q) use ($userAuth) {
            $q->where('user_id', $userAuth->value('id'));
        });
    }*/

    // Your existing filter conditions...
    if ($request->filled('nom_prenom')) {
        $query->whereHas('visite.prospect', function ($q) use ($request) {
            $q->where('nom', 'like', '%' . $request->input('nom_prenom') . '%')
              ->orWhere('prenom', 'like', '%' . $request->input('nom_prenom') . '%');
        });
    }

    // Other filter conditions...

    $clients = [];

    if($query->exists()) { // Changed from count() > 0 to exists() for better performance
        $freins = $query->get();

        foreach ($freins as $fr) {
            $fr_type = [];

            // Check each frein type and add to array
            if ($fr->tranche == 1) $fr_type[] = 'TRANCHE';
            if ($fr->etage == 1) $fr_type[] = 'ETAGE';
            if ($fr->orientation == 1) $fr_type[] = 'ORIENTATION';
            if ($fr->typologie == 1) $fr_type[] = 'TYPOLOGIE';
            if ($fr->vue == 1) $fr_type[] = 'VUE';
            if ($fr->avance != null) $fr_type[] = 'AVANCE';
            if ($fr->prix_min != null || $fr->prix_max != null) $fr_type[] = 'PRIX';
            if ($fr->superficie_min != null && $fr->superficie_max != null) $fr_type[] = 'SUPERFICIE';

            $clients[] = [
                'id' => $fr->id,
                'date' => $fr->created_at,
                'nom' => $fr->visite->prospect->nom,
                'prenom' => $fr->visite->prospect->prenom,
                'telephone' => $fr->visite->prospect->telephone,
                'telephone_2' => $fr->visite->prospect->telephone_num2,
                'id_origin' => $fr->visite->origin_id,
                'visite_id' => $fr->visite->id,
                'frein' => implode(',', $fr_type) // Convert array to comma-separated string
            ];
        }
    }

    // Ensure size is at least 1 to avoid division by zero
    $size = max(1, (int)$size);

    // Paginate the array of clients
    $data = PaginationHelper::paginate_array($clients, $size, $page, $request->url());

    return response()->json([
        'data' => $data->items(),
        'pagination' => [
            'currentPage' => $data->currentPage(),
            'totalItems' => $data->total(),
            'totalPages' => $data->lastPage(),
        ]
    ], 200);
}


    public function biens_by_frein(Request $request, $frein_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
           $query = Frein_Bien::on('temp')
                ->where('frein_id', $frein_id)
                ->with([
                    'is_proposed',  // Load this relationship
                    'bien' => function($query) {
                        $query->with([
                            'immeuble' => function($q) {
                                $q->select('id', 'nom')
                                ->without(['projet', 'tranche', 'bloc']);
                            },
                            'bloc' => function($q) {
                                $q->select('id', 'nom')
                                ->without(['projet', 'tranche']);
                            },
                            'tranche' => function($q) {
                                $q->select('id', 'nom')
                                ->without(['projet']);
                            }
                        ])->without('projet', 'typologie', 'vue', 'compositionBien');
                    }
                ]);

            if ($request->filled('bien_filtre')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', $request->bien_filtre);
                });
            }
            if ($request->filled('numero_filtre')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('numero', $request->numero_filtre);
                });
            }
            if ($request->filled('orientation_filtre')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('orientation', $request->orientation_filtre);
                });
            }

            if ($request->filled('type_filtre')) {
                $query->whereHas('bien.typeBien', function ($q) use ($request) {
                    $q->where('type', $request->type_filtre);
                });
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $all_biens= $query->orderBy('created_at', 'desc')->get();
                $biens = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $biens->currentPage(),
                    'totalItems' => $biens->total(),
                    'totalPages' => $biens->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $biens = $biens->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'all_biens'=>$all_biens,
                    'data' => $biens,
                    'pagination' => $pagination,
                ], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function traiter_bien_frein(Traite_Bien_freinRequest $request, $frein_id)
    {
        if (RoleHelper::ACSup()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $frein = Frein::on('temp')->with('visite.traitement_frein')->findOrFail($frein_id);
            //store traitement frein
            //si ya aucun traitement Frein on store ancien donnee de frein (hisorique)
               /* if(count($frein->visite->traitement_frein)==0){
                    $t_f = new TraitementFrein();
                    $t_f->setConnection('temp');
                    $t_f->visite_id=$frein->visite_id;
                    $t_f->frein_id=$frein_id;
                     $t_f->origin_id=$frein->visite->origin_id;
                    $t_f->commentaire=$frein->commentaire;
                    $t_f->interet=3;
                    $t_f->date=$frein->visite->created_at;
                    $t_f->user_id=$frein->visite->user_id;
                    $t_f->save();
                }*/
                // store le new Traitement
                $t_f = new TraitementFrein();
                $t_f->setConnection('temp');
                $t_f->visite_id=$frein->visite_id;
                $t_f->origin_id=$frein->visite->origin_id;
                $t_f->frein_id=$frein_id;
                $t_f->commentaire=$request->commentaire;
                $t_f->interet=$request->interet;
                $t_f->date=Carbon::now();
                if($t_f->interet==1){
                    //pre reservation
                    $t_f->statut=1;
                    $t_f->bien_id=$request->bien_id;
                }
                $t_f->user_id= $userAuth->value('id');
                if($t_f->save()){
                    //set ancien relance to traite automatique
                    /***RENDRE LES OLD RELANCES ET OLD RDV EN TRAITE AUTOMATIQUE****/
                    $old_relances_rdv=Relance_Rdv_Visite::on('temp')->where('visite_id',$frein->visite->id)->where('type_traitement',0)->get();
                    if(count($old_relances_rdv)>0){
                        foreach($old_relances_rdv as $old){
                            $old->type_traitement=2;//auto
                            $old->date_traitement=Carbon::now();
                            $old->user_id_traite=$userAuth->value('id');
                            $old->save();
                        }
                    }
                    //receptif
                    if ($request->interet == InteretEnum::Réceptif->value || $request->interet == InteretEnum::Perdu->value) {
                        //rendre bien disponible si interet!=Intéressé ==>Réceptif ou Perdu
                        if ($request->list_biens_clickable) {
                            foreach ($request->list_biens_clickable as $key => $bien_id) {
                                if ($bien_id) {
                                    Bien_Helper::libererBien($bien_id, null, null,false);
                                }
                            }
                        }
                        //receptif
                        if ($request->interet == InteretEnum::Réceptif->value) {
                            if ($request->date_relance != null) {
                              //  Config::set('broadcasting.default', 'pusher_3');
                                $data_notif = [
                                    'lien' => '/crm/visites/' . $frein->visite->origin_id,
                                    'date' => $request->date_relance,
                                    'type' => 1,
                                    'description' => 'RELANCE VISITE',
                                    'user_id' => Auth::guard('api')->user()->id,
                                    'role' => null,
                                    'visite_id' => $frein->visite->getAttribute('id'),
                                    'prospect_id' => $frein->visite->prospect_id,
                                    'projet_id' => $frein->visite->projet_id,

                                ];
                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification($request->merge($data_notif));
                               // broadcast(new NotificationEvent($frein->visite->id));

                                $relance = new Relance_Rdv_Visite();
                                $relance->setConnection('temp');
                                $relance->type = 1; //relance
                                $relance->mode_relance = $request->mode_relance;
                                $relance->date_relance = $request->date_relance;
                                $relance->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                $relance->user_id = $userAuth->value('id');
                                $relance->visite_id = $frein->visite->id;
                                if($relance->save()){
                                    $t_f->setConnection('temp');
                                    $t_f->relance_rdv_id=$relance->id;
                                    $t_f->save();
                                }


                            }

                        }else{
                            //perdu store new frein
                            $selectedEtages = ($request->etages == "0") ? -100 : $request->etages;

                            $frein_new = new Frein();
                            $frein_new->setConnection('temp');
                            $frein_new->prix_min = $request->prix_min;
                            $frein_new->prix_max = $request->prix_max;
                            $frein_new->superficie_min = $request->sup_min;
                            $frein_new->superficie_max = $request->sup_max;
                            $frein_new->description_autre = $request->description_autre;
                            //create new frein by appel bien disponible
                            $frein_new->etat = 6;
                            $frein_new->avance = $request->avance;
                            $frein_new->visite_id = $frein->visite_id;
                            $frein_new->traite_appel_id =null;
                            $frein_new->tranche = empty($request->tranches) ? false : true;
                            $frein_new->etage = empty($request->selectedEtages) ? false : true;
                            $frein_new->orientation = empty($request->orientations) ? false : true;
                            $frein_new->vue = empty($request->vues) ? false : true;
                            $frein_new->typologie = empty($request->typologies) ? false : true;
                            if ($frein_new->save()) {

                                if (!empty($request->tranches)) {
                                    $tranches_array = explode(',', $request->tranches); // $tranches_array sera ['5', '2']
                                    foreach ($tranches_array as $valeur) {
                                        FreinTrancheHelper::createFreinTranche($valeur, $frein_new->id);
                                    }
                                }


                                if ($selectedEtages !== '') {
                                    $array_etage = explode(',', $selectedEtages);
                                    foreach ($array_etage as $valeur) {
                                        // Convert to integer first
                                        $intValue = (int)$valeur;
                                        if (is_numeric($valeur)) {
                                            // EXPLICIT conversion of -100 to 0
                                            $valueToStore = ($intValue === -100) ? 0 : $intValue;
                                            FreinEtageHelper::createFreinEtage($valueToStore, $frein_new->id);
                                        }
                                    }
                                }

                                if (!empty($request->orientations)) {
                                    $array_orientation = explode(',', $request->orientations); // $tranches_array sera ['5', '2']
                                    foreach ($array_orientation as $valeur) {
                                        FreinOrientationHelper::createFreinOrientation($valeur, $frein_new->id);
                                    }
                                }
                                if (!empty($request->typologies)) {
                                    $array_typologie = explode(',', $request->typologies); // $tranches_array sera ['5', '2']
                                    foreach ($array_typologie as $valeur) {
                                        FreinTypologieHelper::createFreinTypologie($valeur, $frein_new->id);
                                    }
                                }
                                if (!empty($request->vues)) {
                                    $array_vue = explode(',', $request->vues); // $tranches_array sera ['5', '2']
                                    foreach ($array_vue as $valeur) {
                                        FreinVueHelper::createFreinVue($valeur,
                                        $frein_new->id);
                                    }
                                }
                                $t_f->setConnection('temp');
                                //desactiver par appel et re create new frein
                                $t_f->frein_id=$frein_new->id;
                                $t_f->save();
                            }
                            $frein->setConnection('temp');
                            //desactiver par appel et re create new frein
                            $frein->etat = 5;
                            if ($frein->save()) {
                                //destroy frein bien
                                FreinBienHelper::destroyFreinBien($frein_id);
                                //notification des biens disponible pour ce frein
                                NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
                            }

                        }

                    }
                    else{
                            //interesse
                            if ($request->rdv != null) {
                                //Config::set('broadcasting.default', 'pusher_3');

                                $data_notif = [
                                    'lien' => '/crm/visites/' . $frein->visite->origin_id,
                                    'date' => $request->rdv,
                                    'type' => 2,
                                    'description' => 'RDV VISITE',
                                    'user_id' => Auth::guard('api')->user()->id,
                                    'role' => null,
                                    'visite_id' => $frein->visite->getAttribute('id'),
                                    'prospect_id' => $frein->visite->prospect_id,
                                    'projet_id' => $frein->visite->projet_id,

                                ];
                                $notif_helper = new NotificationHelper();
                                $notif_helper->storeNotification($request->merge($data_notif));

                               // broadcast(new NotificationEvent($frein->visite->id));
                                $rdv = new Relance_Rdv_Visite();
                                $rdv->setConnection('temp');
                                $rdv->type = 2; //rdv
                                $rdv->rdv = $request->rdv;
                                $rdv->type_traitement = 0; //0 non_traite 1//mnuelle 2// auto //3 nouvel relance_rdv
                                $rdv->user_id = $userAuth->value('id');
                                $rdv->visite_id = $frein->visite->id;
                                if($rdv->save()){
                                    $t_f->setConnection('temp');
                                    $t_f->relance_rdv_id=$rdv->id;
                                    $t_f->save();
                                }

                            }
                            $bien = new BienController();
                            $bien->prereserverBien($request->bien_id, $frein->visite_id, null,null);
                            $frein->etat = 3;
                            if ($frein->save()) {
                                //destroy frein bien
                                FreinBienHelper::destroyFreinBien($frein_id);
                                //notification des biens disponible pour ce frein
                                NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
                            }

                    }

                }

            return response()->json(['message' => $frein], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }


    /**************************************Apppellls********************** */
    public function searchFreinByAppelId($id,$text){
        if($text=='without_row_deleted'){
            $frein=Frein::on('temp')->where('traite_appel_id',$id)->first();
            if($frein){

                    $frein_tranches=FreinTranche::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_tranches)>0){
                        $frein['frein_tranches']=$frein_tranches;
                    }

                    $frein_etages=FreinEtage::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_etages)){
                        $frein['frein_etages']=$frein_etages;
                    }


                    $frein_vues=FreinVue::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_vues)){
                        $frein['frein_vues']=$frein_vues;
                    }



                    $frein_typologies=FreinTypologie::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_typologies)){
                        $frein['frein_typologies']=$frein_typologies;
                    }

                    $frein_orientations=FreinOrientation::on('temp')->where('frein_id',$frein->id)->get();
                    if(count($frein_orientations)){
                        $frein['frein_orientations']=$frein_orientations;
                    }


                return $frein;
            }
            else
            {
                return null;
            }

        }

    }


    public function desactiver_freins($param,Request $request)
    {
        if (RoleHelper::ACSup()||RoleHelper::RespoCommercial()) {
            DatabaseHelper::Config();
            $exit=0;
            foreach ($request->list_freins as $key => $list) {
                //Annuler perdu

                if ($list['action'] == 2) {
                    $exit=1;
                    $frein = Frein::on('temp')->findorfail($list['fr_id']);
                    $frein->etat = 4;
                    if ($frein->save()) {
                        //destroy frein bien
                        FreinBienHelper::destroyFreinBien($frein->id);
                        //notification des biens disponible pour ce frein
                        NotificationHelper::destroy_notif_bien_dispo_frein($frein->visite_id);
                    }

                }
            }
            if($exit==1){
                Config::set('broadcasting.default', 'pusher_5');
                broadcast(new NotifMenuEvent('C'));
            }

            return response()->json(['message' => 'suceees'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
}
