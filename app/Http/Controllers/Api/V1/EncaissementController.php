<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Models\Encaissement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Helpers\RoleHelper;

class EncaissementController extends Controller
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
        $query = Encaissement::on('temp')->with('reservations', 'remboursement', 'penalite.last_statut', 'avance.last_statut')
            ->whereHas('reservations', function ($q) use ($projet_id) {
                $q->where('projet_id', $projet_id);
            });

        //show d client
        if ($request->filled('client_id')) {
            //si show du client
            $query->where(function($q) use ($request) {
                $q->whereHas('reservations.aquereurs', function ($q) use ($request) {
                    $q->where('client_id', $request->input('client_id'));
                })
                ->orWhereHas('reservations.aquereurs_ancien', function ($q) use ($request) {
                    $q->where('client_id', $request->input('client_id'));
                });
            });
        }

        if ($request->filled('bien_id')) {
            //si show du bein
            $query->where(function($q) use ($request) {
                $q->whereHas('reservations', function ($q) use ($request) {
                    $q->where('bien_id', $request->input('bien_id'));
                })
                ->orWhereHas('remboursement.reservation', function ($q) use ($request) {
                    $q->where('bien_id', $request->input('bien_id'));
                })
                ->orWhereHas('penalite.desistement', function ($q) use ($request) {
                    $q->where('bien_id_ancien', $request->input('bien_id'));
                });
            });
        }

        if ($request->filled('bien')) {
            $query->whereHas('reservations.bien', function ($q) use ($request) {
                $q->where('propriete_dite_bien', 'like', '%' . $request->input('bien') . '%');
            });
        }

        if ($request->filled('code_reservation')) {
            $query->whereHas('reservations', function ($q) use ($request) {
                $q->where('code_reservation', 'like', '%' . $request->input('code_reservation') . '%');
            });
        }

        if ($request->filled('bienId')) {
            $query->where('bien_id', $request->input('bienId'));
        }

        if ($request->filled('type_encaissement')) {
            $query->where('type_encaissement', $request->input('type_encaissement'));
        }

        if ($request->filled('montant')) {
            $query->where('montant', $request->input('montant'));
        }

        if ($request->filled('de')) {
            $start = Carbon::parse($request->input('de'));
            $query->whereDate('date_encaissement', '>=', $start);
        }

        if ($request->filled('a')) {
            $end = Carbon::parse($request->input('a'));
            $query->whereDate('date_encaissement', '<=', $end);
        }
        //actualite filter part type avance
         if ($request->filled('user_id')) {
             if (RoleHelper::Com()){
                $userAuth = User::on('temp')->where('user_id_origin', $request->input('user_id'))->first();
              $id=$userAuth->id;
             }else{
                $id=$request->input('user_id');
             }

             $query->where(function($q) use ($id) {
                $q->whereHas('avance', function ($q) use ($id) {
                    $q->where('user_id', $id);
                });
            });
        }

        if ($request->filled('client')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('reservations.aquereurs.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                })
                ->orWhereHas('reservations.aquereurs_ancien.client', function ($q) use ($request) {
                    $q->where(function ($q) use ($request) {
                        $q->where('nom', 'like', '%' . $request->input('client') . '%')
                            ->orWhere('prenom', 'like', '%' . $request->input('client') . '%');
                    });
                });
            });
        }

        if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
            $encaissements = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $encaissements->currentPage(),
                'totalItems'  => $encaissements->total(),
                'totalPages'  => $encaissements->lastPage(),
            ];

            $encaissements = $encaissements->items();

            return response()->json([
                'data'       => $encaissements,
                'pagination' => $pagination,
            ], 200);
        } else {
            $encaissements = $query->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['encaissements' => $encaissements]);
        }
    }

    return response()->json(['error' => 'Unauthorized'], 401);
}

    /**
     * Remove the specified resource from storage.
     */

}
