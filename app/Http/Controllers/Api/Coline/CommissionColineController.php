<?php

namespace App\Http\Controllers\Api\Coline;

use App\Models\UserProjet;
use App\Http\Controllers\Controller;
use App\Models\coline\CommissionColine;
use App\Models\User;
use App\Models\Avance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\DatabaseHelper;

class CommissionColineController extends Controller
{
    /** ============================================================
     *  📋 LISTE PAGINÉE (utilisée par le front)
     *  ============================================================ */
    public function index_paginated(Request $request)
    {
        ini_set('memory_limit', '-1');
        DatabaseHelper::Config();

        $projet_id = $request->input('projet_id');

        if (!$projet_id) {
            return response()->json(['error' => 'projet_id requis'], 400);
        }

        // 👥 Récupérer les users du projet
        $users = $this->getUsers($projet_id);
        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return response()->json([
                'users'       => [],
                'commissions' => [],
                'total'       => 0,
                'pagination'  => [
                    'currentPage' => 1,
                    'totalItems'  => 0,
                    'totalPages'  => 1,
                ],
            ]);
        }

        // 🔍 Construire la requête
        $query = $this->baseQuery($request, $projet_id)
            ->whereIn('commissions_coline.user_id', $userIds);

        // ⚙️ Pagination
        $size = $request->input('size', 10);
        $page = $request->input('page', 1);

        $commissions = $query->paginate($size, ['commissions_coline.*'], 'page', $page);

