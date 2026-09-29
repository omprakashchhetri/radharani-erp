<?php
namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit Log Viewer — reads spatie/laravel-activitylog's activity_log
 * table. Movement, Sale, Purchase, Item (stock) and Order all use the
 * LogsActivity trait, so every write to those writes a real audit row
 * here — exactly the tamper-evident trail rule 1 (never update/delete
 * movements/sales/purchases) is meant to support.
 */
class AuditLogViewer extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $logNameFilter = '';

    protected function sortableColumns(): array
    {
        return [
            'created' => 'created_at',
            'type' => 'log_name',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['logNameFilter'];
    }

    public function render()
    {
        $query = Activity::with('causer')
            ->when($this->logNameFilter, fn ($q) => $q->where('log_name', $this->logNameFilter))
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"));

        return view('livewire.admin.audit-log-viewer', [
            'activities' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'total' => Activity::count(),
                'today' => Activity::whereDate('created_at', today())->count(),
                'thisWeek' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Audit Log — Radharani Jewellery']);
    }
}
