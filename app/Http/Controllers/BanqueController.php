<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreBanqueRequest;
use App\Http\Requests\UpdateBanqueRequest;
use App\Models\Banque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BanqueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSizee', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);

            $banques = Banque::on('temp')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['banques' => $banques], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function get_banques()
    {
        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
            $banques = Banque::all();
            return response()->json(['banques' => $banques]);
        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $banques = Banque::on('temp')->get();
            return response()->json(['banques' => $banques], 200);
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
    public function store(StoreBanqueRequest $request)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = new Banque();
            $banque->setConnection('temp');
            $banque->nom = $request->nom;
            if ($banque->save()) {
                return response()->json(['banque' => $banque], 200);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $banque = Banque::on('temp')->findOrFail($id);
            return response()->json(['banque' => $banque], 200);
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
    public function update(UpdateBanqueRequest $request, $id)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = Banque::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $banque->$key = $value;
            }
            $banque->save();
            return response()->json(['banque' => $banque], 200);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::SuperAdmin() || RoleHelper::Comptable() || RoleHelper::AdminComptable()) {
            DatabaseHelper::Config();
            $banque = Banque::on('temp')->findOrFail($id);
            if ($banque->delete()) {
                return response()->json(['message' => 'Bank deleted successfully'], 200);
            } else {
                return response()->json(['message' => 'Bank non deleted'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
