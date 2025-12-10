<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\AdminController;
use App\Http\Controllers\EventController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Handle preflight OPTIONS requests
Route::options('/{any}', function () {
    return response()->json(['status' => 'success'], 200);
})->where('any', '.*');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/chatbot/message', [\App\Http\Controllers\ChatbotController::class, 'generateResponse']);

Route::get('/user/profile/{id?}', [AuthController::class, 'getProfile']);
// Email verification
Route::post('/email/verify/request', [AuthController::class, 'sendEmailVerification']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

// Password reset (user)
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Admin routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login']);
    
    // Protected admin routes
    Route::middleware(\App\Http\Middleware\JwtMiddleware::class)->group(function () {
        Route::post('/create', [AdminController::class, 'create']);
        Route::get('/list', [AdminController::class, 'index']);
        Route::put('/{id}', [AdminController::class, 'update']);
        Route::delete('/{id}', [AdminController::class, 'delete']);
    });

    Route::post('/password/forgot', [AdminController::class, 'forgotPassword']);
    Route::post('/password/reset', [AdminController::class, 'resetPassword']);

    Route::get('/users', [AuthController::class, 'getAllUsers']);
    Route::get('/eventattendees', [EventController::class, 'listAttendeesForAllEvents']);
});

Route::prefix('events')->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::prefix('{id}')->group(function () {
        Route::get('/', [EventController::class, 'show']);
        Route::post('/attend', [EventController::class, 'attend']);
        Route::post('/join-crew', [EventController::class, 'assignCrewMember']);
        Route::get('/joined', [EventController::class, 'checkIfJoinedAsCrewOrAttendee']);
    });
});

// Admin password reset
Route::post('admin/password/forgot', [AdminController::class, 'forgotPassword']);
Route::post('admin/password/reset', [AdminController::class, 'resetPassword']);

// Protected user routes
Route::middleware(\App\Http\Middleware\JwtMiddleware::class)->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // Return user profile. If {id} is provided return that user's profile, otherwise use token.
    Route::get('/user/{id?}', [AuthController::class, 'getProfile']);
    
    // User profile routes
    Route::prefix('user')->group(function () {
        Route::put('/{id}', [AuthController::class, 'updateProfile']);
    });

    // Admin profile routes
    Route::prefix('admin/profile')->group(function () {
        Route::get('/{id?}', [AdminController::class, 'getProfile']);
        Route::put('/{id}', [AdminController::class, 'update']);
    });

    // Event routes
    Route::prefix('events')->group(function () {
        Route::post('/create', [EventController::class, 'create']);
        Route::put('/{id}', [EventController::class, 'update']);
        Route::delete('/{id}', [EventController::class, 'delete']);
    });
});