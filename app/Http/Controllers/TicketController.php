<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketMail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class TicketController extends Controller
{
  /**
 * Get all tickets using JOIN
 */
public function index(Request $request)
{
    try {
        $query = DB::table('tickets')
            ->join('event', 'tickets.event_id', '=', 'event.id')  // ✅ CHANGED from 'events' to 'event'
            ->join('persons', 'tickets.person_id', '=', 'persons.id')
            ->join('clubs', 'event.club_id', '=', 'clubs.id')  // ✅ CHANGED from 'events.club_id'
            ->select(
                'tickets.*',
                'event.title as event_title',  // ✅ CHANGED
                'event.event_date',  // ✅ CHANGED
                'event.location as event_location',  // ✅ CHANGED
                'persons.first_name',
                'persons.last_name',
                'persons.email',
                'clubs.name as club_name'
            );

        if ($request->has('event_id')) {
            $query->where('tickets.event_id', $request->event_id);
        }

        if ($request->has('person_id')) {
            $query->where('tickets.person_id', $request->person_id);
        }

        if ($request->has('status')) {
            $query->where('tickets.status', $request->status);
        }

        $orderBy = $request->get('order_by', 'generated_at');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy('tickets.' . $orderBy, $orderDir);

        $tickets = $query->get();

        \Log::info('Fetched tickets', [
            'count' => $tickets->count(),
            'person_id' => $request->person_id,
            'status' => $request->status
        ]);

        return response()->json($tickets, 200);
    } catch (\Exception $e) {
        \Log::error('Error fetching tickets: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json([
            'message' => 'Erreur lors de la récupération des tickets',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Create a new ticket
     * ✅ GENERATES PDF TICKET FOR EMAIL
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'person_id' => 'required|exists:persons,id',
            'status' => 'sometimes|in:valid,scanned,cancelled,expired',
            'auto_generated' => 'boolean',
            'generated_by' => 'nullable|exists:persons,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exists = DB::table('tickets')
                ->where('event_id', $request->event_id)
                ->where('person_id', $request->person_id)
                ->whereIn('status', ['valid', 'scanned'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Cette personne a déjà un ticket pour cet événement'
                ], 409);
            }

            $ticketCode = $this->generateQRCode();

            $ticketId = DB::table('tickets')->insertGetId([
                'event_id' => $request->event_id,
                'person_id' => $request->person_id,
                'qr_code' => $ticketCode,
                'status' => $request->status ?? 'valid',
                'auto_generated' => $request->auto_generated ?? false,
                'generated_by' => $request->generated_by,
                'generated_at' => now(),
            ]);

            $ticket = DB::table('tickets')
                ->join('events', 'tickets.event_id', '=', 'events.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->join('clubs', 'events.club_id', '=', 'clubs.id')
                ->where('tickets.id', $ticketId)
                ->select(
                    'tickets.*',
                    'events.title as event_title',
                    'events.event_date',
                    'events.location as event_location',
                    'events.description as event_description',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email',
                    'clubs.name as club_name',
                    'clubs.logo as club_logo'
                )
                ->first();

            // Generate QR Code as base64 for PDF
            $qrData = json_encode([
                'ticket_id' => $ticketId,
                'event_id' => $request->event_id,
                'person_id' => $request->person_id,
                'ticket_code' => $ticketCode,
                'event_title' => $ticket->event_title
            ]);
            
            $qrCodeBase64 = base64_encode(QrCode::format('png')->size(300)->generate($qrData));
            
            // Generate PDF
            $pdfPath = $this->generateTicketPDF($ticket, $ticketCode, $qrCodeBase64);
            
            $emailData = [
                'memberName' => $ticket->first_name . ' ' . $ticket->last_name,
                'eventTitle' => $ticket->event_title,
                'eventDate' => $ticket->event_date,
                'eventLocation' => $ticket->event_location,
                'eventDescription' => $ticket->event_description,
                'pdfPath' => $pdfPath,
            ];
            
            Mail::to($ticket->email)->send(new TicketMail($emailData));
            
            // Delete PDF after sending
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            
            Log::info('Ticket created and email sent to: ' . $ticket->email . ' for event: ' . $request->event_id);

            return response()->json([
                'message' => 'Ticket créé avec succès et email envoyé',
                'ticket' => $ticket
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating ticket: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la création du ticket',
                'error' => $e->getMessage()
            ], 500);
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
 * Get a specific ticket using JOIN
 */
public function show($id)
{
    try {
        $ticket = DB::table('tickets')
            ->join('event', 'tickets.event_id', '=', 'event.id')  // ✅ CHANGED
            ->join('persons', 'tickets.person_id', '=', 'persons.id')
            ->join('clubs', 'event.club_id', '=', 'clubs.id')  // ✅ CHANGED
            ->where('tickets.id', $id)
            ->select(
                'tickets.*',
                'event.title as event_title',  // ✅ CHANGED
                'event.event_date',  // ✅ CHANGED
                'event.location as event_location',  // ✅ CHANGED
                'event.banner_image',  // ✅ CHANGED
                'persons.first_name',
                'persons.last_name',
                'persons.email',
                'clubs.name as club_name',
                'clubs.logo as club_logo'
            )
            ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

        return response()->json($ticket, 200);
    } catch (\Exception $e) {
        \Log::error('Error fetching ticket: ' . $e->getMessage());
        return response()->json([
            'message' => 'Erreur lors de la récupération',
            'error' => $e->getMessage()
        ], 500);
    }
}

 /**
 * Get ticket by QR code
 */
public function showByQRCode($qrCode)
{
    try {
        $ticket = DB::table('tickets')
            ->join('event', 'tickets.event_id', '=', 'event.id')  // ✅ CHANGED
            ->join('persons', 'tickets.person_id', '=', 'persons.id')
            ->join('clubs', 'event.club_id', '=', 'clubs.id')  // ✅ CHANGED
            ->where('tickets.qr_code', $qrCode)
            ->select(
                'tickets.*',
                'event.title as event_title',  // ✅ CHANGED
                'event.event_date',  // ✅ CHANGED
                'event.location as event_location',  // ✅ CHANGED
                'persons.first_name',
                'persons.last_name',
                'persons.email',
                'clubs.name as club_name'
            )
            ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

        return response()->json($ticket, 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Erreur lors de la récupération',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Scan a ticket by QR code data
     */
  /**
 * Scan a ticket by QR code data
 */
public function scanByQRData(Request $request)
{
    $validator = Validator::make($request->all(), [
        'qr_data' => 'required|string',
        'scanned_by' => 'required|exists:persons,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        Log::info('🔍 Scanning QR Code', [
            'raw_data' => $request->qr_data,
            'scanned_by' => $request->scanned_by
        ]);
        
        // Decode QR data
        $qrData = json_decode($request->qr_data, true);
        
        if (!$qrData || !isset($qrData['ticket_id'])) {
            Log::error('❌ Invalid QR code format', ['qr_data' => $request->qr_data]);
            return response()->json(['message' => 'QR code invalide'], 400);
        }

        $ticketId = $qrData['ticket_id'];
        
        Log::info('✅ QR decoded successfully', [
            'ticket_id' => $ticketId,
            'event_id' => $qrData['event_id'] ?? 'N/A',
            'person_id' => $qrData['person_id'] ?? 'N/A'
        ]);

        // ✅ FIXED QUERY - Use proper table name "event" not "events"
        $ticket = DB::table('tickets')
            ->join('event', 'tickets.event_id', '=', 'event.id')
            ->join('persons', 'tickets.person_id', '=', 'persons.id')
            ->where('tickets.id', $ticketId)
            ->select(
                'tickets.*',
                'event.title as event_title',
                'event.event_date',
                'event.location as event_location',
                'persons.first_name',
                'persons.last_name',
                'persons.email'
            )
            ->first();

        if (!$ticket) {
            Log::error('❌ Ticket not found in database', [
                'ticket_id' => $ticketId,
                'query_check' => 'Joined event + persons tables'
            ]);
            
            // Double-check if ticket exists without joins
            $ticketOnly = DB::table('tickets')->where('id', $ticketId)->first();
            if ($ticketOnly) {
                Log::error('⚠️ Ticket exists but JOIN failed!', [
                    'ticket' => $ticketOnly,
                    'event_id' => $ticketOnly->event_id,
                    'person_id' => $ticketOnly->person_id
                ]);
            }
            
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

        Log::info('✅ Ticket found', [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'person' => $ticket->first_name . ' ' . $ticket->last_name
        ]);

        if ($ticket->status === 'scanned') {
            Log::warning('⚠️ Ticket already scanned', [
                'ticket_id' => $ticket->id,
                'scanned_at' => $ticket->scanned_at
            ]);
            return response()->json([
                'message' => 'Ce ticket a déjà été scanné',
                'scanned_at' => $ticket->scanned_at,
                'ticket' => $ticket
            ], 409);
        }

        if ($ticket->status === 'cancelled') {
            Log::warning('⚠️ Ticket is cancelled');
            return response()->json([
                'message' => 'Ce ticket est annulé',
                'ticket' => $ticket
            ], 400);
        }

        if ($ticket->status === 'expired') {
            Log::warning('⚠️ Ticket is expired');
            return response()->json([
                'message' => 'Ce ticket est expiré',
                'ticket' => $ticket
            ], 400);
        }

        // Mark as scanned
        DB::table('tickets')
            ->where('id', $ticketId)
            ->update([
                'status' => 'scanned',
                'scanned_at' => now(),
                'scanned_by' => $request->scanned_by,
            ]);

        $ticket->status = 'scanned';
        $ticket->scanned_at = now();

        Log::info('✅ Ticket scanned successfully', [
            'ticket_id' => $ticket->id,
            'person' => $ticket->first_name . ' ' . $ticket->last_name,
            'event' => $ticket->event_title
        ]);

        return response()->json([
            'message' => 'Ticket scanné avec succès',
            'ticket' => $ticket
        ], 200);
        
    } catch (\Exception $e) {
        Log::error('❌ Error scanning ticket', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'message' => 'Erreur lors du scan',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Scan a ticket
     */
    public function scan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'scanned_by' => 'required|exists:persons,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $ticket = DB::table('tickets')->where('id', $id)->first();

            if (!$ticket) {
                return response()->json(['message' => 'Ticket non trouvé'], 404);
            }

            if ($ticket->status === 'scanned') {
                return response()->json([
                    'message' => 'Ce ticket a déjà été scanné',
                    'scanned_at' => $ticket->scanned_at
                ], 409);
            }

            if ($ticket->status === 'cancelled') {
                return response()->json(['message' => 'Ce ticket est annulé'], 400);
            }

            if ($ticket->status === 'expired') {
                return response()->json(['message' => 'Ce ticket est expiré'], 400);
            }

            DB::table('tickets')
                ->where('id', $id)
                ->update([
                    'status' => 'scanned',
                    'scanned_at' => now(),
                    'scanned_by' => $request->scanned_by,
                ]);

            $updatedTicket = DB::table('tickets')
                ->join('events', 'tickets.event_id', '=', 'events.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->where('tickets.id', $id)
                ->select('tickets.*', 'events.title as event_title', 'persons.first_name', 'persons.last_name')
                ->first();

            return response()->json([
                'message' => 'Ticket scanné avec succès',
                'ticket' => $updatedTicket
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du scan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a ticket
     */
    public function cancel($id)
    {
        try {
            $exists = DB::table('tickets')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Ticket non trouvé'], 404);
            }

            DB::table('tickets')
                ->where('id', $id)
                ->update(['status' => 'cancelled']);

            return response()->json(['message' => 'Ticket annulé avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'annulation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get event statistics
     */
    public function getEventStats($eventId)
    {
        try {
            $stats = [
                'total_tickets' => DB::table('tickets')->where('event_id', $eventId)->count(),
                'valid_tickets' => DB::table('tickets')->where('event_id', $eventId)->where('status', 'valid')->count(),
                'scanned_tickets' => DB::table('tickets')->where('event_id', $eventId)->where('status', 'scanned')->count(),
                'cancelled_tickets' => DB::table('tickets')->where('event_id', $eventId)->where('status', 'cancelled')->count(),
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique QR code
     */
    private function generateQRCode()
    {
        do {
            $qrCode = 'TKT-' . strtoupper(Str::random(12));
        } while (DB::table('tickets')->where('qr_code', $qrCode)->exists());

        return $qrCode;
    }
}