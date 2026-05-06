<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\Prestataire;
use App\Models\Reclamation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrestatairesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', null);
            $page = $request->input('page', null);
            DatabaseHelper::Config();

            // Démarrer la requête directement sur le modèle
            $query = Prestataire::on('temp')->with('service', 'reclamations');

            if ($request->filled('serviceId')) {
                $query->where('service_id', $request->input('serviceId'));
            }
            if ($request->filled('nom')) {
                $query->where('nom', 'like', '%' . $request->input('nom') . '%');
            }
            if ($request->filled('cin')) {
                $query->where('cin', 'like', '%' . $request->input('cin') . '%');
            }
            if ($request->filled('prenom')) {
                $query->where('prenom', 'like', '%' . $request->input('prenom') . '%');
            }
            if ($request->filled('telephone')) {
                $query->where('telephone', 'like', '%' . $request->input('telephone') . '%');
            }
            if ($request->filled('email')) {
                $query->where('email', 'like', '%' . $request->input('email') . '%');
            }
            if ($request->filled('adresse')) {
                $query->where('adresse', 'like', '%' . $request->input('adresse') . '%');
            }

            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                $pre = $query->orderBy('created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                // Extraire les propriétés du paginateur
                $pagination = [
                    'currentPage' => $pre->currentPage(),
                    'totalItems'  => $pre->total(),
                    'totalPages'  => $pre->lastPage(),
                ];

                // Extraire les éléments d'utilisateur du paginateur
                $pre = $pre->items();

                // Retourner la réponse simplifiée
                return response()->json([
                    'data' => $pre,
                    'pagination'   => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $pre = $query->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['prestataire' => $pre], 200);
            }
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function search_prestataire_by_param($param_1, $value)
    {
        //cin ou email
        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            $prestataire = Prestataire::on('temp')->where($param_1, $value)
                ->get()->first();

            return response()->json(['prestataire' => $prestataire]);
        }
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            $pre = new Prestataire();
            $pre->setConnection('temp');
            $pre->nom        = $request->nom;
            $pre->cin        = $request->cin;
            $pre->prenom     = $request->prenom;
            $pre->telephone  = $request->telephone;
            $pre->adresse    = $request->adresse;
            $pre->service_id = $request->service_id;
            $pre->email      = $request->email;
            $pre->civilite      = $request->civilite;
            if ($pre->save()) {
                return response()->json(['prestataire' => $pre], 200);
            }
        }
        return response()->json(['error' => 'Unauthorized'], 401);

    }
    /*function store(Request $request)
    {
        $twilioSid = env('TWILIO_SID');
        $twilioToken = env('TWILIO_AUTH_TOKEN');
        $twilioWhatsAppNumber = env('TWILIO_PHONE_NUMBER');
        $recipientNumber = 'whatsapp:+'.'212641622329';
        $message = 'jh';

        try {

            $twilio = new Client($twilioSid, $twilioToken);
            $twilio->messages->create(
                $recipientNumber,
                [
                    "from" => "whatsapp:". $twilioWhatsAppNumber,
                    "body" => $message,
                ]
            );
            return response()->json('hhh');
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }*/

    public function get_info_cin_prestataire_unique($id, $cin)
    {
        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            //cin unique
            $pres_count = Prestataire::on('temp')->where('cin', $cin)->where('id', '!=', $id)->count();
            return response()->json(['pres_count' => $pres_count]);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            $pre = Prestataire::on('temp')->findOrFail($id);
            return response()->json(['prestataire' => $pre], 200);
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
    public function update(Request $request, $id)
    {
        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            $pre = Prestataire::on('temp')->findOrfail($id);
            $pre->setConnection('temp');
            $pre->nom        = $request->nom;
            $pre->cin        = $request->cin;
            $pre->prenom     = $request->prenom;
            $pre->telephone  = $request->telephone;
            $pre->adresse    = $request->adresse;
            $pre->service_id = $request->service_id;
            $pre->email      = $request->email;
            $pre->civilite      = $request->civilite;

            if ($pre->save()) {
                return response()->json(['prestataire' => $pre], 200);
            }

        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (RoleHelper::AdminSup()||RoleHelper::SAV()) {
            DatabaseHelper::Config();
            $pre          = Prestataire::on('temp')->findOrFail($id);
            $reclamations = Reclamation::on('temp')->where('prestataire_id', $id)->get();
            if (count($reclamations) > 0) {
                foreach ($reclamations as $rec) {
                    $recController = new ReclamationSavController();
                    $recController->destroy($rec->id);
                }
            }
            if ($pre->delete()) {
                return response()->json(['message' => 'prestataire Supprimé avec succés'], 200);
            } else {
                return response()->json(['message' => 'prestataire Non Suprimé'], 400);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
}
