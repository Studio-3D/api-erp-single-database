<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreImmeubleRequest;
use App\Http\Requests\UpdateImmeubleRequest;
use App\Models\Bloc;
use App\Models\Immeuble;
use App\Models\TraitementAppel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImmeubleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size      = $request->input('size', config('app.default_item_number_perpage'));
            $page      = $request->input('page', 1);
            $projet_id = $request->input('projet_id');
            DatabaseHelper::Config();

            $query = Immeuble::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('tranche_id')) {
                $query->where('tranche_id', $request->input('tranche_id'));
            }
            if ($request->filled('bloc_id')) {
                $query->where('bloc_id', $request->input('bloc_id'));
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }
            if ($request->filled('bloc')) {
                $query->whereHas('bloc', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('bloc'));
                });
            }

            $immeubles = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $immeubles->currentPage(),
                'totalItems'  => $immeubles->total(),
                'totalPages'  => $immeubles->lastPage(),
            ];

            $immeubles = $immeubles->items();

            return response()->json([
                'data'       => $immeubles,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            $query = Immeuble::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('tranche_id')) {
                $query->where('tranche_id', $request->input('tranche_id'));
            }
            if ($request->filled('bloc_id')) {
                $query->where('bloc_id', $request->input('bloc_id'));
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('titre_foncier')) {
                $query->where('titre_foncier', 'like', '%' . $request->input('titre_foncier') . '%');
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }
            if ($request->filled('bloc')) {
                $query->whereHas('bloc', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('bloc'));
                });
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $immeuble = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $immeuble->currentPage(),
                    'totalItems'  => $immeuble->total(),
                    'totalPages'  => $immeuble->lastPage(),
                ];

                $immeuble = $immeuble->items();

                return response()->json([
                    'data'       => $immeuble,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $immeubles = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['immeubles' => $immeubles], 200);
            }

        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreImmeubleRequest $request)
    {
        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $immeuble = new immeuble();
            $immeuble->setConnection('temp');
            $immeuble->nom           = $request->nom;
            $immeuble->titre_foncier = $request->titre_foncier;
            $immeuble->projet_id     = $request->projet_id;
            $immeuble->tranche_id    = $request->tranche_id;
            $immeuble->bloc_id       = $request->bloc_id;
            $immeuble->nbre_biens    = $request->nbre_biens ? $request->nbre_biens : 0;
            if ($request->bloc_id && ($request->tranche_id === null || ! $request->tranche_id)) {
                $bloc                 = Bloc::on('temp')->findOrfail($request->bloc_id);
                $immeuble->tranche_id = $bloc->tranche_id;

            }

            $immeuble->save();

            return response()->json(['message' => $immeuble], 200);

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
            $immeuble = Immeuble::on('temp')->with('projet','tranche','bloc')->withCount('bien')->findOrfail($id);
            return response()->json(['immeuble' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $immeuble = Immeuble::on('temp')->findOrfail($id);
            return response()->json(['message' => $immeuble], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateImmeubleRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $immeuble = immeuble::on('temp')->findOrfail($id);
            $update   = $request->all();
            foreach ($update as $key => $value) {
                $immeuble->$key = $value;
            }
            $immeuble->save();
            return response()->json(['message' => $immeuble], 200);
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
            $immeuble = immeuble::on('temp')->findOrfail($id);
            if (count($immeuble->bien) > 0) {
                foreach ($immeuble->bien as $b) {
                    $bien = new BienController();
                    $bien->destroy($b->id);
                }
            }
            //traitement_appel
            $traitement_appels = TraitementAppel::on('temp')->where('immeuble_id', $id)->get();
            if (count($traitement_appels) > 0) {
                foreach ($traitement_appels as $tr) {
                    $appel = new AppelController();
                    $appel->destroy_t_appel($tr->id, 1);
                }

            }
            if ($immeuble->delete()) {
                return response()->json(['message' => 'immeuble supprimé avec Succés'], 200);
            } else {
                return response()->json(['message' => 'immeuble non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function restoreImmeuble($immeuble_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Immeuble::on('temp')->where('id', $immeuble_id)->withTrashed()->restore();
            return response()->json(['message' => 'Immeuble restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedImmeubles()
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $immeubles = Immeuble::on('temp')->onlyTrashed()->get();
            return response()->json(['message' => $immeubles], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
