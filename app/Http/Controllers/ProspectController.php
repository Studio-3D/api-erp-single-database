<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', 5);
            $page = $request->input('page', 1);
            $prospects = Prospect::on('temp')->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['prospects' => $prospects]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }
    public function get_prospects()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $prospects = Prospect::on('temp')->orderBy('created_at', 'desc')->get();
            return response()->json(['prospects' => $prospects]);
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
    public function store(StoreProspectRequest $request)
    {
        if (RoleHelper::ACSup()) {
            Log::info($request);
            DatabaseHelper::Config();
            $prospect = new Prospect();
            $prospect->setConnection("temp");
            $prospect->cin = $request->cin;
            $prospect->nom = $request->nom;
            $prospect->prenom = $request->prenom;
            $prospect->telephone = $request->telephone;
            $prospect->telephone_num2 = $request->telephone_num2;
            $prospect->email = $request->email;
            $prospect->origin = 'manuel';
            $prospect->notifie = $request->notifie;
            $prospect->source = $request->source;
            $prospect->partenaire_id = $request->partenaire_id;
            $prospect->save();
            return $prospect;

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    public static function Store_WhatsApp($phone_number_id, $from, $msg_body, $name, $societe_id)
    {

        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin = null;
        $prospect->message = $msg_body;
        $prospect->nom = $name;
        $prospect->telephone = $from;
        $prospect->email = null;
        $prospect->origin = 'whatssap';
        $prospect->source = 1;
        $prospect->save();
    }

    public static function Store_LandingPage($name, $prenom, $phone, $email, $societe_id)
    {

        DatabaseHelper::Config($societe_id);
        $prospect = new Prospect();
        $prospect->setConnection("temp");
        $prospect->cin = null;
        $prospect->message = null;
        $prospect->nom = $name;
        $prospect->prenom = $prenom;
        $prospect->telephone = $phone;
        $prospect->email = $email;
        $prospect->origin = 'landingPage';
        $prospect->source = 3;
        $prospect->save();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visites_perdu')->findOrfail($id);
            return response()->json(['prospect' => $prospect], 200);
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
    public function update(UpdateProspectRequest $request, $id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            if ($request->cin != null) {
                $cin_exist = Prospect::on('temp')->where('cin', $request->cin)->where('id', '!=', $id)->count();
                if ($cin_exist > 0) {
                    return response()->json(['errors' => 'Le Cin que vous avez saisi' . $request->cin . ' apprtient à un autre utilisateur'], 422);
                }}
            $prospect = Prospect::on('temp')->findOrFail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $prospect->$key = $value;
            }
            $prospect->save();
            return response()->json(['prospect' => $prospect], 200);
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
            $prospect = Prospect::on('temp')->findOrFail($id);
            if ($prospect->delete()) {
                return response()->json(['message' => 'Prospect supprimé avec succès.'], 200);
            } else {
                return response()->json(['error' => "Le prospect n'a pas été supprimé."], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function search_prospect_by_cin($cin)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visite_pre_reserves')->where('cin', $cin)
                ->get()->first();
            return response()->json(['prospect' => $prospect]);
        }
    }
    public function search_prospect_by_email($email)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visite_pre_reserves')->where('email', $email)
                ->get()->first();
            return response()->json(['prospect' => $prospect]);
        }
    }
    public function search_prospect_by_phone($phone)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $prospect = Prospect::on('temp')->with('visite_pre_reserves')
                ->where(function ($query) use ($phone) {
                    $query->where('telephone', $phone)
                        ->orwhere('telephone_num2', $phone)
                    ;})
                ->get()->first();
            return response()->json(['prospect' => $prospect]);
        }
    }
}
