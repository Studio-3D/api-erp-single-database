<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Models\Client;
use App\Models\Prospect;
use App\Models\Source;
use App\Models\Visite;
use App\Models\Notification;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\TraitementFrein;
use App\Enum\InteretEnum;
use App\Enum\StatutVisiteEnum;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = prospect::on('temp')->with('client','visites','appels');
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                            ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                    });
                }
            });
            if ($request->filled('cin')) {
                $query->where('cin', 'like', '%' . $request->input('cin') . '%');
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('prenom')) {
                $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $prospects = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $prospects->currentPage(),
                    'totalItems' => $prospects->total(),
                    'totalPages' => $prospects->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $prospects = $prospects->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'prospects' => $prospects,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $prospects = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['prospects' => $prospects], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request,$projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = prospect::on('temp')->where('projet_id', $projet_id);
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone', 'like', '%' . $request->input('telephone') . '%')
                            ->orWhere('telephone_num2', 'like', '%' . $request->input('telephone') . '%');
                    });
                }
            });
            if ($request->filled('cin')) {
                $query->where('cin', 'like', '%' . $request->input('cin') . '%');
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('prenom')) {
                $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $prospects = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $prospects->currentPage(),
                    'totalItems' => $prospects->total(),
                    'totalPages' => $prospects->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $prospects = $prospects->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'prospects' => $prospects,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $prospects = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['prospects' => $prospects], 200);
            }

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
    public function store(StoreProspectRequest $request)
    {
        if (RoleHelper::ACSup()) {
            Log::info($request);
            DatabaseHelper::Config();
            $prospect = new Prospect();
            $prospect->setConnection("temp");
            $prospect->cin = $request->cin;
            $prospect->nom = $request->nom;
            $prospect->prenom = $request->prenom;
            $prospect->telephone = $request->telephone;
            $prospect->telephone_num2 = $request->telephone_num2;
            $prospect->email = $request->email;
            $prospect->origin = 'manuel';
            $prospect->notifie = $request->notifie;
            $prospect->source = $request->source;
            $prospect->partenaire_id = $request->partenaire_id;
            $prospect->message = $request->message;
            $prospect->ville = $request->ville;
            $prospect->save();
            return $prospect;

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public static function Store_WhatsApp($phone_number_id, $from, $msg_body, $name, $societe_id)
    {

        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin = null;
        $prospect->message = $msg_body;
        $prospect->nom = $name;
        $prospect->telephone = $from;
        $prospect->email = null;
        $prospect->origin = 'whatssap';
        $prospect->source = 1;
        $prospect->save();
    }

    public static function Store_LandingPage($name, $prenom, $phone, $email, $societe_id)
    {

        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin = null;
        $prospect->message = null;
        $prospect->nom = $name;
        $prospect->prenom = $prenom;
        $prospect->telephone = $phone;
        $prospect->email = $email;
        $prospect->origin = 'landingPage';
        $prospect->source = 3;
        $prospect->save();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visites_perdu')->findOrfail($id);
            return response()->json(['prospect' => $prospect], 200);
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
    public function update(UpdateProspectRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($request->cin != null) {
                $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['errors' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }
            }
            $prospect = Prospect::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $prospect->$key = $value;
            }
            $prospect->save();
            return response()->json(['prospect' => $prospect], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->findOrFail($id);
            if(count($prospect->visites)>0||$prospect->appel!=null || $prospect->client!=null){
                return response()->json(['error' => 'Il est impossible de supprimer ce Prospect car il possède plusieurs dossiers liés à des visites, des appels ou client.'], 422);
            }else{
                $notifications=Notification::on('temp')->where('prospect_id',$id)->get();
                if(count($notifications)){
                    foreach($notifications as $nt){
                        $nt->forceDelete();
                    }
                }
                if ($prospect->delete()) {
                    return response()->json(['message' => 'Prospect supprimé avec succès.'], 200);
                } else {
                    return response()->json(['error' => "Le prospect n'a pas été supprimé."], 404);
                }
            }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function search_prospect_by_param($param_1, $value)
    {
        //cin ou email
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($param_1 == 'cin' || $param_1 == 'email') {
                $prospect = Prospect::on('temp')->with('visite_pre_reserves', 'visites','visites.freins','visites.freins.freinTranche','visites.freins.FreinEtage','visites.freins.FreinOrientation','visites.freins.FreinTypologie','visites.freins.FreinVue', 'appels')->where($param_1, $value)
                    ->get()->first();
                $client = Client::on('temp')->with('prospect')->where($param_1, $value)->get()->first();

            } else {
                //telephone
                $prospect = Prospect::on('temp')->with('visite_pre_reserves', 'visites', 'visites.freins','visites.freins.freinTranche','visites.freins.FreinEtage','visites.freins.FreinOrientation','visites.freins.FreinTypologie','visites.freins.FreinVue','appels')
                    ->where(function ($query) use ($value) {
                        $query->where('telephone', $value)
                            ->orwhere('telephone_num2', $value)
                        ;
                    })
                    ->get()->first();
                $client = Client::on('temp')->with('prospect')
                    ->where(function ($query) use ($value) {
                        $query->where('telephone_num1', $value)
                            ->orwhere('telephone_num2', $value)
                        ;
                    })
                    ->get()->first();
            }

            //bien pre reserve par appel on cas des biens disponibles
            $biens_traitement_freins=[];
            if($prospect!=null){
                $biens_traitement_freins = TraitementFrein::on('temp')->with('bien','visite')
                ->whereHas('visite', function ($q) use ($prospect) {
                $q->where('prospect_id', $prospect->id);
                })
                ->where('interet', InteretEnum::Intéressé->value)
                ->where('statut', StatutVisiteEnum::Pré_Réservation->value)->orderby('created_at', 'desc')->get(['bien_id', 'id'])->take(1);
            }

            return response()->json(['prospect' => $prospect, 'client' => $client,'biens_traitement_freins'=>$biens_traitement_freins]);
        }
    }
    public function VisitesByprospect(Request $request, $prospect_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $visites = Visite::on('temp')->latest('created_at')->where('etat', 1)
                ->select('visites.*')
                ->where('prospect_id', $prospect_id)
                ->get()->groupby('origin_id');

            $visites = $visites->map(function ($visite) {
                return [
                    'id' => $visite->first()->id,
                    'origin_id' => $visite->first()->origin_id,
                    'nom_cc' => $visite->first()->user->name,
                    'prenom_cc' => $visite->first()->user->prenom,
                    'date' => $visite->first()->created_at,
                    'prospect_id' => $visite->first()->prospect->id,
                    'cin' => $visite->first()->prospect->cin,
                    'nom' => $visite->first()->prospect->nom,
                    'prenom' => $visite->first()->prospect->prenom,
                    'telephone' => $visite->first()->prospect->telephone,
                    'telephone2' => $visite->first()->prospect->telephone_num2,
                    'interet' => $visite->first()->interet,
                    'statut' => $visite->first()->statut,
                    'propriete_dite_bien' => $visite->first()->bien_id ? $visite->first()->bien->propriete_dite_bien : '',
                    'etat_bien' => $visite->first()->bien_id ? $visite->first()->bien->etat : '',
                    'bien_id' => $visite->first()->bien_id ? $visite->first()->bien_id : '',
                    'visit_count' => count($visite),

                ];
            });

            $data = PaginationHelper::paginate_array($visites->toArray(), $perPage, $page, $request->url());
            return response()->json(['visites' => $data], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }



    public function upload(Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            set_time_limit(0);
            ini_set('memory_limit', '-1');

            $data = $request->input('jsonData');
            if(count($data)>0){
                foreach($data as $row)
                {
                    $prospect_cin=0;
                    $prospect_email=0;
                    $prospect_tel=0;
                    $prospect_tel2=0;

                    //cin unique
                    if (!empty($row['cin'])) {
                        $prospect_cin=Prospect::on('temp')->where('cin',$row['cin'])->count();
                    }

                    //tel1 unique
                    if (!empty($row['telephone'])) {
                        $prospect_tel=Prospect::on('temp')
                        ->where(function ($subQuery) use ($row) {
                            $subQuery->where('telephone',$row['telephone'])
                                     ->orWhere('telephone_num2', $row['telephone']);
                        })-> count();
                    }

                    //tel2 unique
                    if (!empty($row['telephone_num2'])) {
                        $prospect_tel2=Prospect::on('temp')
                        ->where(function ($subQuery) use ($row) {
                            $subQuery->where('telephone',$row['telephone_num2'])
                                     ->orWhere('telephone_num2', $row['telephone_num2']);
                        })-> count();
                    }
                    if (!empty($row['email'])) {
                        $prospect_email=Prospect::on('temp')->where('email',$row['email'])->count();
                    }

                    if($prospect_cin==0 && $prospect_email==0&&$prospect_tel==0&&$prospect_tel2==0){
                        $source_id=null;
                        if(!empty($row['source'])){
                            $source=Source::on('temp')->where('source',$row['source'])->first();
                            if($source!=null){
                                $source_id=$source->id;
                            }
                        }

                        $prospect=new Prospect();
                        $prospect->setConnection("temp");
                        $prospect->cin = $row['cin'];
                        $prospect->nom = $row['nom'];
                        $prospect->prenom = $row['prenom'];
                        $prospect->telephone = $row['telephone'];
                        $prospect->telephone_num2 = empty($row['telephone_num2'])?null:$row['telephone_num2'];
                        $prospect->email = empty($row['email'])?null:$row['email'];
                        $prospect->origin = 'import';
                        $prospect->notifie = 0;
                        $prospect->source = $source_id;
                        $prospect->partenaire_id =NULL;
                        $prospect->message = NULL;
                        $prospect->ville =empty($row['ville'])?null:$row['ville'];;
                        $prospect->save();
                        return response()->json('done');
                    }

                }
            }else{
            return response()->json(['error' => 'Le fichier doit être rempli.'], 400);
                }

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
}
