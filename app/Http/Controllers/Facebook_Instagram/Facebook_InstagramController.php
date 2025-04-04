<?php

namespace App\Http\Controllers\Facebook_Instagram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
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
use App\Models\ConfigurationSocialNetwork;
use App\Http\Helpers\RoleHelper;


class Facebook_InstagramController extends Controller
{



        //get posts https://graph.facebook.com/v22.0/537798629425112/feed?access_token=EAAI3GumKq0oBOy1YU24xu5XOm048xx7RpSW1uvmY6zsQuwWmjiyKlZAIVFoGACdo5SXFbuZCE8n3B0cER55C82wSvadDbfEqUnVcjfftR0SZCWhBesqVXYqAtRez7ZA8rbYXUKcSo1jl3QZCVDezLxN8FH5gbk5yaIQBYe8240g2jkEdj5pNR2vkT850PbZBl0hlbRj1Eaas0XkU2uuRSTUZBw9&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors
        //pour commenter /***"https://graph.facebook.com/{page-post-id}/comments?message=I%20want%20chocolate%20cake%20! &access_token=page-access-token"  */
        //get comments           //https://graph.facebook.com/v22.0/{page-post-id}537798629425112_122104722890793117/comments?access_token=EAAI3GumKq0oBO3e3PWinEHAOpbupHdC115jYneAbK2jWQsgAW0UfSj3da54JW9ZCZBfRKn6zm1lteZBzopLobZALsZBiHkdRPuqhFSfEjY1AxTwj8vkLeUO4rjiQpnAZBDshxdL8HmkwvSXFscFcLhe42G1DtQhD0RTRRVMhZCLgtHmBAVDw4UFFY46abpNsgVcp1fHLM8iZBLbRyzmCxt3ye08b&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors


       /*
        FACEBOOK_APP_ID=623538680277834
        FACEBOOK_APP_SECRET=0a098bcc8bee14da7836f5d6f30c0613
        FACEBOOK_PAGE_ACCESS_TOKEN=EAAI3GumKq0oBOzLNB26ZBfUgaUc1ucryeCk8rbLXlQKM5PnsKIbDCRXH2z3MnBQsR62UZAa2ZBOZAlkm176I2tJqC2zNkF2Kqwb4OcZCeMVYJtNBx3w1P6jgZAybNSkD3jR9ZCGbhzwcsiup8dnt5dZCLkFHZCCeX5jEhaYUISBZAqQ62ykbVYXZBCZAlKynxGY2ZBrEKjmGoQZCKF0WixXAMb0SvNm8Ua
        */

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
                    if (str_contains($network, '3')) {
                        //your facebok page Id
                        $pageId = 537798629425112;
                       // $accessToken = 'YOUR_FACEBOOK_ACCESS_TOKEN';
                        $accessToken='EAAI3GumKq0oBO4ZC9XRiXExUc2bvQ4yNtG25am68sUZBawMjOg98j9lnOZBxzWgIRZAZBWCmy8TrCnZAK6gyIkAVG1NiTkoJZAmCsnBUcdHlVaiQw2geVjnMvuT6hM4KI7HvWQyaQDMMZAvzp9LvK902rcJZCzBsoZAxpt5TOSukJ3Yef31H7tRwjvqImIY57aFveXFTQJa3BVuZCLVmQp3ZBmDCo9TK';
                        if ($mode == 'existante') {
                            $url = str_replace('\/\/', '/', $request->img_existant_url);
                            $text = 'photos';
                        }
                        $data = [
                            'pageId_InstagramId'=>$pageId,
                            'caption' => $description,
                            'text'=>$text,
                            //'url'=>$url
                            //https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg
                            //'https://immogestion.online/coline_dev/storage/reservations/video_1.mp4'
                            'url'=> str_replace('\/\/', '/', 'https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg'),
                            'network'=>'facebook',
                            'accessToken'=>$accessToken
                        ];
                        $this->store($request->merge($data));
                        //                        // return response()->json( $this->store($request->merge($data));


                    }
                     /* return response()->json($response = Http:: timeout(60)-> post("https://graph.facebook.com/v22.0/{$pageId_InstagramId}/photos", [
                        'url' => $url, // Must be a public image URL
                        'access_token' => $accessToken,
                        'caption' => $request->description,
                     ]));   */

