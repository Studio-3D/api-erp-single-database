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
                'ads' => $this->getAdStats($pageId, $accessToken, $dateRange),
                'temporal' => $this->getTemporalAnalysis($projet_id, $dateRange),
                'quality' => $this->getLeadQuality($projet_id, $dateRange),
                'funnel' => $this->getConversionFunnel($projet_id, $dateRange),
                'ad_performance' => $this->getAdPerformance($pageId, $accessToken, $dateRange),
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
                'avg_daily' => count($leadsByDay) > 0 ? round($totalLeads / count($leadsByDay), 1) : 0,
                'avg_hourly' => count($leadsByHour) > 0 ? round($totalLeads / count($leadsByHour), 1) : 0,
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
                'avg_daily' => 0,
                'avg_hourly' => 0,
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
private function getCommercialStats($collection)
{
    if (!$collection || $collection->isEmpty()) {
        return [];
    }

    // ✅ Filtrer les prospects avec commercial_affecte non null
    $filteredCollection = $collection->filter(function($item) {
        return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
    });

    if ($filteredCollection->isEmpty()) {
        return [];
    }

    $grouped = $filteredCollection->groupBy('commercial_affecte');
    $result = [];

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
            ->where('origin', 'facebook')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
            ->get();

        $total = $prospects->count();

        // ✅ Récupérer TOUS les statuts de tous les prospects
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

        // ✅ STATISTIQUES PAR COMMERCIAL ET STATUT (UNIQUEMENT ceux avec prospects)
        $commercialStatusStats = $this->getCommercialAllStatusStats($prospects, $allStatuses);

        // ✅ STATISTIQUES PAR COMMERCIAL (UNIQUEMENT ceux avec prospects)
        $commercialStats = $this->getCommercialStats($prospects);

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

        return [
            'total_prospects' => $total,
            'total_statuses' => $totalStatuses,
            'prospects_by_type' => $this->getGroupedStatsFromCollection($prospects, 'type_bien'),
            'prospects_by_budget' => $this->getGroupedStatsFromCollection($prospects, 'budget'),
            'prospects_by_residence' => $this->getGroupedStatsFromCollection($prospects, 'residence'),
            'prospects_by_status' => $statusDistribution,
            'prospects_by_status_raw' => $statusStats,
            'prospects_by_commercial' => $commercialStats, // ✅ UNIQUEMENT les commerciaux avec prospects
            'prospects_by_commercial_status' => $commercialStatusStats, // ✅ UNIQUEMENT les commerciaux avec prospects
            'prospects_by_day' => $prospectsByDayFormatted,
            'conversion_rate' => $total > 0
                ? round($prospects->filter(function($p) use ($allStatuses) {
                    $prospectStatuses = $allStatuses->where('prospect_id', $p->id);
                    return $prospectStatuses->contains(function($s) {
                        return in_array($s->statut, ['4', '10']);
                    });
                })->count() / $total * 100, 2)
                : 0,
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
            'conversion_rate' => 0,
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

    // ✅ Grouper les prospects par commercial (filtrer les null)
    $commercialGroups = $prospects->filter(function($item) {
        return !is_null($item->commercial_affecte) && $item->commercial_affecte !== '';
    })->groupBy('commercial_affecte');

    // ✅ Si aucun commercial, retourner un tableau vide
    if ($commercialGroups->isEmpty()) {
        return [];
    }

    $result = [];
    foreach ($commercialGroups as $commercialId => $commercialProspects) {
        $commercialName = $this->getCommercialName($commercialId);

        // Récupérer les IDs des prospects de ce commercial
        $prospectIds = $commercialProspects->pluck('id')->toArray();

        // Filtrer les statuts de ces prospects
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

        // Trier par ordre décroissant
        usort($statusCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        $result[] = [
            'commercial_id' => $commercialId,
            'commercial_name' => $commercialName,
            'total_prospects' => $commercialProspects->count(),
            'total_statuses' => $commercialStatuses->count(),
            'statuses' => $statusCounts,
        ];
    }

    // Trier par nombre total de statuts décroissant
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
     */
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
    }

    /**
     * 6. Analyse temporelle - AVEC REMPLISSAGE DES JOURS MANQUANTS
     */
    private function getTemporalAnalysis($projetId, $dateRange)
    {
        try {
            $leads = DB::connection('temp')
                ->table('prospects')
                ->where('projet_id', $projetId)
                ->where('origin', 'facebook')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
                ->orderBy('created_at', 'asc')
                ->get();

            if ($leads->isEmpty()) {
                // ✅ Retourner un tableau avec toutes les dates de la période
                $dailyTrend = [];
                $current = $dateRange['since']->copy();
                while ($current <= $dateRange['until']) {
                    $dailyTrend[$current->format('Y-m-d')] = 0;
                    $current->addDay();
                }

                return [
                    'trend' => 'stable',
                    'growth_rate' => 0,
                    'projection_30d' => 0,
                    'total_days' => $dateRange['since']->diffInDays($dateRange['until']) + 1,
                    'avg_daily' => 0,
                    'daily_trend' => $dailyTrend,
                ];
            }

            $total = $leads->count();
            $firstDate = $dateRange['since']->copy();
            $lastDate = $dateRange['until']->copy();
            $daysDiff = $firstDate->diffInDays($lastDate) + 1;

            // ✅ Créer un tableau avec toutes les dates de la période
            $dailyTrend = [];
            for ($i = 0; $i < $daysDiff; $i++) {
                $date = $firstDate->copy()->addDays($i)->format('Y-m-d');
                $dailyTrend[$date] = 0;
            }

            // ✅ Remplir les données réelles
            foreach ($leads as $lead) {
                $date = Carbon::parse($lead->created_at)->format('Y-m-d');
                if (isset($dailyTrend[$date])) {
                    $dailyTrend[$date]++;
                }
            }

            // ✅ Calculer la croissance
            $growthRate = $daysDiff > 0 ? round($total / $daysDiff, 2) : 0;
            $projection = round($growthRate * 30, 0);

            return [
                'trend' => $growthRate > 1 ? 'up' : ($growthRate < 0.5 ? 'down' : 'stable'),
                'growth_rate' => $growthRate,
                'projection_30d' => $projection,
                'total_days' => $daysDiff,
                'avg_daily' => $growthRate,
                'daily_trend' => $dailyTrend,
            ];

        } catch (\Exception $e) {
            Log::error('Error in getTemporalAnalysis: ' . $e->getMessage());
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

    /**
     * 7. Qualité des leads
     */
   /**
 * 7. Qualité des leads - Version améliorée
 */
/**
 * 7. Qualité des leads - Version corrigée avec les bons statuts
 */
/**
 * 7. Qualité des leads - Version corrigée
 */
private function getLeadQuality($projetId, $dateRange)
{
    try {
        // ✅ Récupérer les prospects Facebook
        $prospects = DB::connection('temp')
            ->table('prospects')
            ->where('projet_id', $projetId)
            ->where('origin', 'facebook')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
            ->get();

        $total = $prospects->count();

        if ($total === 0) {
            return $this->getDefaultQualityStats();
        }

        // ✅ Récupérer les statuts
        $prospectIds = $prospects->pluck('id')->toArray();
        $allStatuses = DB::connection('temp')
            ->table('statut_prospects')
            ->whereIn('prospect_id', $prospectIds)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ Mapping des statuts (avec clés STRING)
        $statusMap = [
            '0' => ['label' => 'En attente', 'score' => 10, 'color' => '#FCD34D'],
            '1' => ['label' => 'Planification RDV', 'score' => 30, 'color' => '#60A5FA'],
            '2' => ['label' => 'Injoignable', 'score' => 5, 'color' => '#F87171'],
            '3' => ['label' => 'Rappel', 'score' => 20, 'color' => '#FB923C'],
            '4' => ['label' => 'Converti en Visite', 'score' => 70, 'color' => '#34D399'],
            '5' => ['label' => 'Nouvel Appel', 'score' => 25, 'color' => '#A78BFA'],
            '6' => ['label' => 'Affecté', 'score' => 15, 'color' => '#818CF8'],
            '7' => ['label' => 'Intéressé', 'score' => 50, 'color' => '#F472B6'],
            '8' => ['label' => 'Perdu', 'score' => 0, 'color' => '#9CA3AF'],
            '9' => ['label' => 'Réceptif', 'score' => 40, 'color' => '#2DD4BF'],
            '10' => ['label' => 'Converti en client', 'score' => 100, 'color' => '#10B981'],
            '11' => ['label' => 'WhatsApp Envoyé', 'score' => 35, 'color' => '#22D3EE'],
        ];

        // ✅ Calcul des métriques de qualité
        $statusDistribution = [];
        $totalScore = 0;
        $maxScore = 0;
        $totalStatuses = $allStatuses->count();

        foreach ($statusMap as $code => $info) {
            // ✅ Compter les statuts (en string)
            $count = $allStatuses->filter(function($s) use ($code) {
                return (string)$s->statut === $code;
            })->count();

            $statusDistribution[$info['label']] = [
                'count' => $count,
                'percentage' => $totalStatuses > 0 ? round(($count / $totalStatuses) * 100, 2) : 0,
                'score' => $info['score'],
                'color' => $info['color'],
            ];

            // ✅ Calculer le score total (pour chaque statut, on prend le score * nombre de statuts)
            $totalScore += $count * $info['score'];
            // ✅ Le max possible est 100 * nombre de statuts
            $maxScore += $count * 100;
        }

        // ✅ Score de qualité global (sur 100)
        $qualityScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        // ✅ Taux de conversion (prospects avec statut 4 ou 10)
        $convertedProspects = $prospects->filter(function($p) use ($allStatuses) {
            $prospectStatuses = $allStatuses->where('prospect_id', $p->id);
            $codes = $prospectStatuses->pluck('statut')->map(fn($s) => (string)$s)->toArray();
            return array_intersect($codes, ['4', '10']);
        })->count();
        $conversionRate = $total > 0 ? round(($convertedProspects / $total) * 100, 2) : 0;

        // ✅ Taux de contact
        $contactedStatuses = ['1', '3', '4', '5', '7', '9', '10'];
        $contactedProspects = $prospects->filter(function($p) use ($allStatuses, $contactedStatuses) {
            $prospectStatuses = $allStatuses->where('prospect_id', $p->id);
            $codes = $prospectStatuses->pluck('statut')->map(fn($s) => (string)$s)->toArray();
            return array_intersect($codes, $contactedStatuses);
        })->count();
        $contactRate = $total > 0 ? round(($contactedProspects / $total) * 100, 2) : 0;

        // ✅ Distribution des statuts pour les graphiques
        $statusChartData = [];
        foreach ($statusMap as $code => $info) {
            $count = $allStatuses->filter(function($s) use ($code) {
                return (string)$s->statut === $code;
            })->count();

            if ($count > 0) {
                $statusChartData[] = [
                    'name' => $info['label'],
                    'value' => $count,
                    'percentage' => $totalStatuses > 0 ? round(($count / $totalStatuses) * 100, 2) : 0,
                    'color' => $info['color'],
                    'score' => $info['score'],
                ];
            }
        }

        // ✅ Statistiques de qualité par commercial (APPEL DE LA METHODE)
        $commercialQuality = $this->getCommercialQualityStats($prospects, $allStatuses, $statusMap);

        return [
            'quality_score' => $qualityScore,
            'contact_rate' => $contactRate,
            'conversion_rate' => $conversionRate,
            'total_prospects' => $total,
            'total_statuses' => $totalStatuses,
            'status_distribution' => $statusDistribution,
            'status_chart_data' => $statusChartData,
            'commercial_quality' => $commercialQuality, // ✅ Ajouté
            'quality_level' => $this->getQualityLevel($qualityScore),
        ];

    } catch (\Exception $e) {
        Log::error('Error in getLeadQuality: ' . $e->getMessage());
        Log::error('Trace: ' . $e->getTraceAsString());
        return $this->getDefaultQualityStats();
    }
}

/**
 * ✅ Statistiques de qualité par commercial
 */
/**
 * ✅ Statistiques de qualité par commercial
 */
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
        return [];
    }

    $result = [];
    foreach ($commercialGroups as $commercialId => $commercialProspects) {
        $commercialName = $this->getCommercialName($commercialId);
        $prospectIds = $commercialProspects->pluck('id')->toArray();
        $commercialStatuses = $allStatuses->whereIn('prospect_id', $prospectIds);
        $total = $commercialProspects->count();

        // Calculer le score de qualité pour ce commercial
        $totalScore = 0;
        $maxScore = 0;
        foreach ($commercialStatuses as $status) {
            $code = (string)$status->statut;
            $score = $statusMap[$code]['score'] ?? 0;
            $totalScore += $score;
            $maxScore += 100;
        }

        $qualityScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        // Distribution des statuts
        $statusDistribution = [];
        foreach ($statusMap as $code => $info) {
            $count = $commercialStatuses->filter(function($s) use ($code) {
                return (string)$s->statut === $code;
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

/**
 * 8. Funnel de conversion - Version améliorée
 */
/**
 * 8. Funnel de conversion - Version corrigée avec les bons statuts
 */
private function getConversionFunnel($projetId, $dateRange)
{
    try {
        $prospects = DB::connection('temp')
            ->table('prospects')
            ->where('projet_id', $projetId)
            ->where('origin', 'facebook')
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

        // ✅ Définir les étapes du funnel avec des descriptions
        $funnelSteps = [
            'Leads' => $total,
            'Affectés' => 0,      // ✅ Nouvelle étape pour le statut 6
            'Contactés' => 0,
            'Qualifiés' => 0,
            'Visites' => 0,
            'Négociation' => 0,
            'Vendus' => 0,
        ];

        foreach ($prospects as $prospect) {
            $prospectStatuses = $allStatuses->where('prospect_id', $prospect->id);
            $statusCodes = $prospectStatuses->pluck('statut')->map(function($s) {
                return (string)$s;
            })->toArray();

            if (empty($statusCodes)) {
                continue;
            }

            // ✅ Vérifier le statut "Affecté" (6)
            if (in_array('6', $statusCodes)) {
                $funnelSteps['Affectés']++;
            }

            // ✅ Vérifier les statuts de contact (1, 3, 4, 5, 7, 9, 10)
            $contactStatuses = ['1', '3', '4', '5', '7', '9', '10'];
            if (array_intersect($statusCodes, $contactStatuses)) {
                $funnelSteps['Contactés']++;
            }

            // ✅ Vérifier les statuts de qualification (4, 7, 9, 10)
            $qualifiedStatuses = ['4', '7', '9', '10'];
            if (array_intersect($statusCodes, $qualifiedStatuses)) {
                $funnelSteps['Qualifiés']++;
            }

            // ✅ Vérifier les statuts de visite (4)
            if (in_array('4', $statusCodes)) {
                $funnelSteps['Visites']++;
            }

            // ✅ Vérifier les statuts de négociation (7, 9)
            $negotiationStatuses = ['7', '9'];
            if (array_intersect($statusCodes, $negotiationStatuses)) {
                $funnelSteps['Négociation']++;
            }

            // ✅ Vérifier les statuts de vente (10)
            if (in_array('10', $statusCodes)) {
                $funnelSteps['Vendus']++;
            }
        }

        // ✅ Construire les données du funnel
        $funnelData = [];
        $prevCount = $total;

        // Définir l'ordre des étapes pour le funnel
        $stepOrder = ['Leads', 'Affectés', 'Contactés', 'Qualifiés', 'Visites', 'Négociation', 'Vendus'];

        foreach ($stepOrder as $step) {
            $count = $funnelSteps[$step] ?? 0;
            $dropOff = $prevCount - $count;
            $dropRate = $prevCount > 0 ? round(($dropOff / $prevCount) * 100, 2) : 0;
            $conversionRate = $total > 0 ? round(($count / $total) * 100, 2) : 0;

            $funnelData[] = [
                'name' => $step,
                'value' => $count,
                'conversion_rate' => $conversionRate,
                'drop_off' => $dropOff,
                'drop_rate' => $dropRate,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
            ];

            $prevCount = $count;
        }

        $overallConversion = $total > 0 ? round(($funnelSteps['Vendus'] / $total) * 100, 2) : 0;

        return [
            'steps' => $funnelData,
            'summary' => [
                'total_leads' => $total,
                'total_affected' => $funnelSteps['Affectés'],
                'total_contacted' => $funnelSteps['Contactés'],
                'total_qualified' => $funnelSteps['Qualifiés'],
                'total_visits' => $funnelSteps['Visites'],
                'total_negotiation' => $funnelSteps['Négociation'],
                'total_sold' => $funnelSteps['Vendus'],
                'overall_conversion' => $overallConversion,
            ],
            'drop_off_analysis' => $this->getDropOffAnalysis($funnelData),
        ];

    } catch (\Exception $e) {
        Log::error('Error in getConversionFunnel: ' . $e->getMessage());
        return $this->getDefaultFunnel();
    }
}

/**
 * ✅ Analyse des points de chute
 */
private function getDropOffAnalysis($funnelData)
{
    $analysis = [];
    for ($i = 0; $i < count($funnelData) - 1; $i++) {
        $current = $funnelData[$i];
        $next = $funnelData[$i + 1];

        $dropOff = $current['value'] - $next['value'];
        $dropRate = $current['value'] > 0 ? round(($dropOff / $current['value']) * 100, 2) : 0;
        $conversionRate = $current['value'] > 0 ? round(($next['value'] / $current['value']) * 100, 2) : 0;

        $analysis[] = [
            'from' => $current['name'],
            'to' => $next['name'],
            'drop_off' => $dropOff,
            'drop_rate' => $dropRate,
            'conversion_rate' => $conversionRate,
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
     */
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
    }

    /**
     * 10. Tendances et prévisions
     */
    private function getTrends($projetId, $dateRange)
    {
        try {
            $leads = DB::connection('temp')
                ->table('prospects')
                ->where('projet_id', $projetId)
                ->where('origin', 'facebook')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$dateRange['since'], $dateRange['until']])
                ->orderBy('created_at', 'asc')
                ->get();

            if ($leads->isEmpty()) {
                return [
                    'weekly_trend' => [],
                    'monthly_trend' => [],
                    'best_time' => 'unknown',
                    'seasonal_factors' => []
                ];
            }

            $weekly = [];
            $monthly = [];
            $hourly = [];

            foreach ($leads as $lead) {
                $date = Carbon::parse($lead->created_at);
                $week = $date->format('Y-W');
                $weekly[$week] = ($weekly[$week] ?? 0) + 1;

                $month = $date->format('Y-m');
                $monthly[$month] = ($monthly[$month] ?? 0) + 1;

                $hour = $date->format('H');
                $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
            }

            // Trouver la meilleure heure
            arsort($hourly);
            $bestHour = !empty($hourly) ? key($hourly) . ':00' : 'unknown';

            return [
                'weekly_trend' => $weekly,
                'monthly_trend' => $monthly,
                'best_time' => $bestHour,
                'seasonal_factors' => [
                    'peak_hour' => $bestHour,
                    'peak_hour_count' => !empty($hourly) ? max($hourly) : 0,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error in getTrends: ' . $e->getMessage());
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
