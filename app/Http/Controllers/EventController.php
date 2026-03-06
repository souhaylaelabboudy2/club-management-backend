<?php

namespace App\Http\Controllers;

use App\Models\Club_member;
use App\Models\Event;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    //  PRIVATE HELPER
    // ─────────────────────────────────────────────────────────────

    private function addImageUrls($event)
    {
        $event->banner_url = $event->banner_image
            ? url('storage/' . $event->banner_image)
            : null;

        if ($event->recap_images) {
            $images = is_string($event->recap_images)
                ? json_decode($event->recap_images, true)
                : $event->recap_images;

            if (is_array($images)) {
                $event->recap_images = array_map(function ($path) {
                    return url('storage/' . $path);
                }, $images);
            }
        }

        return $event;
    }

    // ─────────────────────────────────────────────────────────────
    //  PUBLIC CRUD
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        try {
            $events = Event::all();
            $events->each(fn($e) => $this->addImageUrls($e));
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching events', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }
            $this->addImageUrls($event);
            return response()->json($event, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching event', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'club_id'               => 'required|exists:clubs,id',
                'title'                 => 'required|string|max:255',
                'description'           => 'nullable|string',
                'category'              => 'nullable|string|max:100',
                'event_date'            => 'required|date',
                'registration_deadline' => 'nullable|date',
                'location'              => 'nullable|string|max:255',
                'capacity'              => 'nullable|integer|min:0',
                'status'                => 'nullable|in:pending,approved,completed,cancelled',
                'is_public'             => 'nullable|boolean',
                'banner_image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'requires_ticket'       => 'nullable|boolean',
                'tickets_for_all'       => 'nullable|boolean',
                'price'                 => 'nullable|numeric|min:0',
            ]);

            $validated['created_by']       = auth()->id();
            $validated['registered_count'] = 0;
            $validated['attendees_count']  = 0;
            $validated['status']           = $validated['status'] ?? 'pending';

            if ($request->hasFile('banner_image')) {
                $validated['banner_image'] = $request->file('banner_image')
                    ->store('events/banners', 'public');
            }

            $event = Event::create($validated);

            if (!$event || !$event->id) {
                Log::error('Event creation failed - event object is null or has no ID');
                return response()->json(['message' => 'Error creating event - database save failed'], 500);
            }

            $event->refresh();
            $this->addImageUrls($event);

            Log::info('Event created successfully: ' . $event->id . ' by user: ' . auth()->id());

            if ($event->status === 'approved' && $event->tickets_for_all) {
                Log::info('Triggering ticket+notification creation for event: ' . $event->id);
                $this->sendTicketsToClubMembers($event);
            }

            return response()->json([
                'message' => 'Event created successfully',
                'event'   => $event,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating event: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error creating event',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $validated = $request->validate([
                'title'                 => 'nullable|string|max:255',
                'description'           => 'nullable|string',
                'category'              => 'nullable|string|max:100',
                'event_date'            => 'nullable|date',
                'registration_deadline' => 'nullable|date',
                'location'              => 'nullable|string|max:255',
                'capacity'              => 'nullable|integer|min:0',
                'status'                => 'nullable|in:pending,approved,completed,cancelled',
                'is_public'             => 'nullable|boolean',
                'banner_image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'requires_ticket'       => 'nullable|boolean',
                'tickets_for_all'       => 'nullable|boolean',
                'price'                 => 'nullable|numeric|min:0',
            ]);

            if ($request->hasFile('banner_image')) {
                if ($event->banner_image && Storage::disk('public')->exists($event->banner_image)) {
                    Storage::disk('public')->delete($event->banner_image);
                }
                $validated['banner_image'] = $request->file('banner_image')
                    ->store('events/banners', 'public');
            }

            $event->update($validated);
            $event->refresh();
            $this->addImageUrls($event);

            Log::info('Event updated: ' . $id . ' by user: ' . auth()->id());

            return response()->json([
                'message' => 'Event updated successfully',
                'event'   => $event,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating event: ' . $e->getMessage());
            return response()->json(['message' => 'Error updating event', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            if ($event->banner_image && Storage::disk('public')->exists($event->banner_image)) {
                Storage::disk('public')->delete($event->banner_image);
            }

            $event->delete();
            Log::info('Event deleted: ' . $id . ' by user: ' . auth()->id());
            return response()->json(['message' => 'Event deleted successfully'], 200);

        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());
            return response()->json(['message' => 'Error deleting event', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:pending,approved,completed,cancelled',
            ]);

            $oldStatus     = $event->status;
            $event->status = $validated['status'];

            if ($validated['status'] === 'completed') {
                $event->completed_at = now();
            }

            $event->save();
            $event->refresh();
            $this->addImageUrls($event);

            Log::info('Event status updated: ' . $id . ' from ' . $oldStatus . ' to ' . $validated['status']);

            if ($oldStatus !== 'approved' && $validated['status'] === 'approved' && $event->tickets_for_all) {
                Log::info('Triggering ticket+notification creation due to status change for event: ' . $event->id);
                $this->sendTicketsToClubMembers($event);
            }

            return response()->json([
                'message' => 'Event status updated successfully',
                'event'   => $event,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating event status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating event status',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function addRecap(Request $request, $id)
    {
        try {
            $event = Event::find($id);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $request->validate([
                'recap_description' => 'nullable|string',
                'recap_images'      => 'nullable|array',
                'recap_images.*'    => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->has('recap_description')) {
                $event->recap_description = $request->recap_description;
            }

            $existingImages = [];
            if ($event->recap_images) {
                $existingImages = is_string($event->recap_images)
                    ? (json_decode($event->recap_images, true) ?? [])
                    : (array) $event->recap_images;
            }

            if ($request->hasFile('recap_images')) {
                foreach ($request->file('recap_images') as $image) {
                    $existingImages[] = $image->store('events/recaps', 'public');
                }
            }

            $event->recap_images = json_encode($existingImages);
            $event->status       = 'completed';
            $event->completed_at = now();
            $event->save();
            $event->refresh();
            $this->addImageUrls($event);

            Log::info('Event recap added: ' . $id . ' by user: ' . auth()->id());

            return response()->json([
                'message' => 'Event recap added successfully',
                'event'   => $event,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error adding recap: ' . $e->getMessage());
            return response()->json(['message' => 'Error adding recap', 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  TICKET + NOTIFICATION CREATION
    // ─────────────────────────────────────────────────────────────

    private function sendTicketsToClubMembers($event)
    {
        try {
            $club = DB::table('clubs')->where('id', $event->club_id)->first();

            $members = DB::table('club_members')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->where('club_members.club_id', $event->club_id)
                ->where('club_members.status', 'active')
                ->select(
                    'persons.id as person_id',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                )
                ->get();

            $eventDate = \Carbon\Carbon::parse($event->event_date)
                ->locale('fr')
                ->isoFormat('dddd D MMMM YYYY [à] HH[h]mm');

            foreach ($members as $member) {
                // ── 1. Generate unique ticket code ──
                do {
                    $ticketCode = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
                } while (DB::table('tickets')->where('qr_code', $ticketCode)->exists());

                // ── 2. Insert into tickets table ──
                $ticketId = DB::table('tickets')->insertGetId([
                    'event_id'       => $event->id,
                    'person_id'      => $member->person_id,
                    'qr_code'        => $ticketCode,
                    'status'         => 'valid',
                    'auto_generated' => true,
                    'generated_by'   => null,
                    'generated_at'   => now(),
                    'sent_at'        => null,
                    'scanned_at'     => null,
                    'scanned_by'     => null,
                ]);

                // ── 3. Create notification linked to ticket ──
                DB::table('notifications')->insert([
                    'person_id'      => $member->person_id,
                    'type'           => 'event_ticket',
                    'title'          => '🎟️ Votre billet : ' . $event->title,
                    'message'        => sprintf(
                        "Bonjour %s,\n\nVous êtes invité(e) à \"%s\" organisé par %s.\n\n📅 %s\n📍 %s\n\nCode billet : %s\n\nCliquez sur « Télécharger » pour obtenir votre billet PDF.",
                        $member->first_name,
                        $event->title,
                        $club->name ?? 'votre club',
                        $eventDate,
                        $event->location ?? 'Lieu à confirmer',
                        $ticketCode
                    ),
                    'dashboard_link' => null,
                    'data'           => json_encode([
                        'ticket_id'   => $ticketId,
                        'ticket_code' => $ticketCode,
                        'event_id'    => $event->id,
                        'event_title' => $event->title,
                        'event_date'  => $event->event_date,
                        'location'    => $event->location,
                        'club_id'     => $event->club_id,
                        'club_name'   => $club->name ?? '',
                        'club_logo'   => $club->logo ?? null,
                        'member_name' => $member->first_name . ' ' . $member->last_name,
                    ]),
                    'read'           => false,
                    'email_sent'     => false,
                    'created_at'     => now(),
                ]);

                Log::info('Ticket + Notification created for member: ' . $member->person_id . ' ticket_id: ' . $ticketId);
            }

            Log::info('All tickets+notifications done for event ' . $event->id . ' → ' . $members->count() . ' members.');

        } catch (\Exception $e) {
            Log::error('Error in sendTicketsToClubMembers: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  PDF TICKET DOWNLOAD  (by ticket_id from notification data)
    // ─────────────────────────────────────────────────────────────

    public function downloadTicketPdf($ticketId)
    {
        try {
            $user = auth()->user();

            // Load ticket from tickets table — must belong to this user
            $ticket = DB::table('tickets')
                ->where('id', $ticketId)
                ->where('person_id', $user->id)
                ->first();

            if (!$ticket) {
                return response()->json(['message' => 'Billet non trouvé'], 404);
            }

            $event = DB::table('event')->where('id', $ticket->event_id)->first();
            $club  = $event ? DB::table('clubs')->where('id', $event->club_id)->first() : null;

            // ── QR code ──
            $ticketCode = $ticket->qr_code;
            $qrContent  = json_encode([
                'ticket_code' => $ticketCode,
                'ticket_id'   => $ticket->id,
                'event_id'    => $ticket->event_id,
                'person_id'   => $user->id,
            ]);
            $qrSvg        = QrCode::format('svg')->size(200)->errorCorrection('H')->generate($qrContent);
            $qrCodeBase64 = base64_encode($qrSvg);

            // ── Club logo ──
            $clubLogo  = null;
            $logoField = $club->logo ?? null;
            if ($logoField) {
                $path = storage_path('app/public/' . $logoField);
                if (file_exists($path)) {
                    $clubLogo = $path;
                }
            }

            // ── French date ──
            $rawDate            = $event->event_date ?? null;
            $eventDateFormatted = $rawDate
                ? \Carbon\Carbon::parse($rawDate)->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[h]mm')
                : 'Date à confirmer';

            // ── Ticket object for blade ──
            $ticketObj = (object) [
                'event_title'    => $event->title    ?? 'Événement',
                'event_date'     => $rawDate          ?? now(),
                'event_location' => $event->location  ?? 'À confirmer',
                'club_name'      => $club->name       ?? 'Club',
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
            ];

            $pdf = Pdf::loadView('pdf.Ticket', [
                'ticket'       => $ticketObj,
                'ticketCode'   => $ticketCode,
                'clubLogo'     => $clubLogo,
                'qrCodeBase64' => $qrCodeBase64,
                'generatedAt'  => now()->format('d/m/Y H:i'),
            ])->setPaper('a5', 'portrait');

            $filename = 'ticket-' . Str::slug($event->title ?? 'event') . '-' . $ticketCode . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PDF generation error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur génération PDF', 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  LISTING ENDPOINTS
    // ─────────────────────────────────────────────────────────────

    public function upcoming()
    {
        try {
            $events = Event::where('event_date', '>=', now())
                ->where('status', 'approved')
                ->orderBy('event_date', 'asc')
                ->get();
            $events->each(fn($e) => $this->addImageUrls($e));
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching upcoming events', 'error' => $e->getMessage()], 500);
        }
    }

    public function pastEvents()
    {
        try {
            $events = Event::where('event_date', '<', now())
                ->orWhere('status', 'completed')
                ->orderBy('event_date', 'desc')
                ->get();
            $events->each(fn($e) => $this->addImageUrls($e));
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching past events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching past events', 'error' => $e->getMessage()], 500);
        }
    }

    public function getByClub($clubId)
    {
        try {
            $events = Event::where('club_id', $clubId)
                ->orderBy('event_date', 'desc')
                ->get();
            $events->each(fn($e) => $this->addImageUrls($e));
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events by club: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching events', 'error' => $e->getMessage()], 500);
        }
    }
}