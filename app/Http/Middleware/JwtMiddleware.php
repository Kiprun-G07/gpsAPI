<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $authController = new AuthController();
            // verifyToken now throws on error, so allow exception to be caught below
            $decoded = $authController->verifyToken($token);

            // Add user data to request for further use
            // Request input bags expect scalars/arrays, not stdClass objects — convert to array
            $userArray = json_decode(json_encode($decoded->user), true);
            $request->merge(['user' => $userArray]);

            return $next($request);
        } catch (Exception $e) {
            // Log the exception for server-side debugging
            Log::error('JWT middleware error: ' . $e->getMessage());

            // In debug mode include the exception message in the response to help debugging
            $debug = env('APP_DEBUG', false);
            $body = ['error' => 'Invalid token'];
            if ($debug) {
                $body['details'] = $e->getMessage();
            }

            return response()->json($body, 401);
        }
    }
}