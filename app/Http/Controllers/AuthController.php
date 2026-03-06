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
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:persons,email',
            'password'   => 'required|string|min:6|confirmed',
            'cne'        => 'nullable|string|unique:persons,cne',
            'phone'      => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $person = Person::create([
                'first_name'  => $request->first_name,
                'last_name'   => $request->last_name,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'cne'         => $request->cne,
                'phone'       => $request->phone,
                'member_code' => $this->generateMemberCode(),
                'is_active'   => true,
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
            $validator = Validator::make($request->all(), [
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json(['message' => 'Email ou mot de passe incorrect'], 401);
            }

            $person = Auth::user();

            if (!$person->is_active) {
                Auth::logout();
                return response()->json(['message' => 'Compte désactivé'], 403);
            }

            // ✅ 2FA CHECK — if enabled, pause login and wait for TOTP code
            if ($person->two_factor_enabled) {
                Auth::logout();

                // Store pending with 10-minute expiry
                $request->session()->put('2fa_pending_person_id', [
                    'id'         => $person->id,
                    'expires_at' => now()->addMinutes(10)->timestamp,
                ]);
                $request->session()->save();

                return response()->json([
                    'requires_2fa' => true,
                    'person_id'    => $person->id,
                    'message'      => '2FA requis',
                ], 200);
            }

            // No 2FA — normal login
            $request->session()->regenerate();

            $clubRole = null;
            $clubId   = null;

            if ($person->role === 'user') {
                $membership = Club_member::where('person_id', $person->id)
                    ->where('status', 'active')
                    ->orderByRaw("CASE
                        WHEN role = 'president' THEN 1
                        WHEN role = 'board'     THEN 2
                        WHEN role = 'member'    THEN 3
                        ELSE 4
                    END")
                    ->first();

                if ($membership) {
                    $clubRole = $membership->role;
                    $clubId   = $membership->club_id;
                }
            }

            return response()->json([
                'message'   => 'Connexion réussie',
                'user'      => [
                    'id'                 => $person->id,
                    'first_name'         => $person->first_name,
                    'last_name'          => $person->last_name,
                    'email'              => $person->email,
                    'avatar'             => $person->avatar,
                    'avatar_url'         => $person->avatar ? url('storage/' . $person->avatar) : null,
                    'member_code'        => $person->member_code,
                    'two_factor_enabled' => $person->two_factor_enabled,
                    'club_id'            => $clubId,
                ],
                'role'      => $person->role,
                'club_role' => $clubRole,
                'club_id'   => $clubId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la connexion'], 500);
        }
    }

    public function verifySession(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            $person = Auth::user();

            if (!$person) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if (!$person->is_active) {
                Auth::logout();
                return response()->json(['message' => 'Compte désactivé'], 403);
            }

            $clubRole = null;
            $clubId   = null;

            if ($person->role === 'user') {
                $membership = Club_member::where('person_id', $person->id)
                    ->where('status', 'active')
                    ->orderByRaw("CASE
                        WHEN role = 'president' THEN 1
                        WHEN role = 'board'     THEN 2
                        WHEN role = 'member'    THEN 3
                        ELSE 4
                    END")
                    ->first();

                if ($membership) {
                    $clubRole = $membership->role;
                    $clubId   = $membership->club_id;
                }
            }

            return response()->json([
                'message' => 'Session valide',
                'user'    => [
                    'id'                 => $person->id,
                    'first_name'         => $person->first_name,
                    'last_name'          => $person->last_name,
                    'email'              => $person->email,
                    'avatar'             => $person->avatar,
                    'avatar_url'         => $person->avatar ? url('storage/' . $person->avatar) : null,
                    'member_code'        => $person->member_code,
                    'two_factor_enabled' => $person->two_factor_enabled,
                    'club_id'            => $clubId,
                ],
                'role'      => $person->role,
                'club_role' => $clubRole,
                'club_id'   => $clubId,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Session verification error: ' . $e->getMessage());
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

            $person->avatar_url = $person->avatar ? url('storage/' . $person->avatar) : null;

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
            'last_name'  => 'sometimes|required|string|max:100',
            'phone'      => 'nullable|string|max:20',
            'avatar'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
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

            if ($request->hasFile('avatar')) {
                if ($person->avatar && \Storage::disk('public')->exists($person->avatar)) {
                    \Storage::disk('public')->delete($person->avatar);
                }
                $avatarPath  = $request->file('avatar')->store('persons/avatars', 'public');
                $data['avatar'] = $avatarPath;
            }

            $person->update($data);
            $person->refresh();

            Log::info('Profile updated', ['person_id' => $person->id]);

            return response()->json([
                'message' => 'Profil mis à jour',
                'user'    => [
                    'id'                 => $person->id,
                    'first_name'         => $person->first_name,
                    'last_name'          => $person->last_name,
                    'email'              => $person->email,
                    'phone'              => $person->phone,
                    'avatar'             => $person->avatar,
                    'avatar_url'         => $person->avatar ? url('storage/' . $person->avatar) : null,
                    'member_code'        => $person->member_code,
                    'role'               => $person->role,
                    'two_factor_enabled' => $person->two_factor_enabled,
                    'is_active'          => $person->is_active,
                    'club_id'            => $person->club_id ?? null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $person      = $request->user();
        $hasPassword = !empty($person->password);

        $validator = Validator::make($request->all(), [
            'current_password'      => $hasPassword ? 'required|string' : 'nullable',
            'new_password'          => 'required|string|min:6',
            'new_password_confirmation' => 'required|string|same:new_password',
        ], [
            'new_password_confirmation.same' => 'Les mots de passe ne correspondent pas',
            'new_password.min'               => 'Le mot de passe doit contenir au moins 6 caractères',
            'current_password.required'      => 'Le mot de passe actuel est requis',
            'new_password.required'          => 'Le nouveau mot de passe est requis',
            'new_password_confirmation.required' => 'La confirmation du mot de passe est requise',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            if (!$person) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            if ($hasPassword && !Hash::check($request->current_password, $person->password)) {
                return response()->json(['message' => 'Le mot de passe actuel est incorrect'], 401);
            }

            $person->update(['password' => Hash::make($request->new_password)]);

            Log::info('Password changed', ['person_id' => $person->id]);

            return response()->json(['message' => 'Mot de passe changé avec succès'], 200);

        } catch (\Exception $e) {
            Log::error('Password change error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du changement de mot de passe'], 500);
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