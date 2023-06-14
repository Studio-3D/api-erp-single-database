<?php

namespace App\Http\Controllers;

use App\Models\Immeuble;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImmeubleRequest;
use App\Http\Requests\UpdateImmeubleRequest;
use Illuminate\Support\Facades\Auth;



class ImmeubleController extends Controller
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
    public function store(StoreImmeubleRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Immeuble $immeuble)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Immeuble $immeuble)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateImmeubleRequest $request, Immeuble $immeuble)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Immeuble $immeuble)
    {
        //
    }
    public function restoreImmeuble($immeuble_id)
    {
        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {

            Immeuble::where('id', $immeuble_id)->withTrashed()->restore();

            return response()->json(['message' => 'Immeuble est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedImmeubles()
    {

        if (Auth::guard('api')->check() && Auth::guard('api')->user()->type == 1) {
            $immeubles = Immeuble::onlyTrashed()->get();

            return response()->json(['message' => $immeubles], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
