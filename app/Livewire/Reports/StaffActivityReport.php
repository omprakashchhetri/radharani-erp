<?php
namespace App\Livewire\Reports;

use App\Models\Movement\Movement;
use App\Models\Sales\Sale;
use App\Models\User;
use Livewire\Component;

class StaffActivityReport extends Component
{
    public ?int $staffId = null;
    public string $fromDate = '';
    public string $toDate = '';

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function render()
    {
        $movementCounts = Movement::selectRaw('user_id, count(*) as total')
            ->whereDate('created_at', '>=', $this->fromDate)
            ->whereDate('created_at', '<=', $this->toDate)
            ->when($this->staffId, fn ($q) => $q->where('user_id', $this->staffId))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $saleCounts = Sale::selectRaw('created_by, count(*) as total, sum(total) as amount')
            ->whereDate('created_at', '>=', $this->fromDate)
            ->whereDate('created_at', '<=', $this->toDate)
            ->when($this->staffId, fn ($q) => $q->where('created_by', $this->staffId))
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $staff = User::orderBy('name')->get();

        $rows = $staff->map(fn ($u) => [
            'user' => $u,
            'movements' => $movementCounts->get($u->id, 0),
            'sales' => $saleCounts->get($u->id)?->total ?? 0,
            'sales_amount' => $saleCounts->get($u->id)?->amount ?? 0,
        ])
            ->filter(fn ($row) => ! $this->staffId || $row['user']->id === $this->staffId)
            ->sortByDesc(fn ($row) => $row['movements'] + $row['sales'])
            ->values();

        return view('livewire.reports.staff-activity-report', [
            'rows' => $rows,
            'staffOptions' => $staff,
            'stats' => [
                'movements' => $movementCounts->sum(),
                'sales' => $saleCounts->sum('total'),
                'salesValue' => $saleCounts->sum('amount'),
            ],
        ])->layout('components.layouts.app', ['title' => 'Staff Activity Report — Radharani Jewellery ERP']);
    }
}
