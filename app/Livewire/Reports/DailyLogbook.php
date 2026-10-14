<?php
namespace App\Livewire\Reports;

use App\Models\Movement\Movement;
use App\Models\Sales\Sale;
use App\Support\CsvDownload;
use Illuminate\Support\Collection;
use Livewire\Component;

class DailyLogbook extends Component
{
    public string $date = '';
    public string $search = '';

    // Export a stretch of days instead of the one on screen.
    public string $exportFrom = '';
    public string $exportTo = '';

    public function mount()
    {
        $this->date = now()->toDateString();
        $this->exportFrom = now()->startOfMonth()->toDateString();
        $this->exportTo = now()->toDateString();
    }

    /**
     * Everything that happened between two dates (inclusive), movements and sales together, newest first.
     * Each entry: time, kind, label, by, note, and for a sale its total and what was paid.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function entries(string $from, string $to): Collection
    {
        $movements = Movement::with('user')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->get()
            ->map(fn ($m) => [
                'time' => $m->created_at,
                'kind' => 'movement',
                'label' => str($m->movement_type)->replace('_', ' ')->title() . ' — ' . ($m->purpose_label ?: ucfirst($m->trackable_type) . ' #' . $m->trackable_id),
                'by' => $m->user->name ?? '—',
                'note' => $m->note,
                'total' => null,
                'paid' => null,
            ]);

        $sales = Sale::with('customer')->withSum('payments', 'amount')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->get()
            ->map(fn ($s) => [
                'time' => $s->created_at,
                'kind' => 'sale',
                'label' => 'Sale ' . $s->invoice_number . ' — ₹' . number_format($s->total, 2),
                'by' => $s->customer->name ?? '—',
                'note' => $s->confirmed_by_accountant ? 'Confirmed' : 'Reserved',
                'total' => (float) $s->total,
                'paid' => (float) ($s->payments_sum_amount ?? 0),
            ]);

        return $movements->concat($sales)->sortByDesc('time')->values();
    }

    private function matches(array $e): bool
    {
        return $this->search === ''
            || str_contains(strtolower($e['label']), strtolower($this->search))
            || str_contains(strtolower($e['by']), strtolower($this->search));
    }

    // The day on screen, as a CSV, with the search applied.
    public function export()
    {
        return $this->download($this->date, $this->date, true);
    }

    // A stretch of days as a CSV: the whole book, not filtered by the search.
    public function exportRange()
    {
        $this->validate([
            'exportFrom' => 'required|date',
            'exportTo' => 'required|date|after_or_equal:exportFrom',
        ], ['exportTo.after_or_equal' => 'The last day can not be before the first.'], ['exportFrom' => 'first day', 'exportTo' => 'last day']);

        return $this->download($this->exportFrom, $this->exportTo, false);
    }

    private function download(string $from, string $to, bool $filtered)
    {
        $rows = $this->entries($from, $to)->when($filtered, fn ($c) => $c->filter(fn ($e) => $this->matches($e)))->sortBy('time')
            ->map(fn ($e) => [$e['time']->format('Y-m-d'), $e['time']->format('H:i'), $e['kind'] === 'sale' ? 'Sale' : 'Movement', $e['label'], $e['by'], $e['total'], $e['paid'], $e['note']]);

        $name = $from === $to ? "daybook-{$from}.csv" : "daybook-{$from}-to-{$to}.csv";

        return CsvDownload::stream($name, ['Date', 'Time', 'Type', 'Event', 'By / Customer', 'Sale total (₹)', 'Paid (₹)', 'Note'], $rows);
    }

    public function render()
    {
        $all = $this->entries($this->date, $this->date);

        return view('livewire.reports.daily-logbook', [
            'timeline' => $all->filter(fn ($e) => $this->matches($e))->values(),
            'stats' => [
                'movements' => $all->where('kind', 'movement')->count(),
                'sales' => $all->where('kind', 'sale')->count(),
                'salesValue' => Sale::whereDate('created_at', $this->date)->sum('total'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Daily Logbook — Radharani Jewellery ERP']);
    }
}
