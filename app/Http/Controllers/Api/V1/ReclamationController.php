<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\User;
use App\Models\Societe;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mail;

class ReclamationController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();
            $user          = Auth::user();
            $userAuth      = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
            $societe       = Societe::findOrfail($user_societes->societe_id);
            $societeDB = 'erp_' . $societe->raison_sociale_concatene . '_' . $societe->id;
            //$user = Auth::user();
            //$userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $query = DB::connection('mysql_client')->table('reclamations')
                ->Leftjoin("$societeDB.users as users_traite", 'users_traite.id', '=', 'reclamations.user_id_traite')
                ->Leftjoin("$societeDB.reservations", 'reservations.id', '=', 'reclamations.dossier_id')
                ->leftJoin("{$societeDB}.projets", function ($join) use ($request) {
                    $join->on('reservations.projet_id', '=', 'projets.id')->where('reservations.projet_id', $request->projet_id);
                })
                ->leftJoin("$societeDB.services_prestataires as services", 'services.id', '=', 'reclamations.service')

                ->join('users', 'users.id', '=', 'reclamations.user_id')
                ->join("{$societeDB}.clients as clients", 'clients.id', '=', 'users.client_id');
            if (RoleHelper::Com()) {
                //Commerciaux==>1  //compta==>2 //Notaire==>3 //4 Apres Vente
                $query->where('service', 1);
            }
            if ($request->filled('date_reclamation')) {
                $start = Carbon::parse($request->input('date_reclamation'));
                $query->whereDate('reclamations.created_at', $start);
            }

            if ($request->filled('code_reservation')) {
                $query->where('reservations.code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
            }
            if ($request->filled('client')) {
                $query->where('clients.nom', 'like', '%' . $request->input('client') . '%')
                    ->orWhere('clients.prenom', 'like', '%' . $request->input('client') . '%');
            }
            if ($request->filled('etat')) {
                $query->where('reclamations.etat', $request->input('etat'));
            }

            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $rec = $query->select('reclamations.*', 'clients.id as client_id'
                    , 'clients.nom as client_nom', 'clients.prenom as client_prenom'
                    , 'reservations.id as dossier_id', 'reservations.code_reservation'
                    , 'users_traite.name as users_traite_nom', 'users_traite.prenom as users_traite_prenom', 'services.nom as nom_service'
                )->orderBy('reclamations.etat', 'asc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $rec->currentPage(),
                    'totalItems'  => $rec->total(),
                    'totalPages'  => $rec->lastPage(),
                ];

                $recItems = $rec->items();

                // Return the response with pagination
                return response()->json([
                    'data'       => $recItems,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $rec = $query->orderBy('reclamations.created_at', 'desc')
                    ->get();

                return response()->json(['rec' => $rec], 200);
            }
        }

        // Return unauthorized error if user is not authenticated
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

    /**
     * Show the form for editing the specified resource.
     */
    public function traiter_reclamation_client($id, Request $request)
    {
        if (RoleHelper::ACSup()) {

            DatabaseHelper::Config();
            $user     = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

            $rec_f = DB::connection('mysql_client')->table('reclamations')->where('id', $id)->update(['user_id_traite' => $userAuth->value('id'), 'etat' => $request->statut, 'date_fin_traitement' => $request->date_fin_traitement, 'date_traitement' => $request->date_traitement, 'commentaire' => $request->commentaire]);
            //send notif to client
            $rec  = DB::connection('mysql_client')->table('reclamations')->where('id', $id)->get()->first();
            $type = 0;
            $text = '';
            if ($rec->etat == 1) {
                $type = 1;
                $text = 'Le Responsable a résolu votre Réclamation d\'objet :' . $rec->objet;
            } elseif ($rec->etat == 2) {
                $type = 2;
                $text = 'Objet de Réclamation: ' . $rec->objet . ' Non Résolu.';
            } else {
                $type = 3;
                $text = 'Objet de Réclamation: ' . $rec->objet . ' Est En cours De Traitement.';
            }

            $rec_notif_to_client = DB::connection('mysql_client')->table('notifications')->insert(['user_id' => $rec->user_id, 'text' => $text, 'lien' => '/reclamations', 'type' => $type, 'created_at' => Carbon::now()]);
            //send mail to client avec etat

            $to_email = 'fadwa.test02@gmail.com';
            $data     = ['etat' => $rec->etat, 'objet_rec' => $rec->objet, 'comment' => $rec->commentaire];
            Mail::send('Client.mail', $data, function ($message) use ($to_email) {
                $message->to($to_email)
                    ->subject('Avis Réclamation');
                $message->from(env('MAIL_USERNAME'), 'Immobilier Immo ');

            });

            return response()->json('c bien', 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */

}
