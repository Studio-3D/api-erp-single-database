<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
//composer require guzzlehttp/guzzle===>required

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
                           $directory = public_path('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram');
                           //  $directory = public_path('Docs/' . 'societe_principal' . '_' . 10 . '/upload_fcb_instagram');
                           File::makeDirectory($directory, 0755, true, true);
                           $file->move($directory, $fileName);
                               // Generate the file URL
                           $fileUrl = asset('Docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram/' . $fileName);
                           //$url=str_replace('\/\/', '/', $fileUrl);
                           //https://immogestion.online/coline_dev/storage/reservations/10.png
                           //https://immogestion.online/coline_dev/storage/reservations/11.png
                           //https://v.ftcdn.net/01/75/19/28/700_F_175192845_cRe1fUwouwX7vF3GJpRGwACZjl8CC1We_ST.mp4
                           $url=str_replace('\/\/', '/', $fileUrl);
                       }
                    }
                    /* 1 ==> WhatsApp, 2 ==> Instagram, 3 ==> Facebook */
                    if (in_array(3, $selectedNetworks)) {
    // DELETE these hardcoded values:
    // $pageId = 537798629425112;
    // $accessToken='EAAI3GumKq0oBOxTQJRiQ6hZBWbUxGySoXuMOhAyjlL0YZBSp1JQZB3Y7rcoJedNGZCWkXowBvXty2lJT6FDZCOuZAFZAdsVl3D662YH15Aw6FdVYoj0iycMXQzvAvaRvJ1oTTuyfE5mII8sV4pvEZCQZBhqhE7Yd2gZClZBdpCrVzQsKmAWBqZAj9mEM0E0YgnKcqsJWUdUeOmAkCBSuSwEZCOngbEga3LuC8';
    
    // REPLACE with:
    $config = $this->getFacebookConfigForCurrentUser();
    if (!$config) {
        throw new \Exception('Facebook configuration not found for current user');
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
        'url' => $url, // Use dynamic URL instead of hardcoded
        'network' => 'facebook',
        'accessToken' => $accessToken
    ];
    $this->store($request->merge($data));
}

