<?php
namespace App\Livewire\Installments;

use App\Models\Customer\InstallmentPayment;
use App\Models\Customer\InstallmentScheme;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where every active scheme stands. The schedule comes from the start date: the first instalment is due on it
 * and each next one a month after. A scheme is paid up, due this month, or late (an instalment is past its date
 * and unpaid). Recording a payment covers the next unpaid instalment; the late fee is typed in, never worked out.
 */
class MonthlyPaymentStatus extends Component
{
    #[Url(except: 'all')]
    public string $show = 'all'; // all | late | due | paid

    // ---- record a payment
    public bool $showPay = false;
    public ?int $paySchemeId = null;
    public ?int $payExpectedPaid = null; // months paid when the form opened: stops the same instalment being recorded twice
    public $lateFee = '';

    public function setShow(string $show): void
    {
        $this->show = in_array($show, ['all', 'late', 'due', 'paid'], true) ? $show : 'all';
    }

    // The member is reminded by a copy-and-send message, never automatically.
    public function sendReminder(int $schemeId)
    {
        $scheme = InstallmentScheme::with('customer')->findOrFail($schemeId);

        \App\Support\MessageTemplates::queue('installment_reminder', $scheme->customer, \App\Support\MessageTemplates::schemeReminder($scheme), 'installment_scheme', $scheme->id);

        $this->dispatch('toast', message: "Reminder queued for {$scheme->customer->name}.", type: 'success');
    }

    public function openPay(int $schemeId): void
    {
        $scheme = InstallmentScheme::where('status', 'active')->findOrFail($schemeId);
        if ($scheme->is_matured) {
            return;
        }
        $this->resetValidation();
        $this->paySchemeId = $scheme->id;
        $this->payExpectedPaid = $scheme->months_paid;
        $this->lateFee = $scheme->is_late && (float) $scheme->late_fee > 0 ? (string) (float) $scheme->late_fee : '';
        $this->showPay = true;
    }

    public function recordPayment(): void
    {
        $this->validate(['lateFee' => ['nullable', 'numeric', 'min:0', 'max:99999999']], [], ['lateFee' => 'late fee']);

        $recorded = DB::transaction(function () {
            $scheme = InstallmentScheme::with('customer')->lockForUpdate()->find($this->paySchemeId);
            // Someone else may have recorded it meanwhile: the instalment on screen must still be the next one.
            if (! $scheme || $scheme->status !== 'active' || $scheme->is_matured || $scheme->months_paid !== $this->payExpectedPaid) {
                return null;
            }

            $dueOn = $scheme->dueDateFor($scheme->months_paid);
            InstallmentPayment::create([
                'scheme_id' => $scheme->id,
                'amount' => $scheme->monthly_amount,
                'installment_no' => $scheme->months_paid + 1,
                'due_on' => $dueOn->toDateString(),
                'late_fee' => $dueOn->lt(today()) ? (float) ($this->lateFee ?: 0) : 0,
                'paid_on' => now()->toDateString(),
            ]);
            $scheme->increment('months_paid');

            return $scheme->refresh();
        });

        $this->showPay = false;
        if (! $recorded) {
            $this->dispatch('toast', message: 'That instalment was already recorded. The list is up to date now.', type: 'info');

            return;
        }

        $this->dispatch('toast', message: "Instalment {$recorded->months_paid} of {$recorded->total_months} recorded for {$recorded->customer->name}.", type: 'success');
        if ($recorded->is_matured) {
            $this->dispatch('toast', message: "{$recorded->customer->name}'s scheme has matured. Choose what happens next in Scheme List.", type: 'info');
        }
    }

    public function render()
    {
        $monthEnd = now()->endOfMonth()->startOfDay();

        $all = InstallmentScheme::with('customer')->where('status', 'active')->orderBy('start_date')->get()->map(function (InstallmentScheme $s) use ($monthEnd) {
            $s->state = $s->is_late ? 'late' : ($s->next_due_on && $s->next_due_on->lte($monthEnd) ? 'due' : 'paid');

            return $s;
        });

        return view('livewire.installments.monthly-payment-status', [
            'schemes' => $this->show === 'all' ? $all : $all->where('state', $this->show)->values(),
            'paySchemeModel' => $this->paySchemeId ? InstallmentScheme::with('customer')->find($this->paySchemeId) : null,
            'stats' => [
                'active' => $all->count(),
                'paid' => $all->where('state', 'paid')->count(),
                'due' => $all->where('state', 'due')->count(),
                'late' => $all->where('state', 'late')->count(),
                'dueAmount' => $all->sum(fn ($s) => (float) $s->monthly_amount * ($s->months_late + ($s->state === 'due' ? 1 : 0))),
            ],
        ])->layout('components.layouts.app', ['title' => 'Monthly Payment Status — Radharani Jewellery ERP']);
    }
}
