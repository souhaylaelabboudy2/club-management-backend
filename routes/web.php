<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Mail\TicketMail;
use App\Models\Club_member;
use Illuminate\Support\Facades\Log;


/*
|--------------------------------------------------------------------------
| WEB ROUTES - All API routes with session support
|--------------------------------------------------------------------------
*/
Route::get('/force-clear', function() {
    \Artisan::call('config:clear');
    \Artisan::call('cache:clear');
    \Artisan::call('config:cache');
    
    return response()->json([
        'message' => 'Config cleared and cached',
        'new_config' => [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
        ]
    ]);
});
// Auth routes (public - no auth required)
Route::post('/api/login', [AuthController::class, 'login']);
Route::post('/api/register', [AuthController::class, 'register']);

// Google OAuth routes (public) - IMPORTANT: These need web middleware for sessions
Route::prefix('/api/auth/google')->middleware('web')->group(function () {
    // For login flow
    Route::get('/', [GoogleAuthController::class, 'loginRedirect'])->name('google.login');
    Route::get('/callback', [GoogleAuthController::class, 'loginCallback'])->name('google.callback');
    
    // For linking existing account
    Route::get('/link', [GoogleAuthController::class, 'linkRedirect'])->name('google.link');
    Route::get('/link/callback', [GoogleAuthController::class, 'linkCallback'])->name('google.link.callback');
});

// Session verification route (public - checks session internally)
// IMPORTANT: This route should NOT have auth middleware!
Route::get('/api/verify-session', function (Request $request) {
    try {
        Log::info('=== VERIFY SESSION START ===', [
            'session_id' => session()->getId(),
            'auth_check' => Auth::check(),
            'auth_id' => Auth::id(),
            'cookies' => $request->cookies->all(),
            'session_data' => $request->session()->all()
        ]);

        // Check if user is authenticated
        if (!Auth::check()) {
            Log::warning('Verify session failed - not authenticated');
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $person = Auth::user();
        
        Log::info('User found in session', [
            'person_id' => $person->id,
            'email' => $person->email,
            'role' => $person->role
        ]);
        
        // Check if account is active
        if (!$person->is_active) {
            Log::warning('Account is not active', ['person_id' => $person->id]);
            Auth::logout();
            return response()->json(['message' => 'Compte désactivé'], 401);
        }

        // Get club role if applicable
        $clubRole = null;
        $clubId = null;
        
        if ($person->role === 'user') {
            $membership = Club_member::where('person_id', $person->id)
                ->where('status', 'active')
                ->orderByRaw("FIELD(role, 'president', 'board', 'member')")
                ->first();
                
            if ($membership) {
                $clubRole = $membership->role;
                $clubId = $membership->club_id;
            }
            
            Log::info('Club membership found', [
                'club_role' => $clubRole,
                'club_id' => $clubId
            ]);
        }

        Log::info('Session verified successfully', [
            'person_id' => $person->id,
            'role' => $person->role,
            'club_role' => $clubRole,
            'club_id' => $clubId
        ]);
        
        Log::info('=== VERIFY SESSION END - SUCCESS ===');

        return response()->json([
            'user' => [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'email' => $person->email,
                'avatar' => $person->avatar,
                'avatar_url' => $person->avatar ? asset('storage/' . $person->avatar) : null,
                'member_code' => $person->member_code,
                'club_id' => $clubId,
            ],
            'role' => $person->role,
            'club_role' => $clubRole,
            'club_id' => $clubId
        ], 200);

    } catch (\Exception $e) {
        Log::error('Session verification error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Erreur serveur'], 500);
    }
})->middleware('web'); // IMPORTANT: Use web middleware for session access

// Public routes (no auth required)
Route::post('/api/public/persons', [PersonController::class, 'store']);
Route::post('/api/public/members', [MemberController::class, 'store']);

Route::get('/api/clubs', [ClubController::class, 'index']);
Route::get('/api/clubs/{id}', [ClubController::class, 'show']);
Route::get('/api/clubs/code/{code}', [ClubController::class, 'showByCode']);
Route::get('/api/clubs/{id}/statistics', [ClubController::class, 'statistics']);

Route::get('/api/events/upcoming/list', [EventController::class, 'upcoming']);
Route::get('/api/events/past/completed', [EventController::class, 'pastEvents']);
Route::get('/api/events/club/{clubId}', [EventController::class, 'getByClub']);
Route::get('/api/events', [EventController::class, 'index']);
Route::get('/api/events/{id}', [EventController::class, 'show']);

