<?php
namespace App\Livewire\Sales;

use App\Models\Customer\Customer;
use App\Models\Location;
use App\Models\Orders\Order;
use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Stock\Item;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Pricing\TaxSetting;
use App\Services\ExchangeCredit;
use App\Services\InsufficientExchangeCredit;
use App\Services\PricingService;
use App\Support\Phone;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New Sale as a step form (8 Oct change list, section 12): Customer, Items, Charges, Payment,
 * Review. Going back to an earlier step is always possible and the figures follow.
 *
 * On save the sale is held as 'reserved' until an admin verifies it (rule 10). The bill is paid
 * in parts if need be; the balance is worked out from the payments and cleared later.
 */
class NewSale extends Component
{
    public const STEPS = [1 => 'Customer', 2 => 'Items', 3 => 'Charges', 4 => 'Payment', 5 => 'Review'];

    public int $step = 1;
    public int $reach = 1;

    // The order this sale delivers, when the sale is started from an order's path.
    #[Url(as: 'order', except: '')]
    public ?int $orderId = null;

    // A settled exchange whose credit this sale is paid with (started from the exchange's "Use in a sale").
    #[Url(as: 'exchange', except: '')]
    public ?int $exchangeId = null;

    // Set when this sale is how a matured monthly scheme ends (8 Oct change list, 16.1).
    #[Url(as: 'scheme', except: '')]
    public ?int $schemeId = null;


    // ---- 1. customer
    public string $customerSearch = '';
    #[Url(as: 'customer', except: '')]
    public ?int $customerId = null;
    public string $referralCode = '';
    public ?int $referralCustomerId = null;

    // ---- 2. items: item_id => ['label', 'category', 'price', 'inVault', 'order']
    public string $itemSearch = '';
    public array $cart = [];
    public string $overrideNote = '';

    // GST on this bill: the owner's defaults, which staff can change (or set to 0) before billing.
    public string $gstJewellery = '';
    public string $gstHallmark = '';

    // ---- 3. charges
    public array $extras = [];            // [['name' => ..., 'amount' => ...]]
    public string $adjustType = 'flat';   // flat | percent
    public $adjustValue = '';

    // ---- 4. payment
    public array $payments = [['mode' => 'cash', 'amount' => '']];

    // The payment line filled in for the balance due when step 4 opens, and what it was filled with,
    // so it follows the bill if staff go back and change it but never overwrites an amount they typed.
    public ?int $autoPaymentIndex = null;
    public ?string $autoPaymentAmount = null;

    public string $notes = '';

