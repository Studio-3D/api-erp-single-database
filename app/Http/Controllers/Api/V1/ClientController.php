<?php
namespace App\Http\Controllers\Api\V1;

use App\Enum\TypeClient;
use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Aquereur;
use App\Models\Avance;
use App\Models\Client;
use App\Models\Prospect;
use App\Models\Reservation;
use App\Models\Visite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
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
            $query = client::on('temp');
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone_num1', 'like', '%' . $request->input('telephone') . '%')
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
            /* if ($$request->filled('type_client')) {
                $query->where('type_client', $request->input('type_client'));
            } */

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $clients = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $clients->currentPage(),
                    'totalItems'  => $clients->total(),
                    'totalPages'  => $clients->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $clients = $clients->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'clients'    => $clients,
                    'pagination' => $pagination,
                ], 200);
            } else {
                if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
                    $clients = Client::all();
                } else if (RoleHelper::AC()) {
                    $clients = $query->orderBy('nom', 'asc')
                        ->get();
                }

                return response()->json(['clients' => $clients], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = client::on('temp')->with('aquereur', 'prospect', 'aquereur_desistement', 'reclamation')->where('projet_id', $projet_id);
            $query->where(function ($q) use ($request) {
                if ($request->filled('telephone')) {
                    $q->where(function ($subQuery) use ($request) {
                        $subQuery->where('telephone_num1', 'like', '%' . $request->input('telephone') . '%')
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
            /* if ($$request->filled('type_client')) {
                $query->where('type_client', $request->input('type_client'));
            } */

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $clients = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $clients->currentPage(),
                    'totalItems'  => $clients->total(),
                    'totalPages'  => $clients->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $clients = $clients->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data'       => $clients,
                    'pagination' => $pagination,
                ], 200);
            } else {
                if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
                    $clients = Client::all();
                } else if (RoleHelper::AC()) {
                    $clients = $query->orderBy('nom', 'asc')
                        ->get();
                }

                return response()->json(['clients' => $clients], 200);
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
    public function store(StoreClientRequest $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = new Client();
            $client->setConnection('temp');
            $client->type_client = $request->type_client;
            if ($request->type_client == TypeClient::Société->value) {
                $client->partenaire_id = $request->partenaire_id;
            }
            $client->nom                  = $request->nom;
            $client->projet_id            = $request->projet_id;
            $client->prenom               = $request->prenom;
            $client->telephone_num1       = $request->telephone_num1;
            $client->telephone_num2       = $request->telephone_num2 == "null" ? '' : $request->telephone_num2;
            $client->notifie              = $request->notifie;
            $client->email                = $request->email;
            $client->civilite             = $request->civilite;
            $client->adresse              = $request->adresse;
            $client->ville                = $request->ville;
            $client->pays                 = $request->pays;
            $client->profession           = $request->profession;
            $client->cin                  = $request->cin;
            $client->age                  = $request->age;
            $client->lieu_naissance       = $request->lieu_naissance;
            $client->nationalite          = $request->nationalite;
            $client->date_naissance       = $request->date_naissance;
            $client->nom_responsable      = $request->nom_responsable;
            $client->relation_familliale  = $request->relation_familliale;
            $client->situation_familliale = $request->situation_familliale;
            $client->date_mariage         = $request->date_mariage;
            $client->nom_mari             = $request->nom_mari;
            $client->lieu_mariage         = $request->lieu_mariage;
            $client->nom_pere             = $request->nom_pere;
            $client->nom_mere             = $request->nom_mere;
            $client->prospect_id          = $request->prospect_id==''?null:$request->prospect_id;
            $client->code_client          = $request->cin . '_' . $request->nom . '_' . $request->prenom;
            $client->password             = '01020304';
            if ($client->save()) {
                if ($client->prospect_id != null) {
                    $prospect            = Prospect::on('temp')->findorfail($client->prospect_id);
                    $prospect->client_id = $client->id;
                    $prospect->save();
                }
                //store info to database client
                $db_client = DB::connection('mysql_client')->table('users')->insert(['code_client' => $request->cin . '_' . $request->nom, 'name' => $request->nom, 'prenom' => $request->prenom, 'email' => $request->email, 'password' => Hash::make($client->password), 'gender' => $request->civilite, 'client_id' => $client->id]);
                return $client;
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $client = Client::on('temp')->findOrFail($id);
            $reservations = $client->reservations()->with([
                'bien', 'user', 'projet', 'aquereurs.client'
            ])->get();

            $visites = Visite::on('temp')
                ->where('etat', 1)
                ->where('prospect_id', $client->prospect_id)
                ->latest('created_at')
                ->get();

            $groupedVisites = $visites->groupBy('origin_id')->map(function ($visite) {
                $firstVisite = $visite->first();
                return [
                    'id'                  => $firstVisite->id,
                    'origin_id'           => $firstVisite->origin_id,
                    'nom_cc'              => $firstVisite->user ? $firstVisite->user->name : null,
                    'prenom_cc'           => $firstVisite->user ? $firstVisite->user->prenom : null,
                    'date'                => $firstVisite->created_at,
                    'cin'                 => $firstVisite->prospect ? $firstVisite->prospect->cin : null,
                    'nom'                 => $firstVisite->prospect ? $firstVisite->prospect->nom : null,
                    'prenom'              => $firstVisite->prospect ? $firstVisite->prospect->prenom : null,
                    'telephone'           => $firstVisite->prospect ? $firstVisite->prospect->telephone : null,
                    'telephone2'          => $firstVisite->prospect ? $firstVisite->prospect->telephone_num2 : null,
                    'prospect_id'         => $firstVisite->prospect ? $firstVisite->prospect->id : null,
                    'interet'             => $firstVisite->interet,
                    'statut'              => $firstVisite->statut,
                    'propriete_dite_bien' => $firstVisite->bien ? $firstVisite->bien->propriete_dite_bien : '',
                    'etat_bien'           => $firstVisite->bien ? $firstVisite->bien->etat : '',
                    'bien_id'             => $firstVisite->bien_id ?? '',
                    'visit_count'         => $visite->count(),
                    'reservation'         => $firstVisite->reservation ?? null,
                ];
            });

            return response()->json([
                'client'       => $client,
                'reservations' => $reservations,
                'visites'      => $groupedVisites->values(),
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
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
    public function update(UpdateClientRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = Client::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $client->$key = $value;
            }
            $client->save();
            return response()->json(['client' => $client], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = Client::on('temp')->with('prospect', 'aquereur_desistement', 'reclamation', 'aquereur')->findOrFail($id);

            /*
            if($client->prospect!=null){
                $client->prospect->delete();
            }
            if(count($client->aquereur_desistement)>0){
                foreach($client->aquereur_desistement as $aq){
                    $aq->delete();
                }
            }
            if(count($client->aquereurs)>0){
                foreach($client->aquereurs as $aq_cl){
                    $aq_cl->delete();
                }
            }
            if(count($client->reclamation)>0){
                foreach($client->reclamation as $rec){
                    $red->delete();
                }
            }*/

            if (count($client->aquereur) > 0 || count($client->reclamation) > 0 || count($client->aquereur_desistement) > 0) {
                return response()->json(['error' => 'Il est impossible de supprimer ce client car il possède plusieurs dossiers liés à des réservations, des désistements et des réclamations.'], 422);
            } else {
                if ($client->prospect != null) {
                    $client->prospect->delete();
                }
                if ($client->delete()) {
                    return response()->json(['message' => 'Client Supprimé avec Succès '], 200);
                } else {
                    return response()->json(['message' => 'Client non Supprimé'], 400);
                }
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getClient_by_projet(Request $request, $projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page    = $request->input('page', 1);
            $clients = Client::on('temp')->join('aquereurs', 'aquereurs.client_id', '=', 'clients.id')
                ->join('reservations', 'reservations.id', '=', 'aquereurs.reservation_id')
                ->whereNull('reservations.deleted_at')
                ->where('reservations.projet_id', $projet_id)
                ->select('clients.*')
                ->distinct()
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['clients' => $clients], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function search_client_by_cin($cin)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = Client::on('temp')->where('cin', $cin)
                ->get()->first();

            if ($client != null) {
                //si client n'est pas prospect
                if ($client->prospect_id == null) {
                    $prospect = Prospect::on('temp')->with('visites_perdu')->where('cin', $cin)
                        ->get()->first();
                } else {
                    //client est un prospect
                    $prospect = Prospect::on('temp')->where('id', $client->prospect_id)->with('visites_perdu')->get()->first();
                }
            } else {
                //client n'existe  pas
                $prospect = Prospect::on('temp')->with('visites_perdu')->where('cin', $cin)
                    ->get()->first();
            }

            return response()->json(['client' => $client, 'prospect' => $prospect]);
        }
    }
    public function search_client_by_phone($phone)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = Client::on('temp')
                ->where(function ($query) use ($phone) {
                    $query->where('telephone_num1', $phone)
                        ->orwhere('telephone_num2', $phone)
                    ;
                })
                ->get()->first();

            if ($client != null) {
                //si client n'est pas prospect
                if ($client->prospect_id == null) {
                    $prospect = Prospect::on('temp')->with('visites_perdu')
                        ->where(function ($query) use ($phone) {
                            $query->where('telephone', $phone)
                                ->orwhere('telephone_num2', $phone)
                            ;
                        })
                        ->get()->first();
                } else {
                    //client est un prospect
                    $prospect = Prospect::on('temp')->where('id', $client->prospect_id)->with('visites_perdu')->get()->first();
                }
            } else {
                $prospect = Prospect::on('temp')->with('visites_perdu')
                    ->where(function ($query) use ($phone) {
                        $query->where('telephone', $phone)
                            ->orwhere('telephone_num2', $phone)
                        ;
                    })
                    ->get()->first();
            }
        }

        return response()->json(['client' => $client, 'prospect' => $prospect]);

    }

    public function search_client_by_email($email)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $client = Client::on('temp')->where('email', $email)
                ->get()->first();

            if ($client != null) {
                //si client n'est pas prospect
                if ($client->prospect_id == null) {
                    $prospect = Prospect::on('temp')->with('visites_perdu')->where('email', $email)
                        ->get()->first();
                } else {
                    //client est un prospect
                    $prospect = Prospect::on('temp')->where('id', $client->prospect_id)->with('visites_perdu')->get()->first();
                }
            } else {
                //client n'existe  pas
                $prospect = Prospect::on('temp')->with('visites_perdu')->where('email', $email)
                    ->get()->first();
            }

            return response()->json(['client' => $client, 'prospect' => $prospect]);
        }
    }
    public function ReservationsByClient(Request $request, $client_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page    = $request->input('page', 1);
            $avances = Avance::on('temp')->select('reservation_id', DB::raw('SUM(avances.montant) as sum_avances'))
                ->groupby('reservation_id');
            $reservations = Reservation::on('temp')->join('aquereurs', 'aquereurs.reservation_id', '=', 'reservations.id')
                ->joinSub($avances, 'avances_req', function ($join) {
                    $join->on('avances_req.reservation_id', '=', 'reservations.id');
                })
                ->select('reservations.*', 'aquereurs.pourcentage', 'avances_req.sum_avances')
                ->where('aquereurs.client_id', $client_id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['reservations' => $reservations], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function VisitesByClient(Request $request, $client_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $client  = Client::on('temp')->findOrFail($client_id);
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage'));
            $page    = $request->input('page', 1);
            $visites = Visite::on('temp')->latest('created_at')->where('etat', 1)
                ->select('visites.*')
                ->where('prospect_id', $client->prospect_id)
                ->get()->groupby('origin_id');

            $visites = $visites->map(function ($visite) {
                return [
                    'id'                  => $visite->first()->id,
                    'origin_id'           => $visite->first()->origin_id,
                    'nom_cc'              => $visite->first()->user->name,
                    'prenom_cc'           => $visite->first()->user->prenom,
                    'date'                => $visite->first()->created_at,
                    'prospect_id'         => $visite->first()->prospect->id,
                    'cin'                 => $visite->first()->prospect->cin,
                    'nom'                 => $visite->first()->prospect->nom,
                    'prenom'              => $visite->first()->prospect->prenom,
                    'telephone'           => $visite->first()->prospect->telephone,
                    'telephone2'          => $visite->first()->prospect->telephone_num2,
                    'interet'             => $visite->first()->interet,
                    'statut'              => $visite->first()->statut,
                    'propriete_dite_bien' => $visite->first()->bien_id ? $visite->first()->bien->propriete_dite_bien : '',
                    'etat_bien'           => $visite->first()->bien_id ? $visite->first()->bien->etat : '',
                    'bien_id'             => $visite->first()->bien_id ? $visite->first()->bien_id : '',
                    'visit_count'         => count($visite),

                ];
            });

            $data = PaginationHelper::paginate_array($visites->toArray(), $perPage, $page, $request->url());
            return response()->json(['visites' => $data], 200);

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

}
