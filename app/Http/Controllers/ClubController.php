<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Club_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ClubController extends Controller
{
    private function addImageUrls($club)
    {
        // Remove 'public/' prefix if it exists, since storage link already points to storage/app/public
        $logoPath = $club->logo ?? null;
        if ($logoPath && str_starts_with($logoPath, 'public/')) {
            $logoPath = substr($logoPath, 7); // Remove 'public/' prefix
        }
        
        $coverPath = $club->cover_image ?? null;
        if ($coverPath && str_starts_with($coverPath, 'public/')) {
            $coverPath = substr($coverPath, 7);
        }
        
        $club->logo_url = $logoPath ? url('storage/' . $logoPath) : null;
        $club->cover_image_url = $coverPath ? url('storage/' . $coverPath) : null;
        return $club;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:clubs,name',
            'code' => 'nullable|string|max:50|unique:clubs,code',
            'description' => 'required|string',
            'mission' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'category' => 'required|string|max:100',
            'founding_year' => 'required|integer|min:1900|max:' . date('Y'),
            'is_public' => 'boolean',
            'total_members' => 'nullable|integer|min:0',
            'active_members' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->all();
            
            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('clubs/logos', 'public');
            }
            if ($request->hasFile('cover_image')) {
                $data['cover_image'] = $request->file('cover_image')->store('clubs/covers', 'public');
            }
            if (empty($data['code'])) {
                $data['code'] = Str::slug($data['name']);
            }

            $club = Club::create($data);
            $this->addImageUrls($club);

            return response()->json(['message' => 'Club créé avec succès', 'club' => $club], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la création du club', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:clubs,name,' . $id,
            'code' => 'nullable|string|max:50|unique:clubs,code,' . $id,
            'description' => 'sometimes|required|string',
            'mission' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'category' => 'sometimes|required|string|max:100',
            'founding_year' => 'sometimes|required|integer|min:1900|max:' . date('Y'),
            'is_public' => 'boolean',
            'total_members' => 'nullable|integer|min:0',
            'active_members' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $club = Club::findOrFail($id);
            $data = $request->all();

            if ($request->hasFile('logo')) {
                if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                    Storage::disk('public')->delete($club->logo);
                }
                $data['logo'] = $request->file('logo')->store('clubs/logos', 'public');
            }
            if ($request->hasFile('cover_image')) {
                if ($club->cover_image && Storage::disk('public')->exists($club->cover_image)) {
                    Storage::disk('public')->delete($club->cover_image);
                }
                $data['cover_image'] = $request->file('cover_image')->store('clubs/covers', 'public');
            }

            $club->update($data);
            $this->addImageUrls($club);

            return response()->json(['message' => 'Club mis à jour avec succès', 'club' => $club], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Club non trouvé'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la mise à jour du club', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Club::query();

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }
            if ($request->has('is_public')) {
                $query->where('is_public', $request->boolean('is_public'));
            }
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $query->orderBy($request->get('order_by', 'created_at'), $request->get('order_dir', 'desc'));
            $clubs = $query->get();
            
            $clubs->each(function($club) {
                $this->addImageUrls($club);
            });

            return response()->json($clubs, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la récupération des clubs', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $club = Club::findOrFail($id);
            $this->addImageUrls($club);
            return response()->json($club, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Club non trouvé'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur', 'error' => $e->getMessage()], 500);
        }
    }

    public function showByCode($code)
    {
        try {
            $club = Club::where('code', $code)->firstOrFail();
            $this->addImageUrls($club);
            return response()->json($club, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Club non trouvé'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $club = Club::findOrFail($id);
            
            if ($club->logo && Storage::disk('public')->exists($club->logo)) {
                Storage::disk('public')->delete($club->logo);
            }
            if ($club->cover_image && Storage::disk('public')->exists($club->cover_image)) {
                Storage::disk('public')->delete($club->cover_image);
            }
            
            $club->delete();
            return response()->json(['message' => 'Club supprimé avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function statistics($id)
    {
        try {
            $club = Club::findOrFail($id);
            
            return response()->json([
                'club_id' => $club->id,
                'club_name' => $club->name,
                'total_members' => $club->total_members ?? 0,
                'active_members' => $club->active_members ?? 0,
                'founding_year' => $club->founding_year,
                'years_active' => $club->founding_year ? (date('Y') - $club->founding_year) : 0,
                'category' => $club->category,
                'is_public' => $club->is_public,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Club non trouvé'], 404);
        }
    }

    public function getMyClub(Request $request)
    {
        try {
            $person = $request->user();
            
            if (!$person) {
                \Log::warning('getMyClub: No authenticated user');
                return response()->json(['message' => 'Non authentifié'], 401);
            }

            \Log::info('getMyClub called', ['person_id' => $person->id]);

            // OPTIMIZED: Single query with join instead of 3 separate queries
            // This prevents Railway database connection timeout issues
            $result = DB::table('club_members')
                ->join('clubs', 'club_members.club_id', '=', 'clubs.id')
                ->where('club_members.person_id', $person->id)
                ->where('club_members.role', 'president')
                ->where('club_members.status', 'active')
                ->select(
                    'clubs.id',
                    'clubs.name',
                    'clubs.code',
                    'clubs.description',
                    'clubs.mission',
                    'clubs.logo',
                    'clubs.cover_image',
                    'clubs.category',
                    'clubs.founding_year',
                    'clubs.is_public',
                    'clubs.total_members',
                    'clubs.active_members',
                    'clubs.created_at',
                    'clubs.updated_at',
                    'club_members.id as membership_id'
                )
                ->first();

            if (!$result) {
                \Log::warning('getMyClub: No president membership found', ['person_id' => $person->id]);
                return response()->json(['message' => 'Vous n\'êtes président d\'aucun club'], 403);
            }

            // Convert stdClass to object for processing
            $club = $result;
            $this->addImageUrls($club);

            \Log::info('getMyClub success', ['club_id' => $club->id, 'club_name' => $club->name]);
            
            return response()->json($club, 200);

        } catch (\Exception $e) {
            \Log::error('Error in getMyClub: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Erreur lors de la récupération du club', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateMemberCounts($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_members' => 'required|integer|min:0',
            'active_members' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $club = Club::findOrFail($id);
            $club->update([
                'total_members' => $request->total_members,
                'active_members' => $request->active_members,
            ]);

            return response()->json(['message' => 'Nombre de membres mis à jour avec succès', 'club' => $club], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}