                    if (str_contains($network, '2')) {
                        //your instagram Id
                        $pageId = 17841454841928506;
                        //$accessToken = 'YOUR_INSTAGRAM_ACCESS_TOKEN';
                        $accessToken='EAAI3GumKq0oBOwvTWZAa8BYDCxzjwAgmeSE0DBoxndSsrUrGhAedIZCmYwkLD2H8OJaeQ7Q4Tzghlbobax0Dp3Y6gkDlIwpxNeSU6ObWv63bXC99cdGZCpqksjHKmMOMKYGtkWwCGX1dzPuY7pCElz3RFxQcpZAanfz9nbZBCJMI8b7uq0ohCHCKO';

                        if ($mode == 'existante') {
                            $url = $request->img_existant_url;
                            $type_media = 'image_url';
                        }
                        $data = [
                            'pageId_InstagramId'=>$pageId,
                            'caption' => $request->description,
                            'text'=>'media',
                            'type_media'=>$type_media,
                            //'url'=>
                            //https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg
                            //https://v.ftcdn.net/02/14/09/55/700_F_214095572_DrxHKB4RjVj8pyd3onkXlcdCQuXSlmHo_ST.mp4
                            //'https://immogestion.online/coline_dev/storage/reservations/video_1.mp4'
                            'url'=> str_replace('\/\/', '/', 'https://immogestion.online/coline_dev/storage/reservations/C529FF99-F5B3-44A2-9B61-BD895DE0555B.jpeg'),
                            'network'=>'instagram',
                            'accessToken'=>$accessToken
                        ];


                        $this->store($request->merge($data));
                        // return response()->json( $this->store($request->merge($data));

                    }