// REPLACE the hardcoded Instagram section:
if (in_array(2, $selectedNetworks)) {
    // DELETE these hardcoded values:
    // $pageId = 17841454841928506;
    // $accessToken='EAAI3GumKq0oBOwvTWZAa8BYDCxzjwAgmeSE0DBoxndSsrUrGhAedIZCmYwkLD2H8OJaeQ7Q4Tzghlbobax0Dp3Y6gkDlIwpxNeSU6ObWv63bXC99cdGZCpqksjHKmMOMKYGtkWwCGX1dzPuY7pCElz3RFxQcpZAanfz9nbZBCJMI8b7uq0ohCHCKO';
    
    // REPLACE with:
    $config = $this->getInstagramConfigForCurrentUser();
    if (!$config) {
        throw new \Exception('Instagram configuration not found for current user');
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
        'url' => $url, // Use dynamic URL instead of hardcoded
        'network' => 'instagram',
        'accessToken' => $accessToken
    ];
    $this->store($request->merge($data));
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

        private function getFacebookConfigForCurrentUser()
{
    try {
        // You'll need to determine the logic for selecting the right config
        // This could be based on user's current project, société, or other criteria
        
        return DB::connection('temp')
            ->table('facebook_configurations')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first(); // Get the most recent configuration
            
    } catch (\Exception $e) {
        Log::error("Error getting Facebook config for current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Instagram configuration for current authenticated user
 */
private function getInstagramConfigForCurrentUser()
{
    try {
        return DB::connection('temp')
            ->table('instagram_configurations')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first(); // Get the most recent configuration
            
    } catch (\Exception $e) {
        Log::error("Error getting Instagram config for current user: " . $e->getMessage());
        return null;
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
            
            // Handle Instagram messaging events (reactions)
            if (isset($entry['messaging'])) {
                foreach ($entry['messaging'] as $messaging) {
                    $this->handleInstagramMessaging($messaging, $societeId);
                }
            }

            // Handle changes events (comments, mentions, posts)
            foreach ($entry['changes'] ?? [] as $change) {
                $this->processChange($change, $societeId);
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

    private function processChange($change, $societeId)
{
    $platform = $this->detectPlatform($change);
    $event_type = $this->getEventType($change);
    Log::info("Processing Event - Platform: $platform, Type: $event_type, Société: $societeId");

    // Store event in database for the specific société
    Config::set('broadcasting.default', 'pusher_3');
    
    // Extract URL for Instagram posts if needed
    if($event_type == 'instagram_comment'){
        if (isset($change['value']['media']['id'])) {
            $mediaId = $change['value']['media']['id'];
            $accessToken = $this->getAccessTokenForPage($pageId);
            if (!$accessToken) {
                Log::error("No access token found for page ID: {$pageId}");
                return;
            }
            Log::info("Media Id: $mediaId, Type: $event_type");
            
            // Fetch the permalink from Instagram Graph API
            $response = Http::get("https://graph.facebook.com/v22.0/{$mediaId}", [
                'fields' => 'permalink',
                'access_token' => $accessToken
            ]);

            if ($response->successful()) {
                $permalink = $response->json()['permalink'] ?? null;
                if ($permalink) {
                    $change['permalink'] = str_replace('\/\/', '/', $permalink);
                }
            }
        }
    }
    
    try {
        $web = new WebhookEvent();
        $web->setConnection('temp');
        $web->platform = $platform;
        $web->event_type = $event_type;
        $web->data = $change;
        $web->save();
        
        broadcast(new NotificationEvent(0));
        
        Log::info("Webhook event saved successfully for société {$societeId}");
        
    } catch (\Exception $e) {
        Log::error("Error saving webhook event for société {$societeId}: " . $e->getMessage());
    }

    $field = $change['field'] ?? null;

    switch ($field) {
        case 'feed': // Facebook/Instagram posts, comments, and reactions - ALL in feed
            $this->handleFeedEvent($change['value']);
            break;
        case 'mentions': // Instagram mentions
            $this->handleInstagramMention($change['value']);
            break;
        case 'mention': // Facebook mentions
            $this->handleFacebookMention($change['value']);
            break;
        default:
            Log::warning('Unhandled Webhook Event: ' . $field);
    }
}

private function handleFeedEvent($data)
{
    Log::info('Processing feed event:', $data);

    // Check the 'item' field to determine the type of feed event
    $item = $data['item'] ?? null;

    switch ($item) {
        case 'reaction':
            // Only process 'add' verb, ignore 'remove'
            if (isset($data['verb']) && $data['verb'] === 'remove') {
                Log::info('Ignoring reaction removal event:', $data);
                return;
            }
            $this->handleFacebookReaction($data);
            break;
        case 'comment':
            // Check if it's Facebook or Instagram comment
            if (isset($data['post_id'])) {
                $this->handleFacebookComment($data);
            } else {
                $this->handleInstagramComment($data);
            }
            break;
        case 'post':
            // Check if it's Facebook or Instagram post
            if (isset($data['post_id'])) {
                $this->handleFacebookPost($data);
            } else {
                $this->handleInstagramPost($data);
            }
            break;
        default:
            // If no 'item' field, try to detect based on data structure
            if (isset($data['reaction_type']) && isset($data['verb'])) {
                if ($data['verb'] === 'remove') {
                    Log::info('Ignoring reaction removal event:', $data);
                    return;
                }
                $this->handleFacebookReaction($data);
            } elseif (isset($data['message']) && isset($data['comment_id'])) {
                $this->handleFacebookComment($data);
            } elseif (isset($data['message']) || isset($data['from']['name'])) {
                $this->handleFacebookPost($data);
            } else {
                Log::info('Unknown feed event type:', $data);
            }
    }
}

        private function handleFacebookPost($data)
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
            
            $this->createFacebookNotification($description, $postLink, \App\Enum\TypeNotificationEnum::FacebookPublication->value);
        }
        
    } catch (\Exception $e) {
        Log::error('Error handling Facebook post: ' . $e->getMessage());
    }
}

private function handleFacebookComment($data)
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
        
        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookComment->value);
        
    } catch (\Exception $e) {
        Log::error('Error handling Facebook comment: ' . $e->getMessage());
    }
}

private function handleFacebookReaction($data)
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

            $this->createFacebookNotification($description, $postLink);
        } else {
            Log::info("Reaction removed by {$userName}, not creating notification");
        }

    } catch (\Exception $e) {
        Log::error('Error handling Facebook reaction: ' . $e->getMessage());
    }
}

