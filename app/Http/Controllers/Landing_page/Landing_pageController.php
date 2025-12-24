<?php

namespace App\Http\Controllers\Landing_page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\interfaceAPIs;
use App\Http\Controllers\Api\V1\ProspectController;
use Illuminate\Support\Facades\Auth;

class Landing_pageController extends Controller
{
    public function send_landing_page(Request $request)
    {

       Log::info($request->all());
       ProspectController::Store_LandingPage($request->nom,$request->prenom,$request->telephone,$request->email,$request->societe_id,$request->comment,$request->projet_id);
       return response()->json(['message' => 'Data received successfully']);

    }
}
