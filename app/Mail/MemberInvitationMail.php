<?php

namespace App\Mail;

use App\Models\MemberInvitation;
use App\Models\MessSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MemberInvitationMail extends Mailable
{
    /**
     * The invitee has no account yet, so the email is written in the
     * default language the Admin set.
     */
    public function __construct(public MemberInvitation $invitation, public string $url, public string $inviterName)
    {
        $this->locale(MessSetting::defaultLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('You are invited to join :app', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.member-invitation', with: [
            'url' => $this->url,
            'inviterName' => $this->inviterName,
            'expiresAt' => $this->invitation->expires_at->setTimezone('Asia/Dhaka')->format('j M Y, g:i a'),
        ]);
    }
}
