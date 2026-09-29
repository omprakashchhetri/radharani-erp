<?php
namespace App\Livewire\Accounting;

use App\Models\Accounting\Transaction;
use Livewire\Component;

/**
 * Tally-Compatible Export — minimal CSV export stub.
 *
 * There's no existing Tally XML/ODBC integration in this codebase, so this
 * exports a plain CSV of the transactions ledger (date, account, type,
 * reference, debit, credit) for the chosen range, in the column order
 * Tally's generic CSV/XML import expects. A true Tally XML voucher import
 * would need mapping each account to a Tally ledger name, which isn't
 * modeled anywhere yet — flagging that as a later step rather than
 * inventing a mapping table.
 */
class TallyExport extends Component
{
    public string $fromDate = '';
    public string $toDate = '';

    public function mount()
    {
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function download()
    {
        $this->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
        ]);

        $rows = Transaction::with('account')
            ->whereDate('created_at', '>=', $this->fromDate)
            ->whereDate('created_at', '<=', $this->toDate)
            ->orderBy('created_at')
            ->get();

        $fileName = 'ledger-export-' . $this->fromDate . '-to-' . $this->toDate . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Account', 'Account Type', 'Voucher Type', 'Reference No', 'Debit', 'Credit']);
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->created_at->format('d-m-Y'),
                    $t->account->name ?? '',
                    $t->account->type ?? '',
                    ucfirst($t->reference_type),
                    $t->reference_id,
                    $t->debit > 0 ? number_format($t->debit, 2, '.', '') : '',
                    $t->credit > 0 ? number_format($t->credit, 2, '.', '') : '',
                ]);
            }
            fclose($out);
        }, $fileName);
    }

    public function render()
    {
        return view('livewire.accounting.tally-export', [
            'preview' => Transaction::whereDate('created_at', '>=', $this->fromDate)
                ->whereDate('created_at', '<=', $this->toDate)
                ->count(),
        ])->layout('components.layouts.app', ['title' => 'Tally Export — Radharani Jewellery ERP']);
    }
}
