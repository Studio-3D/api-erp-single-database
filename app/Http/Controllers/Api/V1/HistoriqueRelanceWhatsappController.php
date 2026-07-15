<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\PaginationHelper;
use App\Http\Helpers\RoleHelper;
use App\Models\HistoriqueRelanceWhatsapp;
use App\Models\Projet;
use App\Models\Prospect;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HistoriqueRelanceWhatsappController extends Controller
{
    /**
     * Display a listing of the resource.
     */


    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!RoleHelper::ACSup_RC()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();

        $historique = HistoriqueRelanceWhatsapp::on('temp')
            ->with([
                'user' => function($query) {
                    $query->select('id', 'name', 'prenom', 'user_id_origin')
                        ->without('societe');
                },
                'projet' => function($query) {
                    $query->select('id', 'nom');
                }
            ])
            ->findOrFail($id);

        // Get prospects details with pagination
        $prospectIds = $historique->prospect_ids ?? [];
        $prospects = [];
        $pagination = null;

        if (!empty($prospectIds)) {
            $prospectIdsArray = is_string($prospectIds) ? json_decode($prospectIds, true) : $prospectIds;

            if (is_array($prospectIdsArray) && !empty($prospectIdsArray)) {
                // Pagination parameters
                $size = $request->input('size', 10); // Default 10 per page
                $page = $request->input('page', 1);

                // Build the query
                $query = Prospect::on('temp')
                    ->whereIn('id', $prospectIdsArray)
                    ->select('id', 'nom', 'prenom', 'telephone', 'telephone_num2', 'email', 'source', 'commercial_affecte');

                // Get total count for pagination
                $total = $query->count();

                // Apply pagination
                $prospects = $query->skip(($page - 1) * $size)
                    ->take($size)
                    ->get();

                // Build pagination data
                $pagination = [
                    'currentPage' => (int)$page,
                    'totalItems' => $total,
                    'totalPages' => ceil($total / $size),
                    'perPage' => (int)$size,
                ];
            }
        }

        // Parse statistics
        $statistics = $historique->statistics ?? [];
        if (is_string($statistics)) {
            $statistics = json_decode($statistics, true);
        }

        // Parse metadata
        $metadata = $historique->metadata ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        // Parse response
        $response = $historique->response ?? [];
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        return response()->json([
            'historique' => $historique,
            'prospects' => $prospects,
            'pagination' => $pagination,
            'statistics' => $statistics,
            'metadata' => $metadata,
            'response_details' => $response,
        ], 200);
    }

    /**
     * Format historiques data
     */


