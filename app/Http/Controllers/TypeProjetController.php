<?php

namespace App\Http\Controllers;

use App\Models\TypeProjet;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeProjetRequest;
use App\Http\Requests\UpdateTypeProjetRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class TypeProjetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = 20; // Number of items per page
            $page = $request->input('page', 1);
            $typeprojets = TypeProjet::on('temp')->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();            
            return response()->json(['typeProjet' => $typeprojets]);
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
    public function store(StoreTypeProjetRequest $request)
    {
        if (RoleHelper::AdminSup()) {
                       
            DatabaseHelper::Config();                
            $typeprojet = new typeprojet();
            $typeprojet->setConnection('temp');
            $typeprojet->type = $request->type;
            $typeprojet->save();

            return response()->json(['typeProjet' => $typeprojet], 200);
           
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
            $typeprojet = typeprojet::on('temp')->findOrfail($id);
            return response()->json(['typeProjet' => $typeprojet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeprojet = typeprojet::on('temp')->findOrfail($id);
            return response()->json(['typeProjet' => $typeprojet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTypeProjetRequest $request,  $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeprojet = typeprojet::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $typeprojet->$key = $value;
            }
            $typeprojet->save();
            
            return response()->json(['typeProjet' => $typeprojet], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeprojet = typeprojet::on('temp')->findOrfail($id);                         
            if ($typeprojet->delete()) {
                return response()->json(['message' => 'ce type de projet deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce type de projet non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTypeProjet($typeprojet_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            TypeProjet::on('temp')->where('id', $typeprojet_id)->withTrashed()->restore();

            return response()->json(['message' => 'Type projet est projet restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTypesProjet()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();            
            $typeProjets = TypeProjet::on('temp')->onlyTrashed()->get();

            return response()->json(['typeProjet' => $typeProjets], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
