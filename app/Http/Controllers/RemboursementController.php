<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Remboursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;


class RemboursementController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     
     public function get_detail_transfert(Request $request, $reservation_id)
     {
         if (RoleHelper::ACSup()) {
             DatabaseHelper::Config();
             $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
             $page = $request->input('page', 1);
             $data = Remboursement::on('temp')
                    ->with('dossier_transfert')
                 ->where('reservation_id', $reservation_id)
                 ->select('remboursements.*')->orderBy('created_at','desc')
                 ->paginate($perPage,['*'],'page',$page);
             return response()->json(['remboursement' => $data], 200);

         } else {
             return response()->json(['error' => 'Unauthorized'], 401);

         }
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
