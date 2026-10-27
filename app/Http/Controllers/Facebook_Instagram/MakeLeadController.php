<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use App\Models\Prospect;
use App\Models\Notification;
use App\Events\NotificationEvent;
use App\Models\Societe;
use App\Models\StatutProspect;
use App\Models\Source;
use App\Models\User;
use App\Http\Helpers\NotificationHelper;

class MakeLeadController extends Controller
{
    public function store(Request $request)
    {
        Log::info('=========================================');
        Log::info('📨 MAKE LEAD RECEIVED');
        Log::info('=========================================');
        Log::info('📦 Payload:', $request->all());

        // ✅ 1. Vérifier la clé API (sécurité)
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey !== env('MAKE_API_KEY')) {
            Log::warning('❌ Invalid API key');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ✅ 2. Récupérer les données
        $pageId    = $request->input('page_id');
        $full_name = $request->input('full_name');
        $email     = $request->input('email');
        $phone     = $request->input('phone');
        $type_bien = $request->input('type_bien');
        $budget    = $request->input('budget');
        $residence = $request->input('residence');
        $ad_id     = $request->input('ad_id');
        $ad_name   = $request->input('ad_name');
        $form_id   = $request->input('form_id');
        $lead_id   = $request->input('lead_id');
        $projet_id = $request->input('projet_id');

        // ✅ 3. Configurer la connexion temp
        $this->configureTempConnection();

        // ✅ 4. Ila projet_id ma3tich, jibo mn page_id
        if (!$projet_id) {
            $projet_id = $this->getProjetIdFromPageId($pageId);
        }

        // ✅ 5. Vérifier wach prospect deja kayn
        $existingProspect = $this->findExistingProspect($email, $phone, $projet_id);

        if ($existingProspect) {
            Log::info('📝 Updating existing prospect', ['id' => $existingProspect->id]);
            $prospect = $this->updateProspect($existingProspect, [
                'full_name' => $full_name,
                'email'     => $email,
                'phone'     => $phone,
                'type_bien' => $type_bien,
                'budget'    => $budget,
                'residence' => $residence,
                'facebook_lead_id' => $lead_id,
                'ad_id'     => $ad_id,
                'ad_name'   => $ad_name,
                'form_id'   => $form_id,
            ]);
        } else {
            Log::info('📝 Creating new prospect');
            $prospect = $this->createProspect([
                'full_name' => $full_name,
                'email'     => $email,
                'phone'     => $phone,
                'type_bien' => $type_bien,
                'budget'    => $budget,
                'residence' => $residence,
                'facebook_lead_id' => $lead_id,
                'ad_id'     => $ad_id,
                'ad_name'   => $ad_name,
                'form_id'   => $form_id,
                'projet_id' => $projet_id,
                'origin'    => 'facebook',
            ]);

            if ($prospect) {
                $this->createProspectStatus($prospect->id, $projet_id);
            }
        }

        if (!$prospect) {
            return response()->json(['error' => 'Failed to create prospect'], 500);
        }

        Log::info('✅ Make lead processed', ['prospect_id' => $prospect->id]);

        return response()->json([
            'success' => true,
            'prospect_id' => $prospect->id
        ], 200);
    }

    // ============================================
    // ✅ MÉTHODES COPIÉES MN FacebookAdWebhookController
    // ============================================

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

