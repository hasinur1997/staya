<?php

namespace App\Mail;

use App\Models\MessSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMail extends Mailable
{
    public function __construct()
    {
        $this->locale(MessSetting::defaultLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Test email from :app', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test');
    }
}
