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
use App\Models\Notification;


class Facebook_InstagramController extends Controller
{



        //get posts https://graph.facebook.com/v22.0/537798629425112/feed?access_token=EAAI3GumKq0oBOy1YU24xu5XOm048xx7RpSW1uvmY6zsQuwWmjiyKlZAIVFoGACdo5SXFbuZCE8n3B0cER55C82wSvadDbfEqUnVcjfftR0SZCWhBesqVXYqAtRez7ZA8rbYXUKcSo1jl3QZCVDezLxN8FH5gbk5yaIQBYe8240g2jkEdj5pNR2vkT850PbZBl0hlbRj1Eaas0XkU2uuRSTUZBw9&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors
        //pour commenter /***"https://graph.facebook.com/{page-post-id}/comments?message=I%20want%20chocolate%20cake%20! &access_token=page-access-token"  */
        //get comments           //https://graph.facebook.com/v22.0/{page-post-id}537798629425112_122104722890793117/comments?access_token=EAAI3GumKq0oBO3e3PWinEHAOpbupHdC115jYneAbK2jWQsgAW0UfSj3da54JW9ZCZBfRKn6zm1lteZBzopLobZALsZBiHkdRPuqhFSfEjY1AxTwj8vkLeUO4rjiQpnAZBDshxdL8HmkwvSXFscFcLhe42G1DtQhD0RTRRVMhZCLgtHmBAVDw4UFFY46abpNsgVcp1fHLM8iZBLbRyzmCxt3ye08b&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors

