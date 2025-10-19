<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
//composer require guzzlehttp/guzzle===>required
use App\Http\Helpers\NotificationHelper;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\FacebookMessage;
use App\Http\Helpers\DatabaseHelper;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Auth;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Societe;
use Intervention\Image\Facades\Image;
use App\Http\Requests\StoreSocialNetworkRequest;
use App\Http\Helpers\RoleHelper;


class Facebook_InstagramController extends Controller
{



        //get posts https://graph.facebook.com/v22.0/537798629425112/feed?access_token=EAAI3GumKq0oBOy1YU24xu5XOm048xx7RpSW1uvmY6zsQuwWmjiyKlZAIVFoGACdo5SXFbuZCE8n3B0cER55C82wSvadDbfEqUnVcjfftR0SZCWhBesqVXYqAtRez7ZA8rbYXUKcSo1jl3QZCVDezLxN8FH5gbk5yaIQBYe8240g2jkEdj5pNR2vkT850PbZBl0hlbRj1Eaas0XkU2uuRSTUZBw9&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors
        //pour commenter /***"https://graph.facebook.com/{page-post-id}/comments?message=I%20want%20chocolate%20cake%20! &access_token=page-access-token"  */
        //get comments           //https://graph.facebook.com/v22.0/{page-post-id}537798629425112_122104722890793117/comments?access_token=EAAI3GumKq0oBO3e3PWinEHAOpbupHdC115jYneAbK2jWQsgAW0UfSj3da54JW9ZCZBfRKn6zm1lteZBzopLobZALsZBiHkdRPuqhFSfEjY1AxTwj8vkLeUO4rjiQpnAZBDshxdL8HmkwvSXFscFcLhe42G1DtQhD0RTRRVMhZCLgtHmBAVDw4UFFY46abpNsgVcp1fHLM8iZBLbRyzmCxt3ye08b&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors


        public function postTo_Social_Network(StoreSocialNetworkRequest $request){
                try {
                    $user = Auth::user();
                    DatabaseHelper::Config();
                    $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
                    $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
                    $societe = Societe::findOrfail($user_societes->societe_id);

                    $network = $request->reseaux_sociaux;
                    $mode = $request->mode;
                    $file = $request->file('mediaFile');
                    $description = $request->description;
                    $type_media = null;
                    $selectedNetworks = explode(',', $network);

                   // En mode parcourir, l'utilisateur sélectionne un fichier qui est ensuite uploadé dans le stockage. Après l'upload, on récupère son URL ainsi que son type (photo ou vidéo).                    if ($mode == 'parcourir') {
                    if ($mode == 'parcourir') {
                        if ($request->hasFile('mediaFile')) {

                            //get type file photos  or videos

                           $mimeType = $request->file('mediaFile')->getMimeType(); // Get MIME type of file

                           if (str_starts_with($mimeType, 'image/')) {
                                $text = 'photos'; // File is a photo
                                $type_media='image_url';
                           } elseif (str_starts_with($mimeType, 'video/')) {
                                $text = 'videos'; // File is a video
                                $type_media='video_url';
                           } else {
                                $text = 'unknown'; // Neither image nor video
                           }


                               // Get the uploaded file
                           $fileName = $file->getClientOriginalName();
                           $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram');
                           //  $directory = public_path('docs/' . 'societe_principal' . '_' . 10 . '/upload_fcb_instagram');
                           File::makeDirectory($directory, 0755, true, true);
                           $file->move($directory, $fileName);
                               // Generate the file URL
                           $fileUrl = asset('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram/' . $fileName);
                           //$url=str_replace('\/\/', '/', $fileUrl);
                           //https://immogestion.online/coline_dev/storage/reservations/10.png
                           //https://immogestion.online/coline_dev/storage/reservations/11.png
                           //https://v.ftcdn.net/01/75/19/28/700_F_175192845_cRe1fUwouwX7vF3GJpRGwACZjl8CC1We_ST.mp4
                           $url=str_replace('\/\/', '/', $fileUrl);
                       }
                    }
                    /* 1 ==> WhatsApp, 2 ==> Instagram, 3 ==> Facebook */
                    if (in_array(3, $selectedNetworks)) {

                        $config = $this->getFacebookConfigForCurrentUser($request->projet_id);
                        if (!$config) {
                            throw new \Exception('Facebook configuration not found for project ID: ' . $request->projet_id);
                        }
                        $pageId = $config->page_fcb_id;
                        $accessToken = $config->acces_token_page;
                        if ($mode == 'existante') {
                            $url = str_replace('\/\/', '/', $request->img_existant_url);
                            $text = 'photos';
                        }

                        $data = [
                            'pageId_InstagramId' => $pageId,
                            'caption' => $description,
                            'text' => $text,
                        //  'url' => $url, // Use dynamic URL instead of hardcoded
                            'url'=> str_replace('\/\/', '/', 'https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg'),
                            'network' => 'facebook',
                            'accessToken' => $accessToken
                        ];

                            $response = $this->store($request->merge($data));
                            return $response;
                    }

                    // REPLACE the hardcoded Instagram section:
                    if (in_array(2, $selectedNetworks)) {
                        // REPLACE with:
                        $config = $this->getInstagramConfigForCurrentUser($request->projet_id);

                        if (!$config) {
                            throw new \Exception('Instagram configuration not found for project ID: ' . $request->projet_id);
                        }
                        $pageId = $config->instagram_id;
                        $accessToken = $config->acces_token_user;

                        if ($mode == 'existante') {
                            $url = $request->img_existant_url;
                            $type_media = 'image_url';
                        }

                        $data = [
                            'pageId_InstagramId' => $pageId,
                            'caption' => $request->description,
                            'text' => 'media',
                            'type_media' => $type_media,
                            'url'=> str_replace('\/\/', '/', 'https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg'),
                        // 'url' => $url, // Use dynamic URL instead of hardcoded
                            'network' => 'instagram',
                            'accessToken' => $accessToken
                        ];
                        $response = $this->store($request->merge($data));
                            return $response;
                    }

                    if (in_array(1, $selectedNetworks)) {
                        $instanceId =env('INSTANCE_ID_ULTRA_MSG');  // Replace with your instance ID
                        $token = env('TOKEN_ULTRA_MSG');  // Replace with your token
                        $to = $request->phoneNumber;  // Ensure $request contains phoneNumber
                        $description = $request->description;  // Ensure $description is set from the request
                        $mode = $request->mode;  // Ensure $mode is set from the request

                        if ($mode != "null") {
                            // Send image
                            // Make sure the image URL and description are correct
                            $response = Http::timeout(60)->post("https://api.ultramsg.com/$instanceId/messages/image", [
                                'token' => $token,
                                'to' => $to,
                                'image' => $url,
                                'caption' => $description,
                            ]);

                            return response()->json($response->json());  // Return the API response as JSON
                        } /*else {
                            // Send text message
                            $response = Http::timeout(60)->post("https://api.ultramsg.com/$instanceId/messages/chat", [
                                'token' => $token,
                                'to' => $to,
                                'body' => $description,  // Text message content
                            ]);

                            return response()->json($response->json());  // Return the API response as JSON
                        }*/
                    }

                    // Only return invalid if no valid networks were processed
                    if (!array_intersect($selectedNetworks, [1, 2, 3])) {
                        return response()->json(['success' => false, 'message' => 'Invalid social network selection'], 400);
                    }


                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
                }
        }

