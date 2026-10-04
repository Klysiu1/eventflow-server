<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function show(string $eventId, Request $request)
    {
        $event = Event::findOrFail($eventId);

        $from = $request->input('from', Carbon::parse($event->start_at)->format('Y-m-d'));
        $to = $request->input('to', $event->end_at ? Carbon::parse($event->end_at)->format('Y-m-d') : Carbon::parse($from)->format('Y-m-d'));

        $report = $this->reportService->generateReport($event, $from, $to);
        return response()->json($report);
    }

    public function exportCsv(string $eventId, Request $request)
    {
        $event = Event::findOrFail($eventId);

        $from = $request->input('from', Carbon::parse($event->start_at)->format('Y-m-d'));
        $to = $request->input('to', $event->end_at ? Carbon::parse($event->end_at)->format('Y-m-d') : Carbon::parse($from)->format('Y-m-d'));
        $mode = $request->input('mode', 'summary');

        $report = $this->reportService->generateReport($event, $from, $to);
        $csvContent = $this->reportService->toCsv($report, $mode);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event->name);
        $filename = "raport-{$safeName}-{$from}-{$to}-{$mode}.csv";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportPdf(string $eventId, Request $request)
    {
        $event = Event::findOrFail($eventId);

        $from = $request->input('from', Carbon::parse($event->start_at)->format('Y-m-d'));
        $to = $request->input('to', $event->end_at ? Carbon::parse($event->end_at)->format('Y-m-d') : Carbon::parse($from)->format('Y-m-d'));

        $report = $this->reportService->generateReport($event, $from, $to);

        $pdf = Pdf::loadView('reports.pdf', ['report' => $report])
            ->setPaper('a4', 'portrait');

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event->name);
        $filename = "protokol-bezpieczenstwa-{$safeName}-{$from}.pdf";

        return $pdf->download($filename);
    }
}
