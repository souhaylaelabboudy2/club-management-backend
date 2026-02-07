<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Club_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:persons,email',
            'password' => 'required|string|min:6|confirmed',
            'cne' => 'nullable|string|unique:persons,cne',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $person = Person::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'cne' => $request->cne,
                'phone' => $request->phone,
                'member_code' => $this->generateMemberCode(),
                'is_active' => true,
            ]);

            Log::info('User registered', ['person_id' => $person->id]);

            return response()->json(['message' => 'Inscription réussie', 'user' => $person], 201);
        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'inscription'], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            Log::info('Login attempt', ['email' => $request->email]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                Log::warning('Login failed', ['email' => $request->email]);
                return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
            }

            $person = Auth::user();

            if (!$person->is_active) {
                Auth::logout();
                return response()->json(['message' => 'Compte désactivé'], 403);
            }

            // CRITICAL: Regenerate session
            $request->session()->regenerate();

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
            }

            Log::info('Login successful', ['person_id' => $person->id, 'session_id' => session()->getId()]);

            return response()->json([
                'message' => 'Connexion réussie',
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
                'club_id' => $clubId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la connexion'], 500);
        }
    }

    /**
     * Verify session - Used after Google OAuth callback
     */
    public function verifySession(Request $request)
    {
        try {
            Log::info('Verify session called', [
                'session_id' => session()->getId(),
                'authenticated' => Auth::check()
            ]);

            if (!Auth::check()) {
                Log::warning('Session verification failed - not authenticated');
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            $person = Auth::user();
            
            if (!$person) {
                Log::warning('Session verification failed - no user found');
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if (!$person->is_active) {
                Log::warning('Session verification failed - account disabled', ['person_id' => $person->id]);
                Auth::logout();
                return response()->json(['message' => 'Compte désactivé'], 403);
            }

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
            }

            Log::info('Session verified successfully', [
                'person_id' => $person->id,
                'role' => $person->role,
                'club_role' => $clubRole
            ]);

            return response()->json([
                'message' => 'Session valide',
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
                'club_id' => $clubId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Session verification error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Erreur lors de la vérification de session'], 500);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Déconnecté avec succès'], 200);
    }

    public function profile(Request $request)
    {
        try {
            $person = $request->user();
            if (!$person) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            
            // Add avatar_url
            $person->avatar_url = $person->avatar ? asset('storage/' . $person->avatar) : null;
            
            return response()->json(['user' => $person], 200);
        } catch (\Exception $e) {
            Log::error('Profile error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $person = $request->user();
            if (!$person) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            
            $data = $request->only(['first_name', 'last_name', 'phone']);
            
            // Handle avatar file upload
            if ($request->hasFile('avatar')) {
                if ($person->avatar && \Storage::disk('public')->exists($person->avatar)) {
                    \Storage::disk('public')->delete($person->avatar);
                }
                $avatarPath = $request->file('avatar')->store('persons/avatars', 'public');
                $data['avatar'] = $avatarPath;
            }
            
            $person->update($data);
            
            // Return updated user with fresh data
            $person->refresh();
            
            // Add full URL to response
            $person->avatar_url = $person->avatar ? asset('storage/' . $person->avatar) : null;
            
            Log::info('Profile updated', ['person_id' => $person->id]);
            
            return response()->json(['message' => 'Profil mis à jour', 'user' => $person], 200);
        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function changePassword(Request $request)
    {
        // Check if user has a password (for Google-only accounts)
        $person = $request->user();
        $hasPassword = !empty($person->password);
        
        $validator = Validator::make($request->all(), [
            'current_password' => $hasPassword ? 'required|string' : 'nullable',
            'new_password' => 'required|string|min:6',
            'new_password_confirmation' => 'required|string|same:new_password',
        ], [
            'new_password_confirmation.same' => 'Les mots de passe ne correspondent pas',
            'new_password.min' => 'Le mot de passe doit contenir au moins 6 caractères',
            'current_password.required' => 'Le mot de passe actuel est requis',
            'new_password.required' => 'Le nouveau mot de passe est requis',
            'new_password_confirmation.required' => 'La confirmation du mot de passe est requise',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation', 
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if (!$person) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }
            
            // Only verify current password if user has one
            if ($hasPassword) {
                if (!Hash::check($request->current_password, $person->password)) {
                    return response()->json([
                        'message' => 'Le mot de passe actuel est incorrect'
                    ], 401);
                }
            }
            
            // Update password
            $person->update(['password' => Hash::make($request->new_password)]);
            
            Log::info('Password changed', ['person_id' => $person->id]);
            
            return response()->json([
                'message' => 'Mot de passe changé avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Password change error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du changement de mot de passe'
            ], 500);
        }
    }

    private function generateMemberCode()
    {
        do {
            $code = 'MBR' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Person::where('member_code', $code)->exists());
        return $code;
    }
}