// Add a new method to create Facebook notifications
private function createFacebookNotification($description, $link = null, $type = null)
{
    try {
        // Create notification using the notification model
        $notification = new \App\Models\Notification();
        $notification->setConnection('temp');

        // Set required fields
        $notification->date = now()->format('Y-m-d H:i:s');
        $notification->type = $type ?? \App\Enum\TypeNotificationEnum::FacebookReaction->value; // Default to 98
        $notification->description_type = $description;
        $notification->lien = $link ?? 'https://www.facebook.com';

        // Set role to admin so all users can see it
        $notification->role = \App\Enum\RoleEnum::ADMIN->value;

        // Try to get the current project ID from the database context
        // Since we're in webhook context, we need to find an appropriate projet_id
        $projet = DB::connection('temp')->table('projets')->first();
        if ($projet) {
            $notification->projet_id = $projet->id;
        } else {
            // Fallback - create a default project if none exists
            $notification->projet_id = 1;
        }

        $notification->save();

        // Broadcast the notification
        Config::set('broadcasting.default', 'pusher_3');
        broadcast(new \App\Events\NotificationEvent($notification->id));

        Log::info('Social media notification created successfully', [
            'notification_id' => $notification->id,
            'type' => $type,
            'description' => $description
        ]);

    } catch (\Exception $e) {
        Log::error('Error creating social media notification: ' . $e->getMessage());
    }
}

// Handle Instagram comments
private function handleInstagramComment($data)
{
    Log::info('Processing Instagram comment:', $data);

    try {
        // Extract comment information
        $userName = $data['from']['name'] ?? 'Utilisateur inconnu';
        $message = $data['message'] ?? '';
        $mediaId = $data['media']['id'] ?? null;
        $commentId = $data['comment_id'] ?? null;

        // Create notification description
        $description = "{$userName} a commenté votre publication Instagram";
        if (!empty($message)) {
            $description .= ": " . (strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message);
        }

        // Get Instagram post link if available
        $link = null;
        if ($mediaId) {
            $link = "https://www.instagram.com/p/{$mediaId}";
        }

        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::InstagramComment->value);

    } catch (\Exception $e) {
        Log::error('Error handling Instagram comment: ' . $e->getMessage());
    }
}

// Handle Instagram posts/publications
private function handleInstagramPost($data)
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

        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::InstagramPublication->value);

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
private function handleInstagramMention($data)
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

        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::InstagramMention->value);

    } catch (\Exception $e) {
        Log::error('Error handling Instagram mention: ' . $e->getMessage());
    }
}

// Handle Facebook mentions
private function handleFacebookMention($data)
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

        $this->createFacebookNotification($description, $link, \App\Enum\TypeNotificationEnum::FacebookMention->value);

    } catch (\Exception $e) {
        Log::error('Error handling Facebook mention: ' . $e->getMessage());
    }
}

// Handle Instagram messaging events (reactions)
private function handleInstagramMessaging($messaging, $societeId)
{
    Log::info('Processing Instagram messaging event:', $messaging);

    try {
        // Store webhook event
        $web = new WebhookEvent();
        $web->setConnection('temp');
        $web->platform = 'instagram';
        $web->event_type = 'instagram_messaging';
        $web->data = $messaging;
        $web->save();

        broadcast(new NotificationEvent(0));

        // Check if this is a reaction event
        if (isset($messaging['reaction'])) {
            $this->handleInstagramReaction($messaging);
        }

    } catch (\Exception $e) {
        Log::error('Error handling Instagram messaging: ' . $e->getMessage());
    }
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
                        'webhook_subscriptions' => json_encode(['feed', 'mention']), // Only valid fields
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
                        'webhook_enabled' => false, // Explicitly set to false
                        'webhook_subscriptions' => json_encode(['mention']), // Only valid Instagram field
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
                "https://graph.facebook.com/v19.0/{$pageId}/subscribed_apps",
                [
                    'form_params' => [
                        // Use only VALID Facebook page webhook fields
                        'subscribed_fields' => 'feed,mention',
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
                "https://graph.facebook.com/v19.0/{$instagramId}/subscribed_apps",
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
            // Check the 'item' field within the feed event
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
        default:
            return 'unknown_event';
    }
}
}
