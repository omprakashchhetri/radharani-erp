<?php
namespace App\Livewire\Reports;

use App\Models\Movement\Movement;
use App\Models\Sales\Sale;
use Livewire\Component;

class DailyLogbook extends Component
{
    public string $date = '';
    public string $search = '';

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    public function render()
    {
        $movements = Movement::with('user')
            ->whereDate('created_at', $this->date)
            ->get()
            ->map(fn ($m) => [
                'time' => $m->created_at,
                'kind' => 'movement',
                'label' => str($m->movement_type)->replace('_', ' ')->title().' — '.($m->purpose_label ?: ucfirst($m->trackable_type).' #'.$m->trackable_id),
                'by' => $m->user->name ?? '—',
                'note' => $m->note,
            ]);

        $sales = Sale::with('customer')
            ->whereDate('created_at', $this->date)
            ->get()
            ->map(fn ($s) => [
                'time' => $s->created_at,
                'kind' => 'sale',
                'label' => 'Sale '.$s->invoice_number.' — ₹'.number_format($s->total, 2),
                'by' => $s->customer->name ?? '—',
                'note' => $s->confirmed_by_accountant ? 'Confirmed' : 'Reserved',
            ]);

        $timeline = $movements->concat($sales)
            ->when($this->search, fn ($c) => $c->filter(fn ($e) => str_contains(strtolower($e['label']), strtolower($this->search))
                || str_contains(strtolower($e['by']), strtolower($this->search))))
            ->sortByDesc('time')->values();

        return view('livewire.reports.daily-logbook', [
            'timeline' => $timeline,
            'stats' => [
                'movements' => $movements->count(),
                'sales' => $sales->count(),
                'salesValue' => Sale::whereDate('created_at', $this->date)->sum('total'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Daily Logbook — Radharani Jewellery ERP']);
    }
}
