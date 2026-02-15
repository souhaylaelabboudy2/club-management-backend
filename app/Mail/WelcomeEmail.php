<?php

namespace App\Mail;

use App\Models\Person;
use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Person $person,
        public Club $club,
        public string $role,
        public ?string $password = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Bienvenue au ' . $this->club->name . ' - Vos identifiants',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.WelcomeEmail',
            with: [
                'person' => $this->person,
                'club' => $this->club,
                'role' => $this->role,
                'password' => $this->password,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}