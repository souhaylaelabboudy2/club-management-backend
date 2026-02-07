<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    /**
     * Scan a ticket by QR code data
     * This is the main endpoint for the scanner
     */
    public function scanTicket(Request $request)
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
            Log::info('🔍 Scanning ticket...');
            Log::info('QR Data: ' . $request->qr_data);
            Log::info('Scanned by: ' . $request->scanned_by);

            // Decode QR data
            $qrData = json_decode($request->qr_data, true);
            
            if (!$qrData || !isset($qrData['ticket_id'])) {
                Log::error('❌ Invalid QR code structure');
                return response()->json(['message' => 'QR code invalide'], 400);
            }

            $ticketId = $qrData['ticket_id'];
            Log::info('✅ Ticket ID extracted: ' . $ticketId);

            // Get ticket with all details
            $ticket = DB::table('tickets')
                ->join('events', 'tickets.event_id', '=', 'events.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->where('tickets.id', $ticketId)
                ->select(
                    'tickets.*',
                    'events.title as event_title',
                    'events.event_date',
                    'events.location as event_location',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                )
                ->first();

            if (!$ticket) {
                Log::error('❌ Ticket not found in database');
                return response()->json(['message' => 'Ticket non trouvé'], 404);
            }

            Log::info('✅ Ticket found: ' . $ticket->qr_code);

            // Check if already scanned
            if ($ticket->status === 'scanned') {
                Log::warning('⚠️ Ticket already scanned');
                return response()->json([
                    'message' => 'Ce ticket a déjà été scanné',
                    'scanned_at' => $ticket->scanned_at,
                    'ticket' => $ticket
                ], 409);
            }

            // Check if cancelled
            if ($ticket->status === 'cancelled') {
                Log::warning('⚠️ Ticket is cancelled');
                return response()->json([
                    'message' => 'Ce ticket est annulé',
                    'ticket' => $ticket
                ], 400);
            }

            // Check if expired
            if ($ticket->status === 'expired') {
                Log::warning('⚠️ Ticket is expired');
                return response()->json([
                    'message' => 'Ce ticket est expiré',
                    'ticket' => $ticket
                ], 400);
            }

            // Mark ticket as scanned
            DB::table('tickets')
                ->where('id', $ticketId)
                ->update([
                    'status' => 'scanned',
                    'scanned_at' => now(),
                    'scanned_by' => $request->scanned_by,
                ]);

            Log::info('✅ Ticket marked as scanned successfully');

            // Update ticket object
            $ticket->status = 'scanned';
            $ticket->scanned_at = now();
            $ticket->scanned_by = $request->scanned_by;

            return response()->json([
                'message' => 'Ticket scanné avec succès',
                'ticket' => $ticket
            ], 200);

        } catch (\Exception $e) {
            Log::error('💥 Error scanning ticket: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Erreur lors du scan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scan statistics for an event
     */
    public function getEventScanStats($eventId)
    {
        try {
            $stats = [
                'total_tickets' => DB::table('tickets')
                    ->where('event_id', $eventId)
                    ->count(),
                
                'scanned_tickets' => DB::table('tickets')
                    ->where('event_id', $eventId)
                    ->where('status', 'scanned')
                    ->count(),
                
                'valid_tickets' => DB::table('tickets')
                    ->where('event_id', $eventId)
                    ->where('status', 'valid')
                    ->count(),
                
                'cancelled_tickets' => DB::table('tickets')
                    ->where('event_id', $eventId)
                    ->where('status', 'cancelled')
                    ->count(),
            ];

            $stats['scan_percentage'] = $stats['total_tickets'] > 0 
                ? round(($stats['scanned_tickets'] / $stats['total_tickets']) * 100, 2)
                : 0;

            return response()->json($stats, 200);

        } catch (\Exception $e) {
            Log::error('Error fetching scan stats: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all scanned tickets for an event
     */
    public function getScannedTickets($eventId)
    {
        try {
            $scannedTickets = DB::table('tickets')
                ->join('events', 'tickets.event_id', '=', 'events.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->leftJoin('persons as scanner', 'tickets.scanned_by', '=', 'scanner.id')
                ->where('tickets.event_id', $eventId)
                ->where('tickets.status', 'scanned')
                ->select(
                    'tickets.*',
                    'events.title as event_title',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email',
                    'scanner.first_name as scanner_first_name',
                    'scanner.last_name as scanner_last_name'
                )
                ->orderBy('tickets.scanned_at', 'desc')
                ->get();

            return response()->json($scannedTickets, 200);

        } catch (\Exception $e) {
            Log::error('Error fetching scanned tickets: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de la récupération des tickets scannés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate QR code structure without scanning
     * Useful for testing QR codes
     */
    public function validateQRCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $qrData = json_decode($request->qr_data, true);
            
            if (!$qrData) {
                return response()->json([
                    'valid' => false,
                    'message' => 'QR code n\'est pas au format JSON'
                ], 200);
            }

            $requiredFields = ['ticket_id', 'event_id', 'person_id', 'ticket_code'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (!isset($qrData[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Champs manquants: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields
                ], 200);
            }

            // Check if ticket exists
            $ticketExists = DB::table('tickets')
                ->where('id', $qrData['ticket_id'])
                ->exists();

            if (!$ticketExists) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Ce ticket n\'existe pas dans la base de données'
                ], 200);
            }

            return response()->json([
                'valid' => true,
                'message' => 'QR code valide',
                'data' => $qrData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage()
            ], 200);
        }
    }
}