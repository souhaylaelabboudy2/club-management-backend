<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubRoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
        
        // Admins bypass club role checks
        if ($user->role === 'admin') {
            return $next($request);
        }
        
        $clubId = null;
        
        // Special handling for request validation routes
        if ($request->route('id') && str_contains($request->path(), 'requests/')) {
            // This is a request validation route (e.g., /api/requests/5/validate)
            $requestId = $request->route('id');
            $req = DB::table('request')->where('id', $requestId)->first();
            if ($req) {
                $clubId = $req->club_id;
            }
        }
        // Check route parameter 'clubId'
        elseif ($request->route('clubId')) {
            $clubId = $request->route('clubId');
        }
        // Check route parameter 'id' for other routes
        elseif ($request->route('id') && !str_contains($request->path(), 'requests/')) {
            $clubId = $request->route('id');
        }
        // Check request input
        elseif ($request->input('club_id')) {
            $clubId = $request->input('club_id');
        }
        
        // If no specific club, check if user has ANY active membership with required role
        if (!$clubId) {
            $hasRole = DB::table('club_members')
                ->where('person_id', $user->id)
                ->where('status', 'active')
                ->whereIn('role', $roles)
                ->exists();
                
            if (!$hasRole) {
                return response()->json(['message' => 'Accès non autorisé - Aucun club trouvé'], 403);
            }
            return $next($request);
        }
        
        // Check specific club membership
        $membership = DB::table('club_members')
            ->where('person_id', $user->id)
            ->where('club_id', $clubId)
            ->where('status', 'active')
            ->first();
            
        if (!$membership) {
            return response()->json([
                'message' => 'Accès non autorisé - Vous n\'êtes pas membre de ce club',
                'user_id' => $user->id,
                'club_id' => $clubId
            ], 403);
        }
        
        // Check if user has required role
        $hasRequiredRole = in_array($membership->role, $roles);
        
        if (!$hasRequiredRole) {
            return response()->json([
                'message' => 'Accès non autorisé - Rôle insuffisant',
                'required_roles' => $roles,
                'your_role' => $membership->role
            ], 403);
        }
        
        // Attach club_role to request for later use
        $request->merge(['club_role' => $membership->role]);
        $request->merge(['authenticated_club_id' => $clubId]);
        
        return $next($request);
    }
}