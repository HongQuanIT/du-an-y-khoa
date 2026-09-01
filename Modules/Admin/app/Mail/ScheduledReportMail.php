<?php

declare(strict_types=1);

namespace Modules\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Admin\Models\ReportSchedule;

final class ScheduledReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{headers: list<string>, rows: list<list<string>>}  $export
     */
    public function __construct(
        public ReportSchedule $schedule,
        public array $export,
        public string $reportTitle,
        public string $categoryTitle,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[Báo cáo] %s — %s',
                $this->reportTitle,
                now()->format('d/m/Y H:i'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin::emails.scheduled-report',
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $csv = $this->buildCsv();
        $filename = sprintf(
            'report-%s-%s-%s.csv',
            $this->schedule->category_slug,
            $this->schedule->report_slug,
            now()->format('Ymd-His'),
        );

        return [
            Attachment::fromData(fn (): string => $csv, $filename)
                ->withMime('text/csv'),
        ];
    }

    private function buildCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->export['headers']);
        foreach ($this->export['rows'] as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
