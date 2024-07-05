<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Models\Source;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class SourceController extends Controller
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
            $query = Source::on('temp');

            if ($request->filled('source')) {
                $query->where('source', 'like', '%' . $request->input('source') . '%');
            }
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {

                $sources = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $sources->currentPage(),
                    'totalItems' => $sources->total(),
                    'totalPages' => $sources->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $sources = $sources->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'sources' => $sources,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $sources = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['sources' => $sources], 200);
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
    public function store(StoreSourceRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $source=new Source();
            $source->setConnection('temp');
            $source->source=$request->source;
            $source->save();
            return response()->json(['$source'=>$source],200);
        }
        else return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $source = Source::on('temp')->findOrfail($id);
            return response()->json(['source' => $source], 200);
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
    public function update(UpdateSourceRequest $request,$id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $source=Source::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach($update as $key => $value){
                $source->$key= $value;
            }
            $source->save();
            return response()->json(['source'=>$source],200);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(RoleHelper::AdminSup()){
            DatabaseHelper::Config();
            $source=Source::on('temp')->findOrFail($id);
            if($source->delete())
            {
                return response()->json(['message'=>'Source supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La source n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
