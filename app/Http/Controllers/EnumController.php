<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Enum\InteretEnum;
use App\Enum\OrientationEnum;
use App\Enum\TypeNotificationEnum;
use App\Enum\StatutVisiteEnum;


class EnumController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function get_enums()
    {
        $list_interets=array_column(InteretEnum::cases(), 'name', 'value');
        $list_orientations=array_column(OrientationEnum::cases(), 'name', 'value');
        $list_type_notifs=array_column(TypeNotificationEnum::cases(), 'name', 'value');
        $list_statut_Visites=array_column(StatutVisiteEnum::cases(), 'name', 'value');

        return response()->json(['list_interets' => $list_interets,'list_orientations' => $list_orientations,'list_type_notifs' => $list_type_notifs,'list_statut_Visites'=>$list_statut_Visites]);
    }
    public function InteretEnum_get()
    {
        return response()->json(['list' => array_column(InteretEnum::cases(), 'name', 'value')]);
    }
    public function OrientationEnum_get()
    {
        return response()->json(['list' => array_column(OrientationEnum::cases(), 'name', 'value')]);
    }
    public function TypeNotificationEnum_get()
    {
        return response()->json(['list' => array_column(TypeNotificationEnum::cases(), 'name', 'value')]);
    }
    public function StatutVisiteEnum_get()
    {
        return response()->json(['list' => array_column(StatutVisiteEnum::cases(), 'name', 'value')]);
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
