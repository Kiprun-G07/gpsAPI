<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ChatbotQuestion;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ChatbotController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    public function generateResponse(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');

        // Simple keyword-based responses
       


        $responseMessage = ChatbotQuestion::search($userMessage)->first();

        if ($responseMessage) {
            $responseMessage = $responseMessage->answer;
        } else {
            $responseMessage = "I'm sorry, I don't have an answer for that right now.";
        }

        return response()->json([
            'response' => $responseMessage,
        ], 200);
    }
}