        return response()->json([
            'users'       => $users,
            'commissions' => $commissions->items(),
            'total'       => $commissions->total(),
            'pagination'  => [
                'currentPage' => $commissions->currentPage(),
                'totalItems'  => $commissions->total(),
                'totalPages'  => $commissions->lastPage(),
            ],
        ]);
    }

    /** ============================================================
     *  📤 EXPORT (sans pagination)
     *  ============================================================ */
   public function export_paginated(Request $request)
{
    ini_set('memory_limit', '-1');
    DatabaseHelper::Config();

    $projet_id = $request->input('projet_id');

    if (!$projet_id) {
        return response()->json(['error' => 'projet_id requis'], 400);
    }

    $users = $this->getUsers($projet_id);
    $userIds = $users->pluck('id')->toArray();

    if (empty($userIds)) {
        return response()->json(['commissions' => [], 'total' => 0]);
    }

    $query = $this->baseQuery($request, $projet_id)
        ->whereIn('commissions_coline.user_id', $userIds);

    // Si l'onglet actif est envoyé → filtrer par cet user
    if ($request->filled('active_user_id')) {
        $query->where('commissions_coline.user_id', $request->input('active_user_id'));
    }

    // ✅ FILTRE PAR DATE — selon le type
   // ✅ Filtre si AU MOINS UNE date est présente
if ($request->filled('date_from') || $request->filled('date_to')) {
    $dateFrom = $request->input('date_from') ?? $request->input('date_to');
    $dateTo   = $request->input('date_to') ?? $request->input('date_from');
    $exportType = $request->input('export_type', 'creation');

    switch ($exportType) {
        case 'traitement':
            $query->whereDate('commissions_coline.date_traitement', '>=', $dateFrom)
                  ->whereDate('commissions_coline.date_traitement', '<=', $dateTo);
            break;

        case 'reservation':
            $query->whereHas('reservation', function ($q) use ($dateFrom, $dateTo) {
                $q->whereDate('date_reservation', '>=', $dateFrom)
                  ->whereDate('date_reservation', '<=', $dateTo);
            });
            break;

        default: // 'creation'
            $query->whereDate('commissions_coline.created_at', '>=', $dateFrom)
                  ->whereDate('commissions_coline.created_at', '<=', $dateTo);
            break;
    }
}
    $commissions = $query->get();

    // Charger les relations manuellement
    $commissions->load([
        'reservation.bien.tranche',
        'reservation.aquereurs.client',
        'user',
        'userTraite',
    ]);

    return response()->json([
        'commissions' => $commissions,
        'total'       => $commissions->count(),
    ]);
}

    /** ============================================================
     *  🔧 REQUÊTE DE BASE (accepte les filtres)
     *  ============================================================ */
    private function baseQuery($request, $projet_id)
    {
        DatabaseHelper::Config();

        $avancesSub = "(
            SELECT SUM(avances.montant)
            FROM avances
            WHERE avances.reservation_id = commissions_coline.reservation_id
              AND avances.statut = 1
        )";

        $query = CommissionColine::on('temp')
            ->with([
            // ✅ Uniquement ce dont le front a besoin
            'reservation' => function ($q) {
                $q->select(
                    'id',
                    'code_reservation',
                    'date_reservation',
                    'prix',
                    'bien_id',
                    'etat',

                );
            },
            'reservation.bien' => function ($q) {
                $q->select(
                    'id',
                    'propriete_dite_bien',
                    'tranche_id'
                );
            },
            'reservation.bien.tranche' => function ($q) {
                $q->select('id', 'nom');
            },
            'reservation.aquereurs' => function ($q) {
                $q->select('id', 'reservation_id', 'client_id');
            },
            'reservation.aquereurs.client' => function ($q) {
                $q->select('id', 'nom', 'prenom');
            },
            'user' => function ($q) {
                $q->select('id', 'name', 'prenom');
            },
        ])
            ->select(
                'commissions_coline.*',
                DB::raw("COALESCE($avancesSub, 0) as montant_p")
            )
            ->whereIn('commissions_coline.enable', [1, 2])
            ->whereHas('reservation', function ($q) {
                $q->where('etat', 1);
            });

        // 🔍 Filtre par user (onglet actif)
        if ($request->filled('active_user_id')) {
            $query->where('commissions_coline.user_id', $request->input('active_user_id'));
        }

        // 🔍 Filtre par état simple
        if ($request->filled('etat')) {
            $query->where('commissions_coline.etat', $request->input('etat'));
        }

        // 🔍 Filtre par plusieurs états (ex: "1,3")
        if ($request->filled('etats')) {
            $etats = explode(',', $request->input('etats'));
            $query->whereIn('commissions_coline.etat', $etats);
        }

        // 🔍 Filtre recherche
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('reservation', function ($q) use ($search) {
                $q->where('code_res_coline', 'like', "%$search%");
            });
        }

        return $query->orderBy('commissions_coline.created_at', 'desc');
    }

    /** ============================================================
     *  👥 UTILISATEURS (role=3, actifs, du projet)
     *  ============================================================ */
    private function getUsers($projet_id)
    {
        DatabaseHelper::Config();

        $userIds = UserProjet::on('temp')
            ->where('projet_id', $projet_id)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->toArray();

        if (empty($userIds)) {
            return collect();
        }

        return User::on('temp')
            ->whereIn('id', $userIds)
            ->where('is_actif', 1)
            ->where('role', 3)
            ->whereNull('deleted_at')
            ->get();
    }

    /** ============================================================
     *  📄 ANCIENNES MÉTHODES (à garder ou supprimer)
     *  ============================================================ */
    public function index_non_traite(Request $request)
    {
        return $this->listByEtat($request, 0, 'Commissions NON traitées', '#ffc107');
    }

    public function index_traite_attente(Request $request)
    {
        ini_set('memory_limit', '-1');
        DatabaseHelper::Config();

        $projet_id = $request->projet_id;
        $users = $this->getUsers($projet_id);
        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return response()->json([
                'users' => [], 'commissions' => [],
                'page' => 'Commissions Traitées en Attente', 'color' => '#3d554c',
            ]);
        }

        $commissions = $this->baseQuery($request, $projet_id)
            ->where(function ($q) {
                $q->where('commissions_coline.etat', 1)
                  ->orWhere('commissions_coline.etat', 3);
            })
            ->whereIn('commissions_coline.user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        return response()->json([
            'users'       => $users,
            'commissions' => $commissions,
            'page'        => 'Commissions Traitées en Attente',
            'color'       => '#3d554c',
        ]);
    }

    public function index_traite_moitie(Request $request)
    {
        return $this->listByEtat($request, 2, 'Commissions Traitées en moitié', '#003f80');
    }

    public function index_traite(Request $request)
    {
        return $this->listByEtat($request, 4, 'Commissions Traitées', '#008000');
    }

    private function listByEtat(Request $request, $etat, $page, $color)
    {
        ini_set('memory_limit', '-1');
        DatabaseHelper::Config();

        $projet_id = $request->projet_id;
        $users = $this->getUsers($projet_id);
        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return response()->json([
                'users' => [], 'commissions' => [],
                'page' => $page, 'color' => $color,
            ]);
        }

        $commissions = $this->baseQuery($request, $projet_id)
            ->where('commissions_coline.etat', $etat)
            ->whereIn('commissions_coline.user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        return response()->json([
            'users'       => $users,
            'commissions' => $commissions,
            'page'        => $page,
            'color'       => $color,
        ]);
    }

    /** ============================================================
     *  📄 DÉTAILS D'UNE COMMISSION
     *  ============================================================ */
    public function show($id)
    {
        DatabaseHelper::Config();
        $commission = CommissionColine::on('temp')
            ->with([
                'reservation',
                'reservation.bien',
                'reservation.bien.tranche',
                'reservation.aquereurs',
                'reservation.aquereurs.client',
                'user',
                'userTraite',
            ])
            ->findOrFail($id);

        $sumAvances = Avance::on('temp')
            ->where('reservation_id', $commission->reservation_id)
            ->where('statut', 1)
            ->sum('montant');

        $commission->montant_p = $sumAvances ?? 0;

        return response()->json(['commission' => $commission]);
    }

    /** ============================================================
     *  ➕ TRAITER UNE COMMISSION
     *  ============================================================ */
    public function traiter_commission(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'montant'         => 'required|numeric',
            'date_traitement' => 'required|date',
            'etat'            => 'required|in:moitie,complet',
            'type_traitement' => 'required|integer',
            'etat_date'       => 'required|integer',
            'reservation_id'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        DatabaseHelper::Config();

        if ($request->type_traitement == 1) {
                         //traiter premier fois

            $commission = CommissionColine::on('temp')->findOrFail($id);
        } else {
                         //nouvelle traitement store new traitement

            $old = CommissionColine::on('temp')
                ->where('reservation_id', $request->reservation_id)
                ->orderBy('created_at', 'DESC')
                ->first();

            if (!$old) {
                return response()->json(['message' => 'Commission introuvable'], 404);
            }

            $old->enable = 0;
            $old->save();

            $commission = new CommissionColine();
            $commission->setConnection('temp');
            $commission->reservation_id = $old->reservation_id;
            $commission->user_id        = $old->user_id;
                //enable==1 sans historique 2= il a des historiques
            $commission->enable         = 2;
        }

        $commission->user_id_traite  = Auth::id();
        $commission->date_traitement = $request->date_traitement;
        $commission->montant         = $request->montant;

        $number = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);
        $commission->montant_lettre = $number->format($request->montant);

        $commission->etat = $this->resolveEtat($request->etat, $request->etat_date);
        $commission->save();

        return response()->json([
            'message'    => 'La commission est traitée avec succès.',
            'commission' => $commission,
        ]);
    }

    /** ============================================================
     *  ✏️ MODIFIER
     *  ============================================================ */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'montant'         => 'required|numeric',
            'date_traitement' => 'required|date',
            'etat'            => 'required|in:moitie,complet',
            'etat_date'       => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        DatabaseHelper::Config();

        $commission = CommissionColine::on('temp')->findOrFail($id);
        $commission->user_id_traite  = Auth::id();
        $commission->date_traitement = $request->date_traitement;
        $commission->montant         = $request->montant;

        $number = new \NumberFormatter('fr', \NumberFormatter::SPELLOUT);
        $commission->montant_lettre = $number->format($request->montant);

        $commission->etat = $this->resolveEtat($request->etat, $request->etat_date);
        $commission->save();

        return response()->json([
            'message'    => 'La commission est modifiée avec succès.',
            'commission' => $commission,
        ]);
    }

    /** ============================================================
     *  ♻️ SUPPRIMER
     *  ============================================================ */
    public function destroy($id)
    {
                DatabaseHelper::Config();

        $commission = CommissionColine::on('temp')->findOrFail($id);

        $commission->etat             = 0;
        $commission->enable           = 1;
        $commission->user_id_traite   = null;
        $commission->date_traitement  = null;
        $commission->montant          = null;
        $commission->montant_lettre   = null;
        $commission->save();

        $relatives = CommissionColine::on('temp')
            ->where('reservation_id', $commission->reservation_id)
            ->where('id', '!=', $id)
            ->get();

        foreach ($relatives as $c) {
            $c->delete();
        }

        return response()->json(['message' => 'La commission est supprimée avec succès.']);
    }

    /** ============================================================
     *  📅 HISTORIQUE
     *  ============================================================ */
    public function getHistorique(Request $request)
    {
                DatabaseHelper::Config();

        $data = CommissionColine::on('temp')
            ->select('date_traitement', 'montant', 'etat')
            ->where('reservation_id', $request->id)
            ->where('enable', 0)
            ->get();

        return response()->json($data);
    }

    /** ============================================================
     *  🔢 RÉSOUT L'ÉTAT
     *  ============================================================ */
    private function resolveEtat($mode, $etatDate)
    {
        if ($mode === 'moitie') {
            return $etatDate == 0 ? 1 : 2;
        }
        return $etatDate == 0 ? 3 : 4;
    }
    /*  /*1=>moitie attent 2=>moitie traite 3=>complet attent 4=>complet traite
            if($request->etat=='moitie'){
                moitie
                if($request->etat_date==0){
                    //moitie attent
                     $commission->etat=1;
                }
                else{
                    //MOITIE TRAITE
                      $commission->etat=2;
                }
            }
            else{
                //complet
                if($request->etat_date==0){
                    //complet attent
                     $commission->etat=3;
                }
                else{
                    //COMPLET TRAITE
                      $commission->etat=4;
                }
            }*/
}