public function index(Request $request, $projetId)
{
    if (!Auth::guard('api')->check()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Check if user has access
    if (!RoleHelper::ACSup_RC()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    DatabaseHelper::Config();

    $size = $request->input('size', config('app.default_item_number_perpage'));
    $page = $request->input('page', 1);
    $search = $request->input('search', '');

    // If no projet_id provided, return error
    if (!$projetId) {
        return response()->json(['error' => 'projet_id is required'], 422);
    }

    $query = HistoriqueRelanceWhatsapp::on('temp')
        ->with([
            'user' => function($query) {
                $query->select('id', 'name', 'prenom', 'user_id_origin')
                    ->without('societe');
            },
            'projet' => function($query) {
                $query->select('id', 'nom');
            }
        ])
        ->where('projet_id', $projetId);

    // ✅ Add date filter if provided
    if ($request->filled('date')) {
        $date = Carbon::parse($request->input('date'))->format('Y-m-d');
        $query->whereDate('created_at', $date);
    }

    // ✅ FIX: scheduled_date filtering with proper timezone handling
    if ($request->filled('scheduled_date')) {
        $scheduledDate = $request->input('scheduled_date');
        \Log::info('Filtering by scheduled_date:', ['date' => $scheduledDate]);

        try {
            // Parse the date
            $date = Carbon::parse($scheduledDate);

            // Get start and end of day in the same timezone as your database
            // If your database uses UTC
            $startOfDay = $date->copy()->startOfDay()->setTimezone('UTC');
            $endOfDay = $date->copy()->endOfDay()->setTimezone('UTC');

            // If your database uses Africa/Casablanca
            // $startOfDay = $date->copy()->startOfDay()->setTimezone('Africa/Casablanca');
            // $endOfDay = $date->copy()->endOfDay()->setTimezone('Africa/Casablanca');

            \Log::info('Date range:', [
                'start' => $startOfDay->toDateTimeString(),
                'end' => $endOfDay->toDateTimeString()
            ]);

            // Use between for accurate filtering
            $query->whereBetween('scheduled_date', [
                $startOfDay->toDateTimeString(),
                $endOfDay->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error parsing scheduled_date:', ['error' => $e->getMessage()]);
            // Fallback: try to filter by date only
            $query->whereDate('scheduled_date', $scheduledDate);
        }
    }

    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // ✅ Add search filter if provided
    if (!empty($search)) {
        $query->where(function($q) use ($search) {
            $q->where('message', 'LIKE', "%{$search}%")
              ->orWhere('media_url', 'LIKE', "%{$search}%");
        });
    }

    // Log the final query for debugging
    \Log::info('Final SQL query:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

    // Pagination
    if (is_numeric($size) && is_numeric($page) && $size > 0 && $page > 0) {
        $historiques = $query->orderBy('created_at', 'desc')
            ->paginate($size, ['*'], 'page', $page);

        $pagination = [
            'currentPage' => $historiques->currentPage(),
            'totalItems'  => $historiques->total(),
            'totalPages'  => $historiques->lastPage(),
        ];

        $historiques = $historiques->items();

        // Format data for response
        $formattedHistoriques = $this->formatHistoriques($historiques);

        return response()->json([
            'data'       => $formattedHistoriques,
            'pagination' => $pagination,
        ], 200);
    } else {
        $historiques = $query->orderBy('created_at', 'desc')
            ->get();

        $formattedHistoriques = $this->formatHistoriques($historiques);

        return response()->json(['data' => $formattedHistoriques], 200);
    }
}

/**
 * Format historiques data
 */
private function formatHistoriques($historiques)
{
    return array_map(function($item) {
        // Parse prospect_ids
        $prospectIds = $item->prospect_ids ?? [];
        if (is_string($prospectIds)) {
            $prospectIds = json_decode($prospectIds, true);
        }

        // Parse statistics
        $statistics = $item->statistics ?? [];
        if (is_string($statistics)) {
            $statistics = json_decode($statistics, true);
        }

        // Parse metadata
        $metadata = $item->metadata ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        // Parse response
        $response = $item->response ?? [];
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        // Get user info
        $userName = 'N/A';
        if ($item->user) {
            $userName = trim(($item->user->name ?? '') . ' ' . ($item->user->prenom ?? ''));
            if (empty($userName)) {
                $userName = 'User #' . $item->user_id;
            }
        }

        // Get project info
        $projectName = 'N/A';
        if ($item->projet) {
            $projectName = $item->projet->nom ?? 'Projet #' . $item->projet_id;
        }

        // Count prospects
        $prospectsCount = is_array($prospectIds) ? count($prospectIds) : 0;

        // ✅ Get media_url from the model (this is the main fix)
        $mediaUrl = $item->media_url ?? null;

        // If media_url is null but exists in metadata, use that as fallback
        if (empty($mediaUrl) && isset($metadata['media_url'])) {
            $mediaUrl = $metadata['media_url'];
        }

        return [
            'id' => $item->id,
            'projet_id' => $item->projet_id,
            'projet_name' => $projectName,
            'user_id' => $item->user_id,
            'user_name' => $userName,
            'prospect_ids' => $prospectIds,
            'prospects_count' => $prospectsCount,
            'message' => $item->message,
            'media_url' => $mediaUrl, // ✅ Now properly set
            'scheduled_date' => $item->scheduled_date,
            'sent_date' => $item->sent_date,
            'status' => $item->status,
            'status_label' => $this->getStatusLabel($item->status),
            'status_color' => $this->getStatusColor($item->status),
            'response' => $response,
            'error_message' => $item->error_message,
            'metadata' => $metadata,
            'statistics' => $statistics,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'deleted_at' => $item->deleted_at,
        ];
    }, $historiques);
}

/**
 * Get status label
 */
private function getStatusLabel($status)
{
    $labels = [
        'pending' => 'En Attente',
        'sent' => 'Envoyé',
        'failed' => 'Échoué',
        'cancelled' => 'Annulé',
        'processing' => 'En Cours',
        'partial' => 'Partiellement Envoyé',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Get status color
 */
private function getStatusColor($status)
{
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'sent' => 'bg-green-100 text-green-800',
        'failed' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-gray-100 text-gray-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'partial' => 'bg-orange-100 text-orange-800',
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!RoleHelper::AdminSup() && !RoleHelper::AgentAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();

        $historique = HistoriqueRelanceWhatsapp::on('temp')->findOrFail($id);

        // Prevent deletion if status is 'sent' or 'processing'
        if ($historique->status === 'sent') {
            return response()->json([
                'error' => 'Impossible de supprimer un historique déjà envoyé'
            ], 422);
        }

        if ($historique->status === 'processing') {
            return response()->json([
                'error' => 'Impossible de supprimer un historique en cours d\'envoi'
            ], 422);
        }

        if ($historique->delete()) {
            return response()->json([
                'message' => 'Historique supprimé avec succès'
            ], 200);
        }

        return response()->json([
            'error' => 'Erreur lors de la suppression'
        ], 500);
    }

    /**
     * Get statistics summary
     */
    public function statistics(Request $request)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!RoleHelper::ACSup_RC()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();

        $projetId = $request->input('projet_id');

        if (!$projetId) {
            return response()->json(['error' => 'projet_id is required'], 422);
        }

        $query = HistoriqueRelanceWhatsapp::on('temp')
            ->where('projet_id', $projetId);

        $total = $query->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $processing = (clone $query)->where('status', 'processing')->count();
        $sent = (clone $query)->where('status', 'sent')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $partial = (clone $query)->where('status', 'partial')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();

        // Get total prospects contacted
        $allHistories = $query->get();
        $totalProspects = 0;
        $totalSent = 0;
        $totalFailed = 0;

        foreach ($allHistories as $history) {
            $statistics = $history->statistics ?? [];
            if (is_string($statistics)) {
                $statistics = json_decode($statistics, true);
            }

            if (isset($statistics['total_prospects'])) {
                $totalProspects += $statistics['total_prospects'];
            }

            if (isset($statistics['sent_count'])) {
                $totalSent += $statistics['sent_count'];
            }

            if (isset($statistics['failed_count'])) {
                $totalFailed += $statistics['failed_count'];
            }
        }

        return response()->json([
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'sent' => $sent,
            'failed' => $failed,
            'partial' => $partial,
            'cancelled' => $cancelled,
            'total_prospects_contacted' => $totalProspects,
            'total_messages_sent' => $totalSent,
            'total_messages_failed' => $totalFailed,
        ], 200);
    }

    /**
     * Retry failed messages
     */
    public function retry($id)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!RoleHelper::AdminSup() && !RoleHelper::AgentAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        DatabaseHelper::Config();

        $historique = HistoriqueRelanceWhatsapp::on('temp')->findOrFail($id);

        if ($historique->status !== 'failed' && $historique->status !== 'partial') {
            return response()->json([
                'error' => 'Seuls les historiques échoués ou partiels peuvent être relancés'
            ], 422);
        }

        // Reset status to pending
        $historique->status = 'pending';
        $historique->error_message = null;
        $historique->save();

        return response()->json([
            'message' => 'Relance programmée avec succès',
            'historique' => $historique
        ], 200);
    }
}
