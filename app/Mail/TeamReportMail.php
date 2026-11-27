<?php

namespace App\Mail;

use App\Application\Reporting\ReportPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The weekly or biweekly team report, with the PDF attached.
 */
class TeamReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReportPeriod $period, public string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Kudos DOES').' · '.$this->period->title().' · '.$this->period->label());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.team-report', with: ['period' => $this->period]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->period->filename())->withMime('application/pdf'),
        ];
    }
}
