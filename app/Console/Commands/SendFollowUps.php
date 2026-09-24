<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Societe;
use App\Http\Helpers\DatabaseHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendFollowUps extends Command
{
    protected $signature = 'follow-ups:send';
    protected $description = 'Envoyer les relances aux clients qui n\'ont pas répondu depuis 1h';

/**
 * 🔥 VÉRIFIER SI LE CLIENT A UN RDV CONFIRMÉ AVEC UN COMMERCIAL
 *
 * Un RDV est considéré comme CONFIRMÉ uniquement si :
 *   - handoff_notified = true  → lead transmis au commercial (nom + tél + acceptation)
 *   - visit_accepted = true    → visite acceptée
 *
 * IMPORTANT : wants_callback / wants_visit ne sont que des INTENTIONS.
 * Tant que le client n'a pas donné nom + tél, le RDV n'est PAS confirmé
 * → le cron DOIT relancer.
 */
private function hasConfirmedVisit(Conversation $conversation): bool
{
    $state = $conversation->state ?? [];

    // ════════════════════════════════════════════════════════════
    // ✅ RDV CONFIRMÉ — 1 seule condition suffit
    // ════════════════════════════════════════════════════════════

    // 1️⃣ Lead déjà transmis au commercial
    //    (AgentFinalService::dispatchHandoff() → handoff_notified=true)
    if (!empty($state['handoff_notified'])) {
        Log::info("⏭️ Lead transmis au commercial → PAS de relance", [
            'session_id' => $conversation->session_id,
            'name' => $state['name'] ?? null,
            'phone' => $state['phone'] ?? null,
            'requested_visit' => $state['wants_visit'] ?? false,
            'requested_callback' => $state['wants_callback'] ?? false,
        ]);
        return true;
    }

    // 2️⃣ Visite acceptée (RDV complet)
    if (!empty($state['visit_accepted'])) {
        Log::info("⏭️ Visite acceptée → PAS de relance", [
            'session_id' => $conversation->session_id,
            'name' => $state['name'] ?? null,
        ]);
        return true;
    }

    // ════════════════════════════════════════════════════════════
    // ❌ Aucun RDV confirmé → ON RELANCE
    //
    // Cas couverts (mazal khass relance) :
    //   - Client a gal "bghit rappel" walakin ma-3tach nom/tél
    //   - Client a gal "bghit visite" walakin ma-3tach nom/tél
    //   - Client mazal ma-jawebch
    //   - Client gal "non"
    // ════════════════════════════════════════════════════════════
    Log::info("📤 RDV pas confirmé → relance autorisée", [
        'session_id' => $conversation->session_id,
        'wants_visit' => $state['wants_visit'] ?? false,
        'wants_callback' => $state['wants_callback'] ?? false,
        'handoff_notified' => $state['handoff_notified'] ?? false,
        'commercial_offer_declined' => $state['commercial_offer_declined'] ?? false,
        'has_name' => !empty($state['name']),
        'has_phone' => !empty($state['phone']),
    ]);

    return false;
}

    public function handle()
    {
        Log::info('🔄 CRON: Vérification des follow-ups à envoyer...');

        // ✅ BOUCLER SUR TOUTES LES SOCIÉTÉS
        $societes = Societe::all();

        if ($societes->isEmpty()) {
            Log::warning('⚠️ Aucune société trouvée');
            return 0;
        }

        $totalFollowUps = 0;

        foreach ($societes as $societe) {
            try {
                // ✅ Configurer la connexion temp pour cette société
                $this->configureTempConnectionForSociete($societe);

                // ✅ RÉCUPÉRER LES CONVERSATIONS AVEC on('temp')
                $conversations = Conversation::on('temp')
                    ->where('follow_up_scheduled', true)
                    ->whereNotNull('last_bot_message_at')
                    ->where('last_bot_message_at', '<=', now()->subHour())
                    ->where('last_bot_message_at', '<=', now()->subMinutes(2))
                    ->whereNull('follow_up_sent_at')
                    ->where(function ($query) {
                        $query->whereNull('last_client_message_at')
                            ->orWhereColumn('last_client_message_at', '<', 'last_bot_message_at');
                    })
                    ->get();

                if ($conversations->isEmpty()) {
                    Log::info("✅ Aucune conversation à relancer pour société #{$societe->id}");
                    continue;
                }

                Log::info("📤 {$conversations->count()} conversation(s) à relancer pour société #{$societe->id}");
                $this->info("📤 Société #{$societe->id} : {$conversations->count()} conversation(s)");

               foreach ($conversations as $conversation) {
                try {
                    // ✅ VÉRIFIER SI LE CLIENT A DÉJÀ CONFIRMÉ UNE VISITE
                    if ($this->hasConfirmedVisit($conversation)) {
                        Log::info("⏭️ Client a déjà confirmé une visite → PAS de relance", [
                            'session_id' => $conversation->session_id,
                            'name' => $conversation->state['name'] ?? null,
                            'appointment_date' => $conversation->state['appointment_date'] ?? null,
                        ]);

                        // ✅ Marquer comme envoyé pour ne pas boucler
                        $this->markFollowUpAsSent($conversation);
                        continue;
                    }

                    // ✅ Sinon, envoyer le follow-up
                    $this->sendFollowUp($conversation);
                    $totalFollowUps++;
                } catch (\Exception $e) {
                    Log::error("❌ Erreur follow-up #{$conversation->id}: " . $e->getMessage());
                }
                }
            } catch (\Exception $e) {
                Log::error("❌ Erreur pour société #{$societe->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ {$totalFollowUps} follow-up(s) envoyé(s) au total");
        return 0;
    }

    /**
     * ✅ CONFIGURER LA CONNEXION TEMP POUR UNE SOCIÉTÉ
     */
    private function configureTempConnectionForSociete($societe)
    {
        try {
            $databaseName = env('DB_DATABASE');
            $connection = DatabaseHelper::Connection_database($databaseName);

            config(['database.connections.temp' => $connection]);
            DB::purge('temp');
            DB::reconnect('temp');

            Log::info("✅ Connexion temp configurée pour société #{$societe->id}", [
                'database' => $databaseName,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur config temp société #{$societe->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 🔥 ENVOYER UN FOLLOW-UP
     */
    private function sendFollowUp(Conversation $conversation)
    {
        Log::info("📤 Envoi du follow-up", [
            'id' => $conversation->id,
            'session_id' => $conversation->session_id,
            'phone' => $conversation->phone_number,
            'last_bot_message_at' => $conversation->last_bot_message_at,
        ]);

        // ✅ Vérifier qu'on a le numéro de téléphone
        if (empty($conversation->phone_number)) {
            Log::warning("⚠️ Pas de numéro pour conversation #{$conversation->id}");
            $this->markFollowUpAsSent($conversation); // Pour éviter de boucler
            return;
        }

        // ✅ Récupérer la config WhatsApp
        $config = DB::connection('temp')
            ->table('whatsapp_configurations')
            ->where('projet_id', $conversation->projet_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$config) {
            Log::warning("⚠️ Config WhatsApp non trouvée pour projet #{$conversation->projet_id}");
            $this->markFollowUpAsSent($conversation);
            return;
        }

        // ✅ Générer le message de relance
        $message = $this->generateFollowUpMessage($conversation);

        if (empty($message)) {
            Log::warning("⚠️ Aucun message généré pour #{$conversation->id}");
            $this->markFollowUpAsSent($conversation);
            return;
        }

        // ✅ Envoyer via Twilio
        $twilio = new \Twilio\Rest\Client($config->account_sid, $config->access_token);

        $sentMessage = $twilio->messages->create(
            "whatsapp:" . $conversation->phone_number,
            [
                'from' => "whatsapp:" . $config->phone_number_id,
                'body' => $message
            ]
        );

        // ✅ Stocker dans whatsapp_messages
        DB::connection('temp')->table('whatsapp_messages')->insert([
            'projet_id' => $conversation->projet_id,
            'from_number' => $config->phone_number_id,
            'to_number' => $conversation->phone_number,
            'message' => $message,
            'message_sid' => $sentMessage->sid,
            'profile_name' => 'Agent Virtuel Karim',
            'status' => 'sent',
            'message_type' => 'follow_up',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // ✅ Ajouter à l'historique de la conversation
        $history = $conversation->history ?? [];
        $history[] = [
            'role' => 'assistant',
            'content' => $message,
            'timestamp' => now()->toDateTimeString(),
            'type' => 'follow_up'
        ];

        // ✅ Marquer le follow-up comme envoyé (SANS utiliser les méthodes du modèle)
        $this->markFollowUpAsSent($conversation, $history);

        Log::info("✅ Follow-up envoyé", [
            'id' => $conversation->id,
            'phone' => $conversation->phone_number,
            'message_sid' => $sentMessage->sid,
        ]);
    }

    /**
     * 🔥 MARQUER LE FOLLOW-UP COMME ENVOYÉ (SANS toucher au modèle)
     */
    private function markFollowUpAsSent(Conversation $conversation, $history = null)
    {
        try {
            $updateData = [
                'follow_up_sent_at' => now(),
                'follow_up_scheduled' => false,
                'updated_at' => now(),
            ];

            if ($history !== null) {
                $updateData['history'] = json_encode($history);
            }

            DB::connection('temp')
                ->table('conversations')
                ->where('id', $conversation->id)
                ->update($updateData);

            Log::info("✅ Follow-up marqué comme envoyé", [
                'id' => $conversation->id,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Erreur markFollowUpAsSent: " . $e->getMessage());
        }
    }

    /**
     * 🔥 GÉNÉRER LE MESSAGE DE RELANCE (style )
     */
    private function generateFollowUpMessage(Conversation $conversation): string
    {
        $state = $conversation->state ?? [];
        $history = $conversation->history ?? [];

        // ✅ Récupérer le dernier message user pour détecter la langue
        $lastUserMessage = '';
        foreach (array_reverse($history) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastUserMessage = $msg['content'] ?? '';
                break;
            }
        }

        $lang = $this->detectLanguage($lastUserMessage);
        $prenom = $state['name'] ?? $state['prenom'] ?? null;
        $prenomText = $prenom ? " {$prenom}" : "";
        $propertyType = $state['property_type'] ?? null;

        if ($lang === 'fr') {
            return $this->getFrenchFollowUp($prenomText, $propertyType);
        } elseif ($lang === 'en') {
            return $this->getEnglishFollowUp($prenomText, $propertyType);
        } else {
            return $this->getDarijaFollowUp($prenomText, $propertyType);
        }
    }

    /**
 * 🔥 RETOURNER "Bonjour" ou "Bonsoir" SELON L'HEURE
 */
private function getGreeting(): string
{
    $hour = (int) now()->format('H');

    // ✅ Entre 6h et 18h → Bonjour
    // ✅ Entre 18h et 6h → Bonsoir
    if ($hour >= 6 && $hour < 18) {
        return 'Bonjour';
    }

    return 'Bonsoir';
}

/**
 * 🔥 RETOURNER "Salam" (darija) - Toujours identique
 */
private function getGreetingDarija(): string
{
    return 'Salam';
}

/**
 * 🔥 RETOURNER "Hello" (anglais) - Toujours identique
 */
private function getGreetingEnglish(): string
{
    $hour = (int) now()->format('H');

    if ($hour >= 6 && $hour < 18) {
        return 'Good morning';
    }

    return 'Good evening';
}
   private function getFrenchFollowUp($prenomText, $propertyType)
{
    $greeting = $this->getGreeting(); // ✅ Bonjour ou Bonsoir

    if ($propertyType) {
        return "{$greeting}{$prenomText} 😊\n\n" .
               "Nous restons à votre disposition pour finaliser votre projet immobilier.\n\n" .
               "Vous êtes intéressé par un **{$propertyType}** au projet GreenLand.\n\n" .
               "Souhaitez-vous poursuivre votre recherche ou planifier une visite avec l'un de nos conseillers ?\n\n";
    }

    return "{$greeting}{$prenomText} 😊\n\n" .
           "Nous restons à votre disposition pour finaliser votre projet immobilier.\n\n" .
           "Souhaitez-vous poursuivre votre recherche ou planifier une visite avec l'un de nos conseillers ?\n\n";
}
   private function getEnglishFollowUp($prenomText, $propertyType)
{
    $greeting = $this->getGreetingEnglish(); // ✅ Good morning ou Good evening

    if ($propertyType) {
        return "{$greeting}{$prenomText} 😊\n\n" .
               "We remain at your disposal to finalize your real estate project.\n\n" .
               "You are interested in a **{$propertyType}** in the GreenLand project.\n\n" .
               "Would you like to continue your search or schedule a visit with one of our advisors?\n\n";
    }

    return "{$greeting}{$prenomText} 😊\n\n" .
           "We remain at your disposal to finalize your real estate project.\n\n" .
           "Would you like to continue your search or schedule a visit with one of our advisors?\n\n";
}

    private function getDarijaFollowUp($prenomText, $propertyType)
{
    // ✅ En darija, on utilise toujours "Salam"
    $greeting = 'Salam';

    if ($propertyType) {
        return "{$greeting}{$prenomText} 😊\n\n" .
               "Baqin m3ak bach nkmlo l'projet immobilier dyalk.\n\n" .
               "Kenti m'htam b **{$propertyType}** f projet GreenLand.\n\n" .
               "Wach baghi tkml l'recherche wla t'planifier visite m3a wa7ed mn l'conseillers dialna ?\n\n";
    }

    return "{$greeting}{$prenomText} 😊\n\n" .
           "Baqin m3ak bach nkmlo l'projet immobilier dyalk.\n\n" .
           "Wach baghi tkml l'recherche wla t'planifier visite m3a wa7ed mn l'conseillers dialna ?\n\n";
}

    private function detectLanguage(string $message): string
    {
        $message = strtolower(trim($message));

        if (preg_match('/^(bonjour|salut|hello|merci)/i', $message)) return 'fr';
        if (preg_match('/^(hello|hi|thank)/i', $message)) return 'en';
        if (preg_match('/^(salam|slm|marhba)/i', $message)) return 'darija';

        $frWords = ['je', 'vous', 'nous', 'le', 'la', 'les', 'un', 'une', 'bonjour', 'merci', 'prix'];
        $darijaWords = ['bghit', 'brit', 'wach', 'chno', 'chhal', 'fin', 'kayn', 'mzyan'];

        $frCount = 0;
        $darijaCount = 0;

        foreach ($frWords as $word) {
            if (strpos($message, $word) !== false) $frCount++;
        }
        foreach ($darijaWords as $word) {
            if (strpos($message, $word) !== false) $darijaCount++;
        }

        return $darijaCount > $frCount ? 'darija' : 'fr';
    }
}
