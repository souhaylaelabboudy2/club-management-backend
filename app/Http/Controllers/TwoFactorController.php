<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // ─────────────────────────────────────────────
    // 1. GENERATE SECRET + QR CODE
    // ─────────────────────────────────────────────
    public function setup(Request $request)
    {
        $person = $request->user();

        if (!$person) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $secret = $this->google2fa->generateSecretKey();

        $person->update([
            'two_factor_secret'       => encrypt($secret),
            'two_factor_enabled'      => false,
            'two_factor_confirmed_at' => null,
        ]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $person->email,
            $secret
        );

        $renderer  = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer    = new Writer($renderer);
        $qrCodeSvg = base64_encode($writer->writeString($qrCodeUrl));

        Log::info('2FA setup initiated', ['person_id' => $person->id]);

        return response()->json([
            'secret'  => $secret,
            'qr_code' => 'data:image/svg+xml;base64,' . $qrCodeSvg,
            'qr_url'  => $qrCodeUrl,
        ]);
    }

    // ─────────────────────────────────────────────
    // 2. CONFIRM/ENABLE 2FA + GENERATE RECOVERY CODES
    // ─────────────────────────────────────────────
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $person = $request->user();

        if (!$person || !$person->two_factor_secret) {
            return response()->json(['message' => '2FA non initialisé'], 400);
        }

        $secret = decrypt($person->two_factor_secret);
        $valid  = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Code incorrect, réessayez'], 422);
        }

        // Generate 8 recovery codes, store hashed
        $plainCodes  = $this->generateRecoveryCodes(8);
        $hashedCodes = array_map(fn($c) => Hash::make($c), $plainCodes);

        $person->update([
            'two_factor_enabled'        => true,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => json_encode($hashedCodes),
        ]);

        Log::info('2FA enabled', ['person_id' => $person->id]);

        return response()->json([
            'message'        => '2FA activé avec succès',
            'recovery_codes' => $plainCodes,
        ]);
    }

    // ─────────────────────────────────────────────
    // 3. DISABLE 2FA
    // ─────────────────────────────────────────────
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $person = $request->user();

        if (!$person) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $secret = decrypt($person->two_factor_secret);
        $valid  = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Code incorrect'], 422);
        }

        $person->update([
            'two_factor_secret'         => null,
            'two_factor_enabled'        => false,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        Log::info('2FA disabled', ['person_id' => $person->id]);

        return response()->json(['message' => '2FA désactivé']);
    }

    // ─────────────────────────────────────────────
    // 4. VERIFY CODE DURING LOGIN (with expiry + recovery code support)
    // ─────────────────────────────────────────────
    public function verifyLogin(Request $request)
    {
        $request->validate([
            'code'      => 'required|string',
            'person_id' => 'required|integer',
        ]);

        $pending = $request->session()->get('2fa_pending_person_id');

        if (!$pending) {
            return response()->json(['message' => 'Session invalide'], 401);
        }

        // Check 10-minute expiry
        if (now()->timestamp > $pending['expires_at']) {
            $request->session()->forget('2fa_pending_person_id');
            return response()->json(['message' => 'Session expirée, reconnectez-vous'], 401);
        }

        if ($pending['id'] != $request->person_id) {
            return response()->json(['message' => 'Session invalide'], 401);
        }

        $person = \App\Models\Person::find($pending['id']);

        if (!$person) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $code   = $request->code;
        $secret = decrypt($person->two_factor_secret);

        // Try TOTP code first
        $valid = $this->google2fa->verifyKey($secret, $code);

        // If TOTP fails, try recovery codes
        if (!$valid) {
            $valid = $this->tryRecoveryCode($person, $code);
            if (!$valid) {
                return response()->json(['message' => 'Code incorrect'], 422);
            }
        }

        // Complete login
        $request->session()->forget('2fa_pending_person_id');
        Auth::login($person, true);
        $request->session()->regenerate();

        $clubRole = null;
        $clubId   = null;

        if ($person->role === 'user') {
            $membership = \App\Models\Club_member::where('person_id', $person->id)
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

        Log::info('2FA login verified', ['person_id' => $person->id]);

        return response()->json([
            'message'   => 'Connexion réussie',
            'user'      => [
                'id'                 => $person->id,
                'first_name'         => $person->first_name,
                'last_name'          => $person->last_name,
                'email'              => $person->email,
                'avatar_url'         => $person->avatar ? url('storage/' . $person->avatar) : null,
                'member_code'        => $person->member_code,
                'two_factor_enabled' => $person->two_factor_enabled,
                'club_id'            => $clubId,
            ],
            'role'      => $person->role,
            'club_role' => $clubRole,
            'club_id'   => $clubId,
        ]);
    }

    // ─────────────────────────────────────────────
    // 5. REGENERATE RECOVERY CODES
    // ─────────────────────────────────────────────
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $person = $request->user();

        if (!$person || !$person->two_factor_enabled) {
            return response()->json(['message' => '2FA non activé'], 400);
        }

        $secret = decrypt($person->two_factor_secret);
        $valid  = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Code incorrect'], 422);
        }

        $plainCodes  = $this->generateRecoveryCodes(8);
        $hashedCodes = array_map(fn($c) => Hash::make($c), $plainCodes);

        $person->update([
            'two_factor_recovery_codes' => json_encode($hashedCodes),
        ]);

        Log::info('Recovery codes regenerated', ['person_id' => $person->id]);

        return response()->json([
            'message'        => 'Codes de récupération régénérés',
            'recovery_codes' => $plainCodes,
        ]);
    }

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────

    private function generateRecoveryCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        }
        return $codes;
    }

    private function tryRecoveryCode(\App\Models\Person $person, string $inputCode): bool
    {
        if (!$person->two_factor_recovery_codes) {
            return false;
        }

        $storedCodes = json_decode($person->two_factor_recovery_codes, true);

        foreach ($storedCodes as $index => $hashedCode) {
            if (Hash::check(strtoupper($inputCode), $hashedCode)) {
                // Burn the used code — one time use only
                unset($storedCodes[$index]);
                $person->update([
                    'two_factor_recovery_codes' => json_encode(array_values($storedCodes)),
                ]);
                Log::info('Recovery code used', ['person_id' => $person->id]);
                return true;
            }
        }

        return false;
    }
}