<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AgentFinalService;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string'
        ]);

        $sessionId = $request->session_id ?? 'session_' . uniqid();
        $agent = new AgentFinalService();
        $response = $agent->processMessage($request->message, $sessionId);

        return response()->json([
            'success' => true,
            'response' => $response,
            'session_id' => $sessionId
        ]);
    }
}