    private function configureDatabaseForSociete($societeId)
    {
        try {
            $databaseName = env('DB_DATABASE');
            Log::info("Configuring database connection for société {$societeId}: {$databaseName}");
            $baseConfig = config('database.connections.mysql');
            $baseConfig['database'] = $databaseName;
            config(['database.connections.temp' => $baseConfig]);
            DB::purge('temp');
            DB::reconnect('temp');
            $actualDbName = DB::connection('temp')->getDatabaseName();
            Log::info("Successfully connected to database: {$actualDbName}");
        } catch (\Exception $e) {
            Log::error("Error configuring database for société {$societeId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function findSocieteByPageId($pageId)
    {
        try {
            $this->configureTempConnection();
            $societes = Societe::all();
            Log::info("Searching for page ID: {$pageId} across " . $societes->count() . " sociétés");

            foreach ($societes as $societe) {
                try {
                    $this->configureDatabaseForSociete($societe->id);
                    if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                        $facebookMatch = DB::connection('temp')
                            ->table('facebook_configurations')
                            ->where('page_fcb_id', $pageId)
                            ->whereNull('deleted_at')
                            ->exists();

                        if ($facebookMatch) {
                            Log::info("MATCH FOUND! Page ID '{$pageId}' found in société {$societe->id}");
                            return $societe->id;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("Error checking société {$societe->id}: " . $e->getMessage());
                    continue;
                }
            }
            Log::warning("No société found for page ID: {$pageId}");
            return null;
        } catch (\Exception $e) {
            Log::error("Error finding société for page ID {$pageId}: " . $e->getMessage());
            return null;
        }
    }

    private function getProjetIdFromPageId($pageId)
    {
        try {
            $this->configureTempConnection();

            if (!$pageId) {
                Log::warning('⚠️ No page ID provided');
                return 2;
            }

            if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                Log::warning('Table facebook_configurations not found in temp connection');
                if (Schema::hasTable('facebook_configurations')) {
                    $config = DB::table('facebook_configurations')
                        ->where('page_fcb_id', $pageId)
                        ->whereNull('deleted_at')
                        ->first();
                    if ($config) {
                        return $config->projet_id;
                    }
                }
                return 2;
            }

            $config = DB::connection('temp')
                ->table('facebook_configurations')
                ->where('page_fcb_id', $pageId)
                ->whereNull('deleted_at')
                ->first();

            if ($config) {
                Log::info('✅ Project found for page ID', [
                    'page_id' => $pageId,
                    'projet_id' => $config->projet_id,
                ]);
                return $config->projet_id;
            }

            if (strpos($pageId, '_') !== false) {
                $parts = explode('_', $pageId);
                $cleanPageId = $parts[0];
                $config = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('page_fcb_id', $cleanPageId)
                    ->whereNull('deleted_at')
                    ->first();
                if ($config) {
                    return $config->projet_id;
                }
            }

            Log::warning('⚠️ No project found for page ID, using default: ' . $pageId);
            return 2;
        } catch (\Exception $e) {
            Log::error('Error getting project ID: ' . $e->getMessage());
            return 2;
        }
    }

    private function findExistingProspect($email, $phone, $projet_id)
    {
        try {
            $this->configureTempConnection();

            if (empty($email) && empty($phone)) {
                return null;
            }

            $query = Prospect::on('temp')
                ->where('projet_id', $projet_id)
                ->whereNull('deleted_at');

            if ($email) {
                $query->where('email', $email);
            }
            if ($phone) {
                $query->orWhere('telephone', $phone);
                $query->orWhere('telephone_num2', $phone);
            }

            return $query->first();
        } catch (\Exception $e) {
            Log::error('Error finding existing prospect: ' . $e->getMessage());
            return null;
        }
    }

    private function createProspect($data)
    {
        try {
            $this->configureTempConnection();

            $sourceId = null;
            $source = Source::on('temp')
                ->whereIn('source', ['Facebook', 'facebook'])
                ->first();
            if ($source) {
                $sourceId = $source->id;
            }

            $prospect = new Prospect();
            $prospect->setConnection('temp');
            $prospect->nom = $data['full_name'] ?? 'Prospect Facebook';
            $prospect->projet_id = $data['projet_id'];
            $prospect->origin = 'facebook';
            $prospect->prenom = $data['prenom'] ?? null;
            $prospect->email = $data['email'] ?? null;
            $prospect->telephone = $data['phone'] ?? null;
            $prospect->telephone_num2 = $data['phone2'] ?? null;
            $prospect->message = $data['message'] ?? null;
            $prospect->ville = $data['ville'] ?? null;
            $prospect->etat = 1;
            $prospect->facebook_id = $data['facebook_id'] ?? null;
            $prospect->facebook_lead_id = $data['facebook_lead_id'] ?? null;
            $prospect->facebook_ad_id = $data['ad_id'] ?? null;
            $prospect->facebook_ad_name = $data['ad_name'] ?? null;
            $prospect->facebook_form_id = $data['form_id'] ?? null;

            if (isset($data['type_bien'])) $prospect->type_bien = $data['type_bien'];
            if (isset($data['budget'])) $prospect->budget = $data['budget'];
            if (isset($data['residence'])) $prospect->residence = $data['residence'];

            $prospect->source = $sourceId;
            $prospect->notifie = 0;
            $prospect->created_at = now();
            $prospect->updated_at = now();
            $prospect->save();

            Log::info('✅ Prospect created successfully', ['id' => $prospect->id]);
            return $prospect;
        } catch (\Exception $e) {
            Log::error('Error creating prospect: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    private function updateProspect($prospect, $data)
    {
        try {
            $this->configureTempConnection();

            if (isset($data['full_name']) && !empty($data['full_name'])) $prospect->nom = $data['full_name'];
            if (isset($data['email']) && !empty($data['email'])) $prospect->email = $data['email'];
            if (isset($data['phone']) && !empty($data['phone'])) $prospect->telephone = $data['phone'];
            if (isset($data['phone2']) && !empty($data['phone2'])) $prospect->telephone_num2 = $data['phone2'];
            if (isset($data['message']) && !empty($data['message'])) $prospect->message = $data['message'];
            if (isset($data['ville']) && !empty($data['ville'])) $prospect->ville = $data['ville'];
            if (isset($data['type_bien'])) $prospect->type_bien = $data['type_bien'];
            if (isset($data['budget'])) $prospect->budget = $data['budget'];
            if (isset($data['residence'])) $prospect->residence = $data['residence'];
            if (isset($data['facebook_lead_id'])) $prospect->facebook_lead_id = $data['facebook_lead_id'];
            if (isset($data['ad_id'])) $prospect->facebook_ad_id = $data['ad_id'];
            if (isset($data['ad_name'])) $prospect->facebook_ad_name = $data['ad_name'];
            if (isset($data['form_id'])) $prospect->facebook_form_id = $data['form_id'];

            $prospect->updated_at = now();
            $prospect->save();

            Log::info('✅ Prospect updated successfully', ['id' => $prospect->id]);
            return $prospect;
        } catch (\Exception $e) {
            Log::error('Error updating prospect: ' . $e->getMessage());
            return null;
        }
    }

    private function createProspectStatus($prospectId, $projetId)
    {
        try {
            $this->configureTempConnection();

            $existingStatus = StatutProspect::on('temp')
                ->where('prospect_id', $prospectId)
                ->whereNull('deleted_at')
                ->first();

            if ($existingStatus) {
                Log::info('⚠️ Status already exists', ['prospect_id' => $prospectId]);
                return $existingStatus;
            }

            $this->autoAssignSingleProspect($prospectId, $projetId);

            $statutProspect = StatutProspect::on('temp')
                ->where('prospect_id', $prospectId)
                ->whereNull('deleted_at')
                ->orderBy('id', 'desc')
                ->first();

            return $statutProspect;
        } catch (\Exception $e) {
            Log::error('Error in createProspectStatus: ' . $e->getMessage());
            return null;
        }
    }

    private function autoAssignSingleProspect($prospectId, $projetId)
    {
        try {
            Log::info('🔄 Starting auto-assignment for prospect', [
                'prospect_id' => $prospectId,
                'projet_id' => $projetId
            ]);

            $this->configureTempConnection();

            $prospect = Prospect::on('temp')->find($prospectId);
            if (!$prospect) {
                Log::error('❌ Prospect not found for auto-assignment', ['prospect_id' => $prospectId]);
                return false;
            }

            $commercials = User::on('temp')
                ->where(function($query) use ($projetId) {
                    $query->whereHas('projets', function($q) use ($projetId) {
                        $q->where('projet_id', $projetId);
                    });
                })
                ->where('role', 3)
                ->where('is_actif', 1)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            if ($commercials->isEmpty()) {
                Log::warning('⚠️ No active commercials found', ['projet_id' => $projetId]);
                return false;
            }

            if ($commercials->count() === 1) {
                $targetCommercial = $commercials->first();
            } else {
                $allHaveLastAffected = true;
                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $allHaveLastAffected = false;
                        break;
                    }
                }

                if ($allHaveLastAffected) {
                    User::on('temp')
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->update(['last_affected' => 0]);

                    $commercials = User::on('temp')
                        ->where(function($query) use ($projetId) {
                            $query->whereHas('projets', function($q) use ($projetId) {
                                $q->where('projet_id', $projetId);
                            });
                        })
                        ->where('role', 3)
                        ->where('is_actif', 1)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get();
                }

                $targetCommercial = null;
                foreach ($commercials as $commercial) {
                    if ($commercial->last_affected == 0) {
                        $targetCommercial = $commercial;
                        break;
                    }
                }

                if (!$targetCommercial) {
                    $targetCommercial = $commercials->first();
                }
            }

            $systemUser = User::on('temp')
                ->where('role', 1)
                ->whereNull('deleted_at')
                ->first();

            DB::connection('temp')->beginTransaction();

            try {
                $newCommercialId = $targetCommercial->id;

                $prospect->commercial_affecte = $newCommercialId;
                if ($systemUser) {
                    $prospect->affecte_par_admin_id = $systemUser->id;
                }
                $prospect->date_affectation = Carbon::now();
                $prospect->save();

                $statutProspect = new StatutProspect();
                $statutProspect->setConnection('temp');
                $statutProspect->prospect_id = $prospectId;
                $statutProspect->statut = '6';
                $statutProspect->date_traitement = Carbon::now();
                $statutProspect->user_id_traite = $systemUser ? $systemUser->id : null;
                $statutProspect->commentaire = 'Prospect affecté automatiquement via Make';
                $statutProspect->type_traitement_rdv_relance = 0;
                $statutProspect->created_at = now();
                $statutProspect->updated_at = now();
                $statutProspect->save();

                $commercialUser = $targetCommercial;
                $oldCount = $commercialUser->nb_prospects ?? 0;
                $commercialUser->nb_prospects = $oldCount + 1;
                $commercialUser->last_affected = 1;
                $commercialUser->save();

                User::on('temp')
                    ->where('id', '!=', $newCommercialId)
                    ->where('role', 3)
                    ->where('is_actif', 1)
                    ->whereNull('deleted_at')
                    ->update(['last_affected' => 0]);

                DB::connection('temp')->commit();

                $this->sendAffectationNotification($newCommercialId, $prospectId, $projetId);

                Log::info('✅ Auto-assignment completed successfully', [
                    'prospect_id' => $prospectId,
                    'commercial_id' => $newCommercialId
                ]);

                return true;

            } catch (\Exception $e) {
                DB::connection('temp')->rollBack();
                Log::error('❌ Auto-assignment transaction failed: ' . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            Log::error('❌ Auto-assignment failed: ' . $e->getMessage());
            return false;
        }
    }

    private function sendAffectationNotification($commercialId, $prospectId, $projetId)
    {
        try {
            $this->configureTempConnection();

            $commercial = User::on('temp')->find($commercialId);
            if (!$commercial) {
                Log::warning('Commercial not found for notification', ['commercial_id' => $commercialId]);
                return;
            }

            $prospect = Prospect::on('temp')->find($prospectId);
            $prospectName = $prospect ? trim(($prospect->nom ?? '') . ' ' . ($prospect->prenom ?? '')) : 'Nouveau prospect';
            if (empty($prospectName)) {
                $prospectName = 'Nouveau prospect';
            }

            $data_notif = [
                'lien'        => '/crm/prospects/' . $prospectId,
                'date'        => Carbon::now(),
                'type'        => 53,
                'user_id'     => $commercial->user_id_origin,
                'description' => "Un nouveau prospect '{$prospectName}' vous a été affecté via Make (Facebook Ads)",
                'projet_id'   => $projetId,
                'prospect_id' => $prospectId,
            ];

            $notif_helper = new NotificationHelper();
            $notif_helper->storeNotification(new Request($data_notif));

            try {
                Config::set('broadcasting.default', 'pusher_notify');
                broadcast(new NotificationEvent($commercial->user_id_origin));
            } catch (\Exception $e) {
                Log::warning('Broadcast failed but notification was stored: ' . $e->getMessage());
            }

            Log::info('✅ Affectation notification sent', [
                'commercial_id' => $commercialId,
                'prospect_id' => $prospectId,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur envoi notification affectation: ' . $e->getMessage());
        }
    }
}
