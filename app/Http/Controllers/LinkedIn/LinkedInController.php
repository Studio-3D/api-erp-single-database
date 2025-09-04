<?php

namespace App\Http\Controllers\LinkedIn;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;

class LinkedInController extends Controller
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    
    public function __construct()
    {
        $this->clientId = env('LINKEDIN_CLIENT_ID', '');
        $this->clientSecret = env('LINKEDIN_CLIENT_SECRET', '');
        $this->redirectUri = env('LINKEDIN_REDIRECT_URI', '');
    }

    /******************************LinkedIn Configuration by Project*************************/
    
    public function linkedin_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists first
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['configurations' => []], 200);
                }

                $configurations = DB::connection('temp')
                    ->table('linkedin_configurations as lc')
                    ->leftJoin('projets as p', 'lc.projet_id', '=', 'p.id')
                    ->select('lc.*', 'p.nom as projet_nom')
                    ->whereNull('lc.deleted_at')
                    ->orderBy('lc.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'linkedin_page_id' => $config->linkedin_page_id,
                            'linkedin_page_name' => $config->linkedin_page_name,
                            'projet_id' => $config->projet_id,
                            'is_active' => $config->is_active ?? true,
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

    public function get_linkedin_config_by_project(Request $request, $projectId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists first
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['configuration' => null], 200);
                }

                $configuration = DB::connection('temp')
                    ->table('linkedin_configurations as lc')
                    ->leftJoin('projets as p', 'lc.projet_id', '=', 'p.id')
                    ->select('lc.*', 'p.nom as projet_nom')
                    ->where('lc.projet_id', $projectId)
                    ->whereNull('lc.deleted_at')
                    ->first();

                if ($configuration) {
                    $config = [
                        'id' => $configuration->id,
                        'linkedin_page_id' => $configuration->linkedin_page_id,
                        'linkedin_page_name' => $configuration->linkedin_page_name,
                        'projet_id' => $configuration->projet_id,
                        'is_active' => $configuration->is_active ?? true,
                        'created_at' => $configuration->created_at,
                        'projet' => $configuration->projet_nom ? ['nom' => $configuration->projet_nom] : null
                    ];
                    return response()->json(['configuration' => $config], 200);
                } else {
                    return response()->json(['configuration' => null], 200);
                }
            } catch (\Exception $e) {
                // If table doesn't exist, return null
                if (str_contains($e->getMessage(), "doesn't exist")) {
                    return response()->json(['configuration' => null], 200);
                }
                throw $e;
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function store_linkedin_configuration(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            
            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    Schema::connection('temp')->create('linkedin_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('linkedin_page_id');
                        $table->string('linkedin_page_name');
                        $table->longText('access_token');
                        $table->unsignedBigInteger('projet_id');
                        $table->boolean('is_active')->default(true);
                        $table->timestamp('last_stats_sync')->nullable();
                        $table->softDeletes();
                        $table->timestamps();
                        
                        $table->foreign('projet_id')->references('id')->on('projets')->onDelete('cascade');
                        $table->unique(['projet_id', 'deleted_at'], 'unique_project_linkedin_config');
                    });
                }
                
                $request->validate([
                    'linkedin_page_id' => 'required|string',
                    'linkedin_page_name' => 'required|string',
                    'access_token' => 'required|string',
                    'projet_id' => 'required|integer|exists:temp.projets,id'
                ]);

                // Check if configuration already exists for this project
                $existingConfig = DB::connection('temp')
                    ->table('linkedin_configurations')
                    ->where('projet_id', $request->projet_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingConfig) {
                    return response()->json([
                        'error' => 'Une configuration LinkedIn existe déjà pour ce projet'
                    ], 400);
                }

                // Insert new configuration
                $configId = DB::connection('temp')->table('linkedin_configurations')->insertGetId([
                    'linkedin_page_id' => $request->linkedin_page_id,
                    'linkedin_page_name' => $request->linkedin_page_name,
                    'access_token' => $request->access_token,
                    'projet_id' => $request->projet_id,
                    'is_active' => true,
                    'last_stats_sync' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return response()->json([
                    'message' => 'Configuration LinkedIn enregistrée avec succès',
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

    public function delete_linkedin_configuration(Request $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            
            try {
                // Check if table exists
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['error' => 'Configuration non trouvée'], 404);
                }

                $deleted = DB::connection('temp')
                    ->table('linkedin_configurations')
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

    public function getAuthUrl(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            $state = bin2hex(random_bytes(16));
            
            $authUrl = "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
                'response_type' => 'code',
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'state' => $state,
                'scope' => 'openid profile email w_member_social'
            ]);

            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
                'state' => $state
            ]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function handleCallback(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            try {
                $request->validate([
                    'code' => 'required|string',
                    'state' => 'required|string'
                ]);
                
                $code = $request->input('code');
                
                $client = new Client();
                
                // Exchange code for access token
                $tokenResponse = $client->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'form_params' => [
                        'grant_type' => 'authorization_code',
                        'code' => $code,
                        'redirect_uri' => $this->redirectUri,
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                    ]
                ]);
                
                $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);
                
                // Get user profile
                $profileResponse = $client->get('https://api.linkedin.com/v2/userinfo', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                    ]
                ]);
                
                $profile = json_decode($profileResponse->getBody()->getContents(), true);
                
                return response()->json([
                    'success' => true,
                    'access_token' => $tokenData['access_token'],
                    'expires_in' => $tokenData['expires_in'],
                    'profile' => $profile,
                    'pages' => [] // LinkedIn API doesn't provide organization pages in this flow
                ]);
                
            } catch (RequestException $e) {
                Log::error('LinkedIn OAuth error: ' . $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate with LinkedIn: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string',
                'state' => 'required|string'
            ]);
            
            $code = $request->input('code');
            
            $client = new Client();
            
            // Exchange code for access token
            $tokenResponse = $client->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            ]);
            
            $tokenData = json_decode($tokenResponse->getBody()->getContents(), true);
            
            // Use the OpenID Connect userinfo endpoint instead of /v2/me
            $profileResponse = $client->get('https://api.linkedin.com/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                ]
            ]);
            
            $profile = json_decode($profileResponse->getBody()->getContents(), true);
            
            // Log the successful authentication
            Log::info('LinkedIn authentication successful', [
                'user_id' => $profile['sub'] ?? 'unknown'
            ]);
            
            return response()->json([
                'success' => true,
                'access_token' => $tokenData['access_token'],
                'expires_in' => $tokenData['expires_in'],
                'profile' => $profile
            ]);
            
        } catch (RequestException $e) {
            Log::error('LinkedIn OAuth error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to authenticate with LinkedIn: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Share content on LinkedIn
     */
    public function sharePost(Request $request)
    {
        try {
            $request->validate([
                'accessToken' => 'required|string',
                'content' => 'required|string|max:3000',
                'visibility' => 'required|string|in:PUBLIC,CONNECTIONS'
            ]);
            
            $accessToken = $request->input('accessToken');
            $content = $request->input('content');
            $visibility = $request->input('visibility');
            $mediaUrl = $request->input('mediaUrl');
            
            $client = new Client();
            
            // Get user identifier from userinfo endpoint
            $profileResponse = $client->get('https://api.linkedin.com/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);
            
            $profile = json_decode($profileResponse->getBody()->getContents(), true);
            $personUrn = $profile['sub']; // Use 'sub' from OpenID instead of 'id'
            
            // Person URN format needs to be adjusted
            $personUrn = str_replace('linkedin:', '', $personUrn);
            
            // Prepare the share payload
            $sharePayload = [
                'author' => 'urn:li:person:' . $personUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $content
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => $visibility
                ]
            ];
            
            // If media URL is provided, register it with LinkedIn first
            if ($mediaUrl) {
                // Step 1: Register upload with LinkedIn to get an upload URL
                $mediaType = $this->getMediaTypeFromUrl($mediaUrl);
                
                if ($mediaType === 'IMAGE') {
                    $registerUploadResponse = $client->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type' => 'application/json'
                        ],
                        'json' => [
                            'registerUploadRequest' => [
                                'recipes' => [
                                    'urn:li:digitalmediaRecipe:feedshare-image'
                                ],
                                'owner' => 'urn:li:person:' . $personUrn,
                                'serviceRelationships' => [
                                    [
                                        'relationshipType' => 'OWNER',
                                        'identifier' => 'urn:li:userGeneratedContent'
                                    ]
                                ]
                            ]
                        ]
                    ]);
                    
                    $registerUploadData = json_decode($registerUploadResponse->getBody()->getContents(), true);
                    
                    // Step 2: Get the upload URL and asset URN
                    $uploadUrl = $registerUploadData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
                    $assetUrn = $registerUploadData['value']['asset'];
                    
                    // Step 3: Convert HTTP to HTTPS and fetch the media content using APP_URL_HOST
                    try {
                        // Always use HTTPS and the proper host for LinkedIn compatibility
                        $httpsMediaUrl = $mediaUrl;
                        
                        // If it's HTTP, convert to HTTPS
                        if (strpos($mediaUrl, 'http://') === 0) {
                            $httpsMediaUrl = str_replace('http://', 'https://', $mediaUrl);
                        }
                        
                        // Use APP_URL_HOST if it's available and different from the current URL
                        $appUrlHost = env('APP_URL_HOST');
                        if ($appUrlHost) {
                            // Extract the host from the media URL
                            $urlParts = parse_url($httpsMediaUrl);
                            if (isset($urlParts['host'])) {
                                // Replace the host with APP_URL_HOST (which should be HTTPS)
                                $httpsMediaUrl = str_replace($urlParts['host'], str_replace(['http://', 'https://'], '', $appUrlHost), $httpsMediaUrl);
                                
                                // Ensure it starts with https://
                                if (!str_starts_with($httpsMediaUrl, 'https://')) {
                                    $httpsMediaUrl = 'https://' . ltrim($httpsMediaUrl, '/');
                                }
                            }
                        }
                        
                        Log::info("LinkedIn media download attempt", [
                            'original_url' => $mediaUrl,
                            'https_url' => $httpsMediaUrl
                        ]);
                        
                        // First, try to use Guzzle to download the content with HTTPS
                        $mediaResponse = $client->get($httpsMediaUrl, [
                            'timeout' => 30,
                            'verify' => false, // Disable SSL verification for self-signed certificates
                            'headers' => [
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                            ]
                        ]);
                        $mediaContent = $mediaResponse->getBody()->getContents();
                        
                        Log::info("LinkedIn media download successful", [
                            'url' => $httpsMediaUrl,
                            'size' => strlen($mediaContent)
                        ]);
                        
                    } catch (\Exception $e) {
                        Log::warning("HTTPS download failed, trying fallback methods: " . $e->getMessage(), [
                            'url' => $httpsMediaUrl
                        ]);
                        
                        // Fallback 1: Try with different SSL options
                        try {
                            $mediaResponse = $client->get($httpsMediaUrl, [
                                'timeout' => 30,
                                'verify' => false,
                                'allow_redirects' => true,
                                'headers' => [
                                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                    'Accept' => 'image/*,*/*;q=0.8'
                                ]
                            ]);
                            $mediaContent = $mediaResponse->getBody()->getContents();
                        } catch (\Exception $e2) {
                            Log::warning("Guzzle fallback failed, trying cURL: " . $e2->getMessage());
                            
                            // Fallback 2: Use cURL with custom options
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $httpsMediaUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Accept: image/*,*/*;q=0.8'
                            ]);
                            $mediaContent = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $curlError = curl_error($ch);
                            curl_close($ch);
                            
                            if (!$mediaContent || $httpCode !== 200) {
                                throw new \Exception("Could not download media from URL. HTTP Code: {$httpCode}. cURL Error: {$curlError}. Original URL: {$mediaUrl}, HTTPS URL: {$httpsMediaUrl}");
                            }
                            
                            Log::info("LinkedIn media download successful via cURL", [
                                'url' => $httpsMediaUrl,
                                'http_code' => $httpCode,
                                'size' => strlen($mediaContent)
                            ]);
                        }
                    }
                    
                    // Step 4: Upload the media to LinkedIn
                    $client->put($uploadUrl, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken
                        ],
                        'body' => $mediaContent
                    ]);
                    
                    // Step 5: Include the media URN in the share request
                    $sharePayload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
                    $sharePayload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                        [
                            'status' => 'READY',
                            'description' => [
                                'text' => 'Property Image'
                            ],
                            'media' => $assetUrn,
                            'title' => [
                                'text' => 'Property Image'
                            ]
                        ]
                    ];
                } else {
                    // If it's not an image, default to text-only post
                    Log::warning('LinkedIn only supports image sharing. Defaulting to text-only post.');
                }
            }
            
            // Create the share
            $shareResponse = $client->post('https://api.linkedin.com/v2/ugcPosts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ],
                'json' => $sharePayload
            ]);
            
            $shareData = json_decode($shareResponse->getBody()->getContents(), true);
            
            Log::info('LinkedIn post created', [
                'post_id' => $shareData['id'] ?? 'unknown'
            ]);
            
            return response()->json([
                'success' => true,
                'post' => $shareData
            ]);
            
        } catch (RequestException $e) {
            Log::error('LinkedIn share error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to share on LinkedIn: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Determine media type from URL
     */
    private function getMediaTypeFromUrl($url) {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $pathInfo = pathinfo($url);
        
        if (isset($pathInfo['extension'])) {
            $extension = strtolower($pathInfo['extension']);
            if (in_array($extension, $imageExtensions)) {
                return 'IMAGE';
            }
        }
        
        // Try to determine by making a HEAD request
        try {
            $client = new Client();
            $response = $client->head($url);
            $contentType = $response->getHeaderLine('Content-Type');
            
            if (strpos($contentType, 'image/') === 0) {
                return 'IMAGE';
            }
        } catch (\Exception $e) {
            // Ignore exceptions from HEAD request
        }
        
        // Default to assuming it's an image if we can't determine
        return 'IMAGE';
    }

    /******************************LinkedIn Webhook Configuration by Project*************************/
    
    public function linkedin_webhook_configurations(Request $request)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            
            try {
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['webhooks' => []], 200);
                }
                
                $webhooks = DB::connection('temp')
                    ->table('linkedin_configurations as lc')
                    ->leftJoin('projets as p', 'lc.projet_id', '=', 'p.id')
                    ->select('lc.*', 'p.nom as projet_nom')
                    ->whereNull('lc.deleted_at')
                    ->whereNotNull('lc.webhook_verify_token')
                    ->orderBy('lc.created_at', 'desc')
                    ->get()
                    ->map(function ($config) {
                        return [
                            'id' => $config->id,
                            'linkedin_page_id' => $config->linkedin_page_id,
                            'linkedin_page_name' => $config->linkedin_page_name,
                            'projet_id' => $config->projet_id,
                            'webhook_verify_token' => $config->webhook_verify_token,
                            'webhook_enabled' => $config->webhook_enabled ?? false,
                            'webhook_subscriptions' => json_decode($config->webhook_subscriptions ?? '[]'),
                            'webhook_url' => env('APP_URL') . '/api/webhookLinkedIn',
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

    public function store_linkedin_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            
            try {
                $request->validate([
                    'webhook_verify_token' => 'required|string'
                ]);

                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['error' => 'Configuration table not found'], 404);
                }

                $config = DB::connection('temp')
                    ->table('linkedin_configurations')
                    ->where('id', $configId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$config) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                DB::connection('temp')
                    ->table('linkedin_configurations')
                    ->where('id', $configId)
                    ->update([
                        'webhook_verify_token' => $request->webhook_verify_token,
                        'webhook_enabled' => true,
                        'webhook_subscriptions' => json_encode(['SHARE', 'ORGANIZATION_SOCIAL_ACTION']),
                        'updated_at' => now()
                    ]);

                return response()->json(['message' => 'Webhook LinkedIn configuré avec succès'], 200);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur lors de la configuration: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function delete_linkedin_webhook(Request $request, $configId)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            
            try {
                if (!Schema::connection('temp')->hasTable('linkedin_configurations')) {
                    return response()->json(['error' => 'Configuration not found'], 404);
                }

                DB::connection('temp')
                    ->table('linkedin_configurations')
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

    public function handleLinkedInWebhook(Request $request)
    {
        Log::info('LinkedIn Webhook received:', $request->all());

        try {
            DatabaseHelper::Config();
            
            // Verify webhook signature (LinkedIn uses different verification)
            $signature = $request->header('X-LinkedIn-Signature');
            $payload = $request->getContent();
            
            if (!$this->verifyLinkedInSignature($signature, $payload)) {
                Log::error('LinkedIn webhook signature verification failed');
                return response('Unauthorized', 403);
            }

            // Process LinkedIn webhook events
            $events = $request->input('events', []);
            
            foreach ($events as $event) {
                $this->processLinkedInEvent($event);
            }

            return response()->json(['message' => 'LinkedIn webhook processed successfully']);
            
        } catch (\Exception $e) {
            Log::error('LinkedIn webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    public function verifyLinkedInWebhook(Request $request)
    {
        $challenge = $request->input('challenge');
        
        if ($challenge) {
            Log::info('LinkedIn webhook verification challenge: ' . $challenge);
            return response($challenge, 200);
        }
        
        return response('No challenge provided', 400);
    }

    private function verifyLinkedInSignature($signature, $payload)
    {
        if (!$signature) {
            return false;
        }

        DatabaseHelper::Config();
        $config = DB::connection('temp')
            ->table('linkedin_configurations')
            ->whereNotNull('webhook_verify_token')
            ->first();
            
        if (!$config) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $config->webhook_verify_token);
        
        return hash_equals('sha256=' . $expectedSignature, $signature);
    }

    private function processLinkedInEvent($event)
    {
        Log::info('Processing LinkedIn event:', $event);
        
        DatabaseHelper::Config();
        
        // Store event in webhook_events table
        $webhookEvent = new \App\Models\WebhookEvent();
        $webhookEvent->setConnection('temp');
        $webhookEvent->platform = 'linkedin';
        $webhookEvent->type = $event['eventType'] ?? 'unknown';
        $webhookEvent->data = $event;
        $webhookEvent->save();

        // Process different LinkedIn event types
        switch ($event['eventType'] ?? '') {
            case 'SHARE':
                $this->handleLinkedInShare($event);
                break;
            case 'ORGANIZATION_SOCIAL_ACTION':
                $this->handleLinkedInSocialAction($event);
                break;
            default:
                Log::info('Unhandled LinkedIn event type: ' . ($event['eventType'] ?? 'unknown'));
        }
    }

    private function handleLinkedInShare($event)
    {
        Log::info('LinkedIn share event processed:', $event);
        // Handle share-related events (likes, comments, shares)
    }

    private function handleLinkedInSocialAction($event)
    {
        Log::info('LinkedIn social action event processed:', $event);
        // Handle organization social actions (follows, mentions, etc.)
    }
}
