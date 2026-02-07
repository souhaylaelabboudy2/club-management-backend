<?php

namespace App\Http\Controllers;

use App\Models\Club_member;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketMail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    /**
     * Get all events (Public)
     */
    public function index()
    {
        try {
            $events = Event::all();
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching events', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get event by ID (Public)
     */
    public function show($id)
    {
        try {
            $event = Event::find($id);
            
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            if ($event->recap_images && is_string($event->recap_images)) {
                $event->recap_images = json_decode($event->recap_images, true);
            }

            return response()->json($event, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching event: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching event', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new event (Protected - Admin/President only)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'club_id' => 'required|exists:clubs,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string|max:100',
                'event_date' => 'required|date',
                'registration_deadline' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:0',
                'status' => 'nullable|in:pending,approved,completed,cancelled',
                'is_public' => 'nullable|boolean',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'requires_ticket' => 'nullable|boolean',
                'tickets_for_all' => 'nullable|boolean',
                'price' => 'nullable|numeric|min:0',
            ]);

            $validated['created_by'] = auth()->id();
            $validated['registered_count'] = 0;
            $validated['attendees_count'] = 0;
            $validated['status'] = $validated['status'] ?? 'pending';

            // Handle banner image file upload
            if ($request->hasFile('banner_image')) {
                $bannerPath = $request->file('banner_image')->store('events/banners', 'public');
                $validated['banner_image'] = $bannerPath;
            }

            $event = Event::create($validated);
            
            if (!$event || !$event->id) {
                Log::error('Event creation failed - event object is null or has no ID');
                return response()->json([
                    'message' => 'Error creating event - database save failed',
                ], 500);
            }

            $event->refresh();

            Log::info('Event created successfully: ' . $event->id . ' by user: ' . auth()->id());

            if ($event->status === 'approved' && $event->tickets_for_all) {
                Log::info('Triggering automatic ticket sending for event: ' . $event->id);
                
                $eventExists = DB::table('event')->where('id', $event->id)->exists();
                if ($eventExists) {
                    $this->sendTicketsToClubMembers($event);
                } else {
                    Log::error('Cannot send tickets - event ' . $event->id . ' does not exist in database');
                }
            }

            return response()->json([
                'message' => 'Event created successfully',
                'event' => $event
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error creating event: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error creating event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an event (Protected - Admin/President only)
     */
    public function update(Request $request, $id)
    {
        try {
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string|max:100',
                'event_date' => 'nullable|date',
                'registration_deadline' => 'nullable|date',
                'location' => 'nullable|string|max:255',
                'capacity' => 'nullable|integer|min:0',
                'status' => 'nullable|in:pending,approved,completed,cancelled',
                'is_public' => 'nullable|boolean',
                'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'requires_ticket' => 'nullable|boolean',
                'tickets_for_all' => 'nullable|boolean',
                'price' => 'nullable|numeric|min:0',
            ]);

            // Handle banner image file upload
            if ($request->hasFile('banner_image')) {
                // Delete old banner if exists
                if ($event->banner_image && Storage::disk('public')->exists($event->banner_image)) {
                    Storage::disk('public')->delete($event->banner_image);
                }
                $bannerPath = $request->file('banner_image')->store('events/banners', 'public');
                $validated['banner_image'] = $bannerPath;
            }

            $event->update($validated);

            Log::info('Event updated: ' . $id . ' by user: ' . auth()->id());

            return response()->json([
                'message' => 'Event updated successfully',
                'event' => $event
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating event: ' . $e->getMessage());
            return response()->json(['message' => 'Error updating event', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete an event (Protected - Admin/President only)
     */
    public function destroy($id)
    {
        try {
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            // Delete banner image if exists
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

    /**
     * Update event status (Protected)
     */
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

            $oldStatus = $event->status;
            $event->status = $validated['status'];

            if ($validated['status'] === 'completed') {
                $event->completed_at = now();
            }

            $event->save();

            Log::info('Event status updated: ' . $id . ' from ' . $oldStatus . ' to ' . $validated['status']);

            if ($oldStatus !== 'approved' && $validated['status'] === 'approved' && $event->tickets_for_all) {
                Log::info('Triggering ticket sending due to status change for event: ' . $event->id);
                $this->sendTicketsToClubMembers($event);
            }

            return response()->json([
                'message' => 'Event status updated successfully',
                'event' => $event
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating event status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating event status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ SEND TICKETS TO ALL CLUB MEMBERS - FIXED TO USE SVG
     */
    private function sendTicketsToClubMembers($event)
    {
        try {
            Log::info('🎫 ===== STARTING TICKET SEND PROCESS =====');
            Log::info('Event ID: ' . $event->id . ' | Title: ' . $event->title);

            $clubMembers = DB::table('club_members')
                ->join('persons', 'club_members.person_id', '=', 'persons.id')
                ->where('club_members.club_id', $event->club_id)
                ->where('club_members.status', 'active')
                ->select('persons.id', 'persons.first_name', 'persons.last_name', 'persons.email')
                ->get();
            
            if ($clubMembers->isEmpty()) {
                Log::warning('⚠️ No active members found for club: ' . $event->club_id);
                return;
            }

            Log::info('✅ Found ' . $clubMembers->count() . ' active members');

            $club = DB::table('clubs')->where('id', $event->club_id)->first();
            
            if (!$club) {
                Log::error('❌ Club not found: ' . $event->club_id);
                return;
            }
            
            Log::info('✅ Club found: ' . $club->name);
            
            $pdfPath = public_path('temp_tickets');
            if (!file_exists($pdfPath)) {
                mkdir($pdfPath, 0755, true);
                Log::info('📁 Created temp_tickets directory');
            }
            
            $successCount = 0;
            $failCount = 0;
            
            foreach ($clubMembers as $index => $member) {
                try {
                    Log::info("📨 Processing member " . ($index + 1) . "/" . $clubMembers->count() . ": " . $member->email);
                    
                    $existingTicket = DB::table('tickets')
                        ->where('event_id', $event->id)
                        ->where('person_id', $member->id)
                        ->exists();
                    
                    if ($existingTicket) {
                        Log::info('⏭️ Member already has ticket - skipping');
                        continue;
                    }

                    $ticketCode = 'TKT-' . strtoupper(substr(md5(uniqid($event->id . $member->id, true)), 0, 12));
                    Log::info('🎟️ Generated ticket code: ' . $ticketCode);
                    
                    $ticketId = DB::table('tickets')->insertGetId([
                        'event_id' => $event->id,
                        'person_id' => $member->id,
                        'qr_code' => $ticketCode,
                        'status' => 'valid',
                        'auto_generated' => true,
                        'generated_by' => auth()->id() ?? null,
                        'generated_at' => now(),
                    ]);

                    Log::info('✅ Ticket created in DB with ID: ' . $ticketId);

                    // ⚡ USE SVG QR CODE - NO IMAGICK NEEDED!
                    $qrData = json_encode([
                        'ticket_id' => $ticketId,
                        'event_id' => $event->id,
                        'person_id' => $member->id,
                        'event_title' => $event->title,
                        'ticket_code' => $ticketCode
                    ]);
                    
                    // Generate SVG QR code (works without Imagick!)
                    $qrCodeSvg = QrCode::format('svg')->size(300)->generate($qrData);
                    $qrCodeBase64 = base64_encode($qrCodeSvg);
                    
                    Log::info('✅ QR Code generated as SVG');
                    
                    $ticketData = (object)[
                        'id' => $ticketId,
                        'event_title' => $event->title,
                        'event_date' => $event->event_date,
                        'event_location' => $event->location ?? 'TBA',
                        'first_name' => $member->first_name,
                        'last_name' => $member->last_name,
                        'club_name' => $club->name,
                        'club_logo' => $club->logo ?? null,
                    ];
                    
                    Log::info('📄 Generating PDF...');
                    $pdfFilePath = $this->generateTicketPDF($ticketData, $ticketCode, $qrCodeBase64);
                    
                    if (!file_exists($pdfFilePath)) {
                        Log::error('❌ PDF file was not created at: ' . $pdfFilePath);
                        $failCount++;
                        continue;
                    }
                    
                    Log::info('✅ PDF created: ' . basename($pdfFilePath) . ' (' . filesize($pdfFilePath) . ' bytes)');
                    
                    if (!filter_var($member->email, FILTER_VALIDATE_EMAIL)) {
                        Log::error('❌ Invalid email address: ' . $member->email);
                        $failCount++;
                        continue;
                    }
                    
                    $emailData = [
                        'memberName' => $member->first_name . ' ' . $member->last_name,
                        'eventTitle' => $event->title,
                        'eventDate' => $event->event_date,
                        'eventLocation' => $event->location ?? 'TBA',
                        'eventDescription' => $event->description ?? '',
                        'pdfPath' => $pdfFilePath,
                    ];
                    
                    Log::info('📧 Sending email to: ' . $member->email);
                    
                    Mail::to($member->email)->send(new TicketMail($emailData));
                    
                    Log::info('✅ Email sent successfully!');
                    
                    if (file_exists($pdfFilePath)) {
                        unlink($pdfFilePath);
                        Log::info('🗑️ PDF file deleted');
                    }
                    
                    $successCount++;
                    
                    usleep(100000); // 0.1 seconds
                    
                } catch (\Exception $e) {
                    Log::error('❌ Failed for ' . $member->email);
                    Log::error('Error: ' . $e->getMessage());
                    Log::error('Trace: ' . $e->getTraceAsString());
                    $failCount++;
                }
            }

            Log::info("🎉 ===== TICKET SEND PROCESS COMPLETE =====");
            Log::info("Event: {$event->title} (ID: {$event->id})");
            Log::info("✅ Success: {$successCount} | ❌ Failed: {$failCount}");
            
        } catch (\Exception $e) {
            Log::error('💥 CRITICAL ERROR in sendTicketsToClubMembers');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Generate PDF Ticket
     */
    private function generateTicketPDF($ticket, $ticketCode, $qrCodeBase64)
    {
        $data = [
            'ticket' => $ticket,
            'ticketCode' => $ticketCode,
            'qrCodeBase64' => $qrCodeBase64,
            'clubLogo' => $ticket->club_logo ? public_path('storage/' . $ticket->club_logo) : null,
        ];

        $pdf = Pdf::loadView('pdf.ticket', $data);
        
        $pdfPath = public_path('temp_tickets');
        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 0755, true);
        }
        
        $filename = 'ticket_' . $ticket->id . '_' . time() . '.pdf';
        $fullPath = $pdfPath . '/' . $filename;
        
        $pdf->save($fullPath);
        
        return $fullPath;
    }

    /**
     * Add recap to event (Protected)
     */
    public function addRecap(Request $request, $id)
    {
        try {
            $event = Event::find($id);

            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            $validated = $request->validate([
                'recap_description' => 'nullable|string',
                'recap_images' => 'nullable|array',
                'recap_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if (isset($validated['recap_description'])) {
                $event->recap_description = $validated['recap_description'];
            }

            // Handle recap images file uploads
            if ($request->hasFile('recap_images')) {
                $recapImages = [];
                foreach ($request->file('recap_images') as $image) {
                    $path = $image->store('events/recaps', 'public');
                    $recapImages[] = $path;
                }
                $event->recap_images = json_encode($recapImages);
            }

            $event->status = 'completed';
            $event->completed_at = now();
            $event->save();

            Log::info('Event recap added: ' . $id . ' by user: ' . auth()->id());

            return response()->json([
                'message' => 'Event recap added successfully',
                'event' => $event
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error adding recap: ' . $e->getMessage());
            return response()->json(['message' => 'Error adding recap', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get upcoming events (Public)
     */
    public function upcoming()
    {
        try {
            $events = Event::where('event_date', '>=', now())
                ->where('status', 'approved')
                ->orderBy('event_date', 'asc')
                ->get();

            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching upcoming events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching upcoming events', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get past/completed events (Public)
     */
    public function pastEvents()
    {
        try {
            $events = Event::where('event_date', '<', now())
                ->orWhere('status', 'completed')
                ->orderBy('event_date', 'desc')
                ->get();

            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching past events: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching past events', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get events by club (Public)
     */
    public function getByClub($clubId)
    {
        try {
            $events = Event::where('club_id', $clubId)
                ->orderBy('event_date', 'desc')
                ->get();
            return response()->json($events, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching events by club: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching events', 'error' => $e->getMessage()], 500);
        }
    }
}