                    if (str_contains($network, '1')) {
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
                                'image' => 'https://immogestion.online/coline_dev/storage/reservations/10.png',  // Change this URL to your image URL
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

                        return response()->json(['success' => false, 'message' => 'Invalid social network selection'], 400);


                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
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




        public function getCommentsForAllPosts()
        {
            //MOI
            //https://graph.facebook.com/v22.0/537798629425112_122104722890793117/comments?access_token=EAAI3GumKq0oBO3e3PWinEHAOpbupHdC115jYneAbK2jWQsgAW0UfSj3da54JW9ZCZBfRKn6zm1lteZBzopLobZALsZBiHkdRPuqhFSfEjY1AxTwj8vkLeUO4rjiQpnAZBDshxdL8HmkwvSXFscFcLhe42G1DtQhD0RTRRVMhZCLgtHmBAVDw4UFFY46abpNsgVcp1fHLM8iZBLbRyzmCxt3ye08b&debug=all&format=json&method=get&origin_graph_explorer=1&pretty=0&suppress_http_code=1&transport=cors

            // Your Facebook Page Access Token
            $accessToken = 'your-facebook-page-access-token';

            // Facebook Page ID
            $pageId = 'your-facebook-page-id';

            // Create a new Guzzle HTTP client
            $client = new Client();

            try {
                // Fetch posts from the Facebook Page
                $url = "https://graph.facebook.com/{$pageId}/feed?access_token={$accessToken}";
                $response = $client->get($url);
                $posts = json_decode($response->getBody()->getContents(), true);

                // Initialize an array to hold all comments
                $allComments = [];

                // Loop through all posts and fetch comments for each
                foreach ($posts['data'] as $post) {
                    $postId = $post['id'];

                    // Fetch comments for the current post
                    $commentsUrl = "https://graph.facebook.com/{$postId}/comments?access_token={$accessToken}";
                    $commentsResponse = $client->get($commentsUrl);
                    $comments = json_decode($commentsResponse->getBody()->getContents(), true);

                    // Add the post ID and its comments to the result
                    $allComments[] = [
                        'post' => $post,
                        'comments' => $comments['data'] ?? []
                    ];
                }

                // Return the collected comments as a JSON response
                return response()->json($allComments);

            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to fetch posts and comments',
                    'message' => $e->getMessage()
                ], 500);
            }
        }



        // Facebook Webhook Verification
        public function verify(Request $request)
        {

                    //webhook facebook
            //https://immogestion.online/coline_dev/webhook?hub_verify_token=fadwa&hub_challenge=123456&hub_mode=subscribe
            //or ngrok
            //https://5491-105-66-132-248.ngrok-free.app/api/webhook?hub_verify_token=fadwa&hub_challenge=123456&hub_mode=subscribe
            $facebook_verify_token='fadwa';
            $hub_mode = $request->hub_mode;
            $hub_challenge = $request->hub_challenge;
            $hub_verify_token = $request->hub_verify_token;

            // Check the verify token matches your custom token
            Log::info($hub_verify_token);
            if ($hub_verify_token === $facebook_verify_token) {
                return response($hub_challenge, 200);
            }

            return response('Unauthorized', 403);
        }


        public function handleWebhook(Request $request)
        {

            // Handle Meta webhook verification
            /*if ($request->has('hub_mode') && $request->has('hub_verify_token')) {
                return $this->verifyWebhook($request);
            }*/
            // Log::info('Webhook Received:', $request->all());

            $entries = $request->input('entry', []);
            foreach ($entries as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $this->processChange($change);
                }
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        }

        private function verifyWebhook(Request $request)
        {//env('META_VERIFY_TOKEN') set fadwa in verify token webhook
            $verifyToken = 'fadwa';
            if ($request->input('hub_verify_token') === $verifyToken) {
                return response($request->input('hub_challenge'));
            }
            return response('Invalid verification token', 403);
        }

        private function detectPlatform($entry) {
           /* if (isset($entry['messaging'])) {
                return 'whatsapp';
            }*/
            if (isset($entry['changes']) && strpos($entry['id'], 'instagram') !== false) {
                return 'instagram';
            }
            return 'facebook';
        }


        private function getEventType($event) {
            // Facebook Post event Comment (Post created/updated)
            if (isset($event['value']['post'])) {
                return 'facebook_comment';
            }
            if (!isset($event['value']['post']) && isset($event['value']['post_id']) && !isset($event['value']['reaction_type'])) {
                return 'facebook_post';

            }

            // Facebook Reaction event (Like, Love, etc.)
            if (isset($event['value']['reaction_type'])) {
                return 'facebook_reaction';
            }

            /*// WhatsApp Message event
            if (isset($event['message']) && isset($event['from'])) {
                return 'whatsapp_message';
            }*/


            if (isset($event['field'])){
                // Instagram Comment event (New comment on a post)
                if($event['field'] === 'comments'){
                    return 'instagram_comment';
                }elseif($event['field'] === 'mentions'){
                     // Instagram Mention event (Mention of your account in a post/comment)
                    return 'instagram_mention';

                }
            }


            /*// Instagram Direct Message event (New DM on Instagram)
            if (isset($event['message']) && isset($event['from']['id'])) {
                return 'instagram_dm';
            }
            // WhatsApp Status Update (Account status change, like "Online", "Typing")
            if (isset($event['status']) && isset($event['from'])) {
                return 'whatsapp_status';
            }*/

            // Unrecognized event type - log for debugging
            Log::warning("Unknown event structure detected: " . json_encode($event));

            return 'unknown';
        }


        private function processChange($change)
        {
            $platform = $this->detectPlatform($change);
            $eventType = $this->getEventType($change);
            Log::info("Processing Event - Platform: $platform, Type: $eventType");
             // Store event in database
                //10 id du ste
                DatabaseHelper::Config(10);
                Config::set('broadcasting.default', 'pusher_3');
                //if commente deja existe
                //extract url du post in instagram and put it to data
                if($eventType=='instagram_comment'){
                    if (isset($change['value']['media']['id'])) {
                        $mediaId = $change['value']['media']['id'];
                        $accessToken = 'EAAI3GumKq0oBOwvTWZAa8BYDCxzjwAgmeSE0DBoxndSsrUrGhAedIZCmYwkLD2H8OJaeQ7Q4Tzghlbobax0Dp3Y6gkDlIwpxNeSU6ObWv63bXC99cdGZCpqksjHKmMOMKYGtkWwCGX1dzPuY7pCElz3RFxQcpZAanfz9nbZBCJMI8b7uq0ohCHCKO';
                        Log::info("Media Id: $mediaId, Type: $eventType");
                        // Fetch the permalink from Instagram Graph API
                        $response = Http::get("https://graph.facebook.com/v22.0/{$mediaId}", [
                            'fields' => 'permalink',
                            'access_token' => $accessToken
                        ]);

                        if ($response->successful()) {
                            $permalink = $response->json()['permalink'] ?? null;

                            // Add link_url to the data
                            $change['permalink'] =str_replace('\/\/', '/', $permalink) ;
                        }
                    }

                }
                $web=new WebhookEvent();
                $web->setConnection('temp');
                $web->platform=$platform;
                $web->type=$eventType;
                $web->data=$change;
                $web->save();
                broadcast(new NotificationEvent(0));


            $field = $change['field'] ?? null;

            switch ($field) {
                case 'feed'://fcb
                    $this->handleFacebookPost($change['value']);
                    break;
                case 'comments'://instagram
                    $this->handleFacebookComment($change['value']);
                    break;
                case 'reactions':
                    $this->handleFacebookReaction($change['value']);
                    break;
                /* 'statuses'://ne post
                    $this->handleWhatsAppMessage($change['value']);
                    break;*/
                default:
                    Log::warning('Unhandled Webhook Event: ' . $field);
            }
        }

        private function handleFacebookPost($data)
        {
            Log::info('New Post/comment on Facebook Page:', $data);
        }

        private function handleFacebookComment($data)
        {
            Log::info('New Comment on Instagram Page:', $data);
            $commentText = $data['message'] ?? 'No message';
            Log::info('User Commented: ' . $commentText);
        }

        private function handleFacebookReaction($data)
        {
            Log::info('New Reaction on Facebook Page:', $data);
        }

        private function handleWhatsAppMessage($data)
        {
            Log::info('New WhatsApp Message:', $data);
        }



        /******************************Configuration Social network*************************/



         //liste des Configurations
        public function configurations_social_network(Request $request)
        {
            if (Auth::guard('api')->check()) {
                DatabaseHelper::Config();
                $query = ConfigurationSocialNetwork::on('temp');
                $configurations = $query->orderBy('created_at', 'asc')
                        ->first();
                return response()->json(['configurations' => $configurations], 200);
            }
            return response()->json(['error' => 'Unauthorized'], 401);
        }




         //store les Configurations
        public function store_configurations_social_network(Request $request)
        {

            if (RoleHelper::AdminSup()) {
                DatabaseHelper::Config();
                $config=ConfigurationSocialNetwork::on('temp')->first();
                if($config!=null){
                        $config->setConnection('temp');
                        $config->page_fcb_id=$request->page_fcb_id;
                        $config->acces_token_page=$request->acces_token_page;
                        $config->instagram_id=$request->instagram_id;
                        $config->acces_token_user=$request->acces_token_user;
                        $config->save();

                }else{
                    $config = new ConfigurationSocialNetwork();
                        $config->setConnection('temp');
                        $config->page_fcb_id=$request->page_fcb_id;
                        $config->acces_token_page=$request->acces_token_page;
                        $config->instagram_id=$request->instagram_id;
                        $config->acces_token_user=$request->acces_token_user;
                        $config->save();

                }

                return response()->json(['configuration' => 'done'], 200);

            }else{
                return response()->json(['error' => 'Unauthorized'], 401);

            }

        }
    }

