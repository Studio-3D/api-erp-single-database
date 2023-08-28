<?php

namespace App\Http\Controllers;

use App\Models\TypeBien;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeBienRequest;
use App\Http\Requests\UpdateTypeBienRequest;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use Illuminate\Http\Request;



class TypeBienController extends Controller
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
            $typebiens = TypeBien::on('temp')->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();            
            return response()->json(['typeBien' => $typebiens]);
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
    public function store(StoreTypeBienRequest $request)
    {
        if (RoleHelper::AdminSup()) {
                       
            DatabaseHelper::Config();     
            $typebien = new typebien();
            $typebien->setConnection('temp');
            $typebien->type = $request->type;
            $typebien->save();
            return response()->json(['message' => $typebien], 200);
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
            $typebien = typebien::on('temp')->findOrfail($id);
            
            return response()->json(['typeBien' => $typebien], 200);
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
            $typebien = typebien::on('temp')->findOrfail($id);
            return response()->json(['message' => $typebien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTypeBienRequest $request,  $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typebien = typebien::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach($update as $key => $value) {
                $typebien->$key = $value;
            }
            $typebien->save();
            
            return response()->json(['message' => $typebien], 200);
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
            $typebien = typebien::on('temp')->findOrfail($id);             
                        
            if ($typebien->delete()) {
                return response()->json(['message' => 'ce type de bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce type de bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTypeBien($typeBien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            TypeBien::on('temp')->where('id', $typeBien_id)->withTrashed()->restore();

            return response()->json(['message' => 'Type Bien est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTypesBien()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeBiens = TypeBien::on('temp')->onlyTrashed()->get();

            return response()->json(['typeBien' => $typeBiens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
