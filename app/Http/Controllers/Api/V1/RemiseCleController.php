<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\RemiseCle;
use App\Models\Societe;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class RemiseCleController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = RemiseCle::on('temp')->where('remise_cles.projet_id', $projet_id);
            if (RoleHelper::Com()) {
                $user     = Auth::user();
                $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                $query->where('user_id_remis', $userAuth->value('id'));
            }
            if ($request->filled('bien')) {
                $query->where('remise_cles.bien_id', $request->input('bien'));

            }
            if ($request->filled('cc')) {
                $query->where('user_id_remis', $request->input('cc'));

            }
            /*if ($request->filled('client')) {
                $query->whereHas('bien.reservation.aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });

             }*/
            if ($request->filled('date_remise')) {
                $start = Carbon::parse($request->input('date_remise'));
                $query->whereDate('remise_cles.date_remise', $start);
            }

            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $remis = $query->LeftJoin('reservations', 'reservations.bien_id', 'remise_cles.bien_id')
                    ->select('remise_cles.*', 'reservations.id as id_res','reservations.code_reservation as code_reservation')
                    ->where('reservations.etat', 1)
                    ->where('reservations.deleted_at', null)
                    ->orderBy('remise_cles.created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $remis->currentPage(),
                    'totalItems'  => $remis->total(),
                    'totalPages'  => $remis->lastPage(),
                ];

                $Items = $remis->items();

                // Return the response with pagination
                return response()->json([
                    'data'       => $Items,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $remis = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['remis' => $remis], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
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
    public function store(Request $request)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user          = Auth::user();
            $userAuth      = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe       = Societe::findOrfail($user_societes->societe_id);
            $rec           = new RemiseCle();
            $rec->setConnection('temp');
            $rec->bien_id       = $request->bien_id;
            $rec->projet_id     = $request->projet_id;
            $rec->date_remise   = $request->date_remise;
            $rec->user_id       = $userAuth->value('id');
            $rec->user_id_remis = $request->user_id_remise;
            if ($request->hasFile('fichier')) {
                $rec->fichier = $request->file('fichier')->getClientOriginalName();
                $directory    = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier')->move($directory, $request->file('fichier')->getClientOriginalName());
            }
            $rec->save();
            return response()->json(['remise' => $rec], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $remise = RemiseCle::on('temp')->findOrFail($id);
            return response()->json(['remise' => $remise], 200);
        }

        return response()->json(['error', 'Unauthorized'], 401);
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
    public function update(Request $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user          = Auth::user();
            $userAuth      = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe       = Societe::findOrfail($user_societes->societe_id);
            $rec           = RemiseCle::on('temp')->findOrFail($id);
            $rec->setConnection('temp');
            $rec->date_remise   = $request->date_remise;
            $rec->user_id       = $userAuth->value('id');
            $rec->user_id_remis = $request->user_id_remise;
            $rec->bien_id       = $request->bien_id;
            $fich               = $rec->fichier;
            if ($request->hasFile('fichier')) {

                if ($fich != null) {
                    if (File::exists(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles' . '/' . $fich))) {
                        File::delete(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles' . '/' . $fich));
                    }
                }

                $rec->fichier = $request->file('fichier')->getClientOriginalName();
                $directory    = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles');
                File::makeDirectory($directory, 0755, true, true);
                $request->file('fichier')->move($directory, $request->file('fichier')->getClientOriginalName());
            }
            $rec->save();
            return response()->json(['remise' => $rec], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $user          = Auth::user();
            $userAuth      = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe       = Societe::findOrfail($user_societes->societe_id);
            $rem           = RemiseCle::on('temp')->findOrFail($id);
            $fich          = $rem->fichier;
            if ($rem->delete()) {

                if ($fich != null) {
                    if (File::exists(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles' . '/' . $fich))) {
                        File::delete(public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/remise_cles' . '/' . $fich));
                    }
                }

                return response()->json(['message' => 'Remise supprimée avec succès.'], 200);
            } else {
                return response()->json(['error' => "La Remise n'a pas été supprimée."], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public static function AjouterVue($vues, $projet_id)
    {
        $vueController = new VueController();
        $vueRequest    = new StoreVueRequest();

        $datavue = [
            'vue'       => $vues,
            'projet_id' => $projet_id,
        ];
        $vueRequest->merge($datavue);
        $vueController->store($vueRequest);

    }

}
