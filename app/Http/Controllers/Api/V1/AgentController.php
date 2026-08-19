<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AgentFinalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string|max:255',
        ]);

        $sessionId = $request->input('session_id');
        if (!$sessionId) {
            $sessionId = 'session_' . uniqid();
        }

        // ═══════════════════════════════════════════════════
        // RÉCUPÉRER LA CONVERSATION DEPUIS LA BASE DE DONNÉES
        // ═══════════════════════════════════════════════════

        $conversation = Conversation::getOrCreate($sessionId, [
            'user_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Log::info('Conversation récupérée', [
            'session_id' => $sessionId,
            'state' => $conversation->state,
            'history_count' => count($conversation->history ?? []),
        ]);

        // ═══════════════════════════════════════════════════
        // PRÉPARER LES DONNÉES POUR L'AGENT
        // ═══════════════════════════════════════════════════

        $state = $conversation->state ?? [];
        $history = $conversation->history ?? [];

        // Si l'historique est vide mais que le state contient des données
        // on reconstruit l'historique depuis le state
        if (empty($history) && !empty($state)) {
            $history = $this->rebuildHistoryFromState($state);
            Log::info('Historique reconstruit depuis le state', [
                'history' => $history
            ]);
        }

        // ═══════════════════════════════════════════════════
        // APPEL DE L'AGENT
        // ═══════════════════════════════════════════════════

        $agent = new AgentFinalService($state);
        $message = trim($request->input('message'));

        $result = $agent->reply($message, $history);

        // ═══════════════════════════════════════════════════
        // RÉCUPÉRER LE NOUVEAU STATE
        // ═══════════════════════════════════════════════════

        $newState = $result['state'] ?? $agent->getConversationState();

        Log::info('Résultat de l\'agent', [
            'new_state' => $newState,
            'response' => $result['message'] ?? '',
        ]);

        // ═══════════════════════════════════════════════════
        // SAUVEGARDER DANS LA BASE DE DONNÉES
        // ═══════════════════════════════════════════════════

        if ($result['success'] ?? false) {
            // Ajouter les nouveaux messages à l'historique
            $history[] = [
                'role' => 'user',
                'content' => $message,
            ];

            $history[] = [
                'role' => 'assistant',
                'content' => $result['message'] ?? '',
            ];

            // Limiter l'historique à 20 messages
            $history = array_slice($history, -20);

            // Sauvegarder dans la base de données
            $conversation->updateConversation($newState, $history);

            Log::info('Conversation sauvegardée en DB', [
                'session_id' => $sessionId,
                'id' => $conversation->id,
            ]);
        }

        // ═══════════════════════════════════════════════════
        // RÉPONSE
        // ═══════════════════════════════════════════════════

        return response()->json([
            'success' => $result['success'] ?? true,
            'response' => $result['message'] ?? '',
            'session_id' => $sessionId,
            'state' => $newState,
            'pending_contact' => $result['pending_contact'] ?? $agent->getPendingContact(),
        ]);
    }

    /**
     * Reconstruire l'historique depuis le state
     */
    private function rebuildHistoryFromState(array $state): array
    {
        $history = [];

        if (!empty($state['property_type'])) {
            $history[] = ['role' => 'user', 'content' => $state['property_type']];
            $history[] = ['role' => 'assistant', 'content' => 'J\'ai bien noté votre choix : ' . $state['property_type']];
        }

        if (!empty($state['budget']) && ($state['budget_given_by_user'] ?? false)) {
            $history[] = ['role' => 'user', 'content' => (string) $state['budget']];
            $history[] = ['role' => 'assistant', 'content' => 'Budget enregistré : ' . number_format((int) $state['budget'], 0, ',', ' ') . ' DH'];
        }

        if (!empty($state['name'])) {
            $history[] = ['role' => 'user', 'content' => 'je m\'appelle ' . $state['name']];
            $history[] = ['role' => 'assistant', 'content' => 'Merci ' . $state['name'] . ' 😊'];
        }

        if (!empty($state['phone'])) {
            $history[] = ['role' => 'user', 'content' => $state['phone']];
            $history[] = ['role' => 'assistant', 'content' => 'Numéro enregistré 😊'];
        }

        if (!empty($state['appointment_date'])) {
            $history[] = ['role' => 'user', 'content' => 'date: ' . $state['appointment_date']];
        }

        if (!empty($state['appointment_time'])) {
            $history[] = ['role' => 'user', 'content' => 'heure: ' . $state['appointment_time']];
        }

        return $history;
    }

    /**
     * Nettoyer les conversations expirées
     */
    public function cleanExpired()
    {
        Conversation::cleanExpired();
        return response()->json(['message' => 'Conversations expirées nettoyées']);
    }

    /**
     * Récupérer l'état d'une conversation (debug)
     */
    public function getConversation(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $conversation = Conversation::where('session_id', $request->session_id)->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation non trouvée'], 404);
        }

        return response()->json([
            'session_id' => $conversation->session_id,
            'state' => $conversation->state,
            'history' => $conversation->history,
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ]);
    }
}
