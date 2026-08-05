<?php

namespace App\Services;

use App\Models\Bien;
use App\Models\Projet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentFinalService
{
    private $apiKey;
    private $model = 'gpt-4o-mini';

    public function __construct()
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
    }

    public function processMessage($message, $sessionId)
    {
        try {
            // 1. Récupérer les biens (sans filtre projet_id pour tout voir)
            $biens = Bien::with(['projet', 'typeBien'])
                ->limit(15)
                ->get();

            // 2. Récupérer les projets
            $projets = Projet::all();

            // 3. Construire le prompt (100% IA)
            $prompt = $this->buildPrompt($message, $biens, $projets);

            // 4. Appeler OpenRouter
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'http://localhost:8000',
                'X-Title' => 'Agent Immobilier',
            ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt()],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 800
            ]);

            // 5. Si OpenRouter répond, retourner
            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;
                if (!empty($content)) {
                    return $content;
                }
            }

            // 6. Fallback
            Log::error('OpenRouter échoué, statut: ' . $response->status());
            return "Je suis Karim, votre agent immobilier. Désolé, je rencontre un problème technique. Veuillez réessayer. 😊";

        } catch (\Exception $e) {
            Log::error('Erreur: ' . $e->getMessage());
            return "Je suis Karim, votre agent immobilier. Désolé, je rencontre un problème technique. Veuillez réessayer. 😊";
        }
    }

    private function getSystemPrompt()
    {
        return "Tu es **Karim**, un agent immobilier marocain professionnel, chaleureux et sympathique.

**RÈGLES IMPORTANTES :**
1. Parle en **français** ou en **darija** selon la langue du client
2. Réponds à **TOUTES** les questions du client de manière naturelle
3. Utilise les données des biens et projets pour répondre
4. Si le client pose une question générale, réponds avec humour et élégance
5. Sois **utile**, **précis** et **naturel**
6. Propose toujours de l'aide supplémentaire
7. N'invente pas d'informations sur les biens

**STYLE DE RÉPONSE :**
- En darija : expressions marocaines naturelles (Salamo, Labas, Kifash, Mzyan...)
- En français : professionnel mais chaleureux
- Adapte ton langage à celui du client";
    }

    private function buildPrompt($message, $biens, $projets)
    {
        $prompt = "Voici les données disponibles sur les biens et projets :\n\n";

        // Projets
        $prompt .= "**PROJETS DISPONIBLES :**\n";
        foreach ($projets as $projet) {
            $nb = Bien::where('projet_id', $projet->id)->count();
            $prompt .= "- " . $projet->nom . " : " . $nb . " biens\n";
        }

        // Biens
        if ($biens->isNotEmpty()) {
            $prompt .= "\n**BIENS DISPONIBLES :**\n";
            foreach ($biens as $bien) {
                $prix = number_format($bien->prix, 0, ',', ' ') . ' DH';
                $type = $bien->type_bien->type ?? 'Inconnu';
                $projetNom = $bien->projet->nom ?? 'Sans projet';
                $etage = $bien->niveau !== null ? ($bien->niveau == 0 ? 'RDC' : $bien->niveau . 'ème') : 'Non spécifié';
                $orientation = $bien->orientation ?? 'Non spécifiée';
                $prompt .= "- N°{$bien->numero} : {$type} | {$bien->superficie_habitable}m² | {$prix} | {$projetNom} | Étage: {$etage} | Orientation: {$orientation}\n";
            }
        } else {
            $prompt .= "\n**Aucun bien trouvé dans la base de données.**\n";
        }

        // Statistiques
        $totalBiens = Bien::count();
        $totalProjets = Projet::count();
        $prompt .= "\n**STATISTIQUES :**\n";
        $prompt .= "- Total des biens : " . $totalBiens . "\n";
        $prompt .= "- Total des projets : " . $totalProjets . "\n";

        // Question du client
        $prompt .= "\n---\n";
        $prompt .= "**QUESTION DU CLIENT :** " . $message . "\n";
        $prompt .= "\n**RÉPONSE DE KARIM :**";

        return $prompt;
    }
}
