<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Club_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google for authentication - for linking existing account
     */
    public function linkRedirect(Request $request)
    {
        try {
            Log::info('🔵 === GOOGLE LINK REDIRECT START ===');
            Log::info('Session ID: ' . session()->getId());
            Log::info('Auth check: ' . (auth()->check() ? 'YES' : 'NO'));
            Log::info('User ID: ' . (auth()->id() ?? 'NULL'));
            
            if (!auth()->check()) {
                Log::error('❌ User not authenticated for link redirect');
                return redirect('http://localhost:3000/Login/login?error=not_authenticated');
            }
            
            $userId = auth()->id();
            Log::info('✅ Storing user ID in session: ' . $userId);
            
            // Store user ID in session
            $request->session()->put('link_google_user_id', $userId);
            
            // Verify it was stored
            $storedId = $request->session()->get('link_google_user_id');
            Log::info('🔍 Verification - Stored ID: ' . ($storedId ?? 'NULL'));
            
            Log::info('🚀 Redirecting to Google OAuth...');
            
            return Socialite::driver('google')
                ->redirectUrl('http://localhost:8000/api/auth/google/link/callback')
                ->scopes(['email', 'profile'])
                ->redirect();
                
        } catch (\Exception $e) {
            Log::error('❌ LINK REDIRECT ERROR', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return redirect('http://localhost:3000/Login/AccountSetup?error=google_error');
        }
    }

    /**
     * Handle Google callback for linking account
     */
    public function linkCallback(Request $request)
    {
        try {
            Log::info('🔵 === GOOGLE LINK CALLBACK START ===');
            Log::info('Session ID: ' . session()->getId());
            Log::info('Request params: ' . json_encode($request->all()));
            
            // Step 1: Get session data
            $userId = $request->session()->get('link_google_user_id');
            Log::info('🔍 Step 1 - User ID from session: ' . ($userId ?? 'NULL'));
            Log::info('🔍 All session data: ' . json_encode($request->session()->all()));
            
            if (!$userId) {
                Log::error('❌ FAIL: No user ID in session');
                return redirect('http://localhost:3000/Login/AccountSetup?error=session_expired');
            }
            
            // Step 2: Get Google user
            Log::info('🔍 Step 2 - Attempting to get Google user...');
            
            try {
                $googleUser = Socialite::driver('google')->user();
                Log::info('✅ Step 2 SUCCESS - Google user retrieved');
                Log::info('Google ID: ' . $googleUser->getId());
                Log::info('Google Email: ' . $googleUser->getEmail());
                Log::info('Google Name: ' . $googleUser->getName());
            } catch (\Exception $e) {
                Log::error('❌ Step 2 FAIL - Could not get Google user', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return redirect('http://localhost:3000/Login/AccountSetup?error=google_error');
            }
            
            // Step 3: Find the person
            Log::info('🔍 Step 3 - Finding person with ID: ' . $userId);
            
            $person = Person::find($userId);
            
            if (!$person) {
                Log::error('❌ Step 3 FAIL - User not found', ['user_id' => $userId]);
                return redirect('http://localhost:3000/Login/AccountSetup?error=user_not_found');
            }
            
            Log::info('✅ Step 3 SUCCESS - User found', [
                'person_id' => $person->id,
                'person_email' => $person->email,
                'person_name' => $person->first_name . ' ' . $person->last_name,
            ]);
            
            // Step 4: Check if Google account already linked to another user
            Log::info('🔍 Step 4 - Checking if Google account already linked...');
            
            $existingGoogleUser = Person::where('google_id', $googleUser->getId())
                ->where('id', '!=', $userId)
                ->first();
            
            if ($existingGoogleUser) {
                Log::error('❌ Step 4 FAIL - Google account already linked to another user', [
                    'google_id' => $googleUser->getId(),
                    'existing_user_id' => $existingGoogleUser->id,
                    'existing_user_email' => $existingGoogleUser->email,
                ]);
                return redirect('http://localhost:3000/Login/AccountSetup?error=google_already_linked');
            }
            
            Log::info('✅ Step 4 SUCCESS - Google account not linked to anyone else');
            
            // Step 5: Update the person
            Log::info('🔍 Step 5 - Updating person with Google data...');
            
            try {
                $person->google_id = $googleUser->getId();
                $person->google_email = $googleUser->getEmail();
                $person->google_token = $googleUser->token;
                $person->google_refresh_token = $googleUser->refreshToken;
                $person->save();
                
                Log::info('✅ Step 5 SUCCESS - Person updated', [
                    'person_id' => $person->id,
                    'google_id' => $person->google_id,
                    'google_email' => $person->google_email,
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Step 5 FAIL - Database update error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return redirect('http://localhost:3000/Login/AccountSetup?error=google_error');
            }
            
            // Step 6: Clear session
            Log::info('🔍 Step 6 - Clearing session data...');
            $request->session()->forget('link_google_user_id');
            Log::info('✅ Step 6 SUCCESS - Session cleared');
            
            Log::info('🎉 === GOOGLE LINK CALLBACK END - SUCCESS ===');
            
            return redirect('http://localhost:3000/Login/AccountSetup?success=google_linked');
            
        } catch (\Exception $e) {
            Log::error('❌ === GOOGLE LINK CALLBACK - UNEXPECTED ERROR ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('http://localhost:3000/Login/AccountSetup?error=google_error');
        }
    }

    /**
     * Redirect to Google for authentication - for login ONLY (no registration)
     */
    public function loginRedirect()
    {
        try {
            Log::info('=== GOOGLE LOGIN REDIRECT START ===');
            return Socialite::driver('google')
                ->scopes(['email', 'profile'])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('❌ Google login redirect error: ' . $e->getMessage());
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/Login/login?error=google_error');
        }
    }

    /**
     * Handle Google callback for login ONLY (no registration)
     */
    public function loginCallback(Request $request)
    {
        try {
            Log::info('=== GOOGLE LOGIN CALLBACK START ===');
            
            $googleUser = Socialite::driver('google')->user();
            
            Log::info('✅ Google user data retrieved', [
                'google_id' => $googleUser->getId(),
                'google_email' => $googleUser->getEmail(),
            ]);
            
            // ONLY find user by Google ID - NO auto-linking
            $person = Person::where('google_id', $googleUser->getId())->first();
            
            Log::info('🔍 Search by Google ID only', [
                'google_id' => $googleUser->getId(),
                'found' => $person ? 'yes' : 'no',
                'person_id' => $person ? $person->id : null
            ]);
            
            // If no user found with this Google ID, reject
            if (!$person) {
                Log::warning('❌ Google login attempted without linked account', [
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId()
                ]);
                
                return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/Login/login?error=account_not_linked');
            }
            
            // Check if account is active
            if (!$person->is_active) {
                Log::warning('❌ Google login attempted on disabled account', [
                    'person_id' => $person->id,
                    'email' => $person->email
                ]);
                return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/Login/login?error=account_disabled');
            }
            
            // Log the user in
            Auth::login($person, true);
            
            Log::info('✅ Auth::login called', [
                'person_id' => $person->id,
                'auth_check' => Auth::check(),
            ]);
            
            // Regenerate session
            $request->session()->regenerate();
            
            Log::info('✅ Google login successful', [
                'person_id' => $person->id,
                'email' => $person->email,
            ]);
            
            Log::info('=== GOOGLE LOGIN CALLBACK END - REDIRECTING ===');
            
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/Login/login?google_login=success');
            
        } catch (\Exception $e) {
            Log::error('❌ Google login callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/Login/login?error=google_error');
        }
    }

    /**
     * Unlink Google account
     */
    public function unlinkGoogle(Request $request)
    {
        try {
            Log::info('=== GOOGLE UNLINK START ===');
            
            $person = $request->user();
            
            if (!$person) {
                Log::warning('❌ Unlink attempt - not authenticated');
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            
            Log::info('👤 User attempting unlink', [
                'person_id' => $person->id,
                'has_password' => !empty($person->password),
                'google_id' => $person->google_id
            ]);
            
            // Check if user has a password (can't unlink if no password set)
            if (!$person->password) {
                Log::warning('❌ Cannot unlink - no password set');
                return response()->json([
                    'message' => 'Vous devez d\'abord définir un mot de passe avant de délier votre compte Google'
                ], 400);
            }
            
            // Unlink Google account
            $person->update([
                'google_id' => null,
                'google_email' => null,
                'google_token' => null,
                'google_refresh_token' => null,
            ]);
            
            Log::info('✅ Google account unlinked successfully', ['person_id' => $person->id]);
            
            return response()->json(['message' => 'Compte Google délié avec succès'], 200);
            
        } catch (\Exception $e) {
            Log::error('❌ Google unlink error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la déconnexion du compte Google'], 500);
        }
    }

    /**
     * Check Google link status
     */
    public function checkGoogleStatus(Request $request)
    {
        try {
            Log::info('=== CHECK GOOGLE STATUS START ===');
            
            $person = $request->user();
            
            if (!$person) {
                Log::warning('❌ Status check - not authenticated');
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            
            $status = [
                'is_linked' => !empty($person->google_id),
                'google_email' => $person->google_email,
                'has_password' => !empty($person->password),
            ];
            
            Log::info('✅ Google status checked', [
                'person_id' => $person->id,
                'status' => $status
            ]);
            
            return response()->json($status, 200);
            
        } catch (\Exception $e) {
            Log::error('❌ Google status check error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}