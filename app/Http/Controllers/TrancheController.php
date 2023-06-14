<?php

namespace App\Http\Controllers;

use App\Models\Tranche;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrancheRequest;
use App\Http\Requests\UpdateTrancheRequest;
use Illuminate\Support\Facades\Auth;



class TrancheController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreTrancheRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Tranche $tranche)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tranche $tranche)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrancheRequest $request, Tranche $tranche)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tranche $tranche)
    {
        //
    }


    public function restoreTranche($tranche_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            Tranche::where('id', $tranche_id)->withTrashed()->restore();

            return response()->json(['message' => 'Tranche est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTranches()
    {

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $tranches = Tranche::onlyTrashed()->get();

            return response()->json(['message' => $tranches], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
