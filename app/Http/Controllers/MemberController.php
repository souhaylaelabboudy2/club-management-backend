<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeEmail;
use App\Models\Person;
use App\Models\Club;
use App\Models\Club_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            $query = DB::table('club_members')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->select(
                    'club_members.id',
                    'club_members.person_id',
                    'club_members.club_id',
                    'club_members.role',
                    'club_members.status',
                    'club_members.position',
                    'club_members.joined_at',
                    'club_members.left_at',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email',
                    'persons.avatar',
                    'persons.phone',
                    'persons.cne',
                    'persons.member_code',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo'
                );

            // If the user is a president (not admin), only show their club's members
            if ($user->role === 'user') {
                $membership = DB::table('club_members')
                    ->where('person_id', $user->id)
                    ->where('status', 'active')
                    ->where('role', 'president')
                    ->first();
                
                if (!$membership) {
                    return response()->json([
                        'message' => 'Vous n\'êtes pas président d\'un club'
                    ], 403);
                }
                
                // Filter to only this president's club
                $query->where('club_members.club_id', $membership->club_id);
            }

            // Additional filters from query parameters
            if ($request->has('club_id')) {
                $query->where('club_members.club_id', $request->club_id);
            }

            if ($request->has('person_id')) {
                $query->where('club_members.person_id', $request->person_id);
            }

            if ($request->has('status')) {
                $query->where('club_members.status', $request->status);
            }

            if ($request->has('role')) {
                $query->where('club_members.role', $request->role);
            }

            $members = $query->get();
            
            $members = $members->map(function($member) {
                $member->avatar_url = $member->avatar ? url('storage/' . $member->avatar) : null;
                $member->club_logo_url = $member->club_logo ? url('storage/' . $member->club_logo) : null;
                return $member;
            });

            \Log::info('Members fetched', [
                'user_id' => $user->id,
                'role' => $user->role,
                'count' => $members->count()
            ]);

            return response()->json($members, 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching members: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des membres',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'person_id' => 'required|exists:persons,id',
        'club_id' => 'required|exists:clubs,id',
        'role' => 'required|in:president,board,member',
        'position' => 'nullable|string|max:100',
        'status' => 'sometimes|in:active,inactive,pending',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $exists = DB::table('club_members')
            ->where('person_id', $request->person_id)
            ->where('club_id', $request->club_id)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Cette personne est déjà membre de ce club'
            ], 409);
        }

        if ($request->role === 'president') {
            $hasPresident = DB::table('club_members')
                ->where('club_id', $request->club_id)
                ->where('role', 'president')
                ->where('status', 'active')
                ->exists();

            if ($hasPresident) {
                return response()->json([
                    'message' => 'Ce club a déjà un président actif'
                ], 409);
            }
        }

        $membershipId = DB::table('club_members')->insertGetId([
            'person_id' => $request->person_id,
            'club_id' => $request->club_id,
            'role' => $request->role,
            'position' => $request->position,
            'status' => $request->status ?? 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateClubMemberCounts($request->club_id);
        
        // FIXED EMAIL SENDING SECTION
        try {
            $person = Person::find($request->person_id);
            $club = Club::find($request->club_id);
            
            \Log::info('Attempting to send welcome email', [
                'to' => $person->email,
                'club' => $club->name,
                'role' => $request->role,
                'mail_config' => [
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address'),
                ]
            ]);
            
            Mail::to($person->email)->send(new WelcomeEmail($person, $club, $request->role));
            
            \Log::info('Welcome email sent successfully', ['to' => $person->email]);
            
        } catch (\Exception $e) {
            \Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't fail the entire request if email fails
        }

        $membership = DB::table('club_members')
            ->join('persons', 'club_members.person_id', '=', 'persons.id')
            ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
            ->where('club_members.id', $membershipId)
            ->select(
                'club_members.*', 
                'persons.first_name', 
                'persons.last_name',
                'persons.avatar',
                'clubs.name as club_name',
                'clubs.logo as club_logo'
            )
            ->first();
        
        $membership->avatar_url = $membership->avatar ? url('storage/' . $membership->avatar) : null;
        $membership->club_logo_url = $membership->club_logo ? url('storage/' . $membership->club_logo) : null;

        return response()->json([
            'message' => 'Membre ajouté avec succès',
            'membership' => $membership
        ], 201);
    } catch (\Exception $e) {
        \Log::error('Error adding member', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => 'Erreur lors de l\'ajout du membre',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        try {
            $membership = DB::table('club_members')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->where('club_members.id', $id)
                ->select(
                    'club_members.*',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email',
                    'persons.avatar',
                    'persons.phone',
                    'persons.cne',
                    'persons.member_code',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo'
                )
                ->first();

            if (!$membership) {
                return response()->json(['message' => 'Membre non trouvé'], 404);
            }
            
            $membership->avatar_url = $membership->avatar ? url('storage/' . $membership->avatar) : null;
            $membership->club_logo_url = $membership->club_logo ? url('storage/' . $membership->club_logo) : null;

            return response()->json($membership, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'sometimes|in:president,board,member',
            'position' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,pending',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $membership = DB::table('club_members')->where('id', $id)->first();

            if (!$membership) {
                return response()->json(['message' => 'Membre non trouvé'], 404);
            }

            if ($request->has('role') && $request->role === 'president') {
                $hasPresident = DB::table('club_members')
                    ->where('club_id', $membership->club_id)
                    ->where('role', 'president')
                    ->where('status', 'active')
                    ->where('id', '!=', $id)
                    ->exists();

                if ($hasPresident) {
                    return response()->json([
                        'message' => 'Ce club a déjà un président actif'
                    ], 409);
                }
            }

            DB::table('club_members')
                ->where('id', $id)
                ->update(array_merge(
                    $request->only(['role', 'position', 'status']),
                    ['updated_at' => now()]
                ));

            $this->updateClubMemberCounts($membership->club_id);

            $updated = DB::table('club_members')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->where('club_members.id', $id)
                ->select(
                    'club_members.*',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.avatar',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo'
                )
                ->first();
            
            $updated->avatar_url = $updated->avatar ? url('storage/' . $updated->avatar) : null;
            $updated->club_logo_url = $updated->club_logo ? url('storage/' . $updated->club_logo) : null;

            return response()->json([
                'message' => 'Membre mis à jour avec succès',
                'membership' => $updated
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $membership = DB::table('club_members')->where('id', $id)->first();

            if (!$membership) {
                return response()->json(['message' => 'Membre non trouvé'], 404);
            }

            DB::table('club_members')
                ->where('id', $id)
                ->update([
                    'status' => 'inactive',
                    'left_at' => now(),
                    'leave_reason' => $request->leave_reason ?? null,
                    'updated_at' => now(),
                ]);

            $this->updateClubMemberCounts($membership->club_id);

            return response()->json(['message' => 'Membre retiré avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPersonClubs($personId)
    {
        try {
            $memberships = DB::table('club_members')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->where('club_members.person_id', $personId)
                ->where('club_members.status', 'active')
                ->select(
                    'club_members.id as membership_id',
                    'club_members.role',
                    'club_members.position',
                    'club_members.joined_at',
                    'clubs.*'
                )
                ->get();
            
            $memberships = $memberships->map(function($membership) {
                $membership->logo_url = $membership->logo ? url('storage/' . $membership->logo) : null;
                $membership->cover_image_url = $membership->cover_image ? url('storage/' . $membership->cover_image) : null;
                return $membership;
            });

            return response()->json($memberships, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des clubs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClubStats($clubId)
    {
        try {
            $stats = [
                'total_members' => DB::table('club_members')
                    ->where('club_id', $clubId)
                    ->count(),
                'active_members' => DB::table('club_members')
                    ->where('club_id', $clubId)
                    ->where('status', 'active')
                    ->count(),
                'presidents' => DB::table('club_members')
                    ->where('club_id', $clubId)
                    ->where('role', 'president')
                    ->where('status', 'active')
                    ->count(),
                'board_members' => DB::table('club_members')
                    ->where('club_id', $clubId)
                    ->where('role', 'board')
                    ->where('status', 'active')
                    ->count(),
                'regular_members' => DB::table('club_members')
                    ->where('club_id', $clubId)
                    ->where('role', 'member')
                    ->where('status', 'active')
                    ->count(),
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function updateClubMemberCounts($clubId)
    {
        $totalMembers = DB::table('club_members')
            ->where('club_id', $clubId)
            ->count();

        $activeMembers = DB::table('club_members')
            ->where('club_id', $clubId)
            ->where('status', 'active')
            ->count();

        DB::table('clubs')
            ->where('id', $clubId)
            ->update([
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'updated_at' => now(),
            ]);
    }

    public function getMyClubMembership()
    {
        try {
            $personId = auth()->id();
            
            if (!$personId) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            $membership = DB::table('club_members')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->where('club_members.person_id', $personId)
                ->where('club_members.status', 'active')
                ->select(
                    'club_members.id as membership_id',
                    'club_members.role',
                    'club_members.position',
                    'club_members.status',
                    'club_members.joined_at',
                    'clubs.id as club_id',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo',
                    'clubs.description as club_description',
                    'clubs.category as club_category',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                )
                ->first();

            if (!$membership) {
                return response()->json(['message' => 'Aucune adhésion active trouvée'], 404);
            }

            $club = (object)[
                'id' => $membership->club_id,
                'name' => $membership->club_name,
                'logo' => $membership->club_logo,
                'logo_url' => $membership->club_logo ? url('storage/' . $membership->club_logo) : null,
                'description' => $membership->club_description,
                'category' => $membership->club_category,
            ];

            $membershipData = (object)[
                'id' => $membership->membership_id,
                'role' => $membership->role,
                'position' => $membership->position,
                'status' => $membership->status,
                'joined_at' => $membership->joined_at,
            ];

            return response()->json([
                'club' => $club,
                'membership' => $membershipData
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error fetching membership: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'adhésion',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}