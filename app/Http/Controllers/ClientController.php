<?php

namespace App\Http\Controllers;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Banque;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\Prospect;
use App\Enum\TypeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $perPage=$request->input('pageSizee',config('app.default_item_number_perpage'));
            $page=$request->input('page',1);

            $clients= Client::on('temp')
                ->orderBy('created_at','desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json(['clients' => $clients]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    public function get_clients()
    {
        if (RoleHelper::Superadmin() && Auth::guard('api')->user()->societe_id == 1) {
            $clients = Client::all();
            return response()->json(['clients' => $clients]);
        } else if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $clients = Client::on('temp')->get();
            return response()->json(['clients' => $clients], 200);
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
    public function store(StoreClientRequest $request)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $client= new Client();
            $client->setConnection('temp');
            $client->type_client=$request->type_client;
            if($request->type_client=='PART'){//PARTICULIER
                $client->type_client=TypeClient::PARTICULIER->value;
            }
            else{
                $client->type_client=TypeClient::SOCIETE->value;
                $client->societe_id=$request->societe_id;
            }
            $client->nom=$request->nom;
            $client->prenom=$request->prenom;
            $client->telephone_num1=$request->telephone_num1;
            $client->telephone_num2=$request->telephone_num2;
            $client->notifie=$request->notifie;
            $client->email=$request->email;
            $client->civilite=$request->civilite;
            $client->adresse=$request->adresse;
            $client->ville=$request->ville;
            $client->pays=$request->pays;
            $client->profession=$request->profession;
            $client->cin=$request->cin;
            $client->lieu_naissance=$request->lieu_naissance;
            $client->nationalite=$request->nationalite;
            $client->date_naissance=$request->date_naissance;
            $client->nom_responsable=$request->nom_responsable;
            $client->relation_familliale=$request->relation_familliale;
            $client->situation_familliale=$request->situation_familliale;
            $client->date_mariage=$request->date_mariage;
            $client->nom_mari=$request->nom_mari;
            $client->lieu_mariage=$request->lieu_mariage;
            $client->nom_pere=$request->nom_pere;
            $client->nom_mere=$request->nom_mere;
            $client->prospect_id=$request->prospect_id;
            if($client->save()){
                return $client;
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $client=Client::on('temp')->findOrFail($id);
            $perPage = $request->input('pageSizee', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $reservations=Reservation::on('temp')->join('aquereurs', 'aquereurs.reservation_id', '=', 'reservations.id')
            ->select('reservations.*','aquereurs.pourcentage')
            ->where('aquereurs.client_id',$id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);            
            return response()->json(['client' => $client,'reservations'=>$reservations], 200);


        }
        return response()->json(['error' => 'Unauthorized'], 401);
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
    public function update(UpdateClientRequest $request, $id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $client=Client::on('temp')->findOrFail($id);
            $update=$request->all();
            foreach ($update as $key => $value){
                $client->$key=$value;
            }
            $client->save();
            return response()->json(['client'=>$client],200);
        }
        return response()->json(['error'=>'Unauthorized'],401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $client=Client::on('temp')->findOrFail($id);

            if($client->delete()){
                return response()->json(['message'=>'Client deleted successfully'],200);
            }
            else{
                return response()->json(['message'=>'Client non deleted'],400);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    public function getClient_by_projet(Request $request, $projet_id)
    {
        if (RoleHelper::ACSup()) {
            DatabaseHelper::Config();
            $perPage = $request->input('pageSize', config('app.default_item_number_perpage')); // Get the number of items per page
            $page = $request->input('page', 1);
            $clients=Client::on('temp')->join('aquereurs', 'aquereurs.client_id', '=', 'clients.id')
             ->join('reservations', 'reservations.id', '=', 'aquereurs.reservation_id')
             ->where('reservations.projet_id',$projet_id)
             ->select('clients.*')
             ->distinct()
            ->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['clients' => $clients], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function search_client_by_cin($cin)
    {
        if(RoleHelper::ACSup()){
            DatabaseHelper::Config();
            $client = Client::on('temp')->where('cin',$cin)
                ->get()->first();

            if($client!=null){
                //si client n'est pas prospect
                if($client->prospect_id==null){
                    $prospect = Prospect::on('temp')->with('visites_perdu')->where('cin',$cin)
                    ->get()->first();
                }
                else{
                    //client est un prospect
                    $prospect = Prospect::on('temp')->where('id',$client->prospect_id)->with('visites_perdu')->get()->first();
                }
                }
            else{
                //client n'existe  pas
                $prospect = Prospect::on('temp')->with('visites_perdu')->where('cin',$cin)
                ->get()->first();
            }


            return response()->json(['client' => $client,'prospect'=>$prospect]);
         }
     }
     public function search_client_by_phone($phone)
    {
        if(RoleHelper::ACSup()){
             DatabaseHelper::Config();
             $client = Client::on('temp')
             ->where(function($query) use ($phone) {
                $query->where('telephone_num1',$phone)
                    ->orwhere('telephone_num2',$phone)
                    ;})
                    ->get()->first();

            if($client!=null){
                        //si client n'est pas prospect
                       if($client->prospect_id==null){
                         $prospect = Prospect::on('temp')->with('visites_perdu')
                         ->where(function($query) use ($phone) {
                            $query->where('telephone',$phone)
                                ->orwhere('telephone_num2',$phone)
                                ;})
                            ->get()->first();
                        }
                        else{
                            //client est un prospect
                        $prospect = Prospect::on('temp')->where('id',$client->prospect_id)->with('visites_perdu')->get()->first();
                        }
                        }
            else{
                    $prospect = Prospect::on('temp')->with('visites_perdu')
                    ->where(function($query) use ($phone) {
                    $query->where('telephone',$phone)
                        ->orwhere('telephone_num2',$phone)
                        ;})
                    ->get()->first();
                        }
           }

            return response()->json(['client' => $client,'prospect'=>$prospect]);

     }

}
