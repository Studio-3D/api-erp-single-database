<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use App\Http\Requests\StorePartenaireRequest;
use App\Http\Requests\UpdatePartenaireRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class PartenaireController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_partenaires($projet_id)
    {

        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $partenaires = Partenaire::on('temp')->orderBy('created_at', 'desc')->where('projet_id',$projet_id)
                ->get();
            return response()->json(['partenaires' => $partenaires],200);
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }



    }

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $projet_id = $request->input('projet_id');
            DatabaseHelper::Config();

            $query = Partenaire::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('description')) {
                $query->where('description', 'like', '%' . $request->input('description') . '%');
            }
            if ($request->filled('remise')) {
                $query->where('remise', 'like', '%' . $request->input('remise') . '%');
            }
            $partenaires = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $partenaires->currentPage(),
                'totalItems' => $partenaires->total(),
                'totalPages' => $partenaires->lastPage(),
            ];

            $partenaires = $partenaires->items();

            return response()->json([
                'partenaires' => $partenaires,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Show the form for creating a new resource.
     */


    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePartenaireRequest $request)
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            //partenaire unique in this projects
                
                $partenaire = new Partenaire();
                $partenaire->setConnection('temp');
                $partenaire->description = $request->description;
                $partenaire->remise = $request->remise;
                $partenaire->projet_id = $request->projet_id;
                $partenaire->save();
                return response()->json(['message' => $partenaire], 200);
               


        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show( $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            return response()->json(['partenaire' => $partenaire], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePartenaireRequest $request,  $id)
    {
        //unique partenaire
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $partenaire = Partenaire::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $partenaire->$key = $value;
            }
            $partenaire->save();

            return response()->json(['message' => $partenaire], 200);
        

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
            $partenaire = Partenaire::on('temp')->findOrfail($id);

            if ($partenaire->delete()) {
                return response()->json(['message' => 'ce Partenaire est supprimé succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce Partenaire non supprimé'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restorePartenaire($partenaire_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            Partenaire::on('temp')->where('id', $partenaire_id)->withTrashed()->restore();
            return response()->json(['message' => 'Partenaire bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public static function AjouterPartenaire($partenaire, $projet_id)
    {
            $partenaireController = new PartenaireController();
            $partenaireRequest = new StorePartenaireRequest();

                $dataPartenaire = [
                    'description' => $partenaire['description'],
                    'remise' => $partenaire['remise'],
                    'projet_id' => $projet_id,
                ];
            $partenaireRequest->merge($dataPartenaire);
            $partenaireController->store($partenaireRequest);
            
        
       
    }


}