    public function mount(): void
    {
        [$jewellery, $hallmark] = app(PricingService::class)->gstRates();
        $this->gstJewellery = (string) $jewellery;
        $this->gstHallmark = (string) $hallmark;

        if ($this->schemeId) {
            $scheme = \App\Models\Customer\InstallmentScheme::where('status', 'active')->find($this->schemeId);
            if ($scheme) {
                $this->customerId = $scheme->customer_id;
                $this->payments = [['mode' => 'cash', 'amount' => (string) $scheme->paid_in, 'note' => 'Paid in through the monthly scheme']];
            } else {
                $this->schemeId = null;
            }
        }
        if ($this->exchangeId) {
            $exchange = ExchangeTransaction::find($this->exchangeId);
            if ($exchange && $exchange->creditRemaining() > 0) {
                $this->customerId = $exchange->customer_id;
            } else {
                $this->exchangeId = null;
            }
        }
        if ($this->customerId && ! Customer::whereKey($this->customerId)->exists()) {
            $this->customerId = null;
        }
        if ($this->customerId) {
            $this->step = max($this->step, 2);
            $this->reach = max($this->reach, 2);
        }

        if (! $this->orderId) {
            return;
        }
        $order = Order::with('customer', 'stockItem')->whereIn('status', ['placed', 'confirmed', 'ready'])->find($this->orderId);
        if (! $order) {
            $this->orderId = null;

            return;
        }
        $this->customerId = $order->customer_id;
        $this->reach = 2;
        $this->step = 2;
        // The advance already paid on the order counts towards this bill. Staff set the mode it was paid in.
        $lines = [];
        if ((float) $order->advance_amount > 0) {
            $fromExchange = app(ExchangeCredit::class)->orderCredit($order->id);
            $lines[] = ['mode' => 'cash', 'amount' => (string) (float) $order->advance_amount,
                'note' => "Advance paid on order #{$order->id}" . ($fromExchange > 0 ? ' (₹' . number_format($fromExchange) . ' of it from exchange credit)' : '')];
        }
        // Advances received after the order was placed keep the mode they came in.
        foreach ($order->payments()->orderBy('id')->get() as $p) {
            $lines[] = ['mode' => $p->mode, 'amount' => (string) (float) $p->amount, 'note' => "Advance on order #{$order->id}, " . $p->created_at->format('j M') . ($p->note ? ": {$p->note}" : '')];
        }
        if ($lines) {
            $this->payments = $lines;
        }
        if ($order->stockItem && $order->stockItem->status === 'in_stock') {
            $this->cart[$order->stockItem->id] = $this->cartLine($order->stockItem);
        }

        // Pieces made for this order (karigar or hallmarking) that an admin has confirmed into stock.
        Item::where('status', 'in_stock')->where(function ($q) use ($order) {
            $q->whereIn('source_karigar_batch_id', $order->karigarBatches()->select('id'))
                ->orWhereIn('source_hallmark_batch_id', $order->hallmarkBatches()->select('id'));
        })->get()->each(fn ($piece) => $this->cart[$piece->id] ??= $this->cartLine($piece));
    }

    // ================================================================ steps

    public function goToStep(int $n): void
    {
        if ($n >= 1 && $n <= 5 && $n <= $this->reach) {
            $this->resetValidation();
            $this->step = $n;
        }
    }

    public function next(): void
    {
        if (! $this->stepIsValid($this->step)) {
            return;
        }
        $this->step = min(5, $this->step + 1);
        $this->reach = max($this->reach, $this->step);
        $this->refreshCart();
        if ($this->step === 4) {
            $this->prefillPayment();
        }
    }

    // The adjustment now sits on the payment step, so the payment line filled in for the balance follows it.
    public function updatedAdjustValue(): void
    {
        $this->followAdjustment();
    }

    public function updatedAdjustType(): void
    {
        $this->followAdjustment();
    }

    private function followAdjustment(): void
    {
        if ($this->step === 4) {
            $this->prefillPayment();
        }
    }

    // Opens the payment step with the balance due already on a payment line. Staff change it for a part payment.
    // These read the figures through their getters, not as $this->total / $this->balance: Livewire keeps an accessor's
    // value for the rest of the request, so reading one here and then changing the payments would leave the page stale.
    private function prefillPayment(): void
    {
        $this->prefillExchangeCredit();

        $i = $this->autoPaymentIndex;
        $untouched = $i !== null && isset($this->payments[$i]) && (string) ($this->payments[$i]['amount'] ?? '') === (string) $this->autoPaymentAmount;

        if (! $untouched) {
            $i = collect($this->payments)->search(fn ($p) => (string) ($p['amount'] ?? '') === '');
            if ($i === false) {
                if ($this->getBalanceProperty() <= 0) {
                    return;
                }
                $this->payments[] = ['mode' => 'cash', 'amount' => ''];
                $i = array_key_last($this->payments);
            }
        }

        $this->fillBalance($i);
        if ((float) $this->payments[$i]['amount'] <= 0) {
            $this->payments[$i]['amount'] = '';
        }
        $this->autoPaymentIndex = $i;
        $this->autoPaymentAmount = (string) $this->payments[$i]['amount'];
    }

