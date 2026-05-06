<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreReclamationRequest;
use App\Http\Requests\UpdateReclamationRequest;
use App\Models\Reclamation;
use App\Models\Bien;
use App\Models\Prestataires;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Societe;
use App\Models\StatutAvancePenaflite;
use App\Models\User;
use Illuminate\Support\Facades\File;
use App\Http\Requests\StorePiecesJointeRequest;
use App\Models\Prestataire;
use Carbon\Carbon;
use Mail;
use Illuminate\Support\Facades\Log;

class ReclamationSavController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function indexByProjet(Request $request, $projet_id)
    {
        if (Auth::guard('api')->check()) {
            // Default values for pagination null si non pas envoyer avec la raquete
            $size = $request->input('size', null);
            $page = $request->input('page', null);

            DatabaseHelper::Config();

            $query = Reclamation::on('temp')->with('piece_jointe','bien','prestataire')->where('reclamations.projet_id', $projet_id);


            if ($request->filled('propriete_dite_bien')) {
                $query->whereHas('bien', function ($q) use ($request) {
                    $q->where('propriete_dite_bien', 'like', '%' . $request->input('propriete_dite_bien') . '%');
                });
            }
            if ($request->filled('client')) {
                $query->whereHas('bien.reservation.aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });

             }
            if ($request->filled('date_intervention')) {
                $start = Carbon::parse($request->input('date_intervention'));
                $query->whereDate('reclamations.date_intervention','>=', $start);
            }
            if ($request->filled('date_fin_intervention')) {
                $end = Carbon::parse($request->input('date_fin_intervention'));
                $query->whereDate('reclamations.date_fin_intervention','<=', $end);
            }

            if ($request->filled('date_reclamation')) {
                $start = Carbon::parse($request->input('date_reclamation'));
                $query->whereDate('reclamations.date_reclamation', $start);
            }

            if ($request->filled('prestataire')) {
                $query->whereHas('prestataire', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('prestataire') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('prestataire') . '%');
                    });
                });

             }
             if ($request->filled('statut')) {
                $query->where('reclamations.statut', $request->input('statut'));
            }
            if ($request->filled('prestataire_id') && $request->input('prestataire_id')!='false' ) {
                $query->where('reclamations.prestataire_id', $request->input('prestataire_id'));
            }


            // Check if pagination parameters are provided and valid
            if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
                // Paginate the query results
                $rec = $query->LeftJoin('reservations','reservations.bien_id','reclamations.bien_id')
                ->select('reclamations.*','reservations.id as res_id')
                ->where('reservations.etat',1)
                ->where('reservations.deleted_at',null)
                ->orderBy('reclamations.created_at', 'desc')
                    ->paginate($size, ['*'], 'page', $page);

                $pagination = [
                    'currentPage' => $rec->currentPage(),
                    'totalItems' => $rec->total(),
                    'totalPages' => $rec->lastPage(),
                ];


                $recItems = $rec->items();

                // Return the response with pagination
                return response()->json([
                    'data' => $recItems,
                    'pagination' => $pagination,
                ], 200);
            } else {
                // Return all results if pagination parameters are not provided or invalid
                $rec = $query->orderBy('created_at', 'desc')
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
   public function store(StoreReclamationRequest $request)
{
    if(RoleHelper::AdminSup()||RoleHelper::SAV()){
        DatabaseHelper::Config();
        $user = Auth::user();
        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

       /* // Get the prestataire with minimum nb_reclamation for this service
        $prestataire = Prestataire::on('temp')
            ->where('service_id', $request->service_id)
            ->whereNull('deleted_at')
            ->orderBy('nb_reclamations', 'asc')
            ->first();

        // Check if prestataire exists
        if (!$prestataire) {
            return response()->json(['error' => 'No prestataire available for this service'], 400);
        }*/

        $rec = new Reclamation();
        $rec->setConnection('temp');
        $rec->bien_id = $request->bien_id;
        $rec->projet_id = $request->projet_id;
        $rec->client_id = $request->client_id;
        $rec->date_reclamation = $request->date_reclamation;
        $rec->statut = 1;
        $rec->service_id = $request->service_id;
        //$rec->prestataire_id = $prestataire->id; // Assign the selected prestataire
        $rec->emplacements = $request->emplacements;
        $rec->problemes = $request->problemes;
        $rec->user_id = $userAuth->value('id');

        if($rec->save()){
            // Increment the prestataire's nb_reclamation
           // $prestataire->increment('nb_reclamations');

            // Store attached files
            if ($request->files_reclamation) {
                foreach ($request->files_reclamation as $file) {
                    $piecesJointeController = new PiecesJointeController();
                    $pieceJointeRequest = new StorePiecesJointeRequest();
                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                    $user_connecter = $userAuth->value('user_id_origin');
                    $user_societes = User::where('id', $user_connecter)->first();
                    $societe = Societe::findOrfail($user_societes->societe_id);

                    $fileName = $file->getClientOriginalName();
                    $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reclamations');
                    File::makeDirectory($directory, 0755, true, true);
                    $file->move($directory, $fileName);
                    $fileType = $file->getClientOriginalExtension();

                    $datapieceJointe = [
                        'fichier' => $fileName,
                        'type' => $fileType,
                        'reclamation_id' => $rec->id,
                        'active' => 1,
                    ];

                    $pieceJointeRequest->merge($datapieceJointe);
                    $piecesJointeController->store($pieceJointeRequest);
                }
            }

          /*  // Send email to prestataire
            if($prestataire->email != null){
                $to_email = $prestataire->email;
                $data = [
                    'bien' => $rec->bien->propriete_dite_bien ?? 'N/A',
                    'client' => $rec->client->nom.' '.$rec->client->prenom ?? 'N/A',
                    'emplacement' => $rec->emplacements,
                    'prestataire' => $prestataire->nom.' '.$prestataire->prenom
                ];

                try {
                    Mail::send('SAV.mail', $data, function($message) use($to_email) {
                        $message->to($to_email)
                            ->subject('Nouvelle Réclamation');
                        $message->from(env('MAIL_USERNAME'), 'Immobilier');
                    });
                } catch (\Exception $e) {
                    // Log email error but don't fail the whole operation
                    Log::error('Failed to send email to prestataire: ' . $e->getMessage());
                }
            }
*/
            return response()->json([
                'recl' => $rec,
                /*'prestataire_assigne' => [
                    'id' => $prestataire->id,
                    'nom' => $prestataire->nom,
                    'prenom' => $prestataire->prenom,
                    'email' => $prestataire->email,
                    'nb_reclamations' => $prestataire->nb_reclamations
                ]*/
            ], 200);
        }

        return response()->json(['error' => 'Failed to save reclamation'], 500);
    }
    else return response()->json(['error' => 'Unauthorized'], 401);
}

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $rec = Reclamation::on('temp')->with('piece_jointe')->findOrfail($id);
            $bien=Bien::on('temp')->with('reservation')->findorfail($rec->bien_id);
            //$prestataires=Prestataire::on('temp')->where('service_id',$rec->prestataire->service_id)->get();
            return response()->json(['reclamation' => $rec,'bien'=>$bien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
   public function traiter_reclamation($id, Request $request)
{
    if(RoleHelper::AdminSup()||RoleHelper::SAV()){
        DatabaseHelper::Config();
        $rec = Reclamation::on('temp')->findOrFail($id);
        $rec->statut = 2;
        $rec->date_intervention = $request->date_intervention;
        $rec->prestataire_id = $request->prestataire_id;
        $rec->commentaires = $request->commentaire;
        $rec->save();

        // Get prestataire details
        $prestataire = Prestataire::on('temp')->findOrFail($request->prestataire_id);

         // Construire la référence complète du bien
        $bienReference = $rec->bien->propriete_dite_bien ?? '';

        // Ajouter les informations de tranche, bloc, immeuble si elles existent
        $referenceParts = [];

        if ($rec->bien->tranche && !empty($rec->bien->tranche->nom)) {
            $referenceParts[] = $rec->bien->tranche->nom;
        }

        if ($rec->bien->bloc && !empty($rec->bien->bloc->nom)) {
            $referenceParts[] = $rec->bien->bloc->nom;
        }

        if ($rec->bien->immeuble && !empty($rec->bien->immeuble->nom)) {
            $referenceParts[] = $rec->bien->immeuble->nom;
        }

        // Ajouter la propriété dite bien si elle existe
        if (!empty($bienReference)) {
            $referenceParts[] = $bienReference;
        }

        // Construire la référence finale
        $bienReferenceComplete = !empty($referenceParts) ? implode('-', $referenceParts) : 'N/A';

        // Send email to prestataire
        if($prestataire->email != null){
            $to_email = $prestataire->email;

            // Prepare email data
            $data = [
                'prestataire' => [
                    'nom' => $prestataire->nom,
                    'prenom' => $prestataire->prenom,
                ],
                'client' => [
                    'nom' => $rec->client->nom ?? 'N/A',
                    'prenom' => $rec->client->prenom ?? 'N/A',
                    'email' => $rec->client->email ?? null,
                    'telephone' => $rec->client->telephone_num2 ?? null,
                    'adresse' => $rec->client->adresse ?? null,
                ],
                'bien' => [
                   'reference' => $bienReferenceComplete,
                ],
                'service' => [
                    'nom' => $rec->service->nom ?? ' ',
                ],
                'reclamation' => [
                    'id' => $rec->id,
                    'date_reclamation' => $rec->date_reclamation
                        ? date('d/m/Y', strtotime($rec->date_reclamation))
                        : date('d/m/Y'),
                    'emplacements' => $rec->emplacements ?? 'Non spécifié',
                    'problemes' => $rec->problemes ?? 'Description non disponible',
                    'date_intervention' => $rec->date_intervention
                        ? date('d/m/Y', strtotime($rec->date_intervention))
                        : 'À planifier',
                ],
                'reclamationLink' => env('APP_URL') . '/sav/reclamations/show/' . $rec->id,
            ];

            try {
                Mail::send('SAV.nouvelle-reclamation-prestataire', $data, function($message) use($to_email, $prestataire, $rec) {
                    $message->to($to_email)
                        ->subject('Nouvelle Réclamation #' . $rec->id . ' - Intervention Requise');
                    $message->from(env('MAIL_USERNAME'), 'Immobilier Immo - SAV');
                });

                // Log email sent
                Log::info('Email de nouvelle réclamation envoyé au prestataire', [
                    'prestataire_id' => $prestataire->id,
                    'reclamation_id' => $rec->id,
                    'email' => $to_email,
                ]);

            } catch (\Exception $e) {
                // Log email error
                Log::error('Échec d\'envoi d\'email au prestataire : ' . $e->getMessage(), [
                    'prestataire_id' => $prestataire->id,
                    'reclamation_id' => $rec->id,
                    'email' => $to_email,
                ]);
            }
        }

        return response()->json([
            'rec' => $rec,
            'message' => 'Réclamation assignée avec succès au prestataire',
            'prestataire' => [
                'id' => $prestataire->id,
                'nom' => $prestataire->nom,
                'prenom' => $prestataire->prenom,
                'email' => $prestataire->email
            ]
        ], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
    public function resoudre_reclamation($id, Request $request)
{

    if(RoleHelper::AdminSup()||RoleHelper::SAV()){
        DatabaseHelper::Config();
        $rec = Reclamation::on('temp')->findOrFail($id);
        $rec->date_fin_intervention = $request->input('date_fin_intervention');

        $rec->statut = $request->input('statut');
        $rec->commentaire_trait = $request->input('commentaire'); // Vérifie que c’est bien ce champ dans ta base

        $rec->save();

        return response()->json(['rec' => $rec], 200);
    } else {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
}

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReclamationRequest $request,$id)
    {
    if(RoleHelper::AdminSup()||RoleHelper::SAV()){
            DatabaseHelper::Config();
            $rec=Reclamation::on('temp')->findOrFail($id);
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();

            $rec->bien_id=$request->bien_id;
            $rec->client_id=$request->client_id;
            $rec->date_reclamation=$request->date_reclamation;
            $rec->date_intervention=$request->date_intervention;
            $rec->date_fin_intervention=$request->date_fin_intervention;
            $rec->service_id=$request->service_id;
            $rec->emplacements=$request->emplacements;
            $rec->problemes=$request->problemes;
            if($rec->save()){
                ////storer les pieces jointe d

                if ($request->files_reclamation) {

                      //****delete old piece jointe***
                      $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                      $societe = Societe::findOrfail($user_societes->societe_id);
                        $pjController = new PiecesJointeController();
                        $pjController->destoryFileUsingReclamationId($id, $societe);

                    foreach ($request->files_reclamation as $file) {
                        $piecesJointeController = new PiecesJointeController();
                        $pieceJointeRequest = new StorePiecesJointeRequest();
                        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                        $user_connecter = $userAuth->value('user_id_origin');
                        $user_societes = User::where('id', $user_connecter)->first();
                        $societe = Societe::findOrfail($user_societes->societe_id);

                        // Récupérer le nom du fichier
                        $fileName = $file->getClientOriginalName();
                        $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/reclamations');
                        File::makeDirectory($directory, 0755, true, true);
                        $file->move($directory, $fileName);
                        $fileType = $file->getClientOriginalExtension();
                        $datapieceJointe = [
                            'fichier' => $fileName,
                            'type' => $fileType,
                            'reclamation_id' => $rec->id,
                            'active' => 1,
                        ];

                        $pieceJointeRequest->merge($datapieceJointe);
                        $piecesJointeController->store($pieceJointeRequest);
                    }
                }
                  //send email to prestataire
                  /* $pres=Prestataire::on('temp')->findorfail($request->prestataire_id);
                  if($pres->email!=null){
                      $to_email=$pres->email;
                      $data=array('bien'=>$rec->bien->propriete_dite_bien,'client'=>$rec->client->nom.' '.$rec->client->prenom,'emplacement'=>$rec->emplacements);
                        Mail::send('SAV.mail', $data, function($message) use($to_email){
                          $message->to($to_email)
                              ->subject ('Nouvlle Réclamation');
                          $message->from('immo.immobilier02@gmail.com','Immobilier');

                      });
                  } */
            }
            return response()->json(['rec'=>$rec],200);
        }
        else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
    if(RoleHelper::AdminSup()||RoleHelper::SAV()){
            DatabaseHelper::Config();
            $user = Auth::user();
            $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
            $rec=Reclamation::on('temp')->findOrFail($id);
            if($rec->delete())
            {
                $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                $societe = Societe::findOrfail($user_societes->societe_id);
                  $pjController = new PiecesJointeController();
                  $pjController->destoryFileUsingReclamationId($id, $societe);
                return response()->json(['message'=>'Reclamation supprimée avec succès.'],200);
            }
            else{
                return response()->json(['error'=>"La Réclamation n'a pas été supprimée."],404);
            }
        }
        else{
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }





}
