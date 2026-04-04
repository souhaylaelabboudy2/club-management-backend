<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\Person;
use App\Models\Club;
use App\Models\Event;
use App\Models\Club_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('request')
                ->join('clubs', 'request.club_id', '=', 'clubs.id')
                ->join('persons as requester', 'request.requested_by', '=', 'requester.id')
                ->leftJoin('persons as validator', 'request.validated_by', '=', 'validator.id')
                ->select(
                    'request.*',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo',
                    'requester.first_name as requester_first_name',
                    'requester.last_name as requester_last_name',
                    'requester.email as requester_email',
                    'validator.first_name as validator_first_name',
                    'validator.last_name as validator_last_name'
                );

            if ($request->has('club_id')) {
                $query->where('request.club_id', $request->club_id);
            }

            if ($request->has('requested_by')) {
                $query->where('request.requested_by', $request->requested_by);
            }

            if ($request->has('type')) {
                $query->where('request.type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('request.status', $request->status);
            }

            $orderBy = $request->get('order_by', 'requested_at');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy('request.' . $orderBy, $orderDir);

            $requests = $query->get();

            return response()->json($requests, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching requests: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $clubExists = DB::table('clubs')->where('id', $request->club_id)->exists();
        $personExists = DB::table('persons')->where('id', $request->requested_by)->exists();

        if (!$clubExists) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => [
                    'club_id' => ['Le club spécifié n\'existe pas dans la base de données']
                ]
            ], 422);
        }

        if (!$personExists) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => [
                    'requested_by' => ['La personne spécifiée n\'existe pas dans la base de données']
                ]
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'club_id' => 'required|integer|exists:clubs,id',
            'requested_by' => 'required|integer|exists:persons,id',
            'type' => 'required|in:event,member,budget,other',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $requestModel = RequestModel::create([
                'club_id' => $request->club_id,
                'requested_by' => $request->requested_by,
                'type' => $request->type,
                'title' => $request->title,
                'description' => $request->description,
                'metadata' => $request->metadata,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            $createdRequest = DB::table('request')
                ->join('clubs', 'request.club_id', '=', 'clubs.id')
                ->join('persons', 'request.requested_by', '=', 'persons.id')
                ->where('request.id', $requestModel->id)
                ->select(
                    'request.*',
                    'clubs.name as club_name',
                    'persons.first_name as requester_first_name',
                    'persons.last_name as requester_last_name'
                )
                ->first();

            return response()->json([
                'message' => 'Demande créée avec succès',
                'request' => $createdRequest
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la création de la demande',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $request = DB::table('request')
                ->join('clubs', 'request.club_id', '=', 'clubs.id')
                ->join('persons as requester', 'request.requested_by', '=', 'requester.id')
                ->leftJoin('persons as validator', 'request.validated_by', '=', 'validator.id')
                ->where('request.id', $id)
                ->select(
                    'request.*',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo',
                    'requester.first_name as requester_first_name',
                    'requester.last_name as requester_last_name',
                    'requester.email as requester_email',
                    'validator.first_name as validator_first_name',
                    'validator.last_name as validator_last_name'
                )
                ->first();

            if (!$request) {
                return response()->json(['message' => 'Demande non trouvée'], 404);
            }

            if ($request->metadata) {
                $request->metadata = json_decode($request->metadata, true);
            }

            return response()->json($request, 200);
        } catch (\Exception $e) {
            Log::error('Error showing request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:event,member,budget,other',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exists = DB::table('request')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Demande non trouvée'], 404);
            }

            $updateData = $request->only(['type', 'title', 'description']);
            if ($request->has('metadata')) {
                $updateData['metadata'] = json_encode($request->metadata);
            }

            DB::table('request')->where('id', $id)->update($updateData);

            $updatedRequest = DB::table('request')
                ->join('clubs', 'request.club_id', '=', 'clubs.id')
                ->join('persons', 'request.requested_by', '=', 'persons.id')
                ->where('request.id', $id)
                ->select('request.*', 'clubs.name as club_name', 'persons.first_name', 'persons.last_name')
                ->first();

            return response()->json([
                'message' => 'Demande mise à jour avec succès',
                'request' => $updatedRequest
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validate(Request $request, $id)
    {
        Log::info('Validation attempt', [
            'request_id' => $id,
            'validated_by' => $request->validated_by,
            'status' => $request->status,
            'user_id' => auth()->id()
        ]);

        $validator = Validator::make($request->all(), [
            'validated_by' => 'required|exists:persons,id',
            'status' => 'required|in:approved,rejected',
            'validation_comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $req = DB::table('request')->where('id', $id)->first();
            if (!$req) {
                Log::warning('Request not found', ['id' => $id]);
                return response()->json(['message' => 'Demande non trouvée'], 404);
            }

            // Check if user is president of the club
            $isPresident = DB::table('club_members')
                ->where('club_id', $req->club_id)
                ->where('person_id', $request->validated_by)
                ->where('role', 'president')
                ->where('status', 'active')
                ->exists();

            if (!$isPresident) {
                Log::warning('Unauthorized validation attempt', [
                    'user_id' => $request->validated_by,
                    'club_id' => $req->club_id,
                    'request_id' => $id
                ]);
                return response()->json([
                    'message' => 'Vous n\'êtes pas président de ce club'
                ], 403);
            }

            // 1. Update request status
            DB::table('request')
                ->where('id', $id)
                ->update([
                    'status' => $request->status,
                    'validated_by' => $request->validated_by,
                    'validated_at' => now(),
                    'validation_comment' => $request->validation_comment,
                ]);

            // 2. Process approved request based on type
            if ($request->status === 'approved') {
                $metadata = json_decode($req->metadata, true);
                
                Log::info('Processing approved request', [
                    'type' => $req->type,
                    'metadata' => $metadata
                ]);

                if ($req->type === 'other') {
                    $this->processMemberRequest($req->club_id, $metadata);
                } elseif ($req->type === 'event') {
                    $this->processEventRequest($req->club_id, $metadata, $request->validated_by);
                }
            }

            Log::info('Request validated successfully', [
                'request_id' => $id,
                'status' => $request->status,
                'validated_by' => $request->validated_by
            ]);

            $updatedRequest = DB::table('request')
                ->join('clubs', 'request.club_id', '=', 'clubs.id')
                ->join('persons as requester', 'request.requested_by', '=', 'requester.id')
                ->leftJoin('persons as validator', 'request.validated_by', '=', 'validator.id')
                ->where('request.id', $id)
                ->select(
                    'request.*',
                    'clubs.name as club_name',
                    'requester.first_name as requester_first_name',
                    'requester.last_name as requester_last_name',
                    'validator.first_name as validator_first_name',
                    'validator.last_name as validator_last_name'
                )
                ->first();

            return response()->json([
                'message' => 'Demande validée avec succès',
                'request' => $updatedRequest
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error validating request: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_id' => $id
            ]);
            return response()->json([
                'message' => 'Erreur lors de la validation: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $exists = DB::table('request')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Demande non trouvée'], 404);
            }

            DB::table('request')->where('id', $id)->delete();

            return response()->json(['message' => 'Demande supprimée avec succès'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting request: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getClubStats($clubId)
    {
        try {
            $stats = [
                'total_requests' => DB::table('request')->where('club_id', $clubId)->count(),
                'pending_requests' => DB::table('request')->where('club_id', $clubId)->where('status', 'pending')->count(),
                'approved_requests' => DB::table('request')->where('club_id', $clubId)->where('status', 'approved')->count(),
                'rejected_requests' => DB::table('request')->where('club_id', $clubId)->where('status', 'rejected')->count(),
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            Log::error('Error getting club stats: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  PRIVATE METHODS FOR PROCESSING APPROVED REQUESTS
    // ─────────────────────────────────────────────────────────────

    /**
     * Traite une demande d'ajout de membre (type 'other')
     */
    private function processMemberRequest($clubId, $metadata)
    {
        // 🔥 Validate required metadata fields
        $required = ['first_name', 'last_name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($metadata[$field])) {
                throw new \Exception("Champ manquant dans metadata: $field");
            }
        }

        DB::beginTransaction();
        try {
            // Check if person already exists by email
            $person = Person::where('email', $metadata['email'])->first();

            if (!$person) {
                // Create new person
                $personData = [
                    'first_name'  => $metadata['first_name'],
                    'last_name'   => $metadata['last_name'],
                    'email'       => $metadata['email'],
                    'password'    => Hash::make($metadata['password']),
                    'cne'         => $metadata['cne'] ?? null,
                    'phone'       => $metadata['phone'] ?? null,
                    'member_code' => $this->generateMemberCode(),
                    'is_active'   => true,
                    'role'        => 'user',
                ];
                
                $person = Person::create($personData);
                Log::info('Person created', ['person_id' => $person->id, 'email' => $person->email]);
            } else {
                Log::info('Person already exists', ['person_id' => $person->id]);
            }

            // Check if already a member of this club
            $existing = Club_member::where('person_id', $person->id)
                ->where('club_id', $clubId)
                ->where('status', 'active')
                ->exists();

            if ($existing) {
                Log::warning('Member already active', ['person_id' => $person->id, 'club_id' => $clubId]);
                DB::commit();
                return;
            }

            // Create membership
            $role = $metadata['role'] ?? 'member';
            $position = $metadata['position'] ?? null;
            
            Club_member::create([
                'person_id' => $person->id,
                'club_id'   => $clubId,
                'role'      => $role,
                'position'  => $position,
                'status'    => 'active',
                'joined_at' => now(),
            ]);
            Log::info('Club membership created', ['person_id' => $person->id, 'club_id' => $clubId, 'role' => $role]);

            // Update club counts
            $this->updateClubMemberCounts($clubId);

            // Send welcome notification
            $club = Club::find($clubId);
            if ($club) {
                $this->createWelcomeNotification($person, $club, $role);
            }

            DB::commit();
            Log::info('Member request processed successfully', ['person_id' => $person->id]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process member request: ' . $e->getMessage(), [
                'metadata' => $metadata,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to create member: " . $e->getMessage());
        }
    }

    /**
     * Traite une demande de création d'événement (type 'event')
     */
   private function processEventRequest($clubId, $metadata, $createdBy)
{
    // 1. Validate required fields
    $required = ['title', 'event_date'];
    foreach ($required as $field) {
        if (empty($metadata[$field])) {
            throw new \Exception("Champ manquant: $field");
        }
    }

    // 2. Convert date strings to proper format (datetime-local gives "Y-m-d\TH:i")
    try {
        $eventDate = \Carbon\Carbon::parse($metadata['event_date'])->format('Y-m-d H:i:s');
        $deadline = !empty($metadata['registration_deadline'])
            ? \Carbon\Carbon::parse($metadata['registration_deadline'])->format('Y-m-d H:i:s')
            : null;
    } catch (\Exception $e) {
        throw new \Exception("Format de date invalide: " . $metadata['event_date']);
    }

    $eventData = [
        'club_id'               => $clubId,
        'title'                 => $metadata['title'],
        'description'           => $metadata['description'] ?? null,
        'category'              => $metadata['category'] ?? null,
        'event_date'            => $eventDate,
        'registration_deadline' => $deadline,
        'location'              => $metadata['location'] ?? null,
        'capacity'              => $metadata['capacity'] ?? null,
        'status'                => 'approved',
        'is_public'             => $metadata['is_public'] ?? true,
        'banner_image'          => $metadata['banner_image'] ?? null,
        'requires_ticket'       => $metadata['requires_ticket'] ?? false,
        'tickets_for_all'       => $metadata['tickets_for_all'] ?? false,
        'price'                 => $metadata['price'] ?? 0,
        'created_by'            => $createdBy,
        'registered_count'      => 0,
        'attendees_count'       => 0,
    ];

    // 3. Create event (outside any transaction – we want it saved regardless)
    try {
        $event = Event::create($eventData);
        if (!$event || !$event->id) {
            throw new \Exception("Event creation failed – no ID returned");
        }
        Log::info("Event created from request", ['event_id' => $event->id, 'club_id' => $clubId]);
    } catch (\Exception $e) {
        Log::error("Event creation failed: " . $e->getMessage(), ['data' => $eventData]);
        throw new \Exception("Impossible de créer l'événement: " . $e->getMessage());
    }

    // 4. Generate tickets only if required – but DO NOT rollback event on failure
    if ($event->tickets_for_all) {
        try {
            $eventController = new EventController();
            $eventController->sendTicketsToClubMembers($event);
        } catch (\Exception $e) {
            // Log the error but don't stop – event already saved
            Log::error("Ticket generation failed after event creation: " . $e->getMessage(), [
                'event_id' => $event->id
            ]);
        }
    }
}

    /**
     * Génère un code membre unique
     */
    private function generateMemberCode()
    {
        do {
            $code = 'MBR' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (Person::where('member_code', $code)->exists());
        return $code;
    }

    /**
     * Met à jour les compteurs total_members et active_members du club
     */
    private function updateClubMemberCounts($clubId)
    {
        $totalMembers = Club_member::where('club_id', $clubId)->count();
        $activeMembers = Club_member::where('club_id', $clubId)->where('status', 'active')->count();

        Club::where('id', $clubId)->update([
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
        ]);
    }

    /**
     * Crée une notification de bienvenue pour le nouveau membre
     */
    private function createWelcomeNotification($person, $club, $role)
    {
        $roleNames = [
            'president' => 'Président',
            'board' => 'Membre du Bureau',
            'member' => 'Membre'
        ];
        $roleName = $roleNames[$role] ?? 'Membre';

        \App\Models\Notification::create([
            'person_id' => $person->id,
            'type' => 'welcome',
            'title' => "🎉 Bienvenue au club {$club->name} !",
            'message' => "Vous êtes maintenant inscrit en tant que **{$roleName}** au club **{$club->name}**.",
            'dashboard_link' => '/Member/Dashboard',
            'data' => json_encode([
                'club_id' => $club->id,
                'club_name' => $club->name,
                'role' => $role,
                'role_name' => $roleName,
                'joined_at' => now()->toDateTimeString()
            ]),
            'read' => false,
            'email_sent' => false,
            'created_at' => now(),
        ]);
    }
}