Route::get('/api/members', [MemberController::class, 'index']);
Route::get('/api/members/{id}', [MemberController::class, 'show']);
Route::get('/api/clubs/{clubId}/stats', [MemberController::class, 'getClubStats']);

Route::get('/api/tickets/qr/{qrCode}', [TicketController::class, 'showByQRCode']);

// ============================================
// PROTECTED ROUTES - require auth:web
// ============================================
Route::middleware(['auth:web'])->prefix('/api')->group(function () {
    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Google Account Management (protected routes)
    Route::get('/google/status', [GoogleAuthController::class, 'checkGoogleStatus']);
    Route::post('/google/unlink', [GoogleAuthController::class, 'unlinkGoogle']);

    // Clubs
    Route::get('/my-club', [ClubController::class, 'getMyClub']);
    Route::post('/clubs', [ClubController::class, 'store']);
    Route::put('/clubs/{id}', [ClubController::class, 'update']);
    Route::delete('/clubs/{id}', [ClubController::class, 'destroy']);
    Route::patch('/clubs/{id}/members/count', [ClubController::class, 'updateMemberCounts']);

    // Events
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::patch('/events/{id}/status', [EventController::class, 'updateStatus']);
    Route::post('/events/{id}/recap', [EventController::class, 'addRecap']);

    // Members
    Route::post('/members', [MemberController::class, 'store']);
    Route::put('/members/{id}', [MemberController::class, 'update']);
    Route::get('/my-club-membership', [MemberController::class, 'getMyClubMembership']);
    Route::delete('/members/{id}', [MemberController::class, 'destroy']);
    Route::get('/persons/{personId}/clubs', [MemberController::class, 'getPersonClubs']);

    // Persons
    Route::get('/persons', [PersonController::class, 'index']);
    Route::post('/persons', [PersonController::class, 'store']);
    Route::get('/persons/{id}', [PersonController::class, 'show']);
    Route::put('/persons/{id}', [PersonController::class, 'update']);
    Route::delete('/persons/{id}', [PersonController::class, 'destroy']);
    Route::post('/persons/{id}/reactivate', [PersonController::class, 'reactivate']);
    Route::put('/persons/{id}/password', [PersonController::class, 'updatePassword']);
    Route::get('/persons/search/query', [PersonController::class, 'search']);

    // Tickets
    
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::post('/tickets/scan-qr', [TicketController::class, 'scanByQRData']); // <-- ADD THIS LINE
    Route::post('/tickets/{id}/scan', [TicketController::class, 'scan']);
    Route::post('/tickets/{id}/cancel', [TicketController::class, 'cancel']);
    Route::get('/events/{eventId}/tickets/stats', [TicketController::class, 'getEventStats']);

    // Requests
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/requests/{id}', [RequestController::class, 'show']);
    Route::put('/requests/{id}', [RequestController::class, 'update']);
    Route::delete('/requests/{id}', [RequestController::class, 'destroy']);
    Route::post('/requests/{id}/validate', [RequestController::class, 'validate']);
    Route::get('/clubs/{clubId}/requests/stats', [RequestController::class, 'getClubStats']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/bulk', [NotificationController::class, 'bulkCreate']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/persons/{personId}/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/persons/{personId}/notifications/read', [NotificationController::class, 'deleteAllRead']);
    Route::get('/persons/{personId}/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::get('/persons/{personId}/notifications/stats', [NotificationController::class, 'getStats']);
});
Route::get('/test-member-email', function() {
    \Log::info('=== DIRECT EMAIL TEST START ===');
    
    $person = \App\Models\Person::first();
    $club = \App\Models\Club::first();
    
    if (!$person || !$club) {
        return 'ERROR: No person or club found in database';
    }
    
    \Log::info('Found person and club', [
        'person' => $person->email,
        'club' => $club->name
    ]);
    
    try {
        \Log::info('Attempting to send email...');
        Mail::to($person->email)->send(new \App\Mail\WelcomeEmail($person, $club, 'member'));
        \Log::info('=== EMAIL SENT SUCCESSFULLY ===');
        return 'SUCCESS! Email sent to ' . $person->email . ' - Check your inbox!';
    } catch (\Exception $e) {
        \Log::error('=== EMAIL FAILED ===', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        return 'FAILED: ' . $e->getMessage();
    }
});
// In routes/web.php
Route::get('/check-mail-config', function() {
    return response()->json([
        'mailer' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'port' => config('mail.mailers.smtp.port'),
        'username' => config('mail.mailers.smtp.username'),
        'encryption' => config('mail.mailers.smtp.encryption'),
        'from_address' => config('mail.from.address'),
        'from_name' => config('mail.from.name'),
    ]);
});