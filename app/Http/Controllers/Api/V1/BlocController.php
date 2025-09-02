<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBlocRequest;
use App\Http\Requests\UpdateBlocRequest;
use App\Models\Bloc;
use App\Models\TraitementAppel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlocController extends Controller
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

            $query = Bloc::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }

            $blocs = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $blocs->currentPage(),
                'totalItems'  => $blocs->total(),
                'totalPages'  => $blocs->lastPage(),
            ];

            $blocs = $blocs->items();

            return response()->json([
                'data'       => $blocs,
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

            $query = Bloc::on('temp')->where('projet_id', $projet_id);

            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('titre_foncier')) {
                $query->where('titre_foncier', 'like', '%' . $request->input('titre_foncier') . '%');
            }
            if ($request->filled('tranche_id')) {
                $query->where('tranche_id', $request->input('tranche_id'));
            }
            if ($request->filled('tranche')) {
                $query->whereHas('tranche', function ($subQuery) use ($request) {
                    $subQuery->where('nom', $request->input('tranche'));
                });
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $blocs = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $blocs->currentPage(),
                    'totalItems'  => $blocs->total(),
                    'totalPages'  => $blocs->lastPage(),
                ];

                $blocs = $blocs->items();

                return response()->json([
                    'data'       => $blocs,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $blocs = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['blocs' => $blocs], 200);
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
    public function store(StoreBlocRequest $request)
    {
        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $bloc = new Bloc();
            $bloc->setConnection('temp');
            $bloc->nom            = $request->nom;
            $bloc->titre_foncier  = $request->titre_foncier;
            $bloc->projet_id      = $request->projet_id;
            $bloc->tranche_id     = $request->tranche_id;
            $bloc->nbre_immeubles = $request->nbre_immeubles ? $request->nbre_immeubles : 0;
            $bloc->nbre_biens     = $request->nbre_biens ? $request->nbre_biens : 0;
            $bloc->save();
            return response()->json(['message' => $bloc], 200);
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
            $bloc = Bloc::on('temp')->with('projet','tranche','bien','immeuble')->withCount('immeuble','bien')->findOrfail($id);
            return response()->json(['bloc' => $bloc], 200);
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
            $bloc = Bloc::on('temp')->findOrfail($id);
            return response()->json(['message' => $bloc], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBlocRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $bloc   = Bloc::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $bloc->$key = $value;
            }
            $bloc->save();

            return response()->json(['message' => $bloc], 200);
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
            $bloc = Bloc::on('temp')->findOrfail($id);
            if (count($bloc->immeuble) > 0) {
                $imme = new ImmeubleController();
                foreach ($bloc->immeuble as $imm) {
                    $imme->destroy($imm->id);
                }
            }
            if(count($bloc->bien)>0){
                $bien=new BienController();
                foreach($bloc->bien as $b){
                    $bien->destroy($b->id);
                }
            }

            //traitement_appel
            $traitement_appels = TraitementAppel::on('temp')->where('bloc_id', $id)->get();
            if (count($traitement_appels) > 0) {
                foreach ($traitement_appels as $tr) {
                    $appel = new AppelController();
                    $appel->destroy_t_appel($tr->id, 1);
                }

            }

            if ($bloc->delete()) {
                return response()->json(['message' => 'bloc supprimé avec Succés'], 200);
            } else {
                return response()->json(['message' => 'bloc non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }

    }
    public function restoreBloc($bloc_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Bloc::on('temp')->where('id', $bloc_id)->withTrashed()->restore();
            return response()->json(['message' => 'Bloc restored'], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedBlocs()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $blocs = Bloc::on('temp')->onlyTrashed()->get();
            return response()->json(['message' => $blocs], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

}