              private function getFacebookConfigForCurrentUser($projetId = null)
{
    try {
        Log::info("🔍 [getFacebookConfigForCurrentUser] START", [
            'projetId' => $projetId,
            'user_id' => Auth::id()
        ]);

        $user = Auth::user();

        if (!$projetId) {
            Log::warning("⚠️ [getFacebookConfigForCurrentUser] No project ID provided");
            return null;
        }

        Log::info("📋 [getFacebookConfigForCurrentUser] Getting user accessible projects", [
            'user_id' => $user->id,
            'projetId' => $projetId
        ]);

        // Get user's accessible projects to ensure they have permission
        $userProjects = $this->getUserAccessibleProjects($user);
        $projectIds = $userProjects->pluck('projet_id')->toArray();

        Log::info("📊 [getFacebookConfigForCurrentUser] User projects", [
            'user_id' => $user->id,
            'project_ids' => $projectIds,
            'count' => count($projectIds),
            'target_projet_id' => $projetId
        ]);

        if (!in_array($projetId, $projectIds)) {
            Log::warning("🚫 [getFacebookConfigForCurrentUser] User does not have access to project", [
                'user_id' => $user->id,
                'projetId' => $projetId,
                'accessible_projects' => $projectIds
            ]);
            return null;
        }

        Log::info("✅ [getFacebookConfigForCurrentUser] User has access to project", [
            'projetId' => $projetId
        ]);

        // Get Facebook configuration for the specific project
        Log::info("🔎 [getFacebookConfigForCurrentUser] Querying facebook_configurations table", [
            'projet_id' => $projetId,
            'table' => 'facebook_configurations'
        ]);

        $config = DB::table('facebook_configurations')
            ->where('projet_id', $projetId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$config) {
            Log::warning("❌ [getFacebookConfigForCurrentUser] No Facebook configuration found", [
                'projet_id' => $projetId,
                'query' => "SELECT * FROM facebook_configurations WHERE projet_id = {$projetId} AND deleted_at IS NULL"
            ]);

            // Vérifier si la table existe
            if (Schema::hasTable('facebook_configurations')) {
                Log::info("📋 [getFacebookConfigForCurrentUser] Table facebook_configurations exists");

                // Compter le nombre total d'enregistrements
                $totalRecords = DB::table('facebook_configurations')->count();
                Log::info("📊 [getFacebookConfigForCurrentUser] Total records in facebook_configurations", [
                    'total' => $totalRecords
                ]);

                // Vérifier tous les projet_id existants
                $allProjetIds = DB::table('facebook_configurations')
                    ->select('projet_id', 'id')
                    ->whereNull('deleted_at')
                    ->get();

                Log::info("📊 [getFacebookConfigForCurrentUser] All project IDs in table", [
                    'records' => $allProjetIds->toArray()
                ]);

                // Vérifier spécifiquement pour projet_id = 1
                $configForProject1 = DB::table('facebook_configurations')
                    ->where('projet_id', 1)
                    ->whereNull('deleted_at')
                    ->first();

                Log::info("📊 [getFacebookConfigForCurrentUser] Config for project_id = 1", [
                    'exists' => $configForProject1 ? 'YES' : 'NO',
                    'data' => $configForProject1
                ]);

            } else {
                Log::error("💥 [getFacebookConfigForCurrentUser] Table facebook_configurations does NOT exist!");
            }

            return null;
        }

        Log::info("✅ [getFacebookConfigForCurrentUser] Configuration found successfully", [
            'projet_id' => $config->projet_id,
            'page_fcb_id' => $config->page_fcb_id,
            'has_token' => !empty($config->acces_token_page),
            'config_id' => $config->id,
            'webhook_enabled' => $config->webhook_enabled
        ]);

        return $config;

    } catch (\Exception $e) {
        Log::error("💥 [getFacebookConfigForCurrentUser] EXCEPTION", [
            'projetId' => $projetId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return null;
    }
}
                 // 'url'=> str_replace('\/\/', '/', 'https://images.unsplash.com/photo-1596705775825-194570c1f0cd?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8Z3JlZW4lMjBmbG93ZXJ8ZW58MHx8MHx8fDA%3D'),

                 /**
 * Detect media type from URL
 */
/**
 * Detect media type from URL
 */
/**
 * Detect media type from URL
 */
private function detectMediaTypeFromUrl($url)
{
    Log::info("🔍 [detectMediaTypeFromUrl] Detecting media type", [
        'url' => $url
    ]);

    // First, try to get the file extension from the URL
    $path = parse_url($url, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $extension = strtolower($extension);

    Log::info("📋 [detectMediaTypeFromUrl] Extension extracted", [
        'extension' => $extension,
        'path' => $path
    ]);

    // Image extensions
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'tiff', 'tif', 'avif', 'heic', 'heif'];
    // Video extensions
    $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm', 'm4v', '3gp', 'mpeg', 'mpg', 'm4p', 'm4v'];

    if (in_array($extension, $imageExtensions)) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE from extension", ['extension' => $extension]);
        return 'image_url';
    } elseif (in_array($extension, $videoExtensions)) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as VIDEO from extension", ['extension' => $extension]);
        return 'video_url';
    }

    // If no extension or unknown extension, try to get mime type from headers
    try {
        // Use stream context to get headers without downloading full file
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 5
            ]
        ]);

        $headers = get_headers($url, 1, $context);

        Log::info("📋 [detectMediaTypeFromUrl] Headers received", [
            'headers_count' => count($headers),
            'headers' => $headers
        ]);

        // Check Content-Type header
        if (isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
            Log::info("📋 [detectMediaTypeFromUrl] Content-Type from headers", ['content_type' => $contentType]);

            if (strpos($contentType, 'image/') === 0) {
                Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE from Content-Type");
                return 'image_url';
            } elseif (strpos($contentType, 'video/') === 0) {
                Log::info("✅ [detectMediaTypeFromUrl] Detected as VIDEO from Content-Type");
                return 'video_url';
            }
        }

        // Also check for Content-Disposition header
        if (isset($headers['Content-Disposition'])) {
            $contentDisposition = is_array($headers['Content-Disposition']) ? $headers['Content-Disposition'][0] : $headers['Content-Disposition'];
            Log::info("📋 [detectMediaTypeFromUrl] Content-Disposition", ['content_disposition' => $contentDisposition]);

            // Check if filename has image or video extension
            if (preg_match('/filename="?([^"]+)"?/', $contentDisposition, $matches)) {
                $filename = $matches[1];
                $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($fileExt, $imageExtensions)) {
                    return 'image_url';
                } elseif (in_array($fileExt, $videoExtensions)) {
                    return 'video_url';
                }
            }
        }
    } catch (\Exception $e) {
        Log::warning("⚠️ [detectMediaTypeFromUrl] Could not get headers: " . $e->getMessage());
    }

    // Check URL patterns for common CDNs
    $urlLower = strtolower($url);

    // Facebook CDN URLs
    if (strpos($urlLower, 'fbcdn.net') !== false ||
        strpos($urlLower, 'facebook.com') !== false ||
        strpos($urlLower, 'scontent.') !== false) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE (Facebook CDN)");
        return 'image_url';
    }

    // Instagram CDN URLs
    if (strpos($urlLower, 'cdninstagram.com') !== false ||
        strpos($urlLower, 'instagram.com') !== false) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE (Instagram CDN)");
        return 'image_url';
    }

    // Unsplash CDN URLs
    if (strpos($urlLower, 'unsplash.com') !== false ||
        strpos($urlLower, 'images.unsplash.com') !== false) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE (Unsplash CDN)");
        return 'image_url';
    }

    // Common CDN image indicators
    $imageIndicators = ['photo', 'image', 'picture', 'img', 'picsum', 'cdn'];
    foreach ($imageIndicators as $indicator) {
        if (strpos($urlLower, $indicator) !== false) {
            Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE from URL pattern", ['indicator' => $indicator]);
            return 'image_url';
        }
    }

    // Check if URL contains common video indicators
    $videoIndicators = ['video', 'mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
    foreach ($videoIndicators as $indicator) {
        if (strpos($urlLower, $indicator) !== false) {
            Log::info("✅ [detectMediaTypeFromUrl] Detected as VIDEO from URL pattern", ['indicator' => $indicator]);
            return 'video_url';
        }
    }

    // Check for common image mime types in URL parameters
    if (strpos($url, '_nc_') !== false || strpos($url, 'stp=') !== false || strpos($url, '&_nc_') !== false) {
        Log::info("✅ [detectMediaTypeFromUrl] Detected as IMAGE (Facebook/Instagram CDN with _nc_ parameters)");
        return 'image_url';
    }

    // Final default - assume image for most web URLs
    Log::info("ℹ️ [detectMediaTypeFromUrl] Defaulting to IMAGE");
    return 'image_url';
}
public function postTo_Social_Network(StoreSocialNetworkRequest $request){
    try {
        Log::info("🚀 [postTo_Social_Network] START", [
            'reseaux_sociaux' => $request->reseaux_sociaux,
            'projet_id' => $request->projet_id,
            'mode' => $request->mode,
            'user_id' => Auth::id()
        ]);

        $user = Auth::user();
        DatabaseHelper::Config();

        Log::info("👤 [postTo_Social_Network] User authenticated", [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        $userAuth = User::on('temp')->where('user_id_origin', $user->getAuthIdentifier())->get();
        Log::info("🔍 [postTo_Social_Network] UserAuth from temp", [
            'count' => $userAuth->count(),
            'data' => $userAuth->toArray()
        ]);

        $user_societes = User::where('id', $userAuth->value('user_id_origin'))->first();
        Log::info("🏢 [postTo_Social_Network] User societe", [
            'user_societes_id' => $user_societes->id ?? null,
            'societe_id' => $user_societes->societe_id ?? null
        ]);

        $societe = Societe::findOrfail($user_societes->societe_id);
        Log::info("🏢 [postTo_Social_Network] Societe found", [
            'societe_id' => $societe->id,
            'raison_sociale' => $societe->raison_sociale
        ]);

        $network = $request->reseaux_sociaux;
        $mode = $request->mode;
        $file = $request->file('mediaFile');
        $description = $request->description;
        $type_media = null;
        $selectedNetworks = explode(',', $network);

        Log::info("📋 [postTo_Social_Network] Request data", [
            'network' => $network,
            'selectedNetworks' => $selectedNetworks,
            'mode' => $mode,
            'description_length' => strlen($description),
            'has_file' => $request->hasFile('mediaFile')
        ]);

        // Handle text-only mode (sans_media)
        if ($mode == 'sans_media') {
            Log::info("📝 [postTo_Social_Network] Mode sans_media (text-only) detected");
            // No file processing needed for text-only posts
            $url = null;
            $text = 'feed'; // For text-only posts on Facebook
        }

        // En mode parcourir, l'utilisateur sélectionne un fichier qui est ensuite uploadé dans le stockage. Après l'upload, on récupère son URL ainsi que son type (photo ou vidéo).
        if ($mode == 'parcourir') {
            Log::info("📁 [postTo_Social_Network] Mode parcourir detected");

            if ($request->hasFile('mediaFile')) {
                Log::info("📎 [postTo_Social_Network] File detected", [
                    'original_name' => $request->file('mediaFile')->getClientOriginalName(),
                    'size' => $request->file('mediaFile')->getSize(),
                    'mime_type' => $request->file('mediaFile')->getMimeType()
                ]);

                //get type file photos or videos
                $mimeType = $request->file('mediaFile')->getMimeType();

                if (str_starts_with($mimeType, 'image/')) {
                    $text = 'photos';
                    $type_media = 'image_url';
                    Log::info("🖼️ [postTo_Social_Network] File type: IMAGE");
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $text = 'videos';
                    $type_media = 'video_url';
                    Log::info("🎬 [postTo_Social_Network] File type: VIDEO");
                } else {
                    $text = 'unknown';
                    Log::warning("⚠️ [postTo_Social_Network] Unknown file type", ['mime_type' => $mimeType]);
                }

                // Get the uploaded file
                $fileName = $file->getClientOriginalName();
                $directory = public_path('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram');
                File::makeDirectory($directory, 0755, true, true);
                $file->move($directory, $fileName);

                // Generate the file URL
                $fileUrl = asset('docs/' . $societe->raison_sociale_concatene . '_' . $societe->id . '/upload_fcb_instagram/' . $fileName);
                $url = str_replace('\/\/', '/', $fileUrl);

                Log::info("✅ [postTo_Social_Network] File uploaded successfully", [
                    'directory' => $directory,
                    'fileUrl' => $fileUrl,
                    'url' => $url
                ]);
            } else {
                Log::warning("⚠️ [postTo_Social_Network] No file in request despite mode=parcourir");
            }
        }

        /* 1 ==> WhatsApp, 2 ==> Instagram, 3 ==> Facebook */
        if (in_array(3, $selectedNetworks)) {
            Log::info("📘 [postTo_Social_Network] Facebook network selected", [
                'projet_id' => $request->projet_id
            ]);

            Log::info("🔍 [postTo_Social_Network] Calling getFacebookConfigForCurrentUser", [
                'projet_id' => $request->projet_id,
                'user_id' => $user->id
            ]);

            $config = $this->getFacebookConfigForCurrentUser($request->projet_id);

            Log::info("📊 [postTo_Social_Network] Config result", [
                'found' => $config ? 'YES' : 'NO',
                'config_data' => $config ? [
                    'id' => $config->id,
                    'page_fcb_id' => $config->page_fcb_id,
                    'projet_id' => $config->projet_id,
                    'has_token' => !empty($config->acces_token_page)
                ] : null
            ]);

            if (!$config) {
                Log::error("❌ [postTo_Social_Network] Config not found via getFacebookConfigForCurrentUser");

                // Tentative de récupération directe
                Log::info("🔍 [postTo_Social_Network] Attempting direct DB query");
                $directConfig = DB::table('facebook_configurations')
                    ->where('projet_id', $request->projet_id)
                    ->whereNull('deleted_at')
                    ->first();

                Log::info("📊 [postTo_Social_Network] Direct query result", [
                    'found' => $directConfig ? 'YES' : 'NO',
                    'data' => $directConfig
                ]);

                $allConfigs = DB::table('facebook_configurations')
                    ->whereNull('deleted_at')
                    ->get();

                Log::info("📊 [postTo_Social_Network] All configurations in table", [
                    'count' => $allConfigs->count(),
                    'data' => $allConfigs->map(function($item) {
                        return [
                            'id' => $item->id,
                            'projet_id' => $item->projet_id,
                            'page_fcb_id' => $item->page_fcb_id
                        ];
                    })->toArray()
                ]);

                $projectExists = DB::table('projets')
                    ->where('id', $request->projet_id)
                    ->whereNull('deleted_at')
                    ->first();

                Log::info("📊 [postTo_Social_Network] Project check", [
                    'projet_id' => $request->projet_id,
                    'exists' => $projectExists ? 'YES' : 'NO',
                    'project_data' => $projectExists
                ]);

                throw new \Exception('Facebook configuration not found for project ID: ' . $request->projet_id);
            }

            $pageId = $config->page_fcb_id;
            $accessToken = $config->acces_token_page;

            Log::info("✅ [postTo_Social_Network] Facebook config found", [
                'pageId' => $pageId,
                'has_token' => !empty($accessToken),
                'token_preview' => $accessToken ? substr($accessToken, 0, 20) . '...' : 'EMPTY'
            ]);

            // Handle URL based on mode with media type detection for Facebook
            if ($mode == 'existante') {
                $url = str_replace('\/\/', '/', $request->img_existant_url);
                // Detect media type from URL for Facebook
                $mediaType = $this->detectMediaTypeFromUrl($url);

                // Set the appropriate text for Facebook API
                if ($mediaType == 'video_url') {
                    $text = 'videos';
                } else {
                    $text = 'photos'; // Default to photos for images
                }

                Log::info("📸 [postTo_Social_Network] Mode existante for Facebook", [
                    'img_existant_url' => $request->img_existant_url,
                    'url' => $url,
                    'detected_media_type' => $mediaType,
                    'text' => $text
                ]);
            } elseif ($mode == 'sans_media') {
                // Text-only post - use feed endpoint
                $text = 'feed';
                $url = null;
                Log::info("📝 [postTo_Social_Network] Text-only mode for Facebook");
            }

            $data = [
                'pageId_InstagramId' => $pageId,
                'caption' => $description,
                'text' => $text,
                'url' => $url,
                'network' => 'facebook',
                'accessToken' => $accessToken,
                'mode' => $mode
            ];

            Log::info("📤 [postTo_Social_Network] Calling store() for Facebook", [
                'pageId' => $pageId,
                'caption_length' => strlen($description),
                'url' => $url,
                'mode' => $mode,
                'text' => $text
            ]);

            $response = $this->store($request->merge($data));
            return $response;
        }

        // Instagram section - updated for text-only
        // Instagram section - updated for text-only
if (in_array(2, $selectedNetworks)) {
    Log::info("📷 [postTo_Social_Network] Instagram network selected", [
        'projet_id' => $request->projet_id
    ]);

    $config = $this->getInstagramConfigForCurrentUser($request->projet_id);

    if (!$config) {
        Log::error("❌ [postTo_Social_Network] Instagram config not found", [
            'projet_id' => $request->projet_id
        ]);
        throw new \Exception('Instagram configuration not found for project ID: ' . $request->projet_id);
    }

    $pageId = $config->instagram_id;
    $accessToken = $config->acces_token_user;

    // Determine media type from the URL or file
    $type_media = null;
    $url = null;

    if ($mode == 'existante') {
        $url = $request->img_existant_url;

        // Clean the URL
        $url = str_replace('\/\/', '/', $url);

        // Detect media type from URL
        $type_media = $this->detectMediaTypeFromUrl($url);

        Log::info("📸 [postTo_Social_Network] Mode existante for Instagram", [
            'url' => $url,
            'type_media' => $type_media
        ]);
    } elseif ($mode == 'parcourir' && $request->hasFile('mediaFile')) {
        // Media type already set from file upload
        $url = $request->url ?? null;
        $type_media = $request->type_media ?? 'image_url';
        Log::info("📁 [postTo_Social_Network] Mode parcourir for Instagram", [
            'url' => $url,
            'type_media' => $type_media
        ]);
    } elseif ($mode == 'sans_media') {
        // Instagram requires media
        return response()->json([
            'success' => false,
            'message' => 'Instagram requires media for posts. Please add an image or video.',
            'requires_media' => true
        ], 400);
    }

    // Ensure we have a valid URL and media type
    if (!$url) {
        return response()->json([
            'success' => false,
            'message' => 'No media URL provided for Instagram post.'
        ], 400);
    }

    // If type_media is still null, default to image_url
    if (!$type_media) {
        $type_media = 'image_url';
    }

    // For video URLs, we need to use a different API endpoint
    // Instagram API: For videos, use media_type 'VIDEO' or 'REELS'
    $instagramMediaType = 'IMAGE'; // Default
    if ($type_media == 'video_url') {
        $instagramMediaType = 'REELS';
    }

    // Validate that the URL is accessible
    try {
        $headers = get_headers($url, 1);
        if ($headers === false || strpos($headers[0], '200') === false) {
            Log::warning("⚠️ [postTo_Social_Network] URL might not be accessible", ['url' => $url]);
            // Continue anyway - the API will handle it
        }
    } catch (\Exception $e) {
        Log::warning("⚠️ [postTo_Social_Network] Could not validate URL: " . $e->getMessage());
    }

    $data = [
        'pageId_InstagramId' => $pageId,
        'caption' => $request->description,
        'text' => 'media',
        'type_media' => $type_media,
        'instagram_media_type' => $instagramMediaType,
        'url' => $url,
        'network' => 'instagram',
        'accessToken' => $accessToken,
        'mode' => $mode
    ];

    Log::info("📤 [postTo_Social_Network] Calling store() for Instagram", [
        'pageId' => $pageId,
        'has_token' => !empty($accessToken),
        'type_media' => $type_media,
        'instagram_media_type' => $instagramMediaType,
        'url' => $url
    ]);

    $response = $this->store($request->merge($data));
    return $response;
}

        // Only return invalid if no valid networks were processed
        if (!array_intersect($selectedNetworks, [2, 3])) {
            Log::warning("⚠️ [postTo_Social_Network] No valid network selected", [
                'selectedNetworks' => $selectedNetworks
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid social network selection'], 400);
        }

        Log::info("✅ [postTo_Social_Network] END - No error");

    } catch (\Exception $e) {
        Log::error("💥 [postTo_Social_Network] EXCEPTION", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

       public function debugFacebookConfigs()
{
    try {
        Log::info("🔍 [DEBUG] Starting Facebook config debug");

        // 1. Vérifier si la table existe
        $tableExists = Schema::hasTable('facebook_configurations');
        Log::info("📋 [DEBUG] Table exists?", ['exists' => $tableExists]);

        if (!$tableExists) {
            return response()->json([
                'error' => 'Table facebook_configurations does not exist'
            ], 404);
        }

        // 2. Récupérer toutes les configurations
        $allConfigs = DB::table('facebook_configurations')->get();
        Log::info("📊 [DEBUG] All configs", [
            'count' => $allConfigs->count(),
            'data' => $allConfigs->toArray()
        ]);

        // 3. Récupérer les configurations non supprimées
        $activeConfigs = DB::table('facebook_configurations')
            ->whereNull('deleted_at')
            ->get();
        Log::info("✅ [DEBUG] Active configs", [
            'count' => $activeConfigs->count(),
            'data' => $activeConfigs->toArray()
        ]);

        // 4. Récupérer pour projet_id = 1
        $configProject1 = DB::table('facebook_configurations')
            ->where('projet_id', 1)
            ->whereNull('deleted_at')
            ->first();
        Log::info("🎯 [DEBUG] Config for project 1", [
            'found' => $configProject1 ? 'YES' : 'NO',
            'data' => $configProject1
        ]);

        // 5. Vérifier les projets
        $projects = DB::table('projets')
            ->whereNull('deleted_at')
            ->get();
        Log::info("🏢 [DEBUG] All projects", [
            'count' => $projects->count(),
            'data' => $projects->toArray()
        ]);

        // 6. Vérifier les permissions de l'utilisateur
        $user = Auth::user();
        $userProjects = DB::table('user_projets')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->get();
        Log::info("👤 [DEBUG] User projects", [
            'user_id' => $user->id,
            'count' => $userProjects->count(),
            'data' => $userProjects->toArray()
        ]);

        return response()->json([
            'table_exists' => $tableExists,
            'total_configs' => $allConfigs->count(),
            'active_configs_count' => $activeConfigs->count(),
            'active_configs' => $activeConfigs->toArray(),
            'config_project_1' => $configProject1,
            'projects' => $projects->toArray(),
            'user_projects' => $userProjects->toArray(),
            'current_user' => [
                'id' => $user->id,
                'email' => $user->email
            ]
        ]);

    } catch (\Exception $e) {
        Log::error("💥 [DEBUG] Exception", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => $e->getMessage()
        ], 500);
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
                $config = DB::table('instagram_configurations')
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
    $rawUrl = $request->url;
    $mode = $request->mode;
    $type_media = $request->type_media ?? null;
    $instagramMediaType = $request->instagram_media_type ?? 'REELS';

    Log::info("📸 [store] Processing", [
        'network' => $network,
        'mode' => $mode,
        'raw_url' => $rawUrl,
        'type_media' => $type_media,
        'instagram_media_type' => $instagramMediaType,
        'text' => $text
    ]);

    // ✅ Nettoyer l'URL (remplacer les espaces, etc.)
    if ($rawUrl) {
        $cleanedUrl = str_replace(' ', '%20', $rawUrl);
        $cleanedUrl = str_replace(['\\', '"', '<', '>'], '', $cleanedUrl);
        $url = str_replace('\/\/', '/', $cleanedUrl);
    } else {
        $url = null;
    }
    $caption = $request->caption;

    $client = new Client([
        'timeout' => 60.0,
    ]);

    $tempFilePath = null;
    $tempFileName = null;

    // ✅ Pour Instagram, vérifier et redimensionner l'image si nécessaire (avec GD)
    if ($network == 'instagram' && $type_media != 'video_url' && $url) {
        try {
            Log::info("🖼️ [store] Processing image for Instagram with GD", ['url' => $url]);

            // Télécharger l'image
            $imageContent = file_get_contents($url);
            if ($imageContent === false) {
                throw new \Exception('Impossible de télécharger l\'image depuis l\'URL');
            }

            // Créer une image avec GD
            $image = imagecreatefromstring($imageContent);
            if ($image === false) {
                throw new \Exception('Format d\'image non supporté');
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $aspectRatio = $width / $height;

            Log::info("📐 [store] Original image dimensions", [
                'width' => $width,
                'height' => $height,
                'aspect_ratio' => $aspectRatio
            ]);

            // Instagram accepte les ratios entre 0.8 (4:5) et 1.91 (1.91:1)
            $minRatio = 0.8;
            $maxRatio = 1.91;

            if ($aspectRatio < $minRatio || $aspectRatio > $maxRatio) {
                Log::warning("⚠️ [store] Aspect ratio not supported, resizing", [
                    'current' => $aspectRatio,
                    'min' => $minRatio,
                    'max' => $maxRatio
                ]);

                // Calculer les nouvelles dimensions
                if ($aspectRatio < $minRatio) {
                    // Trop vertical (hauteur > largeur)
                    $newWidth = $width;
                    $newHeight = (int)($width / $minRatio);
                } else {
                    // Trop horizontal (largeur > hauteur)
                    $newWidth = (int)($height * $maxRatio);
                    $newHeight = $height;
                }

                // Créer une nouvelle image redimensionnée avec fond blanc
                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                // Remplir avec du blanc
                $white = imagecolorallocate($resizedImage, 255, 255, 255);
                imagefill($resizedImage, 0, 0, $white);

                // Redimensionner l'image en conservant le ratio
                imagecopyresampled(
                    $resizedImage, $image,
                    (int)(($newWidth - $width) / 2),
                    (int)(($newHeight - $height) / 2),
                    0, 0,
                    $width, $height,
                    $width, $height
                );

                // Créer le dossier temp
                $tempDir = public_path('docs/temp');
                if (!File::exists($tempDir)) {
                    File::makeDirectory($tempDir, 0755, true);
                }

                $tempFileName = uniqid() . '.jpg';
                $tempPath = $tempDir . '/' . $tempFileName;

                // Sauvegarder en JPEG
                imagejpeg($resizedImage, $tempPath, 90);

                // Libérer la mémoire
                imagedestroy($image);
                imagedestroy($resizedImage);

                Log::info("✅ [store] Image resized and saved with GD", [
                    'temp_path' => $tempPath,
                    'temp_url' => asset('docs/temp/' . $tempFileName),
                    'new_width' => $newWidth,
                    'new_height' => $newHeight,
                    'new_ratio' => $newWidth / $newHeight
                ]);

                // Utiliser l'URL publique de l'image redimensionnée
                $url = asset('docs/temp/' . $tempFileName);
                $tempFilePath = $tempPath;

            } else {
                Log::info("✅ [store] Aspect ratio is valid", [
                    'ratio' => $aspectRatio
                ]);
                imagedestroy($image);
            }
        } catch (\Exception $e) {
            Log::error("❌ [store] Image processing error: " . $e->getMessage());
            // Continuer avec l'image originale si le traitement échoue
        }
    }

    // Build API URL based on network and content type
    $apiUrl = "https://graph.facebook.com/v22.0/{$pageIdInstagramId}/";

    // Handle Facebook text-only posts (feed)
    if ($network == 'facebook' && $text == 'feed') {
        $apiUrl .= "feed";
        $params = [
            'json' => [
                'message' => $caption,
                'access_token' => $accessToken,
            ]
        ];
        Log::info("📝 [store] Creating text-only Facebook post", [
            'api_url' => $apiUrl,
            'message_length' => strlen($caption)
        ]);
    }
    // Handle Facebook photo posts
    elseif ($network == 'facebook' && $text == 'photos') {
        $apiUrl .= "photos";

        // ✅ Vérifier que l'URL est accessible
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inaccessible image URL'
            ], 400);
        }

        $params = [
            'json' => [
                'caption' => $caption,
                'url' => $url,
                'access_token' => $accessToken,
            ]
        ];
        Log::info("📸 [store] Creating Facebook photo post", [
            'api_url' => $apiUrl,
            'url' => $url
        ]);
    }
    // Handle Facebook video posts
    elseif ($network == 'facebook' && $text == 'videos') {
        $apiUrl .= "videos";

        // ✅ Vérifier que l'URL est accessible
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inaccessible video URL'
            ], 400);
        }

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
                    'contents' => $url,
                ],
                [
                    'name' => 'access_token',
                    'contents' => $accessToken,
                ],
            ],
        ];
        Log::info("🎬 [store] Creating Facebook video post", [
            'api_url' => $apiUrl,
            'url' => $url
        ]);
    }
    // Handle Instagram posts
    elseif ($network == 'instagram') {
        // Instagram API - upload media first
        $apiUrl .= "media";

        if ($type_media == 'video_url') {
            // Video post for Instagram
            $params = [
                'json' => [
                    'media_type' => $instagramMediaType, // 'REELS' or 'VIDEO'
                    'caption' => $caption,
                    'video_url' => $url,
                    'access_token' => $accessToken,
                ]
            ];
            Log::info("🎬 [store] Creating Instagram video post", [
                'api_url' => $apiUrl,
                'video_url' => $url,
                'media_type' => $instagramMediaType
            ]);
        } else {
            // Image post (default)
            // ✅ Vérifier que l'URL est accessible
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or inaccessible image URL'
                ], 400);
            }

            $params = [
                'json' => [
                    'caption' => $caption,
                    'image_url' => $url,
                    'access_token' => $accessToken,
                ]
            ];
            Log::info("🖼️ [store] Creating Instagram image post", [
                'api_url' => $apiUrl,
                'image_url' => $url
            ]);
        }
    } else {
        // Invalid combination
        return response()->json([
            'success' => false,
            'message' => 'Invalid network or content type combination',
            'network' => $network,
            'text' => $text,
            'type_media' => $type_media
        ], 400);
    }

    try {
        Log::info("📤 [store] Sending request to Facebook API", [
            'api_url' => $apiUrl,
            'params' => array_keys($params)
        ]);

        // Send POST request to create the post
        $response = $client->post($apiUrl, $params);
        $responseBody = json_decode($response->getBody(), true);
        $statusCode = $response->getStatusCode();

        Log::info("📨 [store] API Response", [
            'status_code' => $statusCode,
            'response' => $responseBody
        ]);

        if (isset($responseBody['id'])) {
            // Instagram requires a second step to publish the media
            if ($network == 'instagram') {
                $mediaId = $responseBody['id'];

                // Step 2: Poll Media Status (Wait Until "FINISHED")
                $maxAttempts = 15; // Increased for videos which take longer
                $attempt = 0;
                $statusBody = [];

                do {
                    sleep(5); // Wait 5 seconds before checking status
                    $statusUrl = "https://graph.facebook.com/v22.0/{$mediaId}?fields=status_code&access_token={$accessToken}";
                    $statusResponse = $client->get($statusUrl);
                    $statusBody = json_decode($statusResponse->getBody(), true);

                    Log::info("📊 [store] Instagram media status", [
                        'attempt' => $attempt + 1,
                        'media_id' => $mediaId,
                        'status' => $statusBody['status_code'] ?? 'unknown'
                    ]);

                    if (isset($statusBody['status_code']) && $statusBody['status_code'] == "FINISHED") {
                        break;
                    }

                    $attempt++;
                } while ($attempt < $maxAttempts);

                // Check if media processing completed
                if (!isset($statusBody['status_code']) || $statusBody['status_code'] !== "FINISHED") {
                    // ✅ Nettoyer le fichier temporaire
                    if ($tempFilePath && File::exists($tempFilePath)) {
                        File::delete($tempFilePath);
                        Log::info("🗑️ [store] Temporary file deleted", ['path' => $tempFilePath]);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Media processing not completed',
                        'status' => $statusBody,
                        'media_id' => $mediaId
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

                Log::info("📤 [store] Publishing Instagram media", [
                    'publish_url' => $publishUrl,
                    'creation_id' => $responseBody['id']
                ]);

                $publishResponse = $client->post($publishUrl, $publishParams);
                $publishResponseBody = json_decode($publishResponse->getBody(), true);

                // ✅ Nettoyer le fichier temporaire
                if ($tempFilePath && File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                    Log::info("🗑️ [store] Temporary file deleted", ['path' => $tempFilePath]);
                }

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

            // For Facebook posts (including text-only)
            return response()->json([
                'success' => true,
                'message' => 'Post created successfully!',
                'post_id' => $responseBody['id'],
                'network' => $network,
                'url' => $url,
                'mode' => $mode
            ], 200);
        } else {
            // Check if there's an error in the response
            if (isset($responseBody['error'])) {
                $errorMessage = $responseBody['error']['message'] ?? 'Unknown error';
                $errorCode = $responseBody['error']['code'] ?? 'unknown';

                Log::error("❌ [store] Facebook API Error", [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'error_data' => $responseBody['error']
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Facebook API Error: ' . $errorMessage,
                    'error_code' => $errorCode,
                    'error' => $responseBody['error']
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'error' => $responseBody
            ], 500);
        }
    } catch (\Exception $e) {
        // ✅ Nettoyer le fichier temporaire en cas d'erreur
        if ($tempFilePath && File::exists($tempFilePath)) {
            File::delete($tempFilePath);
            Log::info("🗑️ [store] Temporary file deleted on error", ['path' => $tempFilePath]);
        }

        Log::error("💥 [store] Exception", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

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

              // $databaseName = "Erp_" . $raison_sociale_concatene . "_" . $societe->id;
                 $databaseName = env('DB_DATABASE');

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
                            {/*if ($objectType === 'instagram') {
                                // Instagram messaging (direct messages)
                                $projet_id=$this->getProjet_id_from_page_id($pageId,'instagram');
                               // $this->handleInstagramMessaging($messaging, $societeId, $pageId,$projet_id);
                            } else */}
                            if ($objectType === 'page') {
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
            Config::set('broadcasting.default', 'pusher_notify');

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
                $this->handleInstagramComment($change,$pageId,$projet_id);
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
        private function createFacebookNotification($description, $link = null, $type = null, $projet_id)
            {
                try {
                    Log::info('Creating Facebook notification:', [
                        'description' => $description,
                        'link' => $link,
                        'type' => $type
                    ]);

                    // ✅ CHECK: If notification with same link and type exists in the last 5 minutes
                    $existingNotification = Notification::on('temp')
                        ->where('type', $type)
                        ->where('lien', $link)
                        ->where('description_type', $description)
                        ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                        ->first();

                    if ($existingNotification) {
                        Log::info('⚠️ Duplicate notification detected, skipping creation', [
                            'existing_id' => $existingNotification->id,
                            'type' => $type,
                            'link' => $link
                        ]);
                        return;
                    }

                    // Create notification
                    $notification = new \App\Models\Notification();
                    $notification->setConnection('temp');

                    $notification->date = now()->format('Y-m-d H:i:s');
                    $notification->type = $type;
                    $notification->description_type = $description;
                    $notification->lien = $link ?? 'https://www.facebook.com';
                    $notification->role = \App\Enum\RoleEnum::ADMIN_COMMERCIAL->value;
                    $notification->projet_id = $projet_id;
                    $notification->save();

                    // Broadcast the notification
                    Config::set('broadcasting.default', 'pusher_notify');
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
        private function handleInstagramComment($data,$pageId,$projet_id)
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


           private function validatePhoneNumber($phoneNumber)
            {
                // Remove all non-digit characters except + sign
                $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

                // Count digits (excluding + sign)
                $digitCount = strlen(preg_replace('/[^0-9]/', '', $cleaned));

                // Check minimum length
                if ($digitCount < 9) {
                    return [
                        'is_valid' => false,
                        'error_message' => "❌ Numéro trop court! Le numéro doit contenir au minimum 9 chiffres. Vous avez fourni {$digitCount} chiffres."
                    ];
                }

                // Check maximum reasonable length
                if ($digitCount > 15) {
                    return [
                        'is_valid' => false,
                        'error_message' => "❌ Numéro trop long! Le numéro semble invalide."
                    ];
                }

                // Define country codes and their patterns
                $countryPatterns = [
                    // Morocco
                    'MA' => [
                        'code' => '+212',
                        'patterns' => [
                            '/^(?:\+212|00212|212|0)([5-7]\d{8})$/',
                            '/^[5-7]\d{8}$/',
                        ],
                        'example' => '+2126XXXXXXXX',
                        'name' => 'Maroc'
                    ],
                    // France
                    'FR' => [
                        'code' => '+33',
                        'patterns' => [
                            '/^(?:\+33|0033|33|0)([1-9]\d{8})$/',
                            '/^0[1-9](\d{2}){4}$/', // 01 23 45 67 89
                        ],
                        'example' => '+331XXXXXXXX',
                        'name' => 'France'
                    ],
                    // Turkey
                    'TR' => [
                        'code' => '+90',
                        'patterns' => [
                            '/^(?:\+90|0090|90|0)([2-9]\d{9})$/',
                            '/^0[2-9]\d{9}$/',
                        ],
                        'example' => '+905XXXXXXXXX',
                        'name' => 'Turquie'
                    ],
                    // Algeria
                    'DZ' => [
                        'code' => '+213',
                        'patterns' => [
                            '/^(?:\+213|00213|213|0)([5-9]\d{8})$/',
                            '/^0[5-9]\d{8}$/',
                        ],
                        'example' => '+2135XXXXXXXX',
                        'name' => 'Algérie'
                    ],
                    // Tunisia
                    'TN' => [
                        'code' => '+216',
                        'patterns' => [
                            '/^(?:\+216|00216|216)([2-9]\d{7})$/',
                            '/^[2-9]\d{7}$/',
                        ],
                        'example' => '+2162XXXXXXX',
                        'name' => 'Tunisie'
                    ],
                    // Generic international pattern (fallback)
                    'INTERNATIONAL' => [
                        'patterns' => [
                            '/^\+\d{10,14}$/', // + followed by 10-14 digits
                            '/^00\d{10,14}$/', // 00 followed by 10-14 digits
                            '/^\d{9,15}$/', // Just digits 9-15
                        ],
                        'name' => 'International'
                    ]
                ];

                // Check against each country pattern
                foreach ($countryPatterns as $countryCode => $countryInfo) {
                    foreach ($countryInfo['patterns'] as $pattern) {
                        if (preg_match($pattern, $cleaned)) {
                            // Normalize the phone number
                            $normalized = $this->normalizePhoneNumberInternational($phoneNumber, $countryCode);

                            return [
                                'is_valid' => true,
                                'normalized' => $normalized,
                                'country' => $countryCode,
                                'country_name' => $countryInfo['name'] ?? $countryCode
                            ];
                        }
                    }
                }

                // If no pattern matches, provide helpful error message with examples
                $examples = [];
                foreach ($countryPatterns as $countryInfo) {
                    if (isset($countryInfo['example'])) {
                        $examples[] = $countryInfo['example'];
                    }
                }

                $errorMsg = "❌ Format de numéro invalide. Formats acceptés:\n\n";

                // Add specific country examples
                $errorMsg .= "**Maroc (MA)**:\n";
                $errorMsg .= "• 06XXXXXXXX (10 chiffres)\n";
                $errorMsg .= "• +2126XXXXXXXX (13 chiffres)\n";
                $errorMsg .= "• 5XXXXXXXX (9 chiffres)\n\n";

                $errorMsg .= "**France (FR)**:\n";
                $errorMsg .= "• 01XXXXXXXX (10 chiffres)\n";
                $errorMsg .= "• +331XXXXXXXX (12 chiffres)\n\n";

                $errorMsg .= "**Turquie (TR)**:\n";
                $errorMsg .= "• 05XXXXXXXXXX (11 chiffres)\n";
                $errorMsg .= "• +905XXXXXXXXX (13 chiffres)\n\n";

                $errorMsg .= "**Algérie (DZ)**:\n";
                $errorMsg .= "• 05XXXXXXXX (10 chiffres)\n";
                $errorMsg .= "• +2135XXXXXXXX (13 chiffres)\n\n";

                $errorMsg .= "**Général**:\n";
                $errorMsg .= "• Le numéro doit contenir 9 à 15 chiffres\n";
                $errorMsg .= "• Format international recommandé: +CodePays Numéro\n";
                $errorMsg .= "• Ex: +212612345678 (Maroc), +331234567890 (France)";

                return [
                    'is_valid' => false,
                    'error_message' => $errorMsg
                ];
            }

/**
 * Normalize phone number to international format
 */
        private function normalizePhoneNumberInternational($phoneNumber, $countryCode = null)
        {
            $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);

            // If country code is provided, use specific normalization
            if ($countryCode) {
                switch ($countryCode) {
                    case 'MA': // Morocco
                        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                            return '+212' . substr($cleaned, 1);
                        } elseif (str_starts_with($cleaned, '00212')) {
                            return '+' . substr($cleaned, 2);
                        } elseif (str_starts_with($cleaned, '212')) {
                            return '+' . $cleaned;
                        } elseif (strlen($cleaned) === 9 && in_array($cleaned[0], ['5', '6', '7'])) {
                            return '+212' . $cleaned;
                        }
                        break;

                    case 'FR': // France
                        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
                            return '+33' . substr($cleaned, 1);
                        } elseif (str_starts_with($cleaned, '0033')) {
                            return '+' . substr($cleaned, 2);
                        } elseif (str_starts_with($cleaned, '33') && strlen($cleaned) === 11) {
                            return '+' . $cleaned;
                        }
                        break;

                    case 'TR': // Turkey
                        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
                            return '+90' . substr($cleaned, 1);
                        } elseif (str_starts_with($cleaned, '0090')) {
                            return '+' . substr($cleaned, 2);
                        } elseif (str_starts_with($cleaned, '90') && strlen($cleaned) === 12) {
                            return '+' . $cleaned;
                        }
                        break;
                }
            }

            // Generic international normalization
            if (str_starts_with($cleaned, '00')) {
                return '+' . substr($cleaned, 2);
            } elseif (str_starts_with($cleaned, '0')) {
                // Remove leading zero for local numbers
                // This is generic, might not work for all countries
                return '+?' . substr($cleaned, 1);
            } elseif (!str_starts_with($cleaned, '+')) {
                return '+' . $cleaned;
            }

            return $cleaned;
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
                                // Check if phone number was provided
                    if ($phoneNumber) {
                         // Validate phone number format and length
                         $validationResult = $this->validatePhoneNumber($phoneNumber);

                        if (!$validationResult['is_valid']) {
                            // Send error message to user about invalid phone number format
                            $errorMessage = $validationResult['error_message'] ??
                                "❌ Format de numéro de téléphone invalide. Veuillez fournir un numéro valide (ex: 06XXXXXXXX ou +2126XXXXXXXX ou 5XXXXXXXXX).";

                            $this->sendFacebookMessageFromPage($senderId, $errorMessage, $pageId);
                            Log::warning("Invalid phone number format from user", [
                                'sender_id' => $senderId,
                                'phone_number' => $phoneNumber,
                                'error' => $validationResult['error_message']
                            ]);
                        }
                                        // Get the normalized phone number from validation result
                                $normalizedPhone = $validationResult['normalized'] ?? $this->normalizePhoneNumberInternational($phoneNumber, $validationResult['country'] ?? null);

                                // Check if phone number already exists for another prospect
                                // Use the normalized phone number for duplicate checking


                        // Check if phone number already exists for another prospect
                                $Duplicate_Prospect = $this->isPhoneNumberDuplicate($normalizedPhone, $senderId, $projet_id);
                                if ($Duplicate_Prospect!=null) {
                                    // Phone number exists - ask for different number
                                    Log::info("Duplicate phone number detected", [
                                        'sender_id' => $senderId,
                                        'phone_number' => $phoneNumber,
                                         'normalized' => $normalizedPhone,
                                        'prospect_id'=>$Duplicate_Prospect->id
                                    ]);
                                            // Inform user about duplicate
                                        $duplicateMessage = "⚠️ Ce numéro de téléphone est déjà associé à un autre compte. Veuillez fournir un numéro différent ou contacter notre service client.";
                                        $this->sendFacebookMessageFromPage($senderId, $duplicateMessage, $pageId);
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
                                                        return; // Stop processing duplicate phone number

                                }
                            // User sent a valid and unique phone number - update prospect
                            $updateSuccess = $this->updateProspectWithPhoneNumber($senderName, $normalizedPhone, $societeId, $projet_id, $senderId,$message);

                            if ($updateSuccess) {
                                // Send confirmation message
                                $confirmationMessage = "✅ Merci ! Votre numéro de téléphone {$normalizedPhone} a été enregistré avec succès. Nous vous contacterons bientôt !";
                                $this->sendFacebookMessageFromPage($senderId, $confirmationMessage, $pageId);
                            } else {
                                // Error updating prospect
                                $errorMessage = "❌ Désolé, une erreur s'est produite lors de l'enregistrement de votre numéro. Veuillez réessayer.";
                                $this->sendFacebookMessageFromPage($senderId, $errorMessage, $pageId);
                            }


                    } else {
                        // Check if we already asked for phone number (to avoid infinite loop)//// Check if this is the first message from user or if we need to ask for phone number
                       // $alreadyAsked = $this->hasAskedForPhoneRecently($senderId);
                        //!alreadyAsked
                    if ($this->isFirstMessageFromUser($senderId)) {
                            // First message from user - ask for phone number
                  // Ask for phone number with updated international examples
                    $welcomeMessage = "Bonjour {$senderName} ! 👋\n\n" .
                        "Merci de nous avoir contactés. Pour mieux vous assister, pourriez-vous nous partager votre numéro de téléphone ?\n\n" .
                        "**Formats acceptés** :\n" .
                        "• **Maroc** : 06XXXXXXXX (10) ou +2126XXXXXXXX (13)\n" .
                        "• **France** : 01XXXXXXXX (10) ou +331XXXXXXXX (12)\n" .
                        "• **Turquie** : 05XXXXXXXXXX (11) ou +905XXXXXXXXX (13)\n" .
                        "• **Algérie** : 05XXXXXXXX (10) ou +2135XXXXXXXX (13)\n" .
                        "• **International** : +CodePays Numéro (9-15 chiffres)\n\n" .
                        "Veuillez inclure le code pays si vous êtes à l'étranger.";
                           $messageSent = $this->sendFacebookMessageFromPage($senderId, $welcomeMessage, $pageId);

                            if ($messageSent) {
                                $this->markAsAskedForPhone($senderId);/*===>24h*/
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
                                Config::set('broadcasting.default', 'pusher_notify');
                                broadcast(new \App\Events\NotificationEvent($notification->id));
                                Log::info("Asked user {$senderId} for phone number");
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
            // Look for phone number patterns in the message
            $patterns = [
                // International formats
                '/\+\d{10,14}\b/', // + followed by 10-14 digits
                '/00\d{10,14}\b/', // 00 followed by 10-14 digits

                // Country specific patterns
                '/(?:\+|00)?212[5-7]\d{8}\b/', // Morocco
                '/(?:\+|00)?33[1-9]\d{8}\b/', // France
                '/(?:\+|00)?90[2-9]\d{9}\b/', // Turkey
                '/(?:\+|00)?213[5-9]\d{8}\b/', // Algeria
                '/(?:\+|00)?216[2-9]\d{7}\b/', // Tunisia

                // National formats
                '/0[5-7]\d{8}\b/', // Morocco national
                '/0[1-9](\d{2}){4}\b/', // France national
                '/0[2-9]\d{9}\b/', // Turkey national

                // Generic digit sequences
                '/\b\d{9,15}\b/', // Any 9-15 digit sequence
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $message, $matches)) {
                    $potentialNumber = $matches[0];

                    // Clean the number
                    $cleaned = preg_replace('/[^\d+]/', '', $potentialNumber);

                    // Validate basic structure
                    $digitCount = strlen(preg_replace('/[^0-9]/', '', $cleaned));
                    if ($digitCount >= 9 && $digitCount <= 15) {
                        return $cleaned;
                    }
                }
            }

            return null;
        }
        /**
         * Check if phone number already exists for another user
         */
            private function isPhoneNumberDuplicate($phoneNumber, $currentSenderId, $projet_id)
        {
            try {
                // Normalize the phone number for comparison
                $normalizedPhone = $phoneNumber;

                // Ensure it's in international format for comparison
                if (!str_starts_with($phoneNumber, '+')) {
                    $validationResult = $this->validatePhoneNumber($phoneNumber);
                    if ($validationResult['is_valid']) {
                        $normalizedPhone = $validationResult['normalized'] ?? $phoneNumber;
                    }
                }

                // Also try to normalize without country code for comparison
                $phoneDigits = preg_replace('/[^0-9]/', '', $normalizedPhone);

                // Check if phone number exists for any other prospect
                $existingProspect = \App\Models\Prospect::on('temp')
                    ->where('projet_id', $projet_id)
                    ->where('telephone', '!=', '')
                    ->whereNotNull('telephone')
                    ->where(function($query) use ($normalizedPhone, $phoneDigits) {
                        // Check exact match
                        $query->where('telephone', $normalizedPhone)
                            // Check without + prefix
                            ->orWhere('telephone', 'LIKE', '%' . substr($normalizedPhone, 1) . '%')
                            // Check last 9-10 digits (most important part)
                            ->orWhere('telephone', 'LIKE', '%' . substr($phoneDigits, -9) . '%')
                            ->orWhere('telephone_num2', 'like', '%' . substr($phoneDigits, -9) . '%');
                    })
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
                return null; // On error, return null
            }
        }

        /**
         * Normalize phone number for consistent comparison
         */



        private function updateProspectWithPhoneNumber($senderName, $phoneNumber, $societeId, $projet_id, $senderId,$message)
        {
            try {
                // Normalize phone number before storing
            // Phone number should already be normalized by validatePhoneNumber
        // But we'll ensure it's properly normalized
        $normalizedPhone = $phoneNumber;

        // If it doesn't start with +, try to normalize it
        if (!str_starts_with($phoneNumber, '+')) {
            $validationResult = $this->validatePhoneNumber($phoneNumber);
            if ($validationResult['is_valid']) {
                $normalizedPhone = $validationResult['normalized'] ?? $phoneNumber;
            }
        }

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
                        $message,
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

      private function shouldAskForPhoneNumber($senderId)
{
    // Check if we've asked recently (within 24 hours)
    if (cache()->has("asked_phone_{$senderId}")) {
        return false;
    }

    // Check if user already has phone number in prospect record
    $prospect = \App\Models\Prospect::on('temp')
        ->where('facebook_id', $senderId)
        ->orWhere(function($query) use ($senderId) {
            // You might want to add other identification methods
            $query->where('telephone', 'LIKE', '%' . substr($senderId, -6) . '%');
        })
        ->first();

    // Ask for phone if no prospect exists or prospect has no phone
    return !$prospect || empty($prospect->telephone);
}*/


      //Mark that we asked this user for phone number

       private function markAsAskedForPhone($senderId)
        {
            try {
                // Store in cache for 24 hours (1440 minutes)
                cache()->put("asked_phone_{$senderId}", true, 1440); // 24h = 1440 minutes
                 Log::info("Marked user {$senderId} as asked for phone number");

            } catch (\Exception $e) {
                Log::error("Error marking asked for phone: " . $e->getMessage());
            }
        }


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
                $response = $client->post("https://graph.facebook.com/v24.0/{$pageId}/messages", [
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

                    return $userData['name'] ?? $userData['first_name'] ?? ' ';
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                            'acces_token_page' => $config->acces_token_page,
                            'acces_token_page_short_term' => $config->acces_token_page_short_terme,
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
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('facebook_configurations')) {
                    Schema::connection('temp')->create('facebook_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('page_fcb_id');
                        $table->longText('acces_token_page');//long term
                        $table->longText('acces_token_page_short_terme');
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
                    'acces_token_page' => $request->acces_token_page,//long term
                    'acces_token_page_short_terme' => $request->acces_token_page_short_term,
                    'projet_id' => $request->projet_id,
                    'webhook_enabled' => false, // Explicitly set to false
                    'webhook_verify_token' => null,
                    'webhook_subscriptions' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                        $this->subscribePageToWebhook($request->page_fcb_id, $request->acces_token_page_short_term);

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
        if (RoleHelper::AdminSup() || RoleHelper::AgentAdmin()) {
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
                        'acces_token_page' => $request->acces_token_page,//long terme
                        'acces_token_page_short_terme' => $request->acces_token_page_short_term,
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                            'acces_token_user'=>$config->acces_token_user,
                            'acces_token_user_short_term'=>$config->acces_token_user_short_terme,
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
            DatabaseHelper::Config();

            try {
                // Check if table exists, create if not
                if (!Schema::connection('temp')->hasTable('instagram_configurations')) {
                    Schema::connection('temp')->create('instagram_configurations', function (Blueprint $table) {
                        $table->id();
                        $table->string('instagram_id');
                        $table->longText('acces_token_user');//long term
                        $table->longText('acces_token_user_short_terme');
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
                    'acces_token_user_short_terme' => $request->acces_token_user_short_term,
                    'projet_id' => $request->projet_id,
                    'webhook_enabled' => true, // Explicitly set to false
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                        'acces_token_user' => $request->acces_token_user,//long term
                        'acces_token_user_short_terme' => $request->acces_token_user_short_term,
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                        'webhook_enabled' => true, // Explicitly set to false
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
                "https://graph.facebook.com/v24.0/{$pageId}/subscribed_apps",
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
        if (RoleHelper::AdminSup()|| RoleHelper::AgentAdmin()) {
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
