<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Prospect;
use App\Models\ProspectFusion;
use App\Models\Visite;
use App\Models\Appel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\User;
use App\Http\Controllers\Controller;

class ProspectFusionController extends Controller
{
    // =====================================================
    // ⭐ RECHERCHE PROSPECTS
    // =====================================================
  /**
     * Recherche des prospects par téléphone, CIN, nom, prénom, email
     * pour les fusionner avec un prospect principal
     *
     * Query params:
     * - q: string (required, min 2 caractères)
     * - projet_id: int (required)
     * - exclude_id: int (optional) - prospect à exclure (lui-même)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rechercherPourFusion(Request $request)
    {
        // ⭐ Vérification des permissions
        if (!RoleHelper::ACSup() && !RoleHelper::AgentAdmin() && !RoleHelper::RespoCommercial()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        // ⭐ Validation
        $request->validate([
            'q' => 'required|string|min:2',
            'projet_id' => 'required|integer',
            'exclude_id' => 'nullable|integer',
        ]);

        DatabaseHelper::Config();

        $q = trim($request->input('q'));
        $projetId = $request->input('projet_id');
        $excludeId = $request->input('exclude_id', 0);

        // ⭐ Normaliser le téléphone pour la recherche
        $cleanPhone = preg_replace('/[^0-9]/', '', $q);
        $phoneVariants = [];

        if (strlen($cleanPhone) >= 4) {
            // Générer les variants du téléphone (marocain)
            $phoneVariants = $this->getPhoneVariants($cleanPhone);
        }

        // ⭐ Recherche
        $prospects = Prospect::on('temp')
            ->where('projet_id', $projetId)
            ->where('id', '!=', $excludeId)
            // ⭐ Filtre: MACHI mdemjin (machi f table prospect_fusions)
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('prospect_fusions')
                    ->where(function ($q) {
                        $q->whereRaw('prospect_fusions.prospect_fusion_id = prospects.id')
                          ->orWhereRaw('prospect_fusions.prospect_principal_id = prospects.id');
                    })
                    ->whereNull('prospect_fusions.deleted_at');
            })
            // ⭐ Recherche par plusieurs champs
            ->where(function ($query) use ($q, $cleanPhone, $phoneVariants) {
                // Recherche par CIN
                $query->where('cin', 'LIKE', "%{$q}%");

                // Recherche par nom/prenom
                $query->orWhere('nom', 'LIKE', "%{$q}%");
                $query->orWhere('prenom', 'LIKE', "%{$q}%");
                $query->orWhereRaw("CONCAT(COALESCE(nom, ''), ' ', COALESCE(prenom, '')) LIKE ?", ["%{$q}%"]);

                // Recherche par email
                $query->orWhere('email', 'LIKE', "%{$q}%");

                // Recherche par téléphone (avec variants)
                if (!empty($phoneVariants)) {
                    $query->orWhereIn('telephone', $phoneVariants);
                    $query->orWhereIn('telephone_num2', $phoneVariants);
                } else {
                    // Recherche LIKE classique
                    $query->orWhere('telephone', 'LIKE', "%{$q}%");
                    $query->orWhere('telephone_num2', 'LIKE', "%{$q}%");
                }
            })
            // ⭐ With relations
            // ⭐ Limiter à 20 résultats
            ->limit(20)
            ->get();

        // ⭐ Ajouter le flag est_fusionne pour chaque prospect
        $prospects->each(function ($p) {
            $p->est_fusionne = false; // Ila kayn hna, ma kanch mdemj
            $p->fusion_comme_fusionne = null;
        });

        return response()->json([
            'prospects' => $prospects,
            'total' => $prospects->count(),
            'query' => $q,
        ], 200);
    }

    /**
     * ⭐ Génère les variants d'un numéro de téléphone
     * Ex: 0612345678 → ['0612345678', '212612345678', '+212612345678', '+212612345678']
     */
    private function getPhoneVariants($phone)
    {
        $clean = preg_replace('/[^0-9]/', '', trim($phone));

        if (empty($clean)) {
            return [];
        }

        $variants = [
            $phone,              // Original
            $clean,              // Digits only
            '+' . $clean,        // With plus
        ];

        // Maroc: gérer 212 <-> 0
        if (str_starts_with($clean, '212')) {
            $local = '0' . substr($clean, 3);
            $variants[] = $local;                    // 0612345678
            $variants[] = '+212' . substr($clean, 3);
        } elseif (str_starts_with($clean, '0')) {
            $intl = '212' . substr($clean, 1);
            $variants[] = $intl;                     // 212612345678
            $variants[] = '+' . $intl;               // +212612345678
        }

        return array_values(array_unique($variants));
    }

