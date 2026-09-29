<?php
namespace App\Livewire\Accounting;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ledger / Transactions View — gated by ledger.view.
 *
 * transactions rows are auto-generated (reference_type sale/purchase/
 * installment) elsewhere in the app once auto-posting is wired (still
 * open — see CLAUDE.md/DEVELOPER_GUIDE.md). Until then, the only way to
 * add a row is the manual entry action here (reference_type = 'manual'),
 * gated on ledger.manage (owner only) — everything else on this screen
 * is read-only.
 */
class LedgerView extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $accountFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    // Manual entry modal
    public bool $showForm = false;
    public ?int $accountId = null;
    public string $direction = 'debit'; // debit | credit
    public string $amount = '';
    public string $note = '';

    protected function sortableColumns(): array
    {
        return [
            'created' => 'created_at',
            'debit' => 'debit',
            'credit' => 'credit',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['accountFilter', 'typeFilter'];
    }

    protected function rules(): array
    {
        return [
            'accountId' => 'required|exists:accounts,id',
            'direction' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ];
    }

    public function create(): void
    {
        abort_unless(Auth::user()?->can('ledger.manage'), 403);

        $this->resetValidation();
        $this->reset(['accountId', 'amount', 'note']);
        $this->direction = 'debit';
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->can('ledger.manage'), 403);

        $this->validate();

        Transaction::create([
            'account_id' => $this->accountId,
            'reference_type' => 'manual',
            'reference_id' => null,
            'debit' => $this->direction === 'debit' ? $this->amount : 0,
            'credit' => $this->direction === 'credit' ? $this->amount : 0,
            'note' => $this->note ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->showForm = false;
        $this->dispatch('toast', message: 'Manual entry recorded.', type: 'success');
    }

    public function render()
    {
        $query = Transaction::with(['account', 'creator'])
            ->when($this->accountFilter, fn ($q) => $q->where('account_id', $this->accountFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('reference_type', $this->typeFilter));

        return view('livewire.accounting.ledger-view', [
            'accounts' => Account::orderBy('name')->get(),
            'transactions' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'debits' => Transaction::sum('debit'),
                'credits' => Transaction::sum('credit'),
                'count' => Transaction::count(),
                'manual' => Transaction::where('reference_type', 'manual')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Ledger — Radharani Jewellery ERP']);
    }
}
