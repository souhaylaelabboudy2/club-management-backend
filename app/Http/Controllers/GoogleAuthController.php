<?php

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private function accountSetupUrl(): string
    {
        return env('FRONTEND_URL') . '/Login/AccountSetup';
    }

    private function loginUrl(): string
    {
        return env('FRONTEND_URL') . '/Login/login';
    }

    // ─────────────────────────────────────────────
    // 1. LOGIN REDIRECT
    // ─────────────────────────────────────────────
    public function loginRedirect()
    {
        return Socialite::driver('google')
            ->redirectUrl(env('GOOGLE_REDIRECT_URI'))
            ->stateless()
            ->redirect();
    }

    // ─────────────────────────────────────────────
    // 2. LOGIN CALLBACK — with 2FA bypass fix
    // ─────────────────────────────────────────────
    public function loginCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl(env('GOOGLE_REDIRECT_URI'))
                ->stateless()
                ->user();

            $person = Person::where('google_id', $googleUser->getId())
                ->orWhere('google_email', $googleUser->getEmail())
                ->first();

            if (!$person) {
                Log::warning('Google login: no linked account found', [
                    'google_email' => $googleUser->getEmail(),
                ]);
                return redirect($this->loginUrl() . '?error=account_not_linked');
            }

            if (!$person->is_active) {
                Log::warning('Google login: account disabled', ['person_id' => $person->id]);
                return redirect($this->loginUrl() . '?error=account_disabled');
            }

            // ✅ 2FA CHECK — don't let Google bypass 2FA
            if ($person->two_factor_enabled) {
                $request->session()->put('2fa_pending_person_id', [
                    'id'         => $person->id,
                    'expires_at' => now()->addMinutes(10)->timestamp,
                ]);
                $request->session()->save();

                Log::info('Google login: 2FA required', ['person_id' => $person->id]);

                return redirect($this->loginUrl() . '?requires_2fa=true&person_id=' . $person->id);
            }

            // No 2FA — log in normally
            Auth::login($person, true);
            $request->session()->regenerate();
            $request->session()->save();

            Log::info('Google login success', ['person_id' => $person->id]);

            return redirect($this->loginUrl() . '?google_login=success');

        } catch (\Exception $e) {
            Log::error('Google loginCallback error: ' . $e->getMessage());
            return redirect($this->loginUrl() . '?error=google_error');
        }
    }

    // ─────────────────────────────────────────────
    // 3. LINK REDIRECT
    // ─────────────────────────────────────────────
    public function linkRedirect(Request $request)
    {
        if (!Auth::check()) {
            Log::warning('linkRedirect: user not authenticated');
            return redirect($this->loginUrl() . '?error=session_expired');
        }

        $personId = Auth::id();
        Log::info('Google linkRedirect', ['person_id' => $personId]);

        return Socialite::driver('google')
            ->redirectUrl(env('GOOGLE_LINK_REDIRECT_URI'))
            ->with(['state' => base64_encode($personId)])
            ->redirect();
    }

    // ─────────────────────────────────────────────
    // 4. LINK CALLBACK
    // ─────────────────────────────────────────────
    public function linkCallback(Request $request)
    {
        try {
            Log::info('linkCallback fired', [
                'state'     => $request->get('state'),
                'has_code'  => $request->has('code'),
                'has_error' => $request->has('error'),
            ]);

            if ($request->has('error')) {
                Log::warning('Google returned error: ' . $request->get('error'));
                return redirect($this->accountSetupUrl() . '?error=google_error');
            }

            $googleUser = Socialite::driver('google')
                ->redirectUrl(env('GOOGLE_LINK_REDIRECT_URI'))
                ->stateless()
                ->user();

            $state    = $request->get('state');
            $personId = $state ? base64_decode($state) : null;

            Log::info('linkCallback decoded', [
                'person_id'    => $personId,
                'google_email' => $googleUser->getEmail(),
                'google_id'    => $googleUser->getId(),
            ]);

            if (!$personId) {
                return redirect($this->accountSetupUrl() . '?error=session_expired');
            }

            $person = Person::find($personId);

            if (!$person) {
                return redirect($this->accountSetupUrl() . '?error=user_not_found');
            }

            $existingLink = Person::where('google_id', $googleUser->getId())
                ->where('id', '!=', $person->id)
                ->first();

            if ($existingLink) {
                return redirect($this->accountSetupUrl() . '?error=google_already_linked');
            }

            $person->update([
                'google_id'            => $googleUser->getId(),
                'google_email'         => $googleUser->getEmail(),
                'google_token'         => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? null,
            ]);

            Auth::login($person, true);
            $request->session()->regenerate();
            $request->session()->save();

            Log::info('Google account linked successfully', [
                'person_id'    => $person->id,
                'google_email' => $googleUser->getEmail(),
            ]);

            return redirect($this->accountSetupUrl() . '?success=google_linked');

        } catch (\Exception $e) {
            Log::error('linkCallback exception: ' . $e->getMessage());
            return redirect($this->accountSetupUrl() . '?error=google_error');
        }
    }

    // ─────────────────────────────────────────────
    // 5. CHECK GOOGLE STATUS
    // ─────────────────────────────────────────────
    public function checkGoogleStatus(Request $request)
    {
        $person = $request->user();

        if (!$person) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        return response()->json([
            'is_linked'    => !empty($person->google_id),
            'google_email' => $person->google_email,
            'has_password' => !empty($person->password),
        ]);
    }

    // ─────────────────────────────────────────────
    // 6. UNLINK GOOGLE
    // ─────────────────────────────────────────────
    public function unlinkGoogle(Request $request)
    {
        $person = $request->user();

        if (!$person) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        if (empty($person->password)) {
            return response()->json([
                'message' => "Définissez d'abord un mot de passe avant de délier Google",
            ], 400);
        }

        $person->update([
            'google_id'            => null,
            'google_email'         => null,
            'google_token'         => null,
            'google_refresh_token' => null,
        ]);

        Log::info('Google unlinked', ['person_id' => $person->id]);

        return response()->json(['message' => 'Compte Google délié avec succès']);
    }
}