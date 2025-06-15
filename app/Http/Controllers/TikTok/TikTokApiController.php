<?php

namespace App\Http\Controllers\TikTok;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\RequestException;

class TikTokApiController extends Controller
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;
    protected $apiBaseUrl;
    
    public function __construct()
    {
        $this->clientId = env('TIKTOK_CLIENT_ID', '');
        $this->clientSecret = env('TIKTOK_CLIENT_SECRET', '');
        $this->redirectUri = env('TIKTOK_REDIRECT_URI', '');
        $this->apiBaseUrl = env('TIKTOK_API_URL', 'https://open.tiktokapis.com/v2');
    }
    
    /**
     * Get TikTok OAuth authorization URL
     */
    public function getAuthUrl(Request $request)
    {
        try {
            $state = Str::random(32);
            $scope = env('TIKTOK_SCOPE', 'user.info.basic,video.upload,video.publish');
            
            // Store state for verification
            session(['tiktok_oauth_state' => $state]);
            
            $authUrl = 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                'client_key' => $this->clientId,
                'scope' => $scope,
                'response_type' => 'code',
                'redirect_uri' => $this->redirectUri,
                'state' => $state
            ]);
            
            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
                'state' => $state
            ]);
        } catch (\Exception $e) {
            Log::error("TikTok OAuth URL generation error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate TikTok OAuth URL'
            ], 500);
        }
    }
    
    /**
     * Handle TikTok OAuth callback and exchange code for access token
     */
    public function handleCallback(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string',
                'state' => 'required|string'
            ]);
            
            // Verify state
            if ($request->state !== session('tiktok_oauth_state')) {
                throw new \Exception('Invalid OAuth state');
            }
            
            $client = new Client(['timeout' => 30]);
            
            // Exchange authorization code for access token
            $response = $client->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'form_params' => [
                    'client_key' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $request->code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ]);
            
            $tokenData = json_decode($response->getBody(), true);
            
            if (isset($tokenData['access_token'])) {
                // Store access token securely (you might want to store this in database)
                session(['tiktok_access_token' => $tokenData['access_token']]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'TikTok authentication successful',
                    'access_token' => $tokenData['access_token'],
                    'expires_in' => $tokenData['expires_in'] ?? null
                ]);
            } else {
                throw new \Exception('Failed to obtain access token');
            }
        } catch (\Exception $e) {
            Log::error("TikTok OAuth callback error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'TikTok authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Publish content to TikTok using authenticated user's access token
     */
    public function publishContent(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:150',
                'description' => 'required|string|max:2200',
                'media_url' => 'required|url',
                'media_type' => 'required|in:PHOTO,VIDEO',
                'access_token' => 'string|nullable'
            ]);
            
            // Get access token from request or session
            $accessToken = $request->access_token ?? session('tiktok_access_token');
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'TikTok authentication required',
                    'requires_auth' => true
                ], 401);
            }
            
            // Generate a unique ID for this publish request
            $publishId = Str::uuid()->toString();
            
            // Log the publish attempt
            Log::info("TikTok publish attempt", [
                'publish_id' => $publishId,
                'media_type' => $request->media_type,
                'media_url' => $request->media_url,
            ]);
            
            // Check if we're in mock mode
            if (env('TIKTOK_MOCK_MODE', true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Content queued for publishing to TikTok',
                    'publish_id' => $publishId,
                    'status' => 'PUBLISH_PROCESSING'
                ]);
            }
            
            // Initialize the HTTP client for the real TikTok API
            $client = new Client([
                'base_uri' => $this->apiBaseUrl,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            // Prepare the request payload for TikTok API v2
            $payload = [
                'post_info' => [
                    'title' => $request->title,
                    'description' => $request->description,
                    'disable_comment' => false,
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'auto_add_music' => true
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => $request->media_type
            ];
            
            // Add the appropriate media source based on type
            if ($request->media_type === 'PHOTO') {
                $payload['source_info']['photo_cover_index'] = 0;
                $payload['source_info']['photo_images'] = [$request->media_url];
            } else {
                $payload['source_info']['video_url'] = $request->media_url;
            }
            
            try {
                $response = $client->post('/post/publish/content/init/', [
                    'json' => $payload
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                Log::info('TikTok API Response', ['response' => $responseData]);
                
                if (isset($responseData['data']['publish_id'])) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Content submitted to TikTok API',
                        'publish_id' => $responseData['data']['publish_id'],
                        'status' => 'PUBLISH_PROCESSING'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid response from TikTok API',
                        'response' => $responseData
                    ], 500);
                }
            } catch (RequestException $e) {
                Log::error('TikTok API Error', [
                    'error' => $e->getMessage(),
                    'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error communicating with TikTok: ' . $e->getMessage(),
                    'requires_auth' => $e->getCode() === 401
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("TikTok publish error: " . $e->getMessage(), [
                'exception' => $e
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish to TikTok: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check the status of a publishing request
     */
    public function checkPublishStatus(Request $request)
    {
        $request->validate([
            'publish_id' => 'required|string',
            'access_token' => 'string|nullable'
        ]);
        
        $publishId = $request->input('publish_id');
        $accessToken = $request->access_token ?? session('tiktok_access_token');
        
        try {
            Log::info("TikTok status check for publish_id: $publishId");
            
            if (env('TIKTOK_MOCK_MODE', true)) {
                $contentId = substr(str_shuffle('0123456789'), 0, 10);
                $username = env('TIKTOK_USERNAME', 'yourbusiness');
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'publish_id' => $publishId,
                        'data' => [
                            'publish_status' => 'PUBLISH_COMPLETE',
                            'tiktok_post_url' => "https://www.tiktok.com/@$username/video/$contentId",
                            'content_id' => $contentId,
                            'timestamp' => now()->toIso8601String()
                        ]
                    ]
                ]);
            }
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'TikTok authentication required',
                    'requires_auth' => true
                ], 401);
            }
            
            $client = new Client([
                'base_uri' => $this->apiBaseUrl,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            try {
                $response = $client->get('/post/publish/status/query/', [
                    'query' => ['publish_id' => $publishId]
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                Log::info('TikTok Status Check Response', ['response' => $responseData]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            } catch (RequestException $e) {
                Log::error('TikTok Status Check Error', [
                    'error' => $e->getMessage(),
                    'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error checking TikTok status: ' . $e->getMessage(),
                    'requires_auth' => $e->getCode() === 401
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("TikTok status check error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check publish status: ' . $e->getMessage()
            ], 500);
        }
    }
}