    private function stepIsValid(int $step): bool
    {
        $this->resetValidation();

        if ($step === 1 && ! $this->customerId) {
            $this->addError('customerId', 'Choose the customer, or add a new one.');

            return false;
        }
        if ($step === 2) {
            if (! $this->cart) {
                $this->addError('cart', 'Add at least one piece.');

                return false;
            }
            $this->refreshCart();
            if (collect($this->cart)->contains('inVault', true)) {
                $this->addError('cart', 'Some pieces are still recorded as in the vault. Move them to the counter first.');

                return false;
            }
            if (collect($this->cart)->contains(fn ($c) => $c['order'] !== null) && ! $this->canOverride()) {
                $this->addError('cart', 'A piece is held for a customer order. An admin has to approve selling it.');

                return false;
            }
            if (collect($this->cart)->contains(fn ($c) => $c['order'] !== null) && trim($this->overrideNote) === '') {
                $this->addError('overrideNote', 'Say why a piece held for an order is being sold.');

                return false;
            }
        }
        if ($step === 2) {
            foreach (['gstJewellery', 'gstHallmark'] as $field) {
                if (! is_numeric($this->{$field}) || (float) $this->{$field} < 0 || (float) $this->{$field} > 100) {
                    $this->addError($field, 'Choose a GST rate between 0 and 100.');

                    return false;
                }
            }
        }
        if ($step === 3) {
            foreach ($this->extras as $i => $e) {
                if (($e['name'] ?? '') === '' && ($e['amount'] ?? '') === '') {
                    continue;
                }
                if (($e['name'] ?? '') === '' || ! is_numeric($e['amount'] ?? null) || (float) $e['amount'] <= 0) {
                    $this->addError("extras.{$i}.amount", 'Give each extra charge a name and an amount.');

                    return false;
                }
            }
        }
        if ($step === 4) {
            if ($this->adjustValue !== '' && (! is_numeric($this->adjustValue) || (float) $this->adjustValue < 0 || ($this->adjustType === 'percent' && (float) $this->adjustValue > 100))) {
                $this->addError('adjustValue', $this->adjustType === 'percent' ? 'Enter a percentage between 0 and 100.' : 'Enter an amount.');

                return false;
            }
            foreach ($this->payments as $i => $p) {
                if (($p['amount'] ?? '') !== '' && (! is_numeric($p['amount']) || (float) $p['amount'] < 0)) {
                    $this->addError("payments.{$i}.amount", 'Enter an amount.');

                    return false;
                }
            }
            foreach ($this->payments as $i => $p) {
                $amount = is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0.0;
                if (($p['mode'] ?? '') === 'exchange' && $amount > 0) {
                    $exchangeId = (int) ($p['exchange_id'] ?? 0);
                    if (! $exchangeId || $amount > $this->exchangeLeft($exchangeId, $i) + 0.005) {
                        $this->addError("payments.{$i}.amount", $exchangeId ? 'That is more exchange credit than this customer has left (₹' . number_format($this->exchangeLeft($exchangeId, $i), 2) . ').' : 'Exchange credit can only be used from the exchange panel.');

                        return false;
                    }
                }
            }
            if ($this->getPaidTotalProperty() > $this->getTotalProperty() + 0.005) {
                $this->addError('payments', 'The payments add up to more than the bill.');

                return false;
            }
        }

        return true;
    }

    // ================================================================ 1. customer

