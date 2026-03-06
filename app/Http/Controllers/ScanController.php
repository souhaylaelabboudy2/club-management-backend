<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScanController extends Controller
{
    public function scanTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data'    => 'required|string',
            'scanned_by' => 'required|exists:persons,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            Log::info('🔍 RAW QR DATA: ' . $request->qr_data);

            // ── 1. Decode QR ──
            // qr_data is a JSON string: {"ticket_code":"ABCD-EFGH","ticket_id":1,...}
            $qrData = json_decode($request->qr_data, true);

            // If not JSON, treat entire string as plain ticket_code
            if (!$qrData || !is_array($qrData)) {
                $qrData = ['ticket_code' => trim($request->qr_data)];
            }

            Log::info('🔍 PARSED QR:', $qrData);

            $ticketCode = $qrData['ticket_code'] ?? null;
            $ticketId   = $qrData['ticket_id']   ?? null;

            if (!$ticketCode && !$ticketId) {
                return response()->json(['message' => 'QR code invalide (ticket_code ou ticket_id manquant)'], 400);
            }

            // ── 2. Find ticket ──
            $query = DB::table('tickets')
                ->join('event',   'tickets.event_id',  '=', 'event.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->select(
                    'tickets.id',
                    'tickets.qr_code',
                    'tickets.status',
                    'tickets.scanned_at',
                    'tickets.scanned_by',
                    'tickets.event_id',
                    'tickets.person_id',
                    'event.title as event_title',
                    'event.event_date',
                    'event.location as event_location',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                );

            if ($ticketId) {
                $query->where('tickets.id', $ticketId);
            } else {
                $query->where('tickets.qr_code', $ticketCode);
            }

            $ticket = $query->first();

            if (!$ticket) {
                Log::error('❌ Ticket not found. ticket_id=' . $ticketId . ' ticket_code=' . $ticketCode);
                return response()->json(['message' => 'Ticket non trouvé'], 404);
            }

            Log::info('✅ Ticket found: id=' . $ticket->id . ' status=' . $ticket->status);

            // ── 3. Status checks ──
            if ($ticket->status === 'scanned') {
                return response()->json([
                    'message'    => 'Ce ticket a déjà été scanné',
                    'scanned_at' => $ticket->scanned_at,
                    'ticket'     => $this->buildTicketResponse($ticket),
                ], 409);
            }

            if ($ticket->status === 'cancelled') {
                return response()->json([
                    'message' => 'Ce ticket est annulé',
                    'ticket'  => $this->buildTicketResponse($ticket),
                ], 400);
            }

            if ($ticket->status === 'expired') {
                return response()->json([
                    'message' => 'Ce ticket est expiré',
                    'ticket'  => $this->buildTicketResponse($ticket),
                ], 400);
            }

            // ── 4. Mark as scanned ──
            $scannedAt = now();

            DB::table('tickets')
                ->where('id', $ticket->id)
                ->update([
                    'status'     => 'scanned',
                    'scanned_at' => $scannedAt,
                    'scanned_by' => $request->scanned_by,
                ]);

            $ticket->status     = 'scanned';
            $ticket->scanned_at = $scannedAt;

            Log::info('✅ Ticket scanned: id=' . $ticket->id . ' person=' . $ticket->first_name . ' ' . $ticket->last_name);

            return response()->json([
                'message' => 'Ticket scanné avec succès',
                'ticket'  => $this->buildTicketResponse($ticket),
            ], 200);

        } catch (\Exception $e) {
            Log::error('💥 Error scanning ticket: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Erreur lors du scan',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function buildTicketResponse($ticket)
    {
        return [
            'id'             => $ticket->id,
            'qr_code'        => $ticket->qr_code,
            'status'         => $ticket->status,
            'event_title'    => $ticket->event_title    ?? '',
            'event_date'     => $ticket->event_date     ?? '',
            'event_location' => $ticket->event_location ?? '',
            'first_name'     => $ticket->first_name     ?? '',
            'last_name'      => $ticket->last_name      ?? '',
            'email'          => $ticket->email          ?? '',
            'scanned_at'     => $ticket->scanned_at     ?? null,
            'scanned_by'     => $ticket->scanned_by     ?? null,
        ];
    }

    public function getEventScanStats($eventId)
    {
        try {
            $total     = DB::table('tickets')->where('event_id', $eventId)->count();
            $scanned   = DB::table('tickets')->where('event_id', $eventId)->where('status', 'scanned')->count();
            $valid     = DB::table('tickets')->where('event_id', $eventId)->where('status', 'valid')->count();
            $cancelled = DB::table('tickets')->where('event_id', $eventId)->where('status', 'cancelled')->count();

            return response()->json([
                'total_tickets'     => $total,
                'scanned_tickets'   => $scanned,
                'valid_tickets'     => $valid,
                'cancelled_tickets' => $cancelled,
                'scan_percentage'   => $total > 0 ? round(($scanned / $total) * 100, 2) : 0,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching scan stats: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur statistiques', 'error' => $e->getMessage()], 500);
        }
    }

    public function getScannedTickets($eventId)
    {
        try {
            $tickets = DB::table('tickets')
                ->join('event',   'tickets.event_id',  '=', 'event.id')
                ->join('persons', 'tickets.person_id', '=', 'persons.id')
                ->leftJoin('persons as scanner', 'tickets.scanned_by', '=', 'scanner.id')
                ->where('tickets.event_id', $eventId)
                ->where('tickets.status', 'scanned')
                ->select(
                    'tickets.id',
                    'tickets.qr_code',
                    'tickets.status',
                    'tickets.scanned_at',
                    'tickets.scanned_by',
                    'event.title as event_title',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email',
                    'scanner.first_name as scanner_first_name',
                    'scanner.last_name as scanner_last_name'
                )
                ->orderBy('tickets.scanned_at', 'desc')
                ->get();

            return response()->json($tickets, 200);
        } catch (\Exception $e) {
            Log::error('Error fetching scanned tickets: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur récupération tickets', 'error' => $e->getMessage()], 500);
        }
    }

    public function validateQRCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $validator->errors()], 422);
        }

        try {
            $qrData = json_decode($request->qr_data, true);

            if (!$qrData || !is_array($qrData)) {
                // Plain string — treat as ticket_code
                $exists = DB::table('tickets')->where('qr_code', trim($request->qr_data))->exists();
                return response()->json([
                    'valid'   => $exists,
                    'message' => $exists ? 'QR code valide' : 'Ce ticket n\'existe pas',
                ], 200);
            }

            if (isset($qrData['ticket_id'])) {
                $exists = DB::table('tickets')->where('id', $qrData['ticket_id'])->exists();
                return response()->json([
                    'valid'   => $exists,
                    'message' => $exists ? 'QR code valide' : 'Ce ticket n\'existe pas',
                    'data'    => $qrData,
                ], 200);
            }

            if (isset($qrData['ticket_code'])) {
                $exists = DB::table('tickets')->where('qr_code', $qrData['ticket_code'])->exists();
                return response()->json([
                    'valid'   => $exists,
                    'message' => $exists ? 'QR code valide' : 'Ce ticket n\'existe pas',
                    'data'    => $qrData,
                ], 200);
            }

            return response()->json(['valid' => false, 'message' => 'Format QR non reconnu'], 200);

        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'message' => 'Erreur: ' . $e->getMessage()], 200);
        }
    }
}