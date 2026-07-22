<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class FacebookLeadStatsController extends Controller
{
    private $projetId;

    /**
     * ✅ Configurer la connexion temp
     */
    private function configureTempConnection()
    {
        try {
            $databaseName = env('DB_DATABASE');

            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $databaseName;

            config(['database.connections.temp' => $baseConfig]);

            DB::purge('temp');
            DB::reconnect('temp');

            Log::info('✅ Temp connection configured successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('❌ Failed to configure temp connection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer TOUTES les statistiques des leads Facebook
     */
    public function getFullStats(Request $request, $projet_id)
    {
        try {
            // ✅ Configurer la connexion temp
            $this->configureTempConnection();
            $this->projetId = $projet_id;

            // ✅ Récupérer la configuration Facebook
            $config = DB::connection('temp')
                ->table('facebook_configurations')
                ->where('projet_id', $projet_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration Facebook non trouvée pour ce projet'
                ], 404);
            }

            $pageId = $config->page_fcb_id;
            $accessToken = $config->acces_token_page;

            // ✅ Récupérer le paramètre period
            $period = $request->period ?? '30d';

            // ✅ Valider le period
            $allowedPeriods = ['7d', '30d', '90d', '1y'];
            if (!in_array($period, $allowedPeriods)) {
                $period = '30d';
            }

            // ✅ Calculer la plage de dates
            $dateRange = $this->getDateRange($period);

            Log::info('📊 Fetching Facebook leads stats', [
                'projet_id' => $projet_id,
                'period' => $period,
                'date_range' => $dateRange
            ]);

            // ✅ Récupérer TOUTES les données avec filtrage par période
            $stats = [
                'general' => $this->getGeneralStats($pageId, $accessToken, $dateRange),
                'leads' => $this->getLeadStats($pageId, $accessToken, $dateRange),
                'forms' => $this->getFormStats($pageId, $accessToken),
                'prospects' => $this->getProspectStats($projet_id, $dateRange),
                //'ads' => $this->getAdStats($pageId, $accessToken, $dateRange),
                'temporal' => $this->getTemporalAnalysis($projet_id, $dateRange),
                'funnel' => $this->getConversionFunnel($projet_id, $dateRange),
              //  'ad_performance' => $this->getAdPerformance($pageId, $accessToken, $dateRange),
                'trends' => $this->getTrends($projet_id, $dateRange),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period,
                'date_range' => [
                    'since' => $dateRange['since']->format('Y-m-d H:i:s'),
                    'until' => $dateRange['until']->format('Y-m-d H:i:s'),
                    'label' => $dateRange['label'] ?? $period
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting full stats: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la plage de dates selon la période
     */
    private function getDateRange($period)
    {
        $now = Carbon::now();
        $endDate = $now->copy()->endOfDay();

        switch ($period) {
            case '7d':
                return [
                    'since' => $now->copy()->subDays(7)->startOfDay(),
                    'until' => $endDate,
                    'label' => '7 derniers jours'
                ];
            case '30d':
                return [
                    'since' => $now->copy()->subDays(30)->startOfDay(),
                    'until' => $endDate,
                    'label' => '30 derniers jours'
                ];
            case '90d':
                return [
                    'since' => $now->copy()->subDays(90)->startOfDay(),
                    'until' => $endDate,
                    'label' => '90 derniers jours'
                ];
            case '1y':
                return [
                    'since' => $now->copy()->subYear()->startOfDay(),
                    'until' => $endDate,
                    'label' => '1 an'
                ];
            default:
                return [
                    'since' => $now->copy()->subDays(30)->startOfDay(),
                    'until' => $endDate,
                    'label' => '30 derniers jours (par défaut)'
                ];
        }
    }

    /**
     * 1. Statistiques générales - AVEC FILTRE DE PÉRIODE
     */
    private function getGeneralStats($pageId, $accessToken, $dateRange)
    {
        try {
            // Récupérer les infos de la page
            $pageInfo = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/{$pageId}", [
                    'fields' => 'name,fan_count,posts_count,talking_about_count'
                ]);

            $pageData = $pageInfo->successful() ? $pageInfo->json() : [];

            // ✅ Récupérer les posts avec filtre de période
            $postsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/{$pageId}/posts", [
                    'fields' => 'id,created_time,likes.summary(true),comments.summary(true),shares',
                    'limit' => 100,
                    'since' => $dateRange['since']->timestamp,
                    'until' => $dateRange['until']->timestamp,
                ]);

            $posts = $postsResponse->successful() ? $postsResponse->json()['data'] ?? [] : [];

            $totalLikes = 0;
            $totalComments = 0;
            $totalShares = 0;

            foreach ($posts as $post) {
                $totalLikes += $post['likes']['summary']['total_count'] ?? 0;
                $totalComments += $post['comments']['summary']['total_count'] ?? 0;
                $totalShares += $post['shares']['count'] ?? 0;
            }

            return [
                'page_name' => $pageData['name'] ?? 'Inconnu',
                'page_fans' => $pageData['fan_count'] ?? 0,
                'posts_count' => $pageData['posts_count'] ?? 0,
                'talking_about' => $pageData['talking_about_count'] ?? 0,
                'total_likes' => $totalLikes,
                'total_comments' => $totalComments,
                'total_shares' => $totalShares,
                'engagement_rate' => $pageData['fan_count'] > 0
                    ? round(($totalLikes + $totalComments + $totalShares) / $pageData['fan_count'] * 100, 2)
                    : 0,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getGeneralStats: ' . $e->getMessage());
            return [
                'page_name' => 'Erreur',
                'page_fans' => 0,
                'posts_count' => 0,
                'talking_about' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_shares' => 0,
                'engagement_rate' => 0,
            ];
        }
    }

    /**
     * 2. Statistiques des leads - AVEC FILTRE DE PÉRIODE
     */
    private function getLeadStats($pageId, $accessToken, $dateRange)
    {
        try {
            $totalLeads = 0;
            $leadsByForm = [];
            $leadsByDay = [];
            $leadsByHour = [];
            $leadsByAd = [];
            $leadsByCountry = [];
            $leadsByCity = [];

            // Récupérer les formulaires
            $formsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/{$pageId}/leadgen_forms", [
                    'fields' => 'id,name,status,created_time'
                ]);

            $forms = $formsResponse->successful() ? $formsResponse->json()['data'] ?? [] : [];

            foreach ($forms as $form) {
                $formId = $form['id'];

                // ✅ Récupérer les leads avec filtre de période (since et until)
                $leadsResponse = Http::withToken($accessToken)
                    ->get("https://graph.facebook.com/v22.0/{$formId}/leads", [
                        'fields' => 'id,created_time,field_data,ad_id,ad_name,adgroup_id',
                        'limit' => 1000,
                        'since' => $dateRange['since']->timestamp,
                        'until' => $dateRange['until']->timestamp,
                    ]);

                if ($leadsResponse->successful()) {
                    $leads = $leadsResponse->json()['data'] ?? [];
                    $totalLeads += count($leads);

                    $leadsByForm[] = [
                        'form_id' => $formId,
                        'form_name' => $form['name'] ?? 'Sans nom',
                        'total' => count($leads),
                        'status' => $form['status'] ?? 'unknown'
                    ];

                    foreach ($leads as $lead) {
                        $created = Carbon::parse($lead['created_time']);

                        // Par jour
                        $day = $created->format('Y-m-d');
                        $leadsByDay[$day] = ($leadsByDay[$day] ?? 0) + 1;

                        // Par heure
                        $hour = $created->format('H:00');
                        $leadsByHour[$hour] = ($leadsByHour[$hour] ?? 0) + 1;

                        // Par annonce
                        $adName = $lead['ad_name'] ?? 'Annonce inconnue';
                        $leadsByAd[$adName] = ($leadsByAd[$adName] ?? 0) + 1;

                        // Extraire les données du formulaire
                        $fieldData = $lead['field_data'] ?? [];
                        foreach ($fieldData as $field) {
                            if ($field['name'] === 'country') {
                                $country = $field['values'][0] ?? 'Inconnu';
                                $leadsByCountry[$country] = ($leadsByCountry[$country] ?? 0) + 1;
                            }
                            if ($field['name'] === 'city') {
                                $city = $field['values'][0] ?? 'Inconnu';
                                $leadsByCity[$city] = ($leadsByCity[$city] ?? 0) + 1;
                            }
                        }
                    }
                }
            }

            // ✅ TRIER les dates par ordre chronologique (ASC)
            ksort($leadsByDay);
            ksort($leadsByHour);

            // Trier les autres par ordre décroissant
            arsort($leadsByAd);
            arsort($leadsByCountry);
            arsort($leadsByCity);

            // ✅ Convertir en tableau avec format ['date' => '2026-06-29', 'count' => 5]
            $leadsByDayFormatted = [];
            foreach ($leadsByDay as $date => $count) {
                $leadsByDayFormatted[] = [
                    'date' => $date,
                    'count' => $count
                ];
            }

            // ✅ Convertir les heures aussi
            $leadsByHourFormatted = [];
            foreach ($leadsByHour as $hour => $count) {
                $leadsByHourFormatted[] = [
                    'hour' => $hour,
                    'count' => $count
                ];
            }

            return [
                'total_leads' => $totalLeads,
                'leads_by_form' => $leadsByForm,
                'leads_by_day' => $leadsByDayFormatted,
                'leads_by_day_raw' => $leadsByDay,
                'leads_by_hour' => $leadsByHourFormatted,
                'leads_by_hour_raw' => $leadsByHour,
                'leads_by_ad' => $leadsByAd,
                'leads_by_country' => $leadsByCountry,
                'leads_by_city' => $leadsByCity,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getLeadStats: ' . $e->getMessage());
            return [
                'total_leads' => 0,
                'leads_by_form' => [],
                'leads_by_day' => [],
                'leads_by_day_raw' => [],
                'leads_by_hour' => [],
                'leads_by_hour_raw' => [],
                'leads_by_ad' => [],
                'leads_by_country' => [],
                'leads_by_city' => [],

            ];
        }
    }

    /**
     * 3. Statistiques des formulaires
     */
    private function getFormStats($pageId, $accessToken)
    {
        try {
            $formsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/{$pageId}/leadgen_forms", [
                    'fields' => 'id,name,status,created_time,questions,leads_count'
                ]);

            $forms = $formsResponse->successful() ? $formsResponse->json()['data'] ?? [] : [];

            $formStats = [];
            foreach ($forms as $form) {
                $formStats[] = [
                    'id' => $form['id'],
                    'name' => $form['name'] ?? 'Sans nom',
                    'status' => $form['status'] ?? 'unknown',
                    'created_at' => $form['created_time'] ?? null,
                    'questions_count' => count($form['questions'] ?? []),
                    'leads_count' => $form['leads_count'] ?? 0,
                    'conversion_rate' => 0
                ];
            }

            return [
                'total_forms' => count($forms),
                'active_forms' => count(array_filter($forms, fn($f) => in_array($f['status'], ['ACTIVE', 'PUBLISHED']))),
                'forms' => $formStats,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getFormStats: ' . $e->getMessage());
            return [
                'total_forms' => 0,
                'active_forms' => 0,
                'forms' => [],
            ];
        }
    }
/**
 * ✅ Statistiques des commerciaux (UNIQUEMENT ceux avec prospects)
 */
/**
 * ✅ Statistiques des commerciaux (UNIQUEMENT ceux avec prospects)
 * Ajout de la catégorie "Non affecté"
 */
/**
 * ✅ Statistiques des commerciaux (UNIQUEMENT ceux avec prospects)
 * Pour les graphiques et classement, on exclut "Non affecté"
 */
private function getCommercialStats($collection, $includeNonAffected = false)
{
    if (!$collection || $collection->isEmpty()) {
        return [];
    }

    $result = [];

    // ✅ Si on demande d'inclure "Non affecté" (pour la carte de résumé)
    if ($includeNonAffected) {
        $nonAffectedCount = $collection->filter(function($item) {
            return is_null($item->commercial_affecte) || $item->commercial_affecte === '';
        })->count();

        if ($nonAffectedCount > 0) {
            $result[] = [
                'name' => 'Non affecté',
                'commercial_id' => null,
                'total' => $nonAffectedCount,
                'is_affected' => false,
            ];
        }
    }

    // ✅ Filtrer les prospects avec commercial_affecte non null
    $filteredCollection = $collection->filter(function($item) {
        return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
    });

    if (!$filteredCollection->isEmpty()) {
        $grouped = $filteredCollection->groupBy('commercial_affecte');

        foreach ($grouped as $commercialId => $items) {
            $commercialName = $this->getCommercialName($commercialId);
            $result[] = [
                'name' => $commercialName,
                'commercial_id' => $commercialId,
                'total' => $items->count(),
                'is_affected' => true,
            ];
        }
    }

    // Trier par total décroissant
    usort($result, function($a, $b) {
        return $b['total'] - $a['total'];
    });

    return $result;
}

/**
 * 4. Statistiques des prospects (base locale)
 */
/**
 * 4. Statistiques des prospects (base locale)
 */
/**
 * 4. Statistiques des prospects (base locale)
 */
private function getProspectStats($projetId, $dateRange)
{
    try {
        // ✅ Récupérer les prospects Facebook
        $prospects = DB::connection('temp')
            ->table('prospects')
            ->where('projet_id', $projetId)
            ->where(function($query) {
                $query->where('origin', 'facebook')
                      ->orWhere('source', 9);
            })
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
            ->get();

        $total = $prospects->count();

        // ✅ Récupérer TOUS les statuts
        $prospectIds = $prospects->pluck('id')->toArray();
        $allStatuses = DB::connection('temp')
            ->table('statut_prospects')
            ->whereIn('prospect_id', $prospectIds)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ Grouper par jour
        $prospectsByDay = [];
        foreach ($prospects as $prospect) {
            $date = Carbon::parse($prospect->created_at)->format('Y-m-d');
            $prospectsByDay[$date] = ($prospectsByDay[$date] ?? 0) + 1;
        }
        ksort($prospectsByDay);

        $prospectsByDayFormatted = [];
        foreach ($prospectsByDay as $date => $count) {
            $prospectsByDayFormatted[] = [
                'date' => $date,
                'count' => $count
            ];
        }

        // ✅ STATISTIQUES PAR STATUT
        $statusStats = $this->getAllStatusStats($allStatuses);

        // ✅ STATISTIQUES PAR COMMERCIAL (exclut "Non affecté" pour les graphiques)
        $commercialStats = $this->getCommercialStats($prospects, false);

        // ✅ STATISTIQUES PAR COMMERCIAL ET STATUT (exclut "Non affecté")
        $commercialStatusStats = $this->getCommercialAllStatusStats($prospects, $allStatuses);

        // ✅ Pour la carte "Non affectés" uniquement
        $nonAffectedCount = $prospects->filter(function($item) {
            return is_null($item->commercial_affecte) || $item->commercial_affecte === '';
        })->count();

        // ✅ STATISTIQUES PAR STATUT (format graphiques)
        $statusDistribution = [];
        $totalStatuses = $allStatuses->count();
        foreach ($statusStats as $status) {
            $statusDistribution[] = [
                'name' => $status['label'],
                'value' => $status['count'],
                'percentage' => $totalStatuses > 0 ? round(($status['count'] / $totalStatuses) * 100, 2) : 0
            ];
        }

        // ✅ TAUX DE CONVERSION - Version améliorée
        $convertedToClient = 0;
        $convertedToVisite = 0;
        $convertedToClientOrVisite = 0;

        foreach ($prospects as $prospect) {
            $prospectStatuses = $allStatuses->where('prospect_id', $prospect->id);
            $codes = $prospectStatuses->pluck('statut')->map(fn($s) => (string)$s)->toArray();

            // ✅ Converti en client (statut 10)
            if (in_array('10', $codes)) {
                $convertedToClient++;
            }

            // ✅ Converti en visite (statut 4)
            if (in_array('4', $codes)) {
                $convertedToVisite++;
            }

            // ✅ Converti en client OU visite (pour le taux global)
            if (array_intersect($codes, ['4', '10'])) {
                $convertedToClientOrVisite++;
            }
        }

        $conversionRateClient = $total > 0 ? round(($convertedToClient / $total) * 100, 2) : 0;
        $conversionRateVisite = $total > 0 ? round(($convertedToVisite / $total) * 100, 2) : 0;
        $conversionRateGlobal = $total > 0 ? round(($convertedToClientOrVisite / $total) * 100, 2) : 0;

        return [
            'total_prospects' => $total,
            'total_statuses' => $totalStatuses,
            'prospects_by_type' => $this->getGroupedStatsFromCollection($prospects, 'type_bien'),
            'prospects_by_budget' => $this->getGroupedStatsFromCollection($prospects, 'budget'),
            'prospects_by_residence' => $this->getGroupedStatsFromCollection($prospects, 'residence'),
            'prospects_by_status' => $statusDistribution,
            'prospects_by_status_raw' => $statusStats,
            'prospects_by_commercial' => $commercialStats,
            'prospects_by_commercial_status' => $commercialStatusStats,
            'prospects_by_day' => $prospectsByDayFormatted,
            'non_affected_count' => $nonAffectedCount,

            // ✅ Taux de conversion détaillés
            'conversion_rate_client' => $conversionRateClient,
            'conversion_rate_visite' => $conversionRateVisite,
            'conversion_rate_global' => $conversionRateGlobal,

            // ✅ Nombre de prospects convertis (pour les cartes)
            'converted_client_count' => $convertedToClient,
            'converted_visite_count' => $convertedToVisite,
        ];

    } catch (\Exception $e) {
        Log::error('Error in getProspectStats: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return [
            'total_prospects' => 0,
            'total_statuses' => 0,
            'prospects_by_type' => [],
            'prospects_by_budget' => [],
            'prospects_by_residence' => [],
            'prospects_by_status' => [],
            'prospects_by_status_raw' => [],
            'prospects_by_commercial' => [],
            'prospects_by_commercial_status' => [],
            'prospects_by_day' => [],
            'non_affected_count' => 0,
            'conversion_rate_client' => 0,
            'conversion_rate_visite' => 0,
            'conversion_rate_global' => 0,
            'converted_client_count' => 0,
            'converted_visite_count' => 0,
        ];
    }
}

/**
 * ✅ Statistiques TOUS les statuts
 */
private function getAllStatusStats($allStatuses)
{
    $statusMap = [
        '0' => 'En attente',
        '1' => 'Planification RDV',
        '2' => 'Injoignable',
        '3' => 'Rappel',
        '4' => 'Converti en Visite',
        '5' => 'Nouvel Appel',
        '6' => 'Affecté',
        '7' => 'Intéressé',
        '8' => 'Perdu',
        '9' => 'Réceptif',
        '10' => 'Converti en client',
        '11' => 'WhatsApp Envoyé',
    ];

    $stats = [];
    foreach ($statusMap as $code => $label) {
        $count = $allStatuses->where('statut', $code)->count();

        $stats[] = [
            'code' => $code,
            'label' => $label,
            'count' => $count,
        ];
    }

    // Trier par ordre décroissant (les plus nombreux en premier)
    usort($stats, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    return $stats;
}

/**
 * ✅ Statistiques par commercial et TOUS les statuts
 */
/**
 * ✅ Statistiques par commercial et TOUS les statuts (UNIQUEMENT ceux avec prospects)
 */
/**
 * ✅ Statistiques par commercial et TOUS les statuts
 */
/**
 * ✅ Statistiques par commercial et TOUS les statuts
 * Exclut "Non affecté" pour le classement
 */
private function getCommercialAllStatusStats($prospects, $allStatuses)
{
    if ($prospects->isEmpty()) {
        return [];
    }

    $statusMap = [
        '0' => 'En attente',
        '1' => 'Planification RDV',
        '2' => 'Injoignable',
        '3' => 'Rappel',
        '4' => 'Converti en Visite',
        '5' => 'Nouvel Appel',
        '6' => 'Affecté',
        '7' => 'Intéressé',
        '8' => 'Perdu',
        '9' => 'Réceptif',
        '10' => 'Converti en client',
        '11' => 'WhatsApp Envoyé',
    ];

    $result = [];

    // ✅ UNIQUEMENT les prospects AFFECTÉS (exclure "Non affecté")
    $affectedGroups = $prospects->filter(function($item) {
        return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
    })->groupBy('commercial_affecte');

    foreach ($affectedGroups as $commercialId => $commercialProspects) {
        $commercialName = $this->getCommercialName($commercialId);
        $prospectIds = $commercialProspects->pluck('id')->toArray();
        $commercialStatuses = $allStatuses->whereIn('prospect_id', $prospectIds);

        $statusCounts = [];
        foreach ($statusMap as $code => $label) {
            $count = $commercialStatuses->where('statut', $code)->count();
            if ($count > 0) {
                $statusCounts[] = [
                    'status_code' => $code,
                    'status_label' => $label,
                    'count' => $count,
                ];
            }
        }

        $result[] = [
            'commercial_id' => $commercialId,
            'commercial_name' => $commercialName,
            'total_prospects' => $commercialProspects->count(),
            'total_statuses' => $commercialStatuses->count(),
            'statuses' => $statusCounts,
        ];
    }

    // Trier par nombre total de prospects décroissant
    usort($result, function($a, $b) {
        return $b['total_prospects'] - $a['total_prospects'];
    });

    return $result;
}

/**
 * ✅ Récupérer le nom d'un commercial
 */
private function getCommercialName($commercialId)
{
    try {
        $user = DB::connection('temp')
            ->table('users')
            ->where('id', $commercialId)
            ->whereNull('deleted_at')
            ->first();

        if ($user) {
            return trim($user->name . ' ' . ($user->prenom ?? ''));
        }
        return 'Commercial #' . $commercialId;
    } catch (\Exception $e) {
        Log::error('Error getting commercial name: ' . $e->getMessage());
        return 'Commercial #' . $commercialId;
    }
}

/**
 * ✅ Statistiques groupées depuis une collection (pour les champs comme type_bien, budget, etc.)
 */
/**
 * ✅ Statistiques groupées depuis une collection (AVEC NOMS DES COMMERCIAUX)
 */
/**
 * ✅ Statistiques groupées depuis une collection (AVEC FILTRE commercial_affecte != null)
 */
private function getGroupedStatsFromCollection($collection, $field)
{
    if (!$collection || $collection->isEmpty()) {
        return [];
    }

    // ✅ Si le champ est 'commercial_affecte', filtrer les null et récupérer les noms
    if ($field === 'commercial_affecte') {
        $result = [];

        // ✅ Filtrer les prospects avec commercial_affecte non null
        $filteredCollection = $collection->filter(function($item) {
            return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
        });

        $grouped = $filteredCollection->groupBy('commercial_affecte');

        foreach ($grouped as $commercialId => $items) {
            $commercialName = $this->getCommercialName($commercialId);
            $result[] = [
                'name' => $commercialName,
                'commercial_id' => $commercialId,
                'total' => $items->count()
            ];
        }

        // Trier par total décroissant
        usort($result, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        return $result;
    }

    // ✅ Pour les autres champs (type_bien, budget, etc.)
    return $collection->groupBy($field)
        ->map(fn($items) => $items->count())
        ->filter(fn($count, $key) => !empty($key) && $key !== '')
        ->map(fn($count, $key) => ['name' => (string)$key, 'total' => $count])
        ->values()
        ->all();
}
/**
 * ✅ Statistiques par statut avec labels
 */
private function getStatusStats($prospects)
{
    $statusMap = [
        0 => 'En attente',
        1 => 'Planification RDV',
        2 => 'Injoignable',
        3 => 'Rappel',
        4 => 'Converti en Visite',
        5 => 'Nouvel Appel',
        6 => 'Affecté',
        7 => 'Intéressé',
        8 => 'Perdu',
        9 => 'Réceptif',
        10 => 'Converti en client',
        11 => 'WhatsApp Envoyé',
    ];

    $stats = [];
    foreach ($statusMap as $code => $label) {
        $count = $prospects->where('statut', (string)$code)->count();
        $stats[] = [
            'code' => $code,
            'label' => $label,
            'count' => $count,
        ];
    }

    // Trier par ordre décroissant (les plus nombreux en premier)
    usort($stats, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    return $stats;
}

/**
 * ✅ Statistiques par commercial et par statut
 */
private function getCommercialStatusStats($prospects)
{
    if ($prospects->isEmpty()) {
        return [];
    }

    $statusMap = [
        0 => 'En attente',
        1 => 'Planification RDV',
        2 => 'Injoignable',
        3 => 'Rappel',
        4 => 'Converti en Visite',
        5 => 'Nouvel Appel',
        6 => 'Affecté',
        7 => 'Intéressé',
        8 => 'Perdu',
        9 => 'Réceptif',
        10 => 'Converti en client',
        11 => 'WhatsApp Envoyé',
    ];

    // Grouper par commercial
    $commercialGroups = $prospects->groupBy('commercial_affecte');

    $result = [];
    foreach ($commercialGroups as $commercialId => $commercialProspects) {
        $commercialName = $commercialId ? $this->getCommercialName($commercialId) : 'Non affecté';

        $statusCounts = [];
        foreach ($statusMap as $code => $label) {
            $count = $commercialProspects->where('statut', (string)$code)->count();
            if ($count > 0) {
                $statusCounts[] = [
                    'status_code' => $code,
                    'status_label' => $label,
                    'count' => $count,
                ];
            }
        }

        // Trier par ordre décroissant
        usort($statusCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $result[] = [
            'commercial_id' => $commercialId,
            'commercial_name' => $commercialName,
            'total' => $commercialProspects->count(),
            'statuses' => $statusCounts,
        ];
    }

    // Trier par total décroissant
    usort($result, function($a, $b) {
        return $b['total'] - $a['total'];
    });

    return $result;
}

/**
 * ✅ Récupérer le nom d'un commercial
 */


/**
 * ✅ Statistiques par statut (version simplifiée pour les graphiques)
 * Alternative pour avoir un format plus simple
 */
private function getSimpleStatusStats($prospects)
{
    $statusMap = [
        0 => 'En attente',
        1 => 'Planification RDV',
        2 => 'Injoignable',
        3 => 'Rappel',
        4 => 'Converti en Visite',
        5 => 'Nouvel Appel',
        6 => 'Affecté',
        7 => 'Intéressé',
        8 => 'Perdu',
        9 => 'Réceptif',
        10 => 'Converti en client',
        11 => 'WhatsApp Envoyé',
    ];

    $stats = [];
    foreach ($statusMap as $code => $label) {
        $count = $prospects->where('statut', (string)$code)->count();
        if ($count > 0) {
            $stats[$label] = $count;
        }
    }

    // Trier par ordre décroissant
    arsort($stats);

    return $stats;
}

    /**
     * 5. Statistiques des annonces - AVEC FILTRE DE PÉRIODE

    private function getAdStats($pageId, $accessToken, $dateRange)
    {
        try {
            // ✅ Récupérer les campagnes avec filtre de période
            $campaignsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/act_{$pageId}/campaigns", [
                    'fields' => 'id,name,objective,status,insights{impressions,clicks,spend,leads}',
                    'since' => $dateRange['since']->format('Y-m-d'),
                    'until' => $dateRange['until']->format('Y-m-d'),
                ]);

            $campaigns = $campaignsResponse->successful() ? $campaignsResponse->json()['data'] ?? [] : [];

            $adStats = [];
            foreach ($campaigns as $campaign) {
                $insights = $campaign['insights']['data'][0] ?? [];
                $adStats[] = [
                    'campaign_id' => $campaign['id'],
                    'campaign_name' => $campaign['name'] ?? 'Sans nom',
                    'objective' => $campaign['objective'] ?? 'unknown',
                    'status' => $campaign['status'] ?? 'unknown',
                    'impressions' => $insights['impressions'] ?? 0,
                    'clicks' => $insights['clicks'] ?? 0,
                    'spend' => $insights['spend'] ?? 0,
                    'leads' => $insights['leads'] ?? 0,
                    'ctr' => $insights['impressions'] > 0
                        ? round($insights['clicks'] / $insights['impressions'] * 100, 2)
                        : 0,
                    'cost_per_lead' => $insights['leads'] > 0
                        ? round($insights['spend'] / $insights['leads'], 2)
                        : 0,
                ];
            }

            return [
                'total_campaigns' => count($campaigns),
                'active_campaigns' => count(array_filter($campaigns, fn($c) => $c['status'] === 'ACTIVE')),
                'campaigns' => $adStats,
                'total_spend' => array_sum(array_column($adStats, 'spend')),
                'total_impressions' => array_sum(array_column($adStats, 'impressions')),
                'total_clicks' => array_sum(array_column($adStats, 'clicks')),
                'overall_ctr' => array_sum(array_column($adStats, 'impressions')) > 0
                    ? round(array_sum(array_column($adStats, 'clicks')) / array_sum(array_column($adStats, 'impressions')) * 100, 2)
                    : 0,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getAdStats: ' . $e->getMessage());
            return [
                'total_campaigns' => 0,
                'active_campaigns' => 0,
                'campaigns' => [],
                'total_spend' => 0,
                'total_impressions' => 0,
                'total_clicks' => 0,
                'overall_ctr' => 0,
            ];
        }
    }*/

    /**
     * 6. Analyse temporelle - AVEC REMPLISSAGE DES JOURS MANQUANTS
     */
   /**
 * Analyse l'évolution temporelle des leads d'un projet sur une période donnée
 *
 * @param int $projetId L'identifiant du projet
 * @param array $dateRange Tableau contenant 'since' (date début) et 'until' (date fin) au format Carbon
 * @return array Tableau contenant les statistiques d'évolution
 */
private function getTemporalAnalysis($projetId, $dateRange)
{
    try {
        // ============================================================
        // 1. RÉCUPÉRATION DES DONNÉES EN BASE DE DONNÉES
        // ============================================================

        $leads = DB::connection('temp')  // Connexion à la base de données nommée 'temp'
            ->table('prospects')          // Table 'prospects'
            ->where('projet_id', $projetId)  // Filtrer par projet
            // Condition OR : soit l'origine est 'facebook', soit la source = 9
            ->where(function($query) {
                $query->where('origin', 'facebook')
                    ->orWhere('source', 9);
            })
            ->whereNull('deleted_at')     // Exclure les leads supprimés (soft delete)
            // Filtrer sur la période : entre 'since' et 'until'
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
            ->orderBy('created_at', 'asc') // Trier du plus ancien au plus récent
            ->get();                       // Exécuter la requête et récupérer les résultats

        // ============================================================
        // 2. CAS PARTICULIER : AUCUN LEAD TROUVÉ
        // ============================================================

        if ($leads->isEmpty()) {
            // ✅ Retourner un tableau avec toutes les dates de la période à 0
            $dailyTrend = [];
            $current = $dateRange['since']->copy(); // Copie pour ne pas modifier l'original

            // Boucle pour générer toutes les dates de la période
            while ($current <= $dateRange['until']) {
                $dailyTrend[$current->format('Y-m-d')] = 0; // Chaque date = 0 lead
                $current->addDay(); // Passer au jour suivant
            }

            // Retourner des valeurs par défaut (tendance stable, pas de croissance)
            return [
                'trend' => 'stable',          // Tendance : stable
                'growth_rate' => 0,           // Taux de croissance : 0
                'projection_30d' => 0,        // Projection 30 jours : 0
                'total_days' => $dateRange['since']->diffInDays($dateRange['until']) + 1, // Nombre de jours total
                'avg_daily' => 0,             // Moyenne journalière : 0
                'daily_trend' => $dailyTrend, // Tableau jour par jour (tous à 0)
            ];
        }

        // ============================================================
        // 3. CALCULS PRÉPARATOIRES AVEC LES DONNÉES RÉELLES
        // ============================================================

        $total = $leads->count(); // Nombre total de leads récupérés
        $firstDate = $dateRange['since']->copy(); // Date de début
        $lastDate = $dateRange['until']->copy();  // Date de fin
        $daysDiff = $firstDate->diffInDays($lastDate) + 1; // Nombre de jours total (+1 car inclusif)

        // ============================================================
        // 4. CRÉATION DU TABLEAU DE TOUTES LES DATES
        // ============================================================

        // ✅ Créer un tableau avec toutes les dates de la période
        $dailyTrend = [];
        for ($i = 0; $i < $daysDiff; $i++) {
            $date = $firstDate->copy()->addDays($i)->format('Y-m-d'); // Génère chaque date
            $dailyTrend[$date] = 0; // Initialise chaque jour à 0
        }

        // ============================================================
        // 5. REMPLISSAGE AVEC LES DONNÉES RÉELLES DES LEADS
        // ============================================================

        // ✅ Remplir les données réelles
        foreach ($leads as $lead) {
            $date = Carbon::parse($lead->created_at)->format('Y-m-d'); // Date de création du lead
            if (isset($dailyTrend[$date])) { // Vérifie si la date existe dans le tableau
                $dailyTrend[$date]++; // Incrémente le compteur pour ce jour
            }
        }

        // ============================================================
        // 6. CALCUL DES INDICATEURS DE PERFORMANCE
        // ============================================================

        // ✅ Calculer la croissance
        // Taux de croissance = nombre total de leads / nombre de jours
        $growthRate = $daysDiff > 0 ? round($total / $daysDiff, 2) : 0;

        // Projection sur 30 jours = taux de croissance × 30
        $projection = round($growthRate * 30, 0);

        // ============================================================
        // 7. DÉTERMINATION DE LA TENDANCE
        // ============================================================

        /**
         * Règle de classification de la tendance :
         * - 'up' (hausse) : plus d'1 lead par jour en moyenne
         * - 'down' (baisse) : moins de 0.5 lead par jour
         * - 'stable' (stable) : entre 0.5 et 1 lead par jour
         */
        $trend = $growthRate > 1 ? 'up' : ($growthRate < 0.5 ? 'down' : 'stable');

        // ============================================================
        // 8. RETOUR DU RÉSULTAT FINAL
        // ============================================================

        return [
            'trend' => $trend,                 // Tendance : up / down / stable
            'growth_rate' => $growthRate,      // Taux de croissance (leads/jour)
            'projection_30d' => $projection,   // Projection sur 30 jours
            'total_days' => $daysDiff,         // Nombre total de jours analysés
            'avg_daily' => $growthRate,        // Moyenne journalière (identique à growth_rate)
            'daily_trend' => $dailyTrend,      // Tableau jour par jour avec les comptages
        ];

    // ============================================================
    // 9. GESTION DES ERREURS
    // ============================================================

    } catch (\Exception $e) {
        // En cas d'erreur, on loggue pour debug
        Log::error('Error in getTemporalAnalysis: ' . $e->getMessage());

        // Retourne des valeurs par défaut (sécurisé pour ne pas casser l'application)
        return [
            'trend' => 'stable',
            'growth_rate' => 0,
            'projection_30d' => 0,
            'total_days' => 0,
            'avg_daily' => 0,
            'daily_trend' => [],
        ];
    }
}



private function getCommercialQualityStats($prospects, $allStatuses, $statusMap)
{
    if ($prospects->isEmpty()) {
        return [];
    }

    // Filtrer les commerciaux avec prospects
    $commercialGroups = $prospects->filter(function($item) {
        return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
    })->groupBy('commercial_affecte');

    if ($commercialGroups->isEmpty()) {
        // ✅ Retourner un tableau vide mais avec une structure correcte
        return [];
    }

    $result = [];
    foreach ($commercialGroups as $commercialId => $commercialProspects) {
        $commercialName = $this->getCommercialName($commercialId);
        $prospectIds = $commercialProspects->pluck('id')->toArray();
        $commercialStatuses = $allStatuses->whereIn('prospect_id', $prospectIds);
        $total = $commercialProspects->count();

        // ✅ Calculer le score de qualité pour ce commercial
        $totalScore = 0;
        $maxScore = $total * 100;

        foreach ($commercialProspects as $prospect) {
            $prospectStatuses = $commercialStatuses->where('prospect_id', $prospect->id);
            $bestScore = 10;
            foreach ($prospectStatuses as $status) {
                $code = (string)$status->statut;
                $score = $statusMap[$code]['score'] ?? 10;
                if ($score > $bestScore) {
                    $bestScore = $score;
                }
            }
            $totalScore += $bestScore;
        }

        $qualityScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        // ✅ Distribution des statuts
        $statusDistribution = [];
        foreach ($statusMap as $code => $info) {
            $count = $commercialStatuses->filter(function($s) use ($code) {
                return (string)$s->statut === (string)$code;
            })->count();
            if ($count > 0) {
                $statusDistribution[] = [
                    'label' => $info['label'],
                    'count' => $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                    'color' => $info['color'],
                ];
            }
        }

        $result[] = [
            'commercial_id' => $commercialId,
            'commercial_name' => $commercialName,
            'total_prospects' => $total,
            'total_statuses' => $commercialStatuses->count(),
            'quality_score' => $qualityScore,
            'quality_level' => $this->getQualityLevel($qualityScore),
            'status_distribution' => $statusDistribution,
        ];
    }

    // Trier par score décroissant
    usort($result, function($a, $b) {
        return $b['quality_score'] - $a['quality_score'];
    });

    return $result;
}
/**
 * ✅ Niveau de qualité
 */
private function getQualityLevel($score)
{
    if ($score >= 80) return ['label' => 'Excellent', 'icon' => '🌟', 'color' => 'text-emerald-600'];
    if ($score >= 60) return ['label' => 'Bon', 'icon' => '👍', 'color' => 'text-blue-600'];
    if ($score >= 40) return ['label' => 'Moyen', 'icon' => '📊', 'color' => 'text-amber-600'];
    if ($score >= 20) return ['label' => 'Faible', 'icon' => '⚠️', 'color' => 'text-orange-600'];
    return ['label' => 'Critique', 'icon' => '🚨', 'color' => 'text-red-600'];
}

/**
 * ✅ Statistiques par défaut pour la qualité
 */
private function getDefaultQualityStats()
{
    return [
        'quality_score' => 0,
        'contact_rate' => 0,
        'conversion_rate' => 0,
        'total_prospects' => 0,
        'total_statuses' => 0,
        'status_distribution' => [],
        'status_chart_data' => [],
        'commercial_quality' => [],
        'quality_level' => ['label' => 'Aucune donnée', 'icon' => '📭', 'color' => 'text-gray-400'],
    ];
}
/*****
 * ***
 * Règles de classification des prospects :
"Si last pre_reservation ==> client potentiel"

Si le dernier statut du prospect est pre_reservation (statut 12 ?) → classer comme Client Potentiel

"Si un seul perdu => à convaincre"

Si le prospect n'a qu'un seul statut perdu (statut 8) dans son historique → classer comme À convaincre (pas définitivement perdu)

"Si toujours plusieurs perdu ===> perdu"

Si le prospect a plusieurs statuts perdu (statut 8) dans son historique → classer comme Perdu définitivement

"Si last receptif ==> en conviction"

Si le dernier statut est receptif → classer comme En conviction

"Si receptif + action de conviction (Nouveau_appel, rappel) + perdu = perdu"

Si le prospect a eu un statut receptif, puis des actions de conviction, et finit par perdu → Perdu

"Si est toujours receptif + receptif toujours receptif ==> receptif"

/**
 * 8. Funnel de conversion - Version corrigée
 */
private function getConversionFunnel($projetId, $dateRange)
{
    try {
        $prospects = DB::connection('temp')
            ->table('prospects')
            ->where('projet_id', $projetId)
            ->where(function($query) {
                $query->where('origin', 'facebook')
                    ->orWhere('source', 9);
            })
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
            ->get();

        $total = $prospects->count();

        if ($total === 0) {
            return $this->getDefaultFunnel();
        }

        $prospectIds = $prospects->pluck('id')->toArray();
        $allStatuses = DB::connection('temp')
            ->table('statut_prospects')
            ->whereIn('prospect_id', $prospectIds)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ Définir les étapes du funnel
        $funnelSteps = [
            'Leads' => $total,
            'Affectés' => 0,
            'Contactés' => 0,
            'À convaincre' => 0,
            'En conviction' => 0,
            'Receptif' => 0,
            'Intéressé' => 0,
            'Visites' => 0,
            'Client Potentiel' => 0,
            'Vendus' => 0,
            'Perdus' => 0,
        ];

        foreach ($prospects as $prospect) {
            $prospectStatuses = $allStatuses->where('prospect_id', $prospect->id);
            $statusCodes = $prospectStatuses->pluck('statut')->map(function($s) {
                return (string)$s;
            })->toArray();

            if (empty($statusCodes)) {
                continue;
            }

            // ============================================================
            // 📌 CONSTANTES DES STATUTS
            // ============================================================
            $STATUT_PERDU = '8';
            $STATUT_AFFECTE = '6';
            $STATUT_PRE_RESERVATION = '12';
            $STATUT_RECEPTIF = '5';
            $STATUT_VENDU = '10';
            $STATUT_VISITE = '4';
            $STATUT_INTERESSE = '7';
            $STATUT_NOUVEAU_APPEL = '9';
            $STATUT_RAPPEL = '7';
            $STATUT_WHATSAPP = '11';

            // Actions de conviction
            $convictionActions = [$STATUT_NOUVEAU_APPEL, $STATUT_RAPPEL, $STATUT_WHATSAPP];

            // Récupérer le dernier statut
            $lastStatus = end($statusCodes);

            // ============================================================
            // 🏆 CLASSIFICATION AVEC PRIORITÉ (CORRIGÉE)
            // ============================================================

            // 1️⃣ VENDU (statut 10) - Priorité maximale
            if (in_array($STATUT_VENDU, $statusCodes)) {
                $funnelSteps['Vendus']++;
                continue;
            }

            // 2️⃣ CLIENT POTENTIEL (statut 12)
            if (in_array($STATUT_PRE_RESERVATION, $statusCodes)) {
                $funnelSteps['Client Potentiel']++;
                continue;
            }

            // 3️⃣ VISITE (statut 4)
            if (in_array($STATUT_VISITE, $statusCodes)) {
                $funnelSteps['Visites']++;
                continue;
            }

            // 4️⃣ INTÉRESSÉ (statut 7)
            if (in_array($STATUT_INTERESSE, $statusCodes)) {
                $funnelSteps['Intéressé']++;
                continue;
            }

            // 5️⃣ RÉCEPTIF (statut 5)
            if (in_array($STATUT_RECEPTIF, $statusCodes)) {
                // Vérifier si des actions de conviction ont été faites AVANT le réceptif
                $hasConvictionBeforeReceptif = false;
                $receptifIndex = array_search($STATUT_RECEPTIF, $statusCodes);

                for ($i = 0; $i < $receptifIndex; $i++) {
                    if (in_array($statusCodes[$i], $convictionActions)) {
                        $hasConvictionBeforeReceptif = true;
                        break;
                    }
                }

                if ($hasConvictionBeforeReceptif) {
                    $funnelSteps['En conviction']++;
                } else {
                    $funnelSteps['Receptif']++;
                }
                continue;
            }

            // 6️⃣ PERDU (plusieurs perdu)
            $lostCount = array_count_values($statusCodes)[$STATUT_PERDU] ?? 0;
            if ($lostCount >= 2) {
                $funnelSteps['Perdus']++;
                continue;
            }

            // 7️⃣ À CONVAINCRE (un seul perdu)
            if ($lostCount === 1 && $lastStatus === $STATUT_PERDU) {
                $funnelSteps['À convaincre']++;
                continue;
            }

            // 8️⃣ CONTACTÉ (statuts 1, 3, 5, 11)
            $contactStatuses = ['1', '3', '5', '11'];
            if (array_intersect($statusCodes, $contactStatuses)) {
                $funnelSteps['Contactés']++;
                continue;
            }

            // 9️⃣ AFFECTÉ (statut 6)
            if (in_array($STATUT_AFFECTE, $statusCodes)) {
                $funnelSteps['Affectés']++;
                continue;
            }
        }

        // ============================================================
        // ✅ Construction des données du funnel
        // ============================================================
        $funnelData = [];
        $prevCount = $total;

        // Ordre logique du funnel
        $stepOrder = [
            'Leads',
            'Affectés',
            'Contactés',
            'À convaincre',
            'En conviction',
            'Receptif',
            'Intéressé',
            'Visites',
            'Client Potentiel',
            'Vendus',
            'Perdus'
        ];

        foreach ($stepOrder as $index => $step) {
            $count = $funnelSteps[$step] ?? 0;

            if ($index === 0) {
                $dropOff = 0;
                $dropRate = 0;
                $conversionRate = 100;
                $retentionRate = 100;
            } else {
                $dropOff = $prevCount - $count;
                $dropRate = $prevCount > 0 ? round(($dropOff / $prevCount) * 100, 2) : 0;
                $conversionRate = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                $retentionRate = $prevCount > 0 ? round(($count / $prevCount) * 100, 2) : 0;
            }

            // ✅ Éviter les valeurs négatives dans drop_off
            $displayDropOff = $dropOff < 0 ? 0 : $dropOff;
            $displayDropRate = $dropRate < 0 ? 0 : $dropRate;

            $funnelData[] = [
                'name' => $step,
                'value' => $count,
                'conversion_rate' => $conversionRate,
                'drop_off' => $displayDropOff,
                'drop_rate' => $displayDropRate,
                'retention_rate' => $retentionRate,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];

            $prevCount = $count;
        }

        // ============================================================
        // 📊 Drop Off Analysis
        // ============================================================
        $dropOffAnalysis = [];
        for ($i = 0; $i < count($funnelData) - 1; $i++) {
            $from = $funnelData[$i]['name'];
            $to = $funnelData[$i + 1]['name'];
            $fromValue = $funnelData[$i]['value'];
            $toValue = $funnelData[$i + 1]['value'];

            $dropOff = $fromValue - $toValue;
            $dropRate = $fromValue > 0 ? round((($fromValue - $toValue) / $fromValue) * 100, 2) : 0;
            $conversionRate = $fromValue > 0 ? round(($toValue / $fromValue) * 100, 2) : 0;

            // ✅ Éviter les valeurs négatives
            $dropOffAnalysis[] = [
                'from' => $from,
                'to' => $to,
                'from_value' => $fromValue,
                'to_value' => $toValue,
                'drop_off' => $dropOff < 0 ? 0 : $dropOff,
                'drop_rate' => $dropRate < 0 ? 0 : $dropRate,
                'conversion_rate' => $conversionRate,
            ];
        }

        // ============================================================
        // 📊 Métriques finales
        // ============================================================
        $overallConversion = $total > 0 ? round(($funnelSteps['Vendus'] / $total) * 100, 2) : 0;
        $lostRate = $total > 0 ? round(($funnelSteps['Perdus'] / $total) * 100, 2) : 0;
        $convictionRate = $total > 0 ? round(($funnelSteps['En conviction'] / $total) * 100, 2) : 0;

        return [
            'steps' => $funnelData,
            'details' => [
                'Leads' => $funnelSteps['Leads'],
                'Affectés' => $funnelSteps['Affectés'],
                'Contactés' => $funnelSteps['Contactés'],
                'À convaincre' => $funnelSteps['À convaincre'],
                'En conviction' => $funnelSteps['En conviction'],
                'Receptif' => $funnelSteps['Receptif'],
                'Intéressé' => $funnelSteps['Intéressé'],
                'Visites' => $funnelSteps['Visites'],
                'Client Potentiel' => $funnelSteps['Client Potentiel'],
                'Vendus' => $funnelSteps['Vendus'],
                'Perdus' => $funnelSteps['Perdus'],
            ],
            'summary' => [
                'total_leads' => $total,
                'total_affected' => $funnelSteps['Affectés'],
                'total_contacted' => $funnelSteps['Contactés'],
                'total_a_convaincre' => $funnelSteps['À convaincre'],
                'total_en_conviction' => $funnelSteps['En conviction'],
                'total_receptif' => $funnelSteps['Receptif'],
                'total_interesse' => $funnelSteps['Intéressé'],
                'total_visits' => $funnelSteps['Visites'],
                'total_client_potentiel' => $funnelSteps['Client Potentiel'],
                'total_sold' => $funnelSteps['Vendus'],
                'total_lost' => $funnelSteps['Perdus'],
                'overall_conversion' => $overallConversion,
                'lost_rate' => $lostRate,
                'conviction_rate' => $convictionRate,
            ],
            'drop_off_analysis' => $dropOffAnalysis,
            'parcours_analysis' => [
                'Réceptif → En conviction' => 0,
                'Réceptif → Perdu' => 0,
                'À convaincre → Perdu' => 0,
                'À convaincre → En conviction' => 0,
                'Intéressé → Visite' => 0,
                'Visite → Client Potentiel' => 0,
            ],
        ];

    } catch (\Exception $e) {
        Log::error('Error in getConversionFunnel: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return $this->getDefaultFunnel();
    }
}

/**
 * ✅ Analyse des points de chute
 */
// Dans le backend, la fonction getDropOffAnalysis doit retourner les transitions entre chaque étape
private function getDropOffAnalysis($funnelData)
{
    $analysis = [];
    for ($i = 0; $i < count($funnelData) - 1; $i++) {
        $from = $funnelData[$i]['name'];
        $to = $funnelData[$i + 1]['name'];
        $fromValue = $funnelData[$i]['value'];
        $toValue = $funnelData[$i + 1]['value'];

        $analysis[] = [
            'from' => $from,
            'to' => $to,
            'from_value' => $fromValue,
            'to_value' => $toValue,
            'drop_off' => $fromValue - $toValue,
            'drop_rate' => $fromValue > 0 ? round((($fromValue - $toValue) / $fromValue) * 100, 2) : 0,
            'conversion_rate' => $fromValue > 0 ? round(($toValue / $fromValue) * 100, 2) : 0,
        ];
    }
    return $analysis;
}

/**
 * ✅ Funnel par défaut
 */
private function getDefaultFunnel()
{
    return [
        'steps' => [
            ['name' => 'Leads', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
            ['name' => 'Contactés', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
            ['name' => 'Qualifiés', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
            ['name' => 'Visites', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
            ['name' => 'Négociation', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
            ['name' => 'Vendus', 'value' => 0, 'conversion_rate' => 0, 'drop_off' => 0, 'drop_rate' => 0, 'percentage' => 0],
        ],
        'summary' => [
            'total_leads' => 0,
            'total_contacted' => 0,
            'total_qualified' => 0,
            'total_visits' => 0,
            'total_negotiation' => 0,
            'total_sold' => 0,
            'overall_conversion' => 0,
        ],
        'drop_off_analysis' => [],
    ];
}

/**
 * ✅ Analyse des points de chute
 */


/**
 * ✅ Funnel par défaut
 */


    /**
     * 8. Funnel de conversion
     */


    /**
     * 9. Performance des annonces

    private function getAdPerformance($pageId, $accessToken, $dateRange)
    {
        try {
            // Récupérer les insights des campagnes avec filtre de période
            $insightsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v22.0/act_{$pageId}/insights", [
                    'fields' => 'campaign_name,impressions,clicks,spend,leads,conversions',
                    'level' => 'campaign',
                    'since' => $dateRange['since']->format('Y-m-d'),
                    'until' => $dateRange['until']->format('Y-m-d'),
                ]);

            $insights = $insightsResponse->successful() ? $insightsResponse->json()['data'] ?? [] : [];

            $bestPerforming = [];
            $worstPerforming = [];
            $totalCost = 0;
            $totalLeads = 0;
            $costs = [];

            foreach ($insights as $insight) {
                $leads = $insight['leads'] ?? 0;
                $spend = $insight['spend'] ?? 0;
                $costPerLead = $leads > 0 ? $spend / $leads : 0;

                $totalCost += $spend;
                $totalLeads += $leads;
                $costs[] = $costPerLead;

                $item = [
                    'campaign_name' => $insight['campaign_name'] ?? 'Inconnu',
                    'impressions' => $insight['impressions'] ?? 0,
                    'clicks' => $insight['clicks'] ?? 0,
                    'spend' => $spend,
                    'leads' => $leads,
                    'cost_per_lead' => $costPerLead,
                ];

                if ($leads > 0) {
                    $bestPerforming[] = $item;
                } else {
                    $worstPerforming[] = $item;
                }
            }

            // Trier
            usort($bestPerforming, fn($a, $b) => $a['cost_per_lead'] <=> $b['cost_per_lead']);
            usort($worstPerforming, fn($a, $b) => $b['leads'] <=> $a['leads']);

            return [
                'best_performing' => array_slice($bestPerforming, 0, 5),
                'worst_performing' => array_slice($worstPerforming, 0, 5),
                'roi' => $totalCost > 0 ? round(($totalLeads * 100) / $totalCost, 2) : 0,
                'cost_analysis' => [
                    'avg_cost_per_lead' => $totalLeads > 0 ? round($totalCost / $totalLeads, 2) : 0,
                    'min_cost_per_lead' => !empty($costs) ? round(min($costs), 2) : 0,
                    'max_cost_per_lead' => !empty($costs) ? round(max($costs), 2) : 0,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error in getAdPerformance: ' . $e->getMessage());
            return [
                'best_performing' => [],
                'worst_performing' => [],
                'roi' => 0,
                'cost_analysis' => [
                    'avg_cost_per_lead' => 0,
                    'min_cost_per_lead' => 0,
                    'max_cost_per_lead' => 0,
                ]
            ];
        }
    }*/

    /**
     * 10. Tendances et prévisions
     */
   /**
 * Analyse les tendances temporelles des leads (hebdomadaires, mensuelles et horaires)
 *
 * @param int $projetId L'identifiant du projet
 * @param array $dateRange Tableau contenant 'since' (date début) et 'until' (date fin) au format Carbon
 * @return array Tableau contenant les tendances et le meilleur moment pour les leads
 */
private function getTrends($projetId, $dateRange)
{
    try {
        // ============================================================
        // 1. RÉCUPÉRATION DES DONNÉES EN BASE DE DONNÉES
        // ============================================================

        $leads = DB::connection('temp')      // Connexion à la base 'temp'
            ->table('prospects')              // Table 'prospects'
            ->where('projet_id', $projetId)   // Filtrer par projet
            // Condition OR : soit origine 'facebook', soit source = 9
            ->where(function($query) {
                $query->where('origin', 'facebook')
                    ->orWhere('source', 9);
            })
            ->whereNull('deleted_at')         // Exclure les leads supprimés
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']]) // Période
            ->orderBy('created_at', 'asc')    // Tri chronologique
            ->get();                          // Exécution de la requête

        // ============================================================
        // 2. CAS PARTICULIER : AUCUN LEAD TROUVÉ
        // ============================================================

        if ($leads->isEmpty()) {
            // Retourne des tableaux vides et une valeur 'unknown'
            return [
                'weekly_trend' => [],        // Tendance hebdomadaire
                'monthly_trend' => [],       // Tendance mensuelle
                'best_time' => 'unknown',    // Meilleur moment inconnu
                'seasonal_factors' => []     // Facteurs saisonniers vides
            ];
        }

        // ============================================================
        // 3. INITIALISATION DES TABLEAUX DE STATISTIQUES
        // ============================================================

        $weekly = [];   // Tableau pour compter les leads par semaine (clé = Année-Semaine)
        $monthly = [];  // Tableau pour compter les leads par mois (clé = Année-Mois)
        $hourly = [];   // Tableau pour compter les leads par heure (clé = Heure de 00 à 23)

        // ============================================================
        // 4. AGRÉGATION DES DONNÉES PAR PÉRIODE
        // ============================================================

        foreach ($leads as $lead) {
            $date = Carbon::parse($lead->created_at); // Convertir en objet Carbon

            // ---------- AGRÉGATION HEBDOMADAIRE ----------
            // Format 'Y-W' donne par exemple '2026-03' pour la 3ème semaine de 2026
            $week = $date->format('Y-W');
            $weekly[$week] = ($weekly[$week] ?? 0) + 1; // Incrémente le compteur

            // ---------- AGRÉGATION MENSUELLE ----------
            // Format 'Y-m' donne par exemple '2026-07' pour juillet 2026
            $month = $date->format('Y-m');
            $monthly[$month] = ($monthly[$month] ?? 0) + 1; // Incrémente le compteur

            // ---------- AGRÉGATION HORAIRE ----------
            // Format 'H' donne l'heure sur 24h (00 à 23)
            $hour = $date->format('H');
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1; // Incrémente le compteur
        }

        // ============================================================
        // 5. DÉTERMINATION DE LA MEILLEURE HEURE
        // ============================================================

        // arsort() trie le tableau par valeurs décroissantes (du plus grand au plus petit)
        // tout en conservant l'association clé => valeur
        arsort($hourly);

        // key($hourly) récupère la première clé du tableau (l'heure avec le plus de leads)
        $bestHour = !empty($hourly) ? key($hourly) . ':00' : 'unknown';
        // Exemple : si key($hourly) = '14', $bestHour sera '14:00'

        // ============================================================
        // 6. RETOUR DU RÉSULTAT FINAL
        // ============================================================

        return [
            'weekly_trend' => $weekly,    // Tableau [semaine => nombre de leads]
            'monthly_trend' => $monthly,  // Tableau [mois => nombre de leads]
            'best_time' => $bestHour,     // Meilleure heure (format 'HH:00')
            'seasonal_factors' => [       // Facteurs saisonniers
                'peak_hour' => $bestHour,     // Heure de pointe
                'peak_hour_count' => !empty($hourly) ? max($hourly) : 0, // Nombre max de leads à cette heure
            ]
        ];

    // ============================================================
    // 7. GESTION DES ERREURS
    // ============================================================

    } catch (\Exception $e) {
        // Log de l'erreur pour le debug
        Log::error('Error in getTrends: ' . $e->getMessage());

        // Retourne des valeurs par défaut en cas d'erreur
        return [
            'weekly_trend' => [],
            'monthly_trend' => [],
            'best_time' => 'unknown',
            'seasonal_factors' => []
        ];
    }
}

    // ============================================
    // MÉTHODES AUXILIAIRES
    // ============================================

    private function getGroupedStats($collection, $field)
    {
        if (!$collection || $collection->isEmpty()) {
            return [];
        }

        return $collection->groupBy($field)
            ->map(fn($items) => $items->count())
            ->filter(fn($count, $key) => !empty($key) && $key !== '')
            ->map(fn($count, $key) => ['name' => (string)$key, 'total' => $count])
            ->values()
            ->all();
    }

    private function getDailyStats($collection)
    {
        if (!$collection || $collection->isEmpty()) {
            return [];
        }

        return $collection->groupBy(fn($item) => Carbon::parse($item->created_at)->format('Y-m-d'))
            ->map(fn($items, $date) => ['date' => $date, 'count' => $items->count()])
            ->values()
            ->all();
    }
}
