<?php
namespace App\Livewire\Pricing;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Movement\RateLog;
use Livewire\Attributes\Url;
use Livewire\Component;

class RateHistoryLog extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $metal = '';

    protected function sortableColumns(): array
    {
        return [
            'created' => 'created_at',
            'metal' => 'metal',
            'rate' => 'rate',
            'source' => 'source',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['metal'];
    }

    public function render()
    {
        $query = RateLog::query()
            ->with('updater')
            ->when($this->metal, fn ($q) => $q->where('metal', $this->metal))
            ->when($this->search, fn ($q) => $q->where('source', 'like', "%{$this->search}%"));

        return view('livewire.pricing.rate-history-log', [
            'rates' => $this->applySorting($query)->paginate($this->perPageValue()),
            'latest' => collect(DailyRateEntry::METALS)->mapWithKeys(fn ($m) => [$m => RateLog::latestFor($m)]),
        ])->layout('components.layouts.app', ['title' => 'Rate History — Radharani Jewellery']);
    }
}
