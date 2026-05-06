<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Enum\InteretEnum;
use App\Enum\InteretEnumAppel;

use App\Enum\OrientationEnum;
use App\Enum\TypeNotificationEnum;
use App\Enum\StatutVisiteEnum;
use App\Enum\StatutReservationEnum;
use App\Enum\ModeFinancement;
use App\Enum\ModePaiement;
use App\Enum\TypeClient;
use App\Enum\Civilite;
use App\Enum\SituationFamilliale;
use App\Enum\EtatBien;
use App\Enum\TypeDesistement;
use App\Enum\TypeDesistementProfit;
use App\Enum\MotifDesistement;
use App\Enum\LienParente;
use App\Enum\StatutRdvEnum;


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

    public function get_enums_desistements()
    {
        $type_desistements=array_column(TypeDesistement::cases(), 'name', 'value');
        $type_desistements_profit=array_column(TypeDesistementProfit::cases(), 'name', 'value');
        $motif_desistements=array_column(MotifDesistement::cases(), 'name', 'value');
        $lien_parentes=array_column(LienParente::cases(), 'name', 'value');
        return response()->json(['type_desistements' => $type_desistements,'type_desistements_profit' => $type_desistements_profit,'motif_desistements' => $motif_desistements,'lien_parentes'=>$lien_parentes]);
    }
    public function InteretEnum_appel_get()
    {
        return response()->json(['list' => array_column(InteretEnumAppel::cases(), 'name', 'value')]);
    }
    public function InteretEnum_get()
    {
        return response()->json(['list' => array_column(InteretEnum::cases(), 'name', 'value')]);
    }
    public function ModefinanceEnum_get()
    {
        return response()->json(['list' => array_column(ModeFinancement::cases(), 'name', 'value')]);
    }

    public function ModePaiementEnum_get()
    {
        return response()->json(['list' => array_column(ModePaiement::cases(), 'name', 'value')]);
    }

    public function TypesClientEnum_get()
    {
        return response()->json(['list' => array_column(TypeClient::cases(), 'name', 'value')]);
    }

    public function CiviliteEnum_get()
    {
        return response()->json(['list' => array_column(Civilite::cases(), 'name', 'value')]);
    }
    public function StatutFamilleEnum_get()
    {
        return response()->json(['list' => array_column(SituationFamilliale::cases(), 'name', 'value')]);
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

    public function StatutReservationEnum_get()
    {
        return response()->json(['list' => array_column(StatutReservationEnum::cases(), 'name', 'value')]);
    }

    public function EtatBien_get()
    {
        return response()->json(['list' => array_column(EtatBien::cases(), 'name', 'value')]);
    }
    public function StatutRdvEnum_get()
    {
        return response()->json(['list' => array_column(StatutRdvEnum::cases(), 'name', 'value')]);
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
