<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Mail\ResetPasswordMail;

class AdminController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    /**
     * Handle admin login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $payload = [
            'iss' => 'gpsAPI',
            'sub' => $admin->id,
            'iat' => time(),
            'exp' => time() + (60 * 60),
            'admin' => [
                'id' => $admin->id,
                'email' => $admin->email,
                'name' => $admin->name,
                'role' => $admin->role
            ]
        ];

        $token = JWT::encode($payload, $this->key, 'HS256');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role
            ]
        ]);
    }

    /**
     * Request password reset (admin)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $admin = Admin::where('email', $request->email)->first();
        if (! $admin) {
            return response()->json(['message' => 'If your email exists, a reset link has been sent']);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $admin->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );

        $url = url('/')."/api/admin/password/reset?token={$token}&email=".urlencode($admin->email);
        Mail::to($admin->email)->send(new ResetPasswordMail($url));

        return response()->json(['message' => 'If your email exists, a reset link has been sent']);
    }

    /**
     * Reset password (admin)
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

        if (Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        $admin = Admin::where('email', $request->email)->firstOrFail();
        $admin->password = Hash::make($request->password);
        $admin->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    }

    /**
     * Create a new admin
     */
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,super_admin'
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role
            ]
        ], 201);
    }

    /**
     * Get admin profile
     */
    public function getProfile($id)
    {
        $admin = Admin::findOrFail($id);
        
        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'created_at' => $admin->created_at
            ]
        ]);
    }

    /**
     * Update admin profile
     */
    public function update(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:admins,email,'.$admin->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:admin,super_admin'
        ]);

        if ($request->has('password')) {
            $request->merge([
                'password' => Hash::make($request->password)
            ]);
        }

        $admin->update($request->only(['name', 'email', 'password', 'role']));

        return response()->json([
            'message' => 'Admin profile updated successfully',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role
            ]
        ]);
    }

    /**
     * Get all admins
     */
    public function index()
    {
        $admins = Admin::select('id', 'name', 'email', 'role', 'created_at')->get();
        return response()->json($admins);
    }

    /**
     * Delete an admin
     */
    public function delete($id)
    {
        $admin = Admin::findOrFail($id);
        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully'
        ]);
    }
}