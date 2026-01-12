<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\VerifyEmailMail;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    /**
     * Handle user registration request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'matriculation_number' => 'required|string|unique:users',
            'faculty' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'matriculation_number' => $request->matriculation_number,
            'faculty' => $request->faculty,
        ]);

        $payload = [
            'iss' => 'gpsAPI',
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * 60),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]
        ];

        $token = JWT::encode($payload, $this->key, 'HS256');

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ], 201);
    }

    /**
     * Send email verification link
     */
    public function sendEmailVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $url = URL::temporarySignedRoute(
            'verification.verify', Carbon::now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::to($user->email)->send(new VerifyEmailMail($url));

        return response()->json(['message' => 'Verification email sent']);
    }

    /**
     * Verify email from signed URL
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired verification link'], 400);
        }

        $user = User::find($id);
        if (! $user || sha1($user->email) !== $hash) {
            return response()->json(['message' => 'Invalid verification data'], 400);
        }

        $user->email_verified_at = Carbon::now();
        $user->save();

        return redirect('http://localhost:5173/verifysuccess'); // Redirect to a success page
    }

    /**
     * Request password reset (user)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            // For security don't reveal existence
            return response()->json(['message' => 'If your email exists, a reset link has been sent']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );

        $url = url('http://localhost:5173/resetpassword')."/?token={$token}&email=".urlencode($user->email);
        Mail::to($user->email)->send(new ResetPasswordMail($url));

        return response()->json(['message' => 'If your email exists, a reset link has been sent']);
    }

    /**
     * Reset password (user)
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (! $row || ! hash_equals($row->token, $request->token)) {
            return response()->json(['message' => 'Invalid token or email'], 400);
        }

        // expire after 60 minutes
        if (Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    }

    /**
     * Handle user login request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $payload = [
            'iss' => 'gpsAPI',
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + (60 * 60), // Token expires in 1 hour
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name
            ]
        ];

        $token = JWT::encode($payload, $this->key, 'HS256');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    /**
     * Handle user logout request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // JWT is stateless, so we don't need to invalidate the token
        // The client should remove the token from their storage
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Verify JWT token
     *
     * @param string $token
     * @return object|false
     */
    public function verifyToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            // Log the detailed JWT error for debugging (local only)
            if (function_exists('logger')) {
                logger()->error('JWT decode error: ' . $e->getMessage());
            }
            // Re-throw so callers can decide how to handle and return details in debug
            throw $e;
        }
    }

    /**
     * Get user profile
     * If no ID is provided, returns the authenticated user's profile
     */
    public function getProfile(Request $request, $id = null)
    {
        try {
            // If no ID provided, try to get user ID from the JWT token
            if ($id === null) {
                $token = $request->bearerToken();
                if (!$token) {
                    return response()->json([
                        'message' => 'No token provided'
                    ], 401);
                }

                $decoded = $this->verifyToken($token);
                if (!$decoded) {
                    return response()->json([
                        'message' => 'Invalid token'
                    ], 401);
                }

                $id = $decoded->user->id;
            }

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'matriculation_number' => $user->matriculation_number,
                    'faculty' => $user->faculty,
                    'created_at' => $user->created_at
                
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching the user profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8',
            'matriculation_number' => 'sometimes|string|unique:users,matriculation_number,'.$user->id,
            'faculty' => 'sometimes|string|max:255'
        ]);

        if ($request->has('password')) {
            $request->merge([
                'password' => Hash::make($request->password)
            ]);
        }

        $user->update($request->only([
            'name',
            'email',
            'password',
            'matriculation_number',
            'faculty'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'matriculation_number' => $user->matriculation_number,
                'faculty' => $user->faculty
            ]
        ]);
    }

    public function getAllUsers(Request $request)
    {
         try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        
            $users = User::select('id', 'name', 'email', 'matriculation_number', 'faculty', 'created_at')->get();
            return response()->json($users);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
}