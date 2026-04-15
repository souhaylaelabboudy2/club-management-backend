<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Mail\ResetPasswordMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset link to the user's email
     */
    public function sendResetLink(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
            }

            // Find user by email
            $user = Person::where('email', $request->email)->first();

            // Always return 200 to prevent email enumeration
            if (!$user) {
                Log::info('Password reset requested for non-existent email', ['email' => $request->email]);
                return response()->json(['message' => 'Si cet email existe, vous recevrez un lien de réinitialisation'], 200);
            }

            // Generate a secure random token
            $token = Str::random(64);

            // Delete any existing tokens for this email
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            // Store the token in the database with 15-minute expiry
            DB::table('password_reset_tokens')->insert([
                'email'      => $user->email,
                'token'      => $token,
                'created_at' => now(),
            ]);

            // Send reset email
            try {
                Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
                Log::info('Password reset link sent', ['user_id' => $user->id, 'email' => $user->email]);
            } catch (\Exception $mailError) {
                Log::error('Mail sending failed: ' . $mailError->getMessage());
                // Still return 200 to not reveal if email exists
            }

            return response()->json(['message' => 'Si cet email existe, vous recevrez un lien de réinitialisation'], 200);
        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la demande de réinitialisation'], 500);
        }
    }

    /**
     * Validate the reset token
     */
    public function validateToken(Request $request, $token)
    {
        try {
            Log::info('Token validation attempt', ['token' => substr($token, 0, 20) . '...']);

            $validator = Validator::make(['token' => $token], [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Token validation failed - invalid format');
                return response()->json(['message' => 'Lien invalide'], 400);
            }

            // Check if token exists and is not expired (15 minutes)
            $resetToken = DB::table('password_reset_tokens')
                ->where('token', $token)
                ->first();

            if (!$resetToken) {
                Log::warning('Token not found in database', ['token' => substr($token, 0, 20) . '...']);
                return response()->json(['message' => 'Lien invalide'], 400);
            }

            Log::info('Token found', ['email' => $resetToken->email, 'created_at' => $resetToken->created_at]);

            // Check if token has expired (15 minutes)
            $expiresAt = Carbon::parse($resetToken->created_at)->addMinutes(15);
            $now = now();
            Log::info('Token expiration check', ['created_at' => $resetToken->created_at, 'expires_at' => $expiresAt, 'now' => $now]);

            if (now()->isAfter($expiresAt)) {
                Log::warning('Token expired', ['token' => substr($token, 0, 20) . '...']);
                DB::table('password_reset_tokens')->where('token', $token)->delete();
                return response()->json(['message' => 'Le lien a expiré'], 400);
            }

            Log::info('Token is valid', ['email' => $resetToken->email]);
            return response()->json(['message' => 'Lien valide', 'email' => $resetToken->email], 200);
        } catch (\Exception $e) {
            Log::error('Token validation error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la validation du lien'], 500);
        }
    }

    /**
     * Reset the user's password
     */
    public function resetPassword(Request $request, $token)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email'                 => 'required|email',
                'password'              => 'required|string|min:6|confirmed',
                'password_confirmation' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
            }

            // Validate token
            $resetToken = DB::table('password_reset_tokens')
                ->where('token', $token)
                ->where('email', $request->email)
                ->first();

            if (!$resetToken) {
                return response()->json(['message' => 'Lien invalide'], 400);
            }

            // Check if token has expired (15 minutes)
            $expiresAt = Carbon::parse($resetToken->created_at)->addMinutes(15);
            if (now()->isAfter($expiresAt)) {
                DB::table('password_reset_tokens')->where('token', $token)->delete();
                return response()->json(['message' => 'Le lien a expiré'], 400);
            }

            // Find user and update password
            $user = Person::where('email', $request->email)->first();

            if (!$user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            // Hash and update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the reset token
            DB::table('password_reset_tokens')->where('token', $token)->delete();

            Log::info('Password reset successful', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json(['message' => 'Mot de passe réinitialisé avec succès'], 200);
        } catch (\Exception $e) {
            Log::error('Password reset error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la réinitialisation du mot de passe'], 500);
        }
    }
}
