<?php

namespace App\Http\Controllers\WhatsApp;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\DatabaseHelper;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Config;
use App\Events\NotificationEvent;

class WhatsAppController extends Controller
{
     // Facebook Webhook Verification
     public function webhook_whtsp(Request $request)
     {
          // Log incoming messages for debugging
         Log::info('UltraMsg Webhook Received:', $request->all());
         DatabaseHelper::Config(10);
         Config::set('broadcasting.default', 'pusher_3');
         // Extraire les données du webhook
         $data = $request->input('data');

         // Extraire les valeurs spécifiques
         $from = $data['from'] ?? 'Unknown'; // Numéro de l'expéditeur
         $pushname = $data['pushname'] ?? 'Unknown'; // Nom de l'expéditeur
         $message = $data['body'] ?? 'No message'; // Message reçu
         $timestamp = $data['time'] ?? time(); // Timestamp Unix
        if($data['type']=='chat'){
            $type='whatsapp_message';
        }elseif($data['type']=='location'){
            $type='whatsapp_location';
        }
        elseif($data['type']=='image'){
            $type='whatsapp_image';
        }
        elseif($data['type']=='audio'||$data['type']=='ptt'){
            $type='whatsapp_audio';
        }
        elseif($data['type']=='video'){
            $type='whatsapp_video';
        }else{
            $type='unknown';
        }
         // Convertir le timestamp en format lisible
         $formattedDate = date('Y-m-d H:i:s', $timestamp);

         $web=new WebhookEvent();
         $web->setConnection('temp');
         $web->platform='whatsap';
         $web->type=$type;
         $web->data=$request->all();
         $web->save();
         broadcast(new NotificationEvent(0));

         // Log des informations reçues
         $typep=$data['type'];
         Log::info("From: $from ($pushname) | Message: $message | Timestamp: $formattedDate | type $typep");

         return response()->json(['status' => 'success']);

     }


}