    // =====================================================
    // ⭐ FUSIONNER MULTIPLE (7ta 10 prospects)
    // =====================================================
    public function fusionnerMultiple(Request $request)
{
    $request->validate([
        'prospect_principal_id' => 'required|exists:prospects,id',
        'prospect_fusion_ids' => 'required|array|min:1|max:10',
        'prospect_fusion_ids.*' => 'required|exists:prospects,id',
    ]);

    DatabaseHelper::Config();

    $principalId = $request->prospect_principal_id;
    $fusionIds = $request->prospect_fusion_ids;
    $userId = Auth::id();
    $user = User::on('temp')->where('user_id_origin', $userId)->first();

    // ⭐ Validation: Principal machi mdemj
    $principalDejaFusionne = ProspectFusion::on('temp')
        ->where('prospect_fusion_id', $principalId)
        ->exists();

    if ($principalDejaFusionne) {
        return response()->json([
            'error' => 'Le prospect principal est déjà fusionné avec un autre prospect'
        ], 422);
    }

    // ⭐ Validation: Kol les fusionIds machi mdemjin
    foreach ($fusionIds as $fusionId) {
        if ($fusionId == $principalId) {
            return response()->json([
                'error' => 'Un prospect ne peut pas être fusionné avec lui-même'
            ], 422);
        }

        $dejaFusionne = ProspectFusion::on('temp')
            ->where('prospect_fusion_id', $fusionId)
            ->orWhere('prospect_principal_id', $fusionId)
            ->exists();

        if ($dejaFusionne) {
            return response()->json([
                'error' => "Le prospect #{$fusionId} est déjà fusionné"
            ], 422);
        }
    }

    DB::connection('temp')->beginTransaction();

    try {
        foreach ($fusionIds as $fusionId) {
            // 1. Zid l-fusion
            $fusion = new ProspectFusion();
            $fusion->setConnection('temp');
            $fusion->prospect_principal_id = $principalId;
            $fusion->prospect_fusion_id = $fusionId;
            $fusion->user_id = $user ? $user->id : $userId;
            $fusion->save();

            /* 2. Naqel les visites
            Visite::on('temp')
                ->where('prospect_id', $fusionId)
                ->update([
                    'prospect_id' => $principalId,
                    'prospect_original_id' => $fusionId,
                ]);

            // 3. Naqel les appels
            Appel::on('temp')
                ->where('prospect_id', $fusionId)
                ->update(['prospect_id' => $principalId]);*/
        }

        DB::connection('temp')->commit();

        return response()->json([
            'success' => true,
            'message' => count($fusionIds) . ' prospect(s) fusionné(s)',
            'count' => count($fusionIds),
        ]);
    } catch (\Exception $e) {
        DB::connection('temp')->rollBack();
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}


    // =====================================================
    // ⭐ DÉFUSIONNER
    // =====================================================
   public function defusionner($fusionId)
{
    DatabaseHelper::Config();

    $fusion = ProspectFusion::on('temp')->findOrFail($fusionId);

    DB::connection('temp')->beginTransaction();

    try {
        $fusionneId = $fusion->prospect_fusion_id;

        /* ⭐ 1. Rejje3 les visites l prospect original
        Visite::on('temp')
            ->where('prospect_original_id', $fusionneId)
            ->update([
                'prospect_id' => $fusionneId,
                'prospect_original_id' => null,
            ]);*/

        // ⭐ 2. Rejje3 les appels
        // (Ila 3andek traçabilité dyal appels, zidha hna)

        // 3. M7i l-fusion (soft delete)
        $fusion->delete();

        DB::connection('temp')->commit();

        return response()->json([
            'success' => true,
            'message' => 'Défusion effectuée avec succès',
        ]);
    } catch (\Exception $e) {
        DB::connection('temp')->rollBack();
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}
    // =====================================================
    // ⭐ GET FUSIONS DYAL PROSPECT
    // =====================================================
    public function getFusions($id)
    {       DatabaseHelper::Config();

        $prospect = Prospect::on('temp')->findOrFail($id);

        // Wach howa principal?
        $fusionsCommePrincipal = ProspectFusion::on('temp')->where('prospect_principal_id', $id)
            ->with('prospectFusionne')
            ->get();

        // Wach howa fusionné?
        $fusionCommeFusionne = ProspectFusion::on('temp')->where('prospect_fusion_id', $id)
            ->with('prospectPrincipal')
            ->first();

        return response()->json([
            'est_principal' => $fusionsCommePrincipal->isNotEmpty(),
            'est_fusionne' => $fusionCommeFusionne !== null,
            'fusions_comme_principal' => $fusionsCommePrincipal,
            'fusion_comme_fusionne' => $fusionCommeFusionne,
        ]);
    }

    // =====================================================
    // ⭐ GET FAMILLE (Principal + Kol les fusionnés)
    // =====================================================
    public function getFamille($id)
    {
          DatabaseHelper::Config();

        $prospect = Prospect::on('temp')->findOrFail($id);

        // Cheki wach howa fusionné
        $fusionCommeFusionne = ProspectFusion::on('temp')->where('prospect_fusion_id', $id)
            ->first();

        if ($fusionCommeFusionne) {
            // Howa fusionné → jib l-principal
            $principalId = $fusionCommeFusionne->prospect_principal_id;

            $principal = Prospect::on('temp')->findorFail($principalId);

            $autresFusions = ProspectFusion::on('temp')->where('prospect_principal_id', $principalId)
                ->with('prospectFusionne')
                ->get()
                ->pluck('prospectFusionne');

            return response()->json([
                'principal' => $principal,
                'fusionnes' => $autresFusions,
            ]);
        }

        // Howa principal → jib les fusionnés
        $fusions = ProspectFusion::on('temp')->where('prospect_principal_id', $id)
            ->with('prospectFusionne')
            ->get()
            ->pluck('prospectFusionne');

        return response()->json([
            'principal' => $prospect,
            'fusionnes' => $fusions,
        ]);
    }
}
