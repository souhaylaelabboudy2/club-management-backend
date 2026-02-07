<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public $memberName;
    public $eventTitle;
    public $eventDate;
    public $eventLocation;
    public $eventDescription;
    public $pdfPath;

    public function __construct($data)
    {
        $this->memberName = $data['memberName'];
        $this->eventTitle = $data['eventTitle'];
        $this->eventDate = $data['eventDate'];
        $this->eventLocation = $data['eventLocation'];
        $this->eventDescription = $data['eventDescription'] ?? '';
        $this->pdfPath = $data['pdfPath'];
        
        Log::info('📧 TicketMail constructed', [
            'subject' => 'Your Event Ticket - ' . $this->eventTitle,
            'pdf_path' => $this->pdfPath,
            'pdf_exists' => file_exists($this->pdfPath),
            'pdf_size' => file_exists($this->pdfPath) ? filesize($this->pdfPath) : 0
        ]);
    }

    public function build()
    {
        try {
            // Verify PDF exists
            if (!file_exists($this->pdfPath)) {
                Log::error('❌ PDF does not exist at path: ' . $this->pdfPath);
                throw new \Exception('PDF file not found: ' . $this->pdfPath);
            }
            
            Log::info('✅ Building email with PDF attachment', [
                'pdf_path' => $this->pdfPath,
                'pdf_size' => filesize($this->pdfPath) . ' bytes',
                'view' => 'emails.TicketMail'
            ]);
            
            return $this->subject('Your Event Ticket - ' . $this->eventTitle)
                        ->view('emails.TicketMail')
                        ->attach($this->pdfPath, [
                            'as' => 'event-ticket.pdf',
                            'mime' => 'application/pdf',
                        ]);
                        
        } catch (\Exception $e) {
            Log::error('❌ Error building TicketMail: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}