    public function chooseCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->resetErrorBag('customerId');
        $this->checkReferral();
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->referralCustomerId = null;
    }

    // The shared customer form saved (or found) someone: they become the customer of this sale.
    #[On('customer-saved')]
    public function customerSaved(int $id): void
    {
        $this->chooseCustomer($id);
    }

    // A person can not use their own code on their own purchase.
    public function checkReferral(): void
    {
        $this->resetErrorBag('referralCode');
        $this->referralCustomerId = null;
        $code = strtoupper(trim($this->referralCode));
        if ($code === '') {
            return;
        }
        $owner = Customer::where('referral_code', $code)->first();
        if (! $owner) {
            $this->addError('referralCode', 'No customer has this referral code.');

            return;
        }
        if ($owner->id === $this->customerId) {
            $this->addError('referralCode', 'A customer can not use their own referral code on their own purchase.');

            return;
        }
        $this->referralCustomerId = $owner->id;
    }

    public function updatedReferralCode(): void
    {
        $this->checkReferral();
    }

    // ================================================================ 2. items

    public function addByCode(string $raw): void
    {
        $item = StockLookup::item($raw);
        if (! $item) {
            $this->itemSearch = trim($raw);

            return;
        }
        $this->addItem($item->id);
    }

    public function addItem(int $itemId): void
    {
        $item = Item::find($itemId);
        if (! $item) {
            return;
        }
        $this->itemSearch = '';
        if (isset($this->cart[$itemId])) {
            return;
        }
        if ($item->status !== 'in_stock') {
            $this->dispatch('toast', message: "{$item->label} is " . str_replace('_', ' ', $item->status) . ' and can not be billed.', type: 'error');

            return;
        }

        $this->cart[$itemId] = $this->cartLine($item);
        $this->resetErrorBag('cart');
    }

    private function cartLine(Item $item): array
    {
        $order = Order::openForItem($item->id);
        if ($order && $this->orderId && $order->id === $this->orderId) {
            $order = null; // selling it to the customer it is held for
        }

        $pricing = app(PricingService::class);
        $b = $pricing->breakdown($item);

        return [
            'label' => $item->label,
            'category' => $item->category,
            'weight' => (float) $item->weight,
            'price' => $b['total'],
            'taxable' => $b['taxable'],
            'huid' => $b['huid_charge'], // the hallmarking part of the price, which has its own GST rate
            'breakup' => $pricing->breakupLines($b),
            'inVault' => Location::isInVault($item),
            'order' => $order ? "Order #{$order->id} for " . ($order->customer?->name ?? 'a customer') : null,
        ];
    }

    // Prices and the vault check are read again whenever the screen moves on.
    private function refreshCart(): void
    {
        foreach (Item::whereKey(array_keys($this->cart))->get() as $item) {
            $this->cart[$item->id] = $this->cartLine($item);
        }
    }

    public function removeItem(int $itemId): void
    {
        unset($this->cart[$itemId]);
    }

    // The offer made when a piece is still recorded as in the vault: move it out to the counter first.
    public function moveToCounter(int $itemId): void
    {
        $item = Item::findOrFail($itemId);
        abort_unless(Auth::user()?->can('movement.create'), 403);
        if (Location::isInVault($item)) {
            Location::sendToFloor($item);
        }
        $this->cart[$itemId] = $this->cartLine($item);
        $this->resetErrorBag('cart');
        $this->dispatch('toast', message: "{$item->label} moved to the counter.", type: 'success');
    }

    public function canOverride(): bool
    {
        return (bool) Auth::user()?->can('sale.approve');
    }

    // ================================================================ 3 and 4

    public function addExtra(): void
    {
        $this->extras[] = ['name' => '', 'amount' => ''];
    }

    public function removeExtra(int $i): void
    {
        unset($this->extras[$i]);
        $this->extras = array_values($this->extras);
    }

    // When the sale was started from an exchange, its credit goes on a payment line, capped at the bill.
    // The line follows the bill if staff come back and change it, but never overwrites an amount they typed.
    private function prefillExchangeCredit(): void
    {
        foreach ($this->payments as $i => $p) {
            if (($p['exchange_id'] ?? null) && isset($p['auto']) && (string) $p['amount'] === (string) $p['auto']) {
                $amount = (string) min($this->exchangeLeft((int) $p['exchange_id'], $i), max(0, $this->getTotalProperty()));
                $this->payments[$i]['amount'] = $this->payments[$i]['auto'] = $amount;
            }
        }

        if ($this->exchangeId && ! collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $this->exchangeId)) {
            $this->addExchangeLine($this->exchangeId, true);
        }
    }

    private function addExchangeLine(int $exchangeId, bool $auto = false): void
    {
        $amount = min($this->exchangeLeft($exchangeId), max(0, round($this->getTotalProperty() - $this->getPaidTotalProperty(), 2)));
        if ($amount <= 0) {
            return;
        }
        $line = ['mode' => 'exchange', 'exchange_id' => $exchangeId, 'amount' => (string) $amount, 'note' => "Exchange #{$exchangeId}"];
        if ($auto) {
            $line['auto'] = (string) $amount;
        }

        $empty = collect($this->payments)->search(fn ($p) => (string) ($p['amount'] ?? '') === '' && empty($p['exchange_id']));
        if ($empty === false) {
            $this->payments[] = $line;
        } else {
            $this->payments[$empty] = $line;
        }
        $this->autoPaymentIndex = null; // the cash line is worked out again for what is left
    }

    /** Credit an exchange still has for this sale: what is left, less what other lines of this bill already use. */
    private function exchangeLeft(int $exchangeId, ?int $exceptLine = null): float
    {
        $tx = ExchangeTransaction::find($exchangeId);
        if (! $tx || $tx->customer_id !== $this->customerId) {
            return 0.0;
        }
        $others = collect($this->payments)->except($exceptLine)->filter(fn ($p) => ($p['exchange_id'] ?? null) === $exchangeId)
            ->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0);

        return max(0.0, round($tx->creditRemaining() - $others, 2));
    }

    // From the "Exchange credit" panel: put an exchange's credit on a payment line for what is still due.
    // The cash line that was filled in for the whole bill is worked out again for what is left.
    public function useCredit(int $exchangeId): void
    {
        if (collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $exchangeId)) {
            return;
        }

        $i = $this->autoPaymentIndex;
        if ($i !== null && isset($this->payments[$i]) && (string) ($this->payments[$i]['amount'] ?? '') === (string) $this->autoPaymentAmount) {
            $this->payments[$i]['amount'] = '';
        }
        if ($this->getBalanceProperty() <= 0) {
            $this->dispatch('toast', message: 'Nothing is left to pay on this bill.', type: 'info');

            return;
        }

        $this->addExchangeLine($exchangeId);
        $this->prefillPayment();
    }

    public function addPayment(): void
    {
        $this->payments[] = ['mode' => 'cash', 'amount' => ''];
    }

    public function removePayment(int $i): void
    {
        unset($this->payments[$i]);
        $this->payments = array_values($this->payments) ?: [['mode' => 'cash', 'amount' => '']];
    }

    // One tap to put what is still due on a payment line.
    public function fillBalance(int $i): void
    {
        $others = collect($this->payments)->except($i)->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0);
        $amount = max(0, round($this->getTotalProperty() - $others, 2));
        if ($exchangeId = $this->payments[$i]['exchange_id'] ?? null) {
            $amount = min($amount, $this->exchangeLeft((int) $exchangeId, $i));
        }
        $this->payments[$i]['amount'] = (string) $amount;
    }

    // ================================================================ figures

    public function getSubtotalProperty(): float
    {
        return round(collect($this->cart)->sum('price'), 2);
    }

    public function getExtrasTotalProperty(): float
    {
        return round(collect($this->extras)->sum(fn ($e) => is_numeric($e['amount'] ?? null) && ($e['name'] ?? '') !== '' ? (float) $e['amount'] : 0), 2);
    }

    /**
     * Every figure of the bill in the order of the sheet: pieces, GST, extra charges, round-off, adjustment, total.
     * Worked out afresh from the cart on every call, never cached mid-request.
     */
    public function totals(): array
    {
        $rates = [is_numeric($this->gstJewellery) ? (float) $this->gstJewellery : 0.0, is_numeric($this->gstHallmark) ? (float) $this->gstHallmark : 0.0];
        $lines = collect($this->cart)->map(fn ($c) => ['total' => (float) $c['price'], 'taxable' => (float) ($c['taxable'] ?? $c['price'] - ($c['huid'] ?? 0)), 'huid_charge' => (float) ($c['huid'] ?? 0)]);
        $v = is_numeric($this->adjustValue) ? (float) $this->adjustValue : 0.0;

        return app(PricingService::class)->billTotals($lines, $this->getExtrasTotalProperty(), $this->adjustType, $v, $rates[0], $rates[1]) + ['rates' => $rates];
    }

    public function getAdjustmentProperty(): float
    {
        return $this->totals()['adjustment'];
    }

    public function gstDetail(): array
    {
        return $this->totals();
    }

    /** The rates on offer: the usual ones plus whatever rate the bill is set to now. */
    private function gstChoices($current): array
    {
        $choices = collect(TaxSetting::CHOICES)->push(is_numeric($current) ? (float) $current : 0.0)->map(fn ($c) => (float) $c)->unique()->sort()->values();

        return $choices->all();
    }

    public function getGstProperty(): float
    {
        return $this->totals()['gst'];
    }

    // The bill including GST, extra charges and round-off, less the adjustment: this is what is paid and kept as the sale's total.
    public function getTotalProperty(): float
    {
        return $this->totals()['total'];
    }

    public function getPaidTotalProperty(): float
    {
        return round(collect($this->payments)->sum(fn ($p) => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 0), 2);
    }

    public function getBalanceProperty(): float
    {
        return round($this->getTotalProperty() - $this->getPaidTotalProperty(), 2); // not the cached accessors: this is read mid-request
    }

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    // ================================================================ save

    public function submit()
    {
        abort_unless(Auth::user()?->can('sale.create'), 403);

        foreach ([1, 2, 3, 4] as $s) {
            if (! $this->stepIsValid($s)) {
                $this->step = $s;

                return;
            }
        }

        // Pieces may have been taken meanwhile: only pieces still in stock can be held for a sale.
        $items = Item::whereKey(array_keys($this->cart))->where('status', 'in_stock')->get();
        if ($items->count() !== count($this->cart)) {
            $this->step = 2;
            $this->addError('cart', 'A piece on this bill is no longer in stock. Remove it and try again.');
            $this->refreshCart();

            return;
        }

        $pricing = app(PricingService::class);
        $extras = collect($this->extras)->filter(fn ($e) => ($e['name'] ?? '') !== '' && is_numeric($e['amount'] ?? null))
            ->map(fn ($e) => ['name' => $e['name'], 'amount' => round((float) $e['amount'], 2)])->values()->all();

        try {
        $sale = DB::transaction(function () use ($items, $pricing, $extras) {
            $breakdowns = $items->mapWithKeys(fn ($i) => [$i->id => $pricing->breakdown($i)]);
            $prices = $breakdowns->map(fn ($b) => $b['total']);
            $subtotal = round($prices->sum(), 2);
            $extrasTotal = round(array_sum(array_column($extras, 'amount')), 2);
            $v = is_numeric($this->adjustValue) ? (float) $this->adjustValue : 0.0;

            // GST at the rates chosen on the bill, kept with the sale so a later change never alters it.
            $rates = [(float) $this->gstJewellery, (float) $this->gstHallmark];
            $bill = $pricing->billTotals($breakdowns, $extrasTotal, $this->adjustType, $v, $rates[0], $rates[1]);
            $gst = $bill;
            $adjustment = $bill['adjustment'];
            // "Final price" is kept as the discount it comes to, so the record reads the same as a flat one.
            $storedType = $this->adjustType === 'final' ? 'flat' : $this->adjustType;
            $storedValue = $this->adjustType === 'final' ? $adjustment : $v;

            $sale = Sale::create([
                'customer_id' => $this->customerId,
                'referral_customer_id' => $this->referralCustomerId,
                'invoice_number' => 'RESV-' . now()->format('YmdHis') . '-' . $this->customerId, // the Tally bill number replaces it at verification
                'type' => 'sale',
                'additional_charges' => $extras ?: null,
                'discount' => $adjustment,
                'adjustment_type' => $adjustment > 0 ? $storedType : null,
                'adjustment_value' => $adjustment > 0 ? $storedValue : null,
                'accountant_note' => trim($this->notes) ?: null,
                'order_override_note' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null) ? trim($this->overrideNote) : null,
                'gst_jewellery_percent' => $rates[0],
                'gst_hallmark_percent' => $rates[1],
                'gst_amount' => $gst['gst'],
                'gst_hallmark_amount' => $gst['gst_hallmark'],
                'round_off' => $bill['round_off'],
                'total' => $bill['total'],
                'confirmed_by_accountant' => false,
                'created_by' => Auth::id(),
            ]);

            foreach ($items as $item) {
                $sale->items()->attach($item->id, ['price_at_sale' => $prices[$item->id]]);
                $item->update(['status' => 'reserved']); // per item, so each piece's history shows it
            }

            foreach ($this->payments as $p) {
                if (is_numeric($p['amount'] ?? null) && (float) $p['amount'] > 0) {
                    $exchangeId = $p['mode'] === 'exchange' ? (int) ($p['exchange_id'] ?? 0) : null;
                    if ($exchangeId) {
                        // Takes the credit under a lock, so it can not be spent twice.
                        app(ExchangeCredit::class)->apply($exchangeId, 'sale', $sale->id, (float) $p['amount'], "Sale #{$sale->id}");
                    }
                    SalePayment::create(['sale_id' => $sale->id, 'mode' => $p['mode'], 'exchange_transaction_id' => $exchangeId ?: null, 'amount' => round((float) $p['amount'], 2), 'note' => $p['note'] ?? null, 'user_id' => Auth::id()]);
                }
            }

            if ($this->schemeId && ($scheme = \App\Models\Customer\InstallmentScheme::with('customer')->where('status', 'active')->find($this->schemeId))) {
                \App\Livewire\Installments\SchemeList::complete($scheme, 'sale', $sale->id);
            }

            // Link the order: it is delivered once an admin verifies this sale.
            if ($this->orderId && ($order = Order::find($this->orderId))) {
                $order->update(['converted_sale_id' => $sale->id]);
            }

            return $sale;
        });
        } catch (InsufficientExchangeCredit $e) {
            $this->step = 4;
            $this->addError('payments', $e->getMessage());

            return;
        }

        session()->flash('toast', "Sale #{$sale->id} is held until an admin verifies it.");

        return $this->redirectRoute('sales.invoice', $sale);
    }

    public function render()
    {
        return view('livewire.sales.new-sale', [
            'steps' => self::STEPS,
            'customer' => $this->customerObject,
            'referrer' => $this->referralCustomerId ? Customer::find($this->referralCustomerId) : null,
            'customerResults' => $this->customerSearch !== ''
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'itemResults' => $this->itemSearch !== ''
                ? Item::where('status', 'in_stock')->whereNotIn('id', array_keys($this->cart))->searchAnything($this->itemSearch)->limit(8)->get()
                : collect(),
            'orderModel' => $this->orderId ? Order::with('customer:id,name', 'images')->find($this->orderId) : null,
            'exchangeCredit' => $this->exchangeId ? ExchangeTransaction::find($this->exchangeId)?->creditRemaining() : null,
            'waitingReview' => $this->orderId ? (Order::find($this->orderId)?->piecesAwaitingReview() ?? 0) : 0,
            'modes' => collect(SalePayment::MODES)->except('exchange')->all(),
            'gstChoices' => ['jewellery' => $this->gstChoices($this->gstJewellery), 'hallmark' => $this->gstChoices($this->gstHallmark)],
            'gst' => $this->gstDetail(),
            'credits' => $this->customerId && $this->step === 4
                ? app(ExchangeCredit::class)->available($this->customerId)
                    ->reject(fn ($x) => collect($this->payments)->contains(fn ($p) => ($p['exchange_id'] ?? null) === $x->id))->values()
                : collect(),
            'canOverride' => $this->canOverride(),
            'hasOrderWarning' => collect($this->cart)->contains(fn ($c) => $c['order'] !== null),
        ])->layout('components.layouts.app', ['title' => 'New Sale — Radharani Jewellery']);
    }
}
