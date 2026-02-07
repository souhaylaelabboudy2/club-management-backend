<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    /**
     * Get all requests using JOIN
     */
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

            // Filter by club
            if ($request->has('club_id')) {
                $query->where('request.club_id', $request->club_id);
            }

            // Filter by requester
            if ($request->has('requested_by')) {
                $query->where('request.requested_by', $request->requested_by);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('request.type', $request->type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('request.status', $request->status);
            }

            // Order by
            $orderBy = $request->get('order_by', 'requested_at');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy('request.' . $orderBy, $orderDir);

            $requests = $query->get();

            return response()->json($requests, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des demandes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new request
     */
    public function store(Request $request)
    {
        // First, let's check if the IDs exist
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
            // Use Eloquent model - it handles timestamps and JSON automatically
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

            // Get request with relations
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
            return response()->json([
                'message' => 'Erreur lors de la création de la demande',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get a specific request using JOIN
     */
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

            // Decode metadata if exists
            if ($request->metadata) {
                $request->metadata = json_decode($request->metadata, true);
            }

            return response()->json($request, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a request
     */
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

            // Get updated request
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
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate/Reject a request
     */
    public function validate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'validated_by' => 'required|exists:persons,id',
            'status' => 'required|in:approved,rejected',
            'validation_comment' => 'nullable|string',
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

            DB::table('request')
                ->where('id', $id)
                ->update([
                    'status' => $request->status,
                    'validated_by' => $request->validated_by,
                    'validated_at' => now(),
                    'validation_comment' => $request->validation_comment,
                ]);

            // Get updated request
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
            return response()->json([
                'message' => 'Erreur lors de la validation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a request
     */
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
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get club request statistics
     */
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
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}