        private function getFacebookConfigForCurrentUser($projetId = null)
        {
            try {
                $user = Auth::user();

                if (!$projetId) {
                    Log::warning("No project ID provided for Facebook configuration retrieval");
                    return null;
                }

                  // Get user's accessible projects to ensure they have permission
                $userProjects = $this->getUserAccessibleProjects($user);
                $projectIds = $userProjects->pluck('projet_id')->toArray();

                if (!in_array($projetId, $projectIds)) {
                    Log::warning("User {$user->id} does not have access to project {$projetId}");
                    return null;
                }

                // Get Facebook configuration for the specific project
                $config = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('projet_id', $projetId)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$config) {
                    Log::info("No Facebook configuration found for project {$projetId}");
                }

                return $config;

            } catch (\Exception $e) {
                Log::error("Error getting Facebook config for project {$projetId}: " . $e->getMessage());
                return null;
            }
        }

        private function getInstagramConfigForCurrentUser($projetId = null)
        {
            try {
                $user = Auth::user();

                if (!$projetId) {
                    Log::warning("No project ID provided for Instagram configuration retrieval");
                    return null;
                }

            // Get user's accessible projects to ensure they have permission
                $userProjects = $this->getUserAccessibleProjects($user);

                $projectIds = $userProjects->pluck('projet_id')->toArray();
                if (!in_array($projetId, $projectIds)) {
                    Log::warning("User {$user->id} does not have access to project {$projetId}");
                    return null;
                }

                // Get Instagram configuration for the specific project
                $config = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('projet_id', $projetId)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$config) {
                    Log::info("No Instagram configuration found for project {$projetId}");
                }

                return $config;

            } catch (\Exception $e) {
                Log::error("Error getting Instagram config for project {$projetId}: " . $e->getMessage());
                return null;
            }
        }

        private function getUserAccessibleProjects($user)
        {
            try {


                $tempUser = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->first();

                // Determine société id to use for filtering. Prefer temp user mapping if available.
               /* $societeId = null;
                if ($tempUser && isset($tempUser->societe_id)) {
                    $societeId = $tempUser->societe_id;
                } elseif (isset($user->societe_id)) {
                    $societeId = $user->societe_id;
                }*/
                // Build query against tenant projets table
                $query = DB::connection('temp')->table('user_projets')->where('user_id',$tempUser->id)
                    ->whereNull('deleted_at');

                // If user is not super admin, filter by their société (use resolved societeId)
                /*if (isset($user->role) && $user->role != 1) { // Not super admin
                    if ($societeId) {

                        $query->where('societe_id', $societeId);
                    } else {

                        // If we can't resolve a société, return empty collection to avoid leaking data
                        return collect();
                    }
                }*/


                return $query->get();
            } catch (\Exception $e) {
                Log::error("Error getting user accessible projects: " . $e->getMessage());
                return collect(); // Return empty collection on error
            }
        }



         public function store(Request $request)
            {
                $pageIdInstagramId = $request->pageId_InstagramId;
                $accessToken = $request->accessToken;
                $network = $request->network;
                $text = $request->text;
                $url = str_replace('\/\/', '/', $request->url);
                $caption = $request->caption;

                $client = new Client([
                    'timeout'  => 60.0, // Increase to 60 seconds
                ]);
                $apiUrl = "https://graph.facebook.com/v22.0/{$pageIdInstagramId}/{$text}";

                // Set request parameters
                $params = [];
                    // Verify that URL is publicly accessible
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid or inaccessible image URL'
                        ], 400);
                    }
                if ($network == 'facebook') {
                    if ($text == 'videos') {
                        $params = [
                            'multipart' => [
                                [
                                    'name' => 'description',
                                    'contents' => $caption,
                                ],
                                [
                                    'name' => 'title',
                                    'contents' => $caption,
                                ],
                                [
                                    'name' => 'file_url',
                                    'contents' =>$url,
                                ],
                                [
                                    'name' => 'access_token',
                                    'contents' => $accessToken,
                                ],
                            ],
                        ];
                    } elseif ($text == 'photos') {
                        $params = [
                            'json' => [
                                'caption' => $caption,
                                'url' =>$url ,
                                'access_token' => $accessToken,
                            ]
                        ];
                    }
                } elseif ($network == 'instagram') {
                    // Step 1: Upload the media
                    if ($request->type_media == 'video_url') {
                        $params = [
                            'json' => [
                                'media_type' => 'REELS',
                                'caption' => $caption,
                                'video_url' => $url,
                                'access_token' => $accessToken,
                            ]
                        ];
                    } else {
                        $params = [
                            'json' => [
                                'caption' => $caption,
                                'image_url' => $url,
                                'access_token' => $accessToken,
                            ]
                        ];
                    }
                }

                try {
                    // Send POST request to create the post
                    $response = $client->post($apiUrl, $params);

                    $responseBody = json_decode($response->getBody(), true);
                    $statusCode = $response->getStatusCode();

                    // Return the actual response data
                /*  return response()->json([
                        'success' => $statusCode >= 200 && $statusCode < 300,
                        'status_code' => $statusCode,
                        'data' => $responseBody,
                        'network' => $network
                    ]);*/
                    if (isset($responseBody['id'])) {
                        // Instagram requires a second step to publish the media

                        if ($network == 'instagram') {
                            $mediaId = $responseBody['id'];
                                // Step 2: Poll Media Status (Wait Until "FINISHED")
                                $maxAttempts = 10;
                                $attempt = 0;
                                do {
                                    sleep(5); // Wait 5 seconds before checking status
                                    $statusUrl = "https://graph.facebook.com/v22.0/{$mediaId}?fields=status_code&access_token={$accessToken}";
                                    $statusResponse = $client->get($statusUrl);
                                    $statusBody = json_decode($statusResponse->getBody(), true);

                                    if ($statusBody['status_code'] == "FINISHED") {
                                        break;
                                    }

                                    $attempt++;
                                } while ($attempt < $maxAttempts);

                                if ($statusBody['status_code'] !== "FINISHED") {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'Media processing not completed',
                                        'status' => $statusBody
                                    ], 500);
                                }
                                // step 3 ==> do media publish
                            $publishUrl = "https://graph.facebook.com/v22.0/{$pageIdInstagramId}/media_publish";
                            $publishParams = [
                                'json' => [
                                    'creation_id' => $responseBody['id'],
                                    'access_token' => $accessToken,
                                ]
                            ];

                            $publishResponse = $client->post($publishUrl, $publishParams);
                            $publishResponseBody = json_decode($publishResponse->getBody(), true);

                            if (isset($publishResponseBody['id'])) {
                                return response()->json([
                                    'success' => true,
                                    'message' => 'Post successfully published on Instagram!',
                                    'post_id' => $publishResponseBody['id'],
                                    'network' => 'instagram',
                                    'url' => $publishResponseBody['id']
                                ], 200);
                            } else {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Failed to publish the Instagram post.',
                                    'error' => $publishResponseBody
                                ], 500);
                            }
                        }

                        return response()->json([
                            'success' => true,
                            'message' => 'Post created successfully!',
                            'post_id' => $responseBody['id'],
                            'network' => $network,  // Could be 'facebook' or 'instagram'
                            'url' => $url  // URL of the media post
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to create post',
                            'error' => $responseBody
                        ], 500);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: ' . $e->getMessage()
                    ], 500);
                }
            }


        /******************************Webhook Configuration*************************/


        // Modified verify method to properly connect to tenant databases
        public function verify(Request $request)
        {
            try {
                $hub_mode = $request->hub_mode;
                $hub_challenge = $request->hub_challenge;
                $hub_verify_token = $request->hub_verify_token;

                Log::info("Facebook Webhook verification attempt", [
                    'mode' => $hub_mode,
                    'verify_token' => $hub_verify_token,
                    'challenge' => $hub_challenge
                ]);

                if ($hub_mode === 'subscribe') {
                    // First check environment fallback (fastest)
                    $fallback_token = env('FACEBOOK_WEBHOOK_VERIFY_TOKEN');
                    if ($fallback_token && $fallback_token === $hub_verify_token) {
                        Log::info("Using fallback environment token");
                        return response($hub_challenge, 200);
                    }

                    // Get all webhook tokens from all sociétés at once
                    $validTokens = $this->getAllWebhookTokens();

                    if (in_array($hub_verify_token, $validTokens)) {
                        Log::info("Valid webhook token found");
                        return response($hub_challenge, 200);
                    }

                    Log::warning("No matching webhook verify token found");
                    return response('Forbidden', 403);
                }

                Log::warning("Invalid hub_mode received: " . $hub_mode);
                return response('Bad Request', 400);

            } catch (\Exception $e) {
                Log::error("Facebook webhook verification error: " . $e->getMessage());
                return response('Internal Server Error', 500);
            }
        }

    /**
     * Get all webhook tokens from all sociétés efficiently
     */
        private function getAllWebhookTokens()
        {
            $tokens = [];

            try {
                // Force connection to main database first
                config(['database.connections.temp' => config('database.connections.mysql')]);
                DB::purge('temp');
                DB::reconnect('temp');

                $societes = \App\Models\Societe::all();
                Log::info("Checking " . $societes->count() . " sociétés for webhook tokens");

                foreach ($societes as $societe) {
                    try {
                        $this->configureDatabaseForSociete($societe);

                        // Get Facebook webhook tokens
                        if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                            $facebookTokens = DB::connection('temp')
                                ->table('facebook_configurations')
                                ->whereNotNull('webhook_verify_token')
                                ->whereNull('deleted_at')
                                ->pluck('webhook_verify_token')
                                ->toArray();

                            $tokens = array_merge($tokens, $facebookTokens);
                            Log::info("Found " . count($facebookTokens) . " Facebook tokens for société {$societe->id}");
                        }

                        // Get Instagram webhook tokens
                        if (Schema::connection('temp')->hasTable('instagram_configurations')) {
                            $instagramTokens = DB::connection('temp')
                                ->table('instagram_configurations')
                                ->whereNotNull('webhook_verify_token')
                                ->whereNull('deleted_at')
                                ->pluck('webhook_verify_token')
                                ->toArray();

                            $tokens = array_merge($tokens, $instagramTokens);
                            Log::info("Found " . count($instagramTokens) . " Instagram tokens for société {$societe->id}");
                        }

                    } catch (\Exception $e) {
                        Log::warning("Error checking société {$societe->id}: " . $e->getMessage());
                        continue;
                    }
                }

                // Remove duplicates and empty values
                $tokens = array_unique(array_filter($tokens));
                Log::info("Total unique webhook tokens found: " . count($tokens));

                return $tokens;

            } catch (\Exception $e) {
                Log::error("Error getting webhook tokens: " . $e->getMessage());
                return [];
            }
        }

        /**
         * Find which société has a configuration for the given page ID - OPTIMIZED
         */
        private function findSocieteByPageId($pageId)
        {
            try {
                // Force connection to main database first
                config(['database.connections.temp' => config('database.connections.mysql')]);
                DB::purge('temp');
                DB::reconnect('temp');

                $societes = \App\Models\Societe::all();
                Log::info("Searching for page ID: {$pageId} across " . $societes->count() . " sociétés");

                foreach ($societes as $societe) {
                    try {
                        $this->configureDatabaseForSociete($societe);

                        // Check Facebook configurations
                        if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                            $facebookMatch = DB::connection('temp')
                                ->table('facebook_configurations')
                                ->where('page_fcb_id', $pageId)
                                ->whereNull('deleted_at')
                                ->exists();

                            if ($facebookMatch) {
                                Log::info("MATCH FOUND! Page ID '{$pageId}' found in société {$societe->id} Facebook configuration");
                                return $societe->id;
                            }
                        }

                        // Check Instagram configurations
                        if (Schema::connection('temp')->hasTable('instagram_configurations')) {
                            $instagramMatch = DB::connection('temp')
                                ->table('instagram_configurations')
                                ->where('instagram_id', $pageId)
                                ->whereNull('deleted_at')
                                ->exists();

                            if ($instagramMatch) {
                                Log::info("MATCH FOUND! Page ID '{$pageId}' found in société {$societe->id} Instagram configuration");
                                return $societe->id;
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error("Error checking société {$societe->id} for page {$pageId}: " . $e->getMessage());
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

        /**
         * Manually configure database connection for a specific société
         */
        private function configureDatabaseForSociete($societe)
        {
            try {
                // Build the database name based on société information
                $raison_sociale_concatene = $societe->raison_sociale_concatene ??
                                        str_replace(' ', '', $societe->raison_sociale ?? 'Societe' . $societe->id);

                $databaseName = "Erp_" . $raison_sociale_concatene . "_" . $societe->id;

                Log::info("Configuring database connection for société {$societe->id}: {$databaseName}");

                // Get the base database configuration
                $baseConfig = config('database.connections.mysql');

                // Override the database name
                $baseConfig['database'] = $databaseName;

                // Update the temp connection configuration
                config(['database.connections.temp' => $baseConfig]);

                // Purge and reconnect
                DB::purge('temp');
                DB::reconnect('temp');

                // Verify the connection
                $actualDbName = DB::connection('temp')->getDatabaseName();
                Log::info("Successfully connected to database: {$actualDbName}");

                if ($actualDbName !== $databaseName) {
                    Log::warning("Database name mismatch! Expected: {$databaseName}, Actual: {$actualDbName}");
                }

            } catch (\Exception $e) {
                Log::error("Error configuring database for société {$societe->id}: " . $e->getMessage());
                throw $e;
            }
        }

        public function handleWebhook(Request $request)
        {
            try {
                Log::info('Facebook webhook received:', $request->all());
                $objectType = $request->input('object'); // Get the object type: 'page facebook' or 'instagram'  for messages
                $entries = $request->input('entry', []);

                foreach ($entries as $entry) {
                    $pageId = $entry['id'] ?? null;

                    if (!$pageId) {
                        Log::warning('No page ID found in webhook entry', ['entry' => $entry]);
                        continue;
                    }

                    $societeId = $this->findSocieteByPageId($pageId);

                    if (!$societeId) {
                        Log::warning("No société configuration found for page ID: {$pageId}");
                        continue;
                    }

                    $this->configureDatabaseForSociete(\App\Models\Societe::find($societeId));

                    // REPLACE this old logic:
                    // $config = ConfigurationSocialNetwork::on('temp')->first();
                    // if (!$config || !$config->webhook_enabled) {

                    // WITH this new logic:
                    $webhookEnabled = $this->isWebhookEnabledForPage($pageId);

                    if (!$webhookEnabled) {
                        Log::info("Webhook received but webhooks are disabled for société {$societeId}");
                        continue;
                    }




                    // Handle messaging events based on object type
                    if (isset($entry['messaging'])) {
                        foreach ($entry['messaging'] as $messaging) {
                            if ($objectType === 'instagram') {
                                // Instagram messaging (direct messages)
                                $projet_id=$this->getProjet_id_from_page_id($pageId,'instagram');
                                $this->handleInstagramMessaging($messaging, $societeId, $pageId,$projet_id);
                            } else if ($objectType === 'page') {
                                // Facebook messaging (direct messages)
                                $projet_id=$this->getProjet_id_from_page_id($pageId,'facebook');
                                $this->handleFacebookMessages($messaging, $societeId, $pageId,$projet_id);
                            }
                        }
                    }else{
                        // Handle changes events (comments, mentions, posts)
                        foreach ($entry['changes'] ?? [] as $change) {
                            $this->processChange($change, $societeId,$pageId);
                        }
                    }

                }

                return response()->json(['message' => 'Webhook processed successfully']);

            } catch (\Exception $e) {
                Log::error('Error processing Facebook webhook: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->all()
                ]);

                return response()->json(['message' => 'Webhook received'], 200);
            }
        }

        /**
         * Check if webhook is enabled for a specific page ID
         */
        private function isWebhookEnabledForPage($pageId)
        {
            try {
                // Check Facebook configurations
                if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                    $facebookEnabled = DB::connection('temp')
                        ->table('facebook_configurations')
                        ->where('page_fcb_id', $pageId)
                        ->where('webhook_enabled', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($facebookEnabled) {
                        return true;
                    }
                }

                // Check Instagram configurations
                if (Schema::connection('temp')->hasTable('instagram_configurations')) {
                    $instagramEnabled = DB::connection('temp')
                        ->table('instagram_configurations')
                        ->where('instagram_id', $pageId)
                        ->where('webhook_enabled', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($instagramEnabled) {
                        return true;
                    }
                }

                return false;

            } catch (\Exception $e) {
                Log::error("Error checking webhook status for page {$pageId}: " . $e->getMessage());
                return false;
            }
        }
          private function getProjet_id_from_page_id($pageId,$platform)
        {
            try {
                // Check Facebook configurations
                if($platform=='facebook'){
                    if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                        $facebook = DB::connection('temp')
                            ->table('facebook_configurations')
                            ->where('page_fcb_id', $pageId)
                            ->where('webhook_enabled', true)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($facebook!=null) {
                            return $facebook->projet_id;
                        }
                     }
                }else{
                    // Check Instagram configurations
                                    if (Schema::connection('temp')->hasTable('instagram_configurations')) {
                                        $instagram = DB::connection('temp')
                                            ->table('instagram_configurations')
                                            ->where('instagram_id', $pageId)
                                            ->where('webhook_enabled', true)
                                            ->whereNull('deleted_at')
                                            ->first();

                                        if ($instagram) {
                                            return $instagram->projet_id;
                                        }
                                    }
                }





            } catch (\Exception $e) {
                Log::error("Error checking webhook status for projet {$projet_id}: " . $e->getMessage());
                return false;
            }
        }

        private function processChange($change, $societeId,$pageId)
        {
            $platform = $this->detectPlatform($change);
            $type = $this->getEventType($change);
            $field = $change['field'] ?? null;

            Log::info("Processing Event - Platform: $platform, Type: $type, Field: $field, Société: $societeId,pageId: $pageId");

            // Store event in database for the specific société
            Config::set('broadcasting.default', 'pusher_3');

            try {
                $web = new WebhookEvent();
                $web->setConnection('temp');
                $web->platform = $platform;
                $web->type = $type;
                $web->data = $change;
                $web->save();

                broadcast(new NotificationEvent(0));
                Log::info("Webhook event saved successfully for société {$societeId}");

            } catch (\Exception $e) {
                Log::error("Error saving webhook event for société {$societeId}: " . $e->getMessage());
            }
            //store prospect get projet_id
               $projet_id=$this->getProjet_id_from_page_id($pageId,$platform);
            //Direct routing for Instagram comments
            if ($field == 'comments' && $platform === 'instagram') {
                Log::info('Direct routing Instagram comment');
                $this->handleInstagramComment($change,$pageId);
                return;
            }

            switch ($field) {

                case 'feed':
                    $this->handleFeedEvent($change['value'] ?? $change,$projet_id);
                    break;
                case 'mentions':
                    $this->handleInstagramMention($change['value'],$projet_id);
                    break;
                case 'mention':
                    $this->handleFacebookMention($change['value'],$projet_id);
                    break;

                default:
                    Log::warning('Unhandled Webhook Event: ' . $field);
            }
             //store new Prospect
        }
        private function handleFeedEvent($data,$projet_id)
        {
            Log::info('Processing feed event:', $data);

            // Check the 'item' field to determine the type of feed event
            $item = $data['item'] ?? null;
            $verb = $data['verb'] ?? null;

            switch ($item) {
                case 'reaction':
                    if ($verb === 'remove') {
                        Log::info('Ignoring reaction removal event:', $data);
                        return;
                    }
                    $this->handleFacebookReaction($data,$projet_id);
                    break;

                case 'comment':
                    // ONLY handle comments here, ignore posts when it's actually a comment
                    if (isset($data['comment_id']) && $verb === 'add') {
                        $this->handleFacebookComment($data,$projet_id);
                    }
                    break;

                case 'post':
                    // ONLY handle posts here, but check if it's actually a comment in disguise
                    if (isset($data['comment_id'])) {
                        // This is actually a comment event disguised as a post
                        Log::info('Detected comment event disguised as post, skipping post handling');
                        return;
                    }

                    if (isset($data['post_id']) && $verb === 'add') {
                        // Additional check to ensure it's really a post and not a comment
                        if (!isset($data['comment_id']) && !isset($data['parent_id'])) {
                            $this->handleFacebookPost($data,$projet_id);
                        }
                    } else {
                        $this->handleInstagramPost($data,$projet_id);
                    }
                    break;

                default:
                 Log::info('Unknown feed event type:', $data);
                    /* Handle cases where the structure might be different
                    if (isset($data['comment_id']) && $verb === 'add') {
                        // This is definitely a comment
                        $this->handleFacebookComment($data);
                    } elseif (isset($data['post_id']) && !isset($data['comment_id']) && $verb === 'add') {
                        // This is definitely a post (has post_id but no comment_id)
                        $this->handleFacebookPost($data);
                    } elseif (isset($data['text']) && isset($data['media']['id'])) {
                        $this->handleInstagramComment(['value' => $data]);
                    } elseif (isset($data['reaction_type']) && $verb === 'add') {
                        $this->handleFacebookReaction($data);
                    } else {
                        Log::info('Unknown feed event type:', $data);
                    }*/
            }
        }

        private function handleFacebookPost($data,$projet_id)
        {
            Log::info('New Post/comment on Facebook Page:', $data);

            try {
                // Check if this is a mention or a post on the page
                if (isset($data['from']['name'])) {
                    $userName = $data['from']['name'];
                    $message = $data['message'] ?? '';

                    // Determine if it's a mention or post
                    $description = !empty($message)
                        ? "{$userName} a publié sur votre page : " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
                        : "{$userName} a publié sur votre page Facebook";

                    // Get post link if available
                    $postLink = isset($data['post_id']) ? "https://www.facebook.com/{$data['post_id']}" : null;

                    $this->createFacebookNotification($description, $postLink, \App\Enum\TypeNotificationEnum::FacebookPublication->value,$projet_id);

                }

            } catch (\Exception $e) {
                Log::error('Error handling Facebook post: ' . $e->getMessage());
            }
        }

        private function handleFacebookComment($data,$projet_id)
        {
            Log::info('New Comment on Facebook Page:', $data);

            try {
                // Extract comment information
                $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
                $message = $data['message'] ?? '';
                $postId = $data['post_id'] ?? null;
                $commentId = $data['comment_id'] ?? null;

                // Create notification description
                $description = "{$userName} a commenté votre publication Facebook";
                if (!empty($message)) {
                    $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
                }

                // Get post/comment link if available
                $link = null;
                if ($postId && $commentId) {
                    $link = "https://www.facebook.com/{$postId}?comment_id={$commentId}";
                } elseif ($postId) {
                    $link = "https://www.facebook.com/{$postId}";
                }


                $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookComment->value,$projet_id);

            } catch (\Exception $e) {
                Log::error('Error handling Facebook comment: ' . $e->getMessage());
            }
        }

        private function handleFacebookReaction($data,$projet_id)
        {
            Log::info('New Reaction on Facebook Page:', $data);

            try {
                // Extract reaction information
                $reactionType = $data['reaction_type'] ?? 'unknown';
                $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
                $postId = $data['post_id'] ?? null;
                $verb = $data['verb'] ?? 'add'; // 'add' or 'remove'

                // Only create notification for new reactions (not removals)
                if ($verb === 'add') {
                    // Create notification description
                    $description = match($reactionType) {
                        'like' => "{$userName} a aimé votre publication Facebook",
                        'love' => "{$userName} adore votre publication Facebook",
                        'wow' => "{$userName} trouve votre publication Facebook impressionnante",
                        'haha' => "{$userName} trouve votre publication Facebook amusante",
                        'sad' => "{$userName} trouve votre publication Facebook triste",
                        'angry' => "{$userName} est en colère contre votre publication Facebook",
                        'care' => "{$userName} se soucie de votre publication Facebook",
                        default => "{$userName} a réagi à votre publication Facebook"
                    };

                    // Get post link if available
                    $postLink = $postId ? "https://www.facebook.com/{$postId}" : null;

                    $this->createFacebookNotification($description, $postLink,null,$projet_id);
                } else {
                    Log::info("Reaction removed by {$userName}, not creating notification");
                }

            } catch (\Exception $e) {
                Log::error('Error handling Facebook reaction: ' . $e->getMessage());
            }
        }

        // Add a new method to create Facebook notifications
        private function createFacebookNotification($description, $link = null, $type = null,$projet_id)
        {
            try {
                Log::info('Creating Facebook notification:', [
                    'description' => $description,
                    'link' => $link,
                    'type' => $type
                ]);
                // Create notification using the notification model
                $notification = new \App\Models\Notification();
                $notification->setConnection('temp');

                // Set required fields
                $notification->date = now()->format('Y-m-d H:i:s');
                $notification->type = $type ; // Default to 98
                $notification->description_type = $description;
                $notification->lien = $link ?? 'https://www.facebook.com';

                // Set role to admin so all users can see it
                $notification->role = \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value;
                $notification->projet_id = $projet_id;
                $notification->save();

                // Broadcast the notification
                Config::set('broadcasting.default', 'pusher_3');
                broadcast(new \App\Events\NotificationEvent($notification->id));

                Log::info('Facebook reaction notification created successfully', [

                    'notification_id' => $notification->id,
                    'type' => $type,
                    'description' => $description
                ]);


            } catch (\Exception $e) {
                Log::error('Error creating social media notification: ' . $e->getMessage());
            }
        }

        // Handle Instagram comments
        private function handleInstagramComment($data,$pageId)
        {
            Log::info('Processing Instagram comment:', $data);

            try {
                // Extract comment information - handle different data structures

                    $commentData = $data['value']??$data;


                $userName = $commentData['from']['username'] ?? $commentData['from']['name'] ?? 'Utilisateur inconnu';
                $message = $commentData['text'] ?? $commentData['message'] ?? '';
                $mediaId = $commentData['media']['id'] ?? null;
                $commentId = $commentData['id'] ?? null;

                Log::info("Instagram comment extracted data:", [
                    'userName' => $userName,
                    'message' => $message,
                    'mediaId' => $mediaId,
                    'commentId' => $commentId
                ]);

                // Create notification description
                $description = "{$userName} a commenté votre publication Instagram";
                if (!empty($message)) {
                    $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
                }
                //get accesToken of instagram
                $instagram = DB::connection('temp')
                        ->table('instagram_configurations')
                        ->where('instagram_id', $pageId)
                        ->whereNull('deleted_at')
                        ->first();

                            // Fetch the permalink from Instagram Graph API
                                $response = Http::get("https://graph.facebook.com/v22.0/{$mediaId}", [
                                    'fields' => 'permalink',
                                    'access_token' =>$instagram->acces_token_user
                                ]);

                                if ($response->successful()) {
                                    $permalink = $response->json()['permalink'] ?? null;
                                    $this->createFacebookNotification($description, $permalink, \App\Enum\TypeNotificationEnum::InstagramComment->value,$projet_id);
                                }
                Log::info('Instagram comment notification created successfully');

            } catch (\Exception $e) {
                Log::error('Error handling Instagram comment: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
            }
        }

        // Handle Instagram posts/publications
        private function handleInstagramPost($data,$projet_id)
        {
            Log::info('Processing Instagram post:', $data);

            try {
                $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
                $mediaId = $data['media']['id'] ?? null;

                $description = "Nouvelle publication Instagram de {$userName}";

                // Get Instagram post link if available
                $link = null;
                if ($mediaId) {
                    $link = "https://www.instagram.com/p/{$mediaId}";
                }

                $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::InstagramPublication->value,$projet_id);

            } catch (\Exception $e) {
                Log::error('Error handling Instagram post: ' . $e->getMessage());
            }
        }

        // Handle Instagram reactions/likes (from messaging)
        private function handleInstagramReaction($data)
        {
            Log::info('Processing Instagram reaction:', $data);

            try {
                $senderId = $data['sender']['id'] ?? 'Utilisateur inconnu';
                $reaction = $data['reaction'] ?? [];
                $action = $reaction['action'] ?? '';
                $reactionType = $reaction['reaction'] ?? '';
                $emoji = $reaction['emoji'] ?? '';

                // Only create notification for new reactions (not unreact)
                if ($action === 'react') {
                    $description = match($reactionType) {
                        'like' => "Quelqu'un a aimé votre message Instagram",
                        'love' => "Quelqu'un adore votre message Instagram ❤️",
                        'wow' => "Quelqu'un trouve votre message Instagram impressionnant",
                        'haha' => "Quelqu'un trouve votre message Instagram amusant",
                        default => "Quelqu'un a réagi à votre message Instagram" . ($emoji ? " {$emoji}" : "")
                    };

                    $this->createFacebookNotification($description, 'https://www.instagram.com', \App\Enum\TypeNotificationEnum::InstagramReaction->value);
                } else {
                    Log::info("Instagram reaction removed, not creating notification");
                }

            } catch (\Exception $e) {
                Log::error('Error handling Instagram reaction: ' . $e->getMessage());
            }
        }

        // Handle Instagram mentions
        private function handleInstagramMention($data,$projet_id)
        {
            Log::info('Processing Instagram mention:', $data);

            try {
                $mediaId = $data['media_id'] ?? null;
                $commentId = $data['comment_id'] ?? null;

                if ($commentId) {
                    // Mention in a comment
                    $description = "Vous avez été mentionné dans un commentaire Instagram";
                    $link = $mediaId ? "https://www.instagram.com/p/{$mediaId}" : 'https://www.instagram.com';
                } else {
                    // Mention in a caption
                    $description = "Vous avez été mentionné dans une publication Instagram";
                    $link = $mediaId ? "https://www.instagram.com/p/{$mediaId}" : 'https://www.instagram.com';
                }

                $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::InstagramMention->value,$projet_id);

            } catch (\Exception $e) {
                Log::error('Error handling Instagram mention: ' . $e->getMessage());
            }
        }

        // Handle Facebook mentions
        private function handleFacebookMention($data,$projet_id)
        {
            Log::info('Processing Facebook mention:', $data);

            try {
                $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
                $message = $data['message'] ?? '';
                $postId = $data['post_id'] ?? null;
                $commentId = $data['comment_id'] ?? null;

                $description = "Vous avez été mentionné par {$userName} sur Facebook";
                if (!empty($message)) {
                    $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
                }

                // Get Facebook post/comment link if available
                $link = null;
                if ($postId && $commentId) {
                    $link = "https://www.facebook.com/{$postId}?comment_id={$commentId}";
                } elseif ($postId) {
                    $link = "https://www.facebook.com/{$postId}";
                }

                $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookMention->value,$projet_id);

            } catch (\Exception $e) {
                Log::error('Error handling Facebook mention: ' . $e->getMessage());
            }
        }





            /**
             * Send Facebook message from page to user
             */
            /*/When user sends first message: auto-ask for phone number.
            If phone exists: notify commercial "ancien prospect vous a contacté".
            Else: store number and confirm.
            If no phone in message: keep asking every time. and send notif to commercial / when he writes it then the message of write your number not showing again */
        private function handleFacebookMessages($messaging, $societeId = null, $pageId = null, $projet_id)
        {
            Log::info('Processing Facebook direct message:', ['messaging' => $messaging]);

            try {
                // Store webhook event
                $web = new WebhookEvent();
                $web->setConnection('temp');
                $web->platform = 'facebook';
                $web->type = 'facebook_messaging';
                $web->data = $messaging;
                if ($pageId) {
                    $web->page_id = $pageId;
                }
                $web->save();

                broadcast(new NotificationEvent(0));

                $senderId = $messaging['sender']['id'] ?? null;
                $message = $messaging['message']['text'] ?? '';
                $timestamp = $messaging['timestamp'] ?? null;
                $messageId = $messaging['mid'] ?? null;

                // Get sender name using Graph API
                $senderName = 'Utilisateur inconnu';
                if ($senderId && $pageId) {
                    $senderName = $this->getFacebookUserName($senderId, $pageId);
                }

                Log::info('Extracted Facebook message details:', [
                    'senderName' => $senderName,
                    'senderId' => $senderId,
                    'message' => $message,
                    'messageId' => $messageId,
                    'timestamp' => $timestamp
                ]);

                // Check if message contains a phone number
                $phoneNumber = $this->extractPhoneNumber($message);

                if ($senderId && $pageId) {
                    if ($phoneNumber) {
                        // Check if phone number already exists for another prospect
                        $Duplicate_Prospect= $this->isPhoneNumberDuplicate($phoneNumber, $senderId,$projet_id);

                                if ($Duplicate_Prospect!=null) {
                                    // Phone number exists - ask for different number
                                    Log::info("Duplicate phone number detected", [
                                        'sender_id' => $senderId,
                                        'phone_number' => $phoneNumber,
                                        'prospect_id'=>$Duplicate_Prospect->id
                                    ]);
                                    $notif_helper = new NotificationHelper();
                                    $req = new \Illuminate\Http\Request();

                                    $notif_helper->storeNotification($req->merge([
                                        'lien'        => '/crm/prospects/' . $Duplicate_Prospect->id,
                                        'date'        => Carbon::now(),
                                        'type'        => 102,
                                        'description'        => 'le Propect '.$Duplicate_Prospect->nom.' vous avez contacté sur Facebook',
                                        'user_id'     => null,
                                        'role'        =>  \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value,
                                        'prospect_id' => $Duplicate_Prospect->id,
                                        'projet_id'   => $projet_id,
                                    ]));
                                }
                            // User sent a valid and unique phone number - update prospect
                            $updateSuccess = $this->updateProspectWithPhoneNumber($senderName, $phoneNumber, $societeId, $projet_id, $senderId);

                            if ($updateSuccess) {
                                // Send confirmation message
                                $confirmationMessage = "✅ Merci ! Votre numéro de téléphone {$phoneNumber} a été enregistré avec succès. Nous vous contacterons bientôt !";
                                $this->sendFacebookMessageFromPage($senderId, $confirmationMessage, $pageId);
                            } else {
                                // Error updating prospect
                                $errorMessage = "❌ Désolé, une erreur s'est produite lors de l'enregistrement de votre numéro. Veuillez réessayer.";
                                $this->sendFacebookMessageFromPage($senderId, $errorMessage, $pageId);
                            }
                        //}

                    } else {
                        // Check if we already asked for phone number (to avoid infinite loop)
                       // $alreadyAsked = $this->hasAskedForPhoneRecently($senderId);
                        //!alreadyAsked
                    if ($this->isFirstMessageFromUser($senderId)) {
                            // First message from user - ask for phone number
                            $welcomeMessage = "Bonjour {$senderName} ! 👋\n\nMerci de nous avoir contactés. Pour mieux vous assister, pourriez-vous nous partager votre numéro de téléphone ?\n\n📱 Format accepté: 06XXXXXXXX ou +2126XXXXXXXX";

                            $messageSent = $this->sendFacebookMessageFromPage($senderId, $welcomeMessage, $pageId);

                            if ($messageSent) {
                              //  $this->markAsAskedForPhone($senderId);
                              //send notif to commercial
                                $description = "Le prospect {$senderName} n'a pas fourni son numéro de téléphone sur Facebook. Contactez-le pour obtenir ses coordonnées.";

                                $notification = new \App\Models\Notification();
                                $notification->setConnection('temp');

                                // Set required fields
                                $notification->date = now()->format('Y-m-d H:i:s');
                                $notification->type = \App\Enum\TypeNotificationEnum::FacebookMessage->value;
                                $notification->description_type = $description;
                                $notification->lien = "https://www.facebook.com/$pageId";

                                // Set role to commercial (adjust role value as needed)
                                $notification->role = \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value; // Assuming 3 is commercial role
                                $notification->projet_id = $projet_id;
                                $notification->save();

                                // Broadcast the notification
                                Config::set('broadcasting.default', 'pusher_3');
                                broadcast(new \App\Events\NotificationEvent($notification->id));
                                Log::info("fadwaa {$senderId}");
                            } else {
                                Log::error("Failed to send phone request to user {$senderId}");
                            }
                      }

                    }
                }

                // Create notification description
                $description = "Nouveau message Facebook de {$senderName}";
                if (!empty($message)) {
                    $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
                }

                // Generate Facebook message link
                $link = null;
                if ($senderId) {
                    $link = "https://www.facebook.com/{$pageId}";
                }

                $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookMessage->value, $projet_id);


                Log::info('Facebook message processing completed');

            } catch (\Exception $e) {
                Log::error('Error handling Facebook message: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
            }
        }
        /**
         * Extract phone number from message text
         */
        private function extractPhoneNumber($message)
        {
            // Remove all non-digit characters except + sign
            $cleaned = preg_replace('/[^\d+]/', '', $message);

            // Moroccan phone number patterns
            $patterns = [
                '/^(?:\+212|0)([5-7]\d{8})$/', // Moroccan format: +2126xxxxxxxx or 06xxxxxxxx
                '/^[5-7]\d{8}$/', // Just the 10 digits starting with 5,6,7
                '/^0[5-7]\d{8}$/', // Starting with 0
                '/^\+212[5-7]\d{8}$/', // Starting with +212
                '/^00212[5-7]\d{8}$/', // Starting with 00212
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $cleaned)) {
                    // Format to standard Moroccan format: +2126xxxxxxxx
                    if (strlen($cleaned) === 10 && in_array($cleaned[0], ['5', '6', '7'])) {
                        return '+212' . $cleaned;
                    } elseif (strlen($cleaned) === 9 && in_array($cleaned[0], ['5', '6', '7'])) {
                        return '+212' . $cleaned;
                    } elseif (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                        return '+212' . substr($cleaned, 1);
                    } elseif (str_starts_with($cleaned, '00212')) {
                        return '+' . substr($cleaned, 2);
                    }
                    return $cleaned;
                }
            }

            return null;
        }
        /**
         * Check if phone number already exists for another user
         */
        private function isPhoneNumberDuplicate($phoneNumber, $currentSenderId,$projet_id)
        {
            try {
                // Normalize phone number for comparison
                $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

                // Check if phone number exists for any other prospect (excluding current user)
                $existingProspect = \App\Models\Prospect::on('temp')
                ->where('projet_id', $projet_id)
                    ->where('telephone', '!=', '')
                    ->whereNotNull('telephone')
                    ->where(function($query) use ($normalizedPhone) {
                        $query->where('telephone', $normalizedPhone)
                            ->orWhere('telephone', 'LIKE', '%' . substr($normalizedPhone, -9) . '%')
                            ->orWhere('telephone_num2', 'like', '%' . substr($normalizedPhone, -9) . '%');; // Last 9 digits
                    })
                    //->where('facebook_id', '!=', $currentSenderId)
                    ->first();

                if ($existingProspect) {
                    Log::warning("Duplicate phone number found", [
                        'phone_number' => $phoneNumber,
                        'normalized' => $normalizedPhone,
                        'existing_prospect_id' => $existingProspect->id,
                        'existing_prospect_name' => $existingProspect->nom,
                        'current_sender_id' => $currentSenderId
                    ]);

                    return $existingProspect;
                }

                return null;

            } catch (\Exception $e) {
                Log::error("Error checking phone number duplicate: " . $e->getMessage());
                return false; // On error, assume not duplicate to avoid blocking legitimate users
            }
        }

        /**
         * Normalize phone number for consistent comparison
         */
        private function normalizePhoneNumber($phoneNumber)
        {
            // Remove all non-digit characters except +
            $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

            // Convert to standard Moroccan format
            if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                return '+212' . substr($cleaned, 1);
            } elseif (str_starts_with($cleaned, '00212')) {
                return '+' . substr($cleaned, 2);
            } elseif (strlen($cleaned) === 9 && in_array($cleaned[0], ['5', '6', '7'])) {
                return '+212' . $cleaned;
            } elseif (strlen($cleaned) === 10 && in_array($cleaned[0], ['5', '6', '7'])) {
                return '+212' . $cleaned;
            }

            return $cleaned;
        }


        private function updateProspectWithPhoneNumber($senderName, $phoneNumber, $societeId, $projet_id, $senderId)
        {
            try {
                // Normalize phone number before storing
                $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

                // Find prospect by Facebook ID or name
                $prospect = \App\Models\Prospect::on('temp')
                    //->where('facebook_id', $senderId)
                    ->Where('nom', $senderName)
                    ->Where('projet_id', $projet_id)
                    ->first();

                if ($prospect) {
                    // Update existing prospefct
                    $prospect->telephone = $normalizedPhone;
                    $prospect->facebook_id = $senderId;
                    $prospect->save();

                    Log::info("Prospect phone number updated", [
                        'prospect_id' => $prospect->id,
                        'phone_number' => $normalizedPhone,
                        'facebook_id' => $senderId
                    ]);
                } else {
                    // Create new prospect with phone number
                    \App\Http\Controllers\Api\V1\ProspectController::Store_fcb_instagram(
                        "Nouveau prospect Facebook avec numéro: {$normalizedPhone}",
                        $senderName,
                        'facebook',
                        $societeId,
                        $projet_id,
                        $normalizedPhone,
                        $senderId
                    );

                    Log::info("New prospect created with phone number", [
                        'name' => $senderName,
                        'phone_number' => $normalizedPhone,
                        'facebook_id' => $senderId
                    ]);
                }

                return true;

            } catch (\Exception $e) {
                Log::error("Error updating prospect with phone number: " . $e->getMessage());
                return false;
            }
        }

        /*
          Check if we recently asked this user for phone number

        private function hasAskedForPhoneRecently($senderId)
        {
            try {
                // You might want to create a table to track this, or use cache
                // For simplicity, using cache with 1-hour expiration
                return cache()->has("asked_phone_{$senderId}");

            } catch (\Exception $e) {
                Log::error("Error checking if asked for phone: " . $e->getMessage());
                return false;
            }
        }*/

    /*
      Mark that we asked this user for phone number

        private function markAsAskedForPhone($senderId)
        {
            try {
                // Store in cache for 1 hour
                cache()->put("asked_phone_{$senderId}", true, 3600);

            } catch (\Exception $e) {
                Log::error("Error marking asked for phone: " . $e->getMessage());
            }
        }*/


     /* Check if this is the first message from user
     * You might need to implement more sophisticated logic based on your message history
     */
        private function isFirstMessageFromUser($senderId)
        {
            // For now, assume it's first message if we haven't stored their phone number
            // You can enhance this by checking your message history database
            $prospect = \App\Models\Prospect::on('temp')
                ->where('facebook_id', $senderId)
                ->orWhere(function($query) use ($senderId) {
                    $query->where('telephone', 'LIKE', '%' . substr($senderId, -6) . '%');
                })
                ->first();

            return !$prospect || empty($prospect->telephone);
        }
        private function sendFacebookMessageFromPage($recipientId, $message, $pageId)
        {
            try {
                $accessToken = $this->getAccessTokenForPage($pageId);

                if (!$accessToken) {
                    Log::error("No access token found for page ID: {$pageId}");
                    return false;
                }

                $client = new Client(['timeout' => 30.0]);

                // Use the page ID in the URL and page access token
                $response = $client->post("https://graph.facebook.com/v22.0/{$pageId}/messages", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'recipient' => ['id' => $recipientId],
                        'message' => ['text' => $message],
                        'messaging_type' => 'RESPONSE'
                    ],
                    'query' => [
                        'access_token' => $accessToken
                    ]
                ]);

                $responseBody = json_decode($response->getBody(), true);

                if (isset($responseBody['message_id'])) {
                    Log::info("Facebook message sent successfully from page", [
                        'page_id' => $pageId,
                        'recipient_id' => $recipientId,
                        'message_id' => $responseBody['message_id']
                    ]);
                    return true;
                } else {
                    Log::error("Failed to send Facebook message from page", $responseBody);
                    return false;
                }

            } catch (\Exception $e) {
                Log::error("Error sending Facebook message from page {$pageId} to {$recipientId}: " . $e->getMessage());
                return false;
            }
        }


        /**
         * Get Facebook user name using Graph API
         */
        private function getFacebookUserName($senderId, $pageId)
        {
            try {
                // Get access token for the page
                $accessToken = $this->getAccessTokenForPage($pageId);

                if (!$accessToken) {
                    Log::warning("No access token found for page ID: {$pageId}");
                    return 'Utilisateur Facebook';
                }

                // Make Graph API call to get user profile
                $response = Http::timeout(30)->get(
                    "https://graph.facebook.com/v22.0/{$senderId}",
                    [
                        'fields' => 'name,first_name,last_name',
                        'access_token' => $accessToken
                    ]
                );

                if ($response->successful()) {
                    $userData = $response->json();
                    Log::info("Facebook user data retrieved:", $userData);

                    return $userData['name'] ?? $userData['first_name'] ?? 'Utilisateur Facebook';
                } else {
                    Log::warning("Failed to get Facebook user data: " . $response->body());
                    return 'Utilisateur Facebook';
                }

            } catch (\Exception $e) {
                Log::error("Error getting Facebook user name for sender {$senderId}: " . $e->getMessage());
                return 'Utilisateur Facebook';
            }
        }

        /***********************************************Instagram Messaging ************************************* */
        // Fix the parameter name and variable usage
        private function handleInstagramMessaging($messaging, $societeId = null, $pageId = null,$projet_id)
        {
            Log::info('Processing Instagram direct message:', ['messaging' => $messaging]);

            try {
                // Extract message information from the messaging structure
                if (!$messaging) {
                    Log::error('Invalid Instagram messaging structure');
                    return;
                }

                // Store webhook event
                $web = new WebhookEvent();
                $web->setConnection('temp');
                $web->platform = 'instagram';
                $web->type = 'instagram_messaging';
                $web->data = $messaging;
                if ($pageId) {
                    $web->page_id = $pageId;
                }
                $web->save();

                broadcast(new NotificationEvent(0));

                $senderId = $messaging['sender']['id'] ?? null;
                $recipientId = $messaging['recipient']['id'] ?? null;
                $messageText = $messaging['message']['text'] ?? '';
                $messageId = $messaging['message']['mid'] ?? null;
                $timestamp = $messaging['timestamp'] ?? null;

                Log::info('Extracted Instagram message details:', [
                    'senderId' => $senderId,
                    'recipientId' => $recipientId,
                    'messageText' => $messageText,
                    'messageId' => $messageId,
                    'timestamp' => $timestamp
                ]);

                 // Get Instagram username using Graph API
                    $username = 'Utilisateur inconnu';
                    if ($senderId && $pageId) {
                        $username = $this->getInstagramUsername($senderId, $pageId);
                    }
                // Create notification description
                $description = "Nouveau message Instagram de @{$username}";
                if (!empty($messageText)) {
                    $description .= ": " . (strlen($messageText) > 50 ? substr($messageText, 0, 50) . '...' : $messageText);
                }
                $this->createFacebookNotification($description, 'https://www.instagram.com/direct/inbox/', \App\Enum\TypeNotificationEnum::InstagramMessage->value,$projet_id);
                //create Prospect
                 $existingProspect = \App\Models\Prospect::on('temp')
                ->where('nom', $username)
                ->where('projet_id', $projet_id)
                ->first();

                if (!$existingProspect) {

                    \App\Http\Controllers\Api\V1\ProspectController::Store_fcb_instagram($description, $username,'instagram', $societeId,$projet_id);
                    Log::info('Instagram message notification created successfully');
                }
            } catch (\Exception $e) {
                Log::error('Error handling Instagram message: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
            }
        }


                /**
         * Get Instagram username using Graph API

        * Get Instagram username using Graph API with better debugging
        */
        /**
         * Practical solution for Instagram username handling
         */
        private function getInstagramUsername($senderId, $instagramId)
        {
            // Instagram Graph API restrictions prevent getting usernames from webhooks
            // You can only identify users by their PSID and store them for future reference

            try {
                $accessToken = $this->getAccessTokenForPage($instagramId);

                if (!$accessToken) {
                    return 'utilisateur_instagram';
                }

                // Check if we've stored this user before
                $storedUser = $this->getStoredInstagramUser($senderId, $instagramId);
                if ($storedUser) {
                    return $storedUser;
                }

                // Try to get user info through Instagram Basic Display API
                // This requires user authorization and won't work for webhooks
                // So we fall back to a generic identifier

                // Generate a friendly identifier based on PSID
                $friendlyId = 'user_' . substr($senderId, -6); // Last 6 chars of PSID

                // Store for future reference
                $this->storeInstagramUser($senderId, $instagramId, $friendlyId);

                return $friendlyId;

            } catch (\Exception $e) {
                Log::error("Error in Instagram username handling: " . $e->getMessage());
                return 'utilisateur_instagram';
            }
        }

        /**
         * Store Instagram user mapping for future reference
         */
        private function storeInstagramUser($senderId, $instagramId, $username)
        {
            try {
                // Create a table to store Instagram user mappings if it doesn't exist
                if (!Schema::connection('temp')->hasTable('instagram_users')) {
                    Schema::connection('temp')->create('instagram_users', function (Blueprint $table) {
                        $table->id();
                        $table->string('sender_id');
                        $table->string('instagram_id');
                        $table->string('username')->nullable();
                        $table->text('last_message')->nullable();
                        $table->timestamps();

                        $table->unique(['sender_id', 'instagram_id']);
                    });
                }

                // Store or update the user mapping
                DB::connection('temp')->table('instagram_users')->updateOrInsert(
                    [
                        'sender_id' => $senderId,
                        'instagram_id' => $instagramId
                    ],
                    [
                        'username' => $username,
                        'updated_at' => now()
                    ]
                );

            } catch (\Exception $e) {
                Log::error("Error storing Instagram user: " . $e->getMessage());
            }
        }

        /**
         * Get stored Instagram user
         */
        private function getStoredInstagramUser($senderId, $instagramId)
        {
            try {
                if (Schema::connection('temp')->hasTable('instagram_users')) {
                    $user = DB::connection('temp')
                        ->table('instagram_users')
                        ->where('sender_id', $senderId)
                        ->where('instagram_id', $instagramId)
                        ->first();

                    return $user->username ?? null;
                }
            } catch (\Exception $e) {
                Log::error("Error getting stored Instagram user: " . $e->getMessage());
            }

            return null;
        }

        private function handleWhatsAppMessage($data)
        {
            Log::info('New WhatsApp Message:', $data);
        }
        private function getAccessTokenForPage($pageId)
        {
            // Check Facebook configurations
            try {
            if (Schema::connection('temp')->hasTable('facebook_configurations')) {
                $facebookConfig = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('page_fcb_id', $pageId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($facebookConfig) {
                    return $facebookConfig->acces_token_page;
                }
            }

            // Check Instagram configurations
            if (Schema::connection('temp')->hasTable('instagram_configurations')) {
                $instagramConfig = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('instagram_id', $pageId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($instagramConfig) {
                    return $instagramConfig->acces_token_user;
                }
            }

            Log::warning("No access token found for page ID: {$pageId}");
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting access token for page {$pageId}: " . $e->getMessage());
            return null;
        }
    }

    /******************************Facebook Configuration by Project*************************/

    public function facebook_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists first
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['configurations' => []], 200);
                }

                $configurations = DB::connection('temp')
                    ->table('facebook_configurations as fc')
                    ->leftJoin('projets as p', 'fc.projet_id', '=', 'p.id')
                    ->select('fc.*', 'p.nom as projet_nom')
                    ->whereNull('fc.deleted_at')
                    ->orderBy('fc.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'page_fcb_id' => $config->page_fcb_id,
                            'projet_id' => $config->projet_id,
                            'created_at' => $config->created_at,
                            'projet' => $config->projet_nom ? ['nom' => $config->projet_nom] : null
                        ];
                    });

                return response()->json(['configurations' => $configurations], 200);
            } catch (\Exception $e) {
                // If table doesn't exist, return empty array
                if (str_contains($e->getMessage(), "doesn't exist")) {
                    return response()->json(['configurations' => []], 200);
                }
                throw $e;
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_facebook_configuration(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    Schema::connection('temp')->create('facebook_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('page_fcb_id');
                        $table->longText('acces_token_page');
                        $table->unsignedBigInteger('projet_id');
                        $table->string('webhook_verify_token')->nullable();
                        $table->boolean('webhook_enabled')->default(false);
                        $table->json('webhook_subscriptions')->nullable();
                        $table->softDeletes();
                        $table->timestamps();

                        $table->foreign('projet_id')->references('id')->on('projets')->onDelete('cascade');
                        $table->unique(['projet_id', 'deleted_at'], 'unique_project_facebook_config');
                    });
                } else {
                    // Table exists, check if webhook columns exist
                    $columns = Schema::connection('temp')->getColumnListing('facebook_configurations');
                    if (!in_array('webhook_verify_token', $columns)) {
                        Schema::connection('temp')->table('facebook_configurations', function (Blueprint $table) {
                            $table->string('webhook_verify_token')->nullable();
                            $table->boolean('webhook_enabled')->default(false);
                            $table->json('webhook_subscriptions')->nullable();
                        });
                    }
                }

                $request->validate([
                    'page_fcb_id' => 'required|string',
                    'acces_token_page' => 'required|string',
                    'projet_id' => 'required|integer|exists:temp.projets,id'
                ]);

                // Check if configuration already exists for this project
                $existingConfig = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('projet_id', $request->projet_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingConfig) {
                    return response()->json([
                        'error' => 'Une configuration Facebook existe déjà pour ce projet'
                    ], 400);
                }

                // Insert new configuration (webhook explicitly disabled by default)
                $configId = DB::connection('temp')->table('facebook_configurations')->insertGetId([
                    'page_fcb_id' => $request->page_fcb_id,
                    'acces_token_page' => $request->acces_token_page,
                    'projet_id' => $request->projet_id,
                    'webhook_enabled' => false, // Explicitly set to false
                    'webhook_verify_token' => null,
                    'webhook_subscriptions' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'message' => 'Configuration Facebook enregistrée avec succès',
                    'configuration_id' => $configId
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update_facebook_configuration(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                // Validate required fields
                if (!$request->page_fcb_id || !$request->acces_token_page || !$request->projet_id) {
                    return response()->json(['error' => 'Tous les champs sont obligatoires'], 400);
                }

                // Check if configuration exists
                $exists = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                // Update configuration
                $updated = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $id)
                    ->update([
                        'page_fcb_id' => $request->page_fcb_id,
                        'acces_token_page' => $request->acces_token_page,
                        'projet_id' => $request->projet_id,
                        'updated_at' => now()
                    ]);

                if ($updated) {
                    return response()->json(['message' => 'Configuration mise à jour avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Aucune modification effectuée'], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_facebook_configuration(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                $deleted = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $id)
                    ->update(['deleted_at' => now()]);

                if ($deleted) {
                    return response()->json(['message' => 'Configuration supprimée avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de la suppression: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /******************************Instagram Configuration by Project*************************/

    public function instagram_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists first
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['configurations' => []], 200);
                }

                $configurations = DB::connection('temp')
                    ->table('instagram_configurations as ic')
                    ->leftJoin('projets as p', 'ic.projet_id', '=', 'p.id')
                    ->select('ic.*', 'p.nom as projet_nom')
                    ->whereNull('ic.deleted_at')
                    ->orderBy('ic.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'instagram_id' => $config->instagram_id,
                            'projet_id' => $config->projet_id,
                            'created_at' => $config->created_at,
                            'projet' => $config->projet_nom ? ['nom' => $config->projet_nom] : null

                        ];
                    });

                return response()->json(['configurations' => $configurations], 200);
            } catch (\Exception $e) {
                // If table doesn't exist, return empty array
                if (str_contains($e->getMessage(), "doesn't exist")) {
                    return response()->json(['configurations' => []], 200);
                }
                throw $e;
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_instagram_configuration(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    Schema::connection('temp')->create('instagram_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('instagram_id');
                        $table->longText('acces_token_user');
                        $table->unsignedBigInteger('projet_id');
                        $table->string('webhook_verify_token')->nullable();
                        $table->boolean('webhook_enabled')->default(false);
                        $table->json('webhook_subscriptions')->nullable();
                        $table->softDeletes();
                        $table->timestamps();

                        $table->foreign('projet_id')->references('id')->on('projets')->onDelete('cascade');
                        $table->unique(['projet_id', 'deleted_at'], 'unique_project_instagram_config');
                    });
                } else {
                    // Table exists, check if webhook columns exist
                    $columns = Schema::connection('temp')->getColumnListing('instagram_configurations');
                    if (!in_array('webhook_verify_token', $columns)) {
                        Schema::connection('temp')->table('instagram_configurations', function (Blueprint $table) {
                            $table->string('webhook_verify_token')->nullable();
                            $table->boolean('webhook_enabled')->default(false);
                            $table->json('webhook_subscriptions')->nullable();
                        });
                    }
                }

                $request->validate([
                    'instagram_id' => 'required|string',
                    'acces_token_user' => 'required|string',
                    'projet_id' => 'required|integer|exists:temp.projets,id'
                ]);

                // Check if configuration already exists for this project
                $existingConfig = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('projet_id', $request->projet_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingConfig) {
                    return response()->json([
                        'error' => 'Une configuration Instagram existe déjà pour ce projet'
                    ], 400);
                }

                // Insert new configuration (webhook explicitly disabled by default)
                $configId = DB::connection('temp')->table('instagram_configurations')->insertGetId([
                    'instagram_id' => $request->instagram_id,
                    'acces_token_user' => $request->acces_token_user,
                    'projet_id' => $request->projet_id,
                    'webhook_enabled' => false, // Explicitly set to false
                    'webhook_verify_token' => null,
                    'webhook_subscriptions' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'message' => 'Configuration Instagram enregistrée avec succès',
                    'configuration_id' => $configId
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function update_instagram_configuration(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                // Validate required fields
                if (!$request->instagram_id || !$request->acces_token_user || !$request->projet_id) {
                    return response()->json(['error' => 'Tous les champs sont obligatoires'], 400);
                }

                // Check if configuration exists
                $exists = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$exists) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                // Update configuration
                $updated = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $id)
                    ->update([
                        'instagram_id' => $request->instagram_id,
                        'acces_token_user' => $request->acces_token_user,
                        'projet_id' => $request->projet_id,
                        'updated_at' => now()
                    ]);

                if ($updated) {
                    return response()->json(['message' => 'Configuration mise à jour avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Aucune modification effectuée'], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_instagram_configuration(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                $deleted = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $id)
                    ->update(['deleted_at' => now()]);

                if ($deleted) {
                    return response()->json(['message' => 'Configuration supprimée avec succès'], 200);
                } else {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Erreur lors de la suppression: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /******************************Facebook Webhook Configuration by Project*************************/

    public function facebook_webhook_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['webhooks' => []], 200);
                }

                $webhooks = DB::connection('temp')
                    ->table('facebook_configurations as fc')
                    ->leftJoin('projets as p', 'fc.projet_id', '=', 'p.id')
                    ->select('fc.*', 'p.nom as projet_nom')
                    ->whereNull('fc.deleted_at')
                    ->whereNotNull('fc.webhook_verify_token')
                    ->orderBy('fc.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'page_fcb_id' => $config->page_fcb_id,
                            'projet_id' => $config->projet_id,
                            'webhook_verify_token' => $config->webhook_verify_token,
                            'webhook_enabled' => $config->webhook_enabled ?? false,
                            'webhook_subscriptions' => json_decode($config->webhook_subscriptions ?? '[]'),
                            'webhook_url' => (config('webhook.base_url') ?? config('app.url')) . '/api/webhookFcb_Insta',
                            'created_at' => $config->created_at,
                            'projet' => $config->projet_nom ? ['nom' => $config->projet_nom] : null
                        ];
                    });

                return response()->json(['webhooks' => $webhooks], 200);
            } catch (\Exception $e) {
                return response()->json(['webhooks' => []], 200);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_facebook_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                $request->validate([
                    'webhook_verify_token' => 'required|string'
                ]);

                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['error' => 'Configuration table not found'], 404);
                }

                $config = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                // Validate that basic Facebook configuration exists
                if (!$config->page_fcb_id || !$config->acces_token_page) {
                    return response()->json([
                        'error' => 'Configuration Facebook de base incomplète. Veuillez d\'abord configurer la page et le token d\'accès.'
                    ], 400);
                }

                // Store webhook configuration but keep it DISABLED by default
                DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => $request->webhook_verify_token,
                        'webhook_enabled' => false, // Explicitly set to false
                        'webhook_subscriptions' => json_encode(['feed', 'mention', 'messages']), // Valid Facebook webhook fields
                        'updated_at' => now()
                    ]);

                return response()->json([
                    'message' => 'Webhook Facebook configuré avec succès',
                    'webhook_enabled' => false // Return false to indicate it's disabled
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la configuration: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_facebook_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => null,
                        'webhook_enabled' => false,
                        'webhook_subscriptions' => null,
                        'updated_at' => now()
                    ]);

                return response()->json(['message' => 'Webhook supprimé avec succès'], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /******************************Instagram Webhook Configuration by Project*************************/

    public function instagram_webhook_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['webhooks' => []], 200);
                }

                $webhooks = DB::connection('temp')
                    ->table('instagram_configurations as ic')
                    ->leftJoin('projets as p', 'ic.projet_id', '=', 'p.id')
                    ->select('ic.*', 'p.nom as projet_nom')
                    ->whereNull('ic.deleted_at')
                    ->whereNotNull('ic.webhook_verify_token')
                    ->orderBy('ic.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'instagram_id' => $config->instagram_id,
                            'projet_id' => $config->projet_id,
                            'webhook_verify_token' => $config->webhook_verify_token,
                            'webhook_enabled' => $config->webhook_enabled ?? false,
                            'webhook_subscriptions' => json_decode($config->webhook_subscriptions ?? '[]'),
                            'webhook_url' => config('app.url') . '/api/webhookFcb_Insta',
                            'created_at' => $config->created_at,
                            'projet' => $config->projet_nom ? ['nom' => $config->projet_nom] : null
                        ];
                    });

                return response()->json(['webhooks' => $webhooks], 200);
            } catch (\Exception $e) {
                return response()->json(['webhooks' => []], 200);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_instagram_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                $request->validate([
                    'webhook_verify_token' => 'required|string'
                ]);

                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['error' => 'Configuration table not found'], 404);
                }

                $config = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                // Validate that basic Instagram configuration exists
                if (!$config->instagram_id || !$config->acces_token_user) {
                    return response()->json([
                        'error' => 'Configuration Instagram de base incomplète. Veuillez d\'abord configurer l\'ID Instagram et le token d\'accès.'
                    ], 400);
                }

                // Store webhook configuration but keep it DISABLED by default
                DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => $request->webhook_verify_token,
                        'webhook_enabled' => true, // Explicitly set to false
                        'webhook_subscriptions' => json_encode(['mentions']), // Only valid Instagram field
                        'updated_at' => now()
                    ]);

                return response()->json([
                    'message' => 'Webhook Instagram configuré avec succès',
                    'webhook_enabled' => false // Return false to indicate it's disabled
                ], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la configuration: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_instagram_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => null,
                        'webhook_enabled' => false,
                        'webhook_subscriptions' => null,
                        'updated_at' => now()
                    ]);

                return response()->json(['message' => 'Webhook supprimé avec succès'], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Subscribe Facebook page to webhook with detailed error handling
     */
    private function subscribePageToWebhook($pageId, $pageAccessToken)
    {
        try {
            $client = new Client(['timeout' => 30.0]);

            Log::info("Attempting to subscribe Facebook page {$pageId} to webhook");

            $response = $client->post(
                "https://graph.facebook.com/v23.0/{$pageId}/subscribed_apps",
                [
                    'form_params' => [
                        // Use only VALID Facebook page webhook fields
                        'subscribed_fields' => 'feed,mention,messages',
                        'access_token' => $pageAccessToken
                    ]
                ]
            );

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            Log::info("Facebook subscription response", [
                'page_id' => $pageId,
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            if ($statusCode === 200 && isset($responseBody['success']) && $responseBody['success']) {
                Log::info("Facebook page {$pageId} successfully subscribed to webhook");
                return true;
            } else {
                $errorMessage = "Facebook subscription failed";
                if (isset($responseBody['error'])) {
                    $errorMessage .= ": " . $responseBody['error']['message'] ?? 'Unknown error';
                    Log::error("Facebook API Error", [
                        'error_code' => $responseBody['error']['code'] ?? 'unknown',
                        'error_message' => $responseBody['error']['message'] ?? 'unknown',
                        'error_type' => $responseBody['error']['type'] ?? 'unknown'
                    ]);
                }
                throw new \Exception($errorMessage);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = json_decode($e->getResponse()->getBody(), true);

            Log::error("Facebook subscription HTTP error", [
                'page_id' => $pageId,
                'status_code' => $statusCode,
                'error_response' => $errorBody
            ]);

            $errorMessage = "Facebook API returned {$statusCode}";
            if (isset($errorBody['error']['message'])) {
                $errorMessage .= ": " . $errorBody['error']['message'];
            }

            throw new \Exception($errorMessage);
        } catch (\Exception $error) {
            Log::error("Error subscribing Facebook page {$pageId} to webhook: " . $error->getMessage());
            throw $error;
        }
    }

    /**
     * Subscribe Instagram account to webhook with detailed error handling
     */
    private function subscribeInstagramToWebhook($instagramId, $accessToken)
    {
        try {
            $client = new Client(['timeout' => 30.0]);

            Log::info("Attempting to subscribe Instagram account {$instagramId} to webhook");

            $response = $client->post(
                "https://graph.facebook.com/v23.0/{$instagramId}/subscribed_apps",
                [
                    'form_params' => [
                        // Use only VALID Instagram webhook fields
                        'subscribed_fields' => 'mention',
                        'access_token' => $accessToken
                    ]
                ]
            );

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody(), true);

            Log::info("Instagram subscription response", [
                'instagram_id' => $instagramId,
                'status_code' => $statusCode,
                'response' => $responseBody
            ]);

            if ($statusCode === 200 && isset($responseBody['success']) && $responseBody['success']) {
                Log::info("Instagram account {$instagramId} successfully subscribed to webhook");
                return true;
            } else {
                $errorMessage = "Instagram subscription failed";
                if (isset($responseBody['error'])) {
                    $errorMessage .= ": " . $responseBody['error']['message'] ?? 'Unknown error';
                    Log::error("Instagram API Error", [
                        'error_code' => $responseBody['error']['code'] ?? 'unknown',
                        'error_message' => $responseBody['error']['message'] ?? 'unknown',
                        'error_type' => $responseBody['error']['type'] ?? 'unknown'
                    ]);
                }
                throw new \Exception($errorMessage);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = json_decode($e->getResponse()->getBody(), true);

            Log::error("Instagram subscription HTTP error", [
                'instagram_id' => $instagramId,
                'status_code' => $statusCode,
                'error_response' => $errorBody
            ]);

            $errorMessage = "Instagram API returned {$statusCode}";
            if (isset($errorBody['error']['message'])) {
                $errorMessage .= ": " . $errorBody['error']['message'];
            }

            throw new \Exception($errorMessage);
        } catch (\Exception $error) {
            Log::error("Error subscribing Instagram account {$instagramId} to webhook: " . $error->getMessage());
            throw $error;
        }
    }

    public function toggle_facebook_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                $request->validate([
                    'webhook_enabled' => 'required|boolean'
                ]);

                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    return response()->json(['error' => 'Configuration table not found'], 404);
                }

                $config = DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                $newStatus = $request->webhook_enabled;

                // If enabling webhook, try to subscribe to Facebook first
                if ($newStatus) {
                    try {
                        // First verify that the webhook is configured in Facebook Developer Console
                        Log::info("Checking if webhook is ready for subscription", [
                            'config_id' => $configId,
                            'page_id' => $config->page_fcb_id,
                            'webhook_token' => $config->webhook_verify_token
                        ]);

                        $this->subscribePageToWebhook($config->page_fcb_id, $config->acces_token_page);
                    } catch (\Exception $subscriptionError) {
                        Log::error("Facebook subscription failed for config {$configId}: " . $subscriptionError->getMessage());

                        // Return more specific error messages based on the actual error
                        $errorMessage = $subscriptionError->getMessage();

                        if (str_contains($errorMessage, 'Bad signature') || str_contains($errorMessage, '190')) {
                            return response()->json([
                                'error' => 'Token d\'accès Facebook invalide ou expiré. Veuillez vérifier votre token.'
                            ], 400);
                        } elseif (str_contains($errorMessage, '100') || str_contains($errorMessage, 'Invalid parameter')) {
                            return response()->json([
                                'error' => 'Paramètres invalides. Vérifiez l\'ID de votre page Facebook.'
                            ], 400);
                        } elseif (str_contains($errorMessage, 'webhook') || str_contains($errorMessage, 'subscription')) {
                            return response()->json([
                                'error' => 'Webhook non configuré dans Facebook Developer Console. Vous devez d\'abord configurer et vérifier votre webhook dans l\'interface Facebook Developers avant de pouvoir l\'activer ici.'
                            ], 400);
                        } else {
                            return response()->json([
                                'error' => 'Erreur lors de l\'abonnement Facebook: ' . $errorMessage
                            ], 500);
                        }
                    }
                }

                // Only update database if Facebook subscription succeeded (or if disabling)
                DB::connection('temp')
                    ->table('facebook_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_enabled' => $newStatus,
                        'updated_at' => now()
                    ]);

                $status = $newStatus ? 'activé' : 'désactivé';
                return response()->json(['message' => "Webhook Facebook {$status} avec succès"], 200);

            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la modification: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function toggle_instagram_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                $request->validate([
                    'webhook_enabled' => 'required|boolean'
                ]);

                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    return response()->json(['error' => 'Configuration table not found'], 404);
                }

                $config = DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                $newStatus = $request->webhook_enabled;

                // If enabling webhook, try to subscribe to Instagram first
                if ($newStatus) {
                    try {
                        Log::info("Checking if Instagram webhook is ready for subscription", [
                            'config_id' => $configId,
                            'instagram_id' => $config->instagram_id,
                            'webhook_token' => $config->webhook_verify_token
                        ]);

                        $this->subscribeInstagramToWebhook($config->instagram_id, $config->acces_token_user);
                    } catch (\Exception $subscriptionError) {
                        Log::error("Instagram subscription failed for config {$configId}: " . $subscriptionError->getMessage());

                        // Return more specific error messages based on the actual error
                        $errorMessage = $subscriptionError->getMessage();

                        if (str_contains($errorMessage, 'Bad signature') || str_contains($errorMessage, '190')) {
                            return response()->json([
                                'error' => 'Token d\'accès Instagram invalide ou expiré. Veuillez vérifier votre token.'
                            ], 400);
                        } elseif (str_contains($errorMessage, '100') || str_contains($errorMessage, 'Invalid parameter')) {
                            return response()->json([
                                'error' => 'Paramètres invalides. Vérifiez l\'ID de votre compte Instagram Business.'
                            ], 400);
                        } elseif (str_contains($errorMessage, 'webhook') || str_contains($errorMessage, 'subscription')) {
                            return response()->json([
                                'error' => 'Webhook non configuré dans Facebook Developer Console. Vous devez d\'abord configurer et vérifier votre webhook dans l\'interface Facebook Developers avant de pouvoir l\'activer ici.'
                            ], 400);
                        } else {
                            return response()->json([
                                'error' => 'Erreur lors de l\'abonnement Instagram: ' . $errorMessage
                            ], 500);
                        }
                    }
                }

                // Only update database if Instagram subscription succeeded (or if disabling)
                DB::connection('temp')
                    ->table('instagram_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_enabled' => $newStatus,
                        'updated_at' => now()
                    ]);

                $status = $newStatus ? 'activé' : 'désactivé';
                return response()->json(['message' => "Webhook Instagram {$status} avec succès"], 200);

            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la modification: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    private function detectPlatform($change)
    {
        // Detect platform based on the change structure
        if (isset($change['field'])) {
            switch ($change['field']) {
                case 'feed':
                case 'mention':
                    return 'facebook';
                case 'comments':
                    // Check if it's Instagram by looking for media object
                    if (isset($change['value']['media'])) {
                        return 'instagram';
                    }
                    return 'facebook';
                default:
                    return 'unknown';
            }
        }
        return 'unknown';


    }

    private function getEventType($change)
    {
        $field = $change['field'] ?? 'unknown';
        $platform = $this->detectPlatform($change);

        switch ($field) {
            case 'feed':
                $item = $change['value']['item'] ?? null;

                switch ($item) {
                    case 'reaction':
                        return $platform === 'facebook' ? 'facebook_reaction' : 'instagram_reaction';
                    case 'comment':
                        return $platform === 'facebook' ? 'facebook_comment' : 'instagram_comment';
                    case 'post':
                        return $platform === 'facebook' ? 'facebook_post' : 'instagram_post';
                    default:
                        // Fallback detection based on data structure
                        if (isset($change['value']['reaction_type'])) {
                            return $platform === 'facebook' ? 'facebook_reaction' : 'instagram_reaction';
                        } elseif (isset($change['value']['comment_id'])) {
                            return $platform === 'facebook' ? 'facebook_comment' : 'instagram_comment';
                        } else {
                            return $platform === 'facebook' ? 'facebook_post' : 'instagram_post';
                        }
                }
            case 'mention':
                return $platform === 'facebook' ? 'facebook_mention' : 'instagram_mention';
            case 'comments':
                return  $platform === 'instagram' ? 'instagram_comment' : 'fcb';
            default:
                return 'unknown_event';
        }
    }
}
