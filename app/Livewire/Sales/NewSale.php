<?php
namespace App\Livewire\Sales;

use App\Models\Customer\Customer;
use App\Models\Customer\LoyaltySetting;
use App\Models\Customer\LoyaltyTransaction;
use App\Models\Pricing\GstRate;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Services\PricingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * New Sale / Billing.
 *
 * sales.invoice_number gets a "RESV-" placeholder here — the real,
 * sequential GST invoice number is assigned by SaleVerificationQueue at
 * verification time (see InvoiceCounter and that component's docblock).
 *
 * items.status now has a 'reserved' value (added alongside 'pending_review'
 * for the same "pending admin verification" purpose used elsewhere). Items
 * added to a sale are flipped to 'reserved' on submit, which is what keeps
 * them out of the `itemResults` search below (scoped to 'in_stock') and
 * therefore unavailable to other staff until SaleVerificationQueue verifies
 * the sale and flips them to 'sold' (or, in future, releases them back to
 * 'in_stock' on a rejected/cancelled sale).
 */
class NewSale extends Component
{
    public string $customerSearch = '';
    public ?int $customerId = null;

    public string $itemSearch = '';
    public array $cart = []; // item_id => ['item' => Item, 'price' => float, 'gst' => float]

    public float $loyaltyPointsUsed = 0;
    public array $paymentModes = [['mode' => 'cash', 'amount' => 0]];

    public function addItem(int $itemId)
    {
        if (isset($this->cart[$itemId])) return;

        $item = Item::findOrFail($itemId);
        $price = app(PricingService::class)->priceFor($item);
        $gstRate = GstRate::forCategory($item->category);

        $this->cart[$itemId] = [
            'label' => $item->huid_code ?: $item->internal_code,
            'category' => $item->category,
            'price' => $price,
            'gst_rate' => $gstRate,
        ];
        $this->itemSearch = '';
    }

    // A scanned tag (camera or keyboard + Enter) goes straight onto the bill.
    public function addByCode(string $raw)
    {
        $item = \App\Support\StockLookup::item($raw);

        if (! $item) {
            // Not an exact code: leave it as a search so the list below can help.
            $this->itemSearch = trim($raw);
            return;
        }
        if ($item->status !== 'in_stock') {
            $this->itemSearch = '';
            $this->dispatch('toast', message: "{$item->label} is " . str_replace('_', ' ', $item->status) . ' and can not be billed.', type: 'error');
            return;
        }

        $this->addItem($item->id);
        $this->dispatch('toast', message: "{$item->label} added to the bill.", type: 'success');
    }

    public function removeItem(int $itemId)
    {
        unset($this->cart[$itemId]);
    }

    public function addPaymentMode()
    {
        $this->paymentModes[] = ['mode' => 'cash', 'amount' => 0];
    }

    public function getSubtotalProperty(): float
    {
        return round(collect($this->cart)->sum('price'), 2);
    }

    public function getGstTotalProperty(): float
    {
        return round(collect($this->cart)->sum(fn ($c) => $c['price'] * $c['gst_rate'] / 100), 2);
    }

    public function getLoyaltyDiscountProperty(): float
    {
        $settings = LoyaltySetting::current();
        $points = min($this->loyaltyPointsUsed, $this->customerObject?->loyalty_points ?? 0);
        if ($points < $settings->min_redeemable_points) return 0;
        return round($points * $settings->point_value_in_rupees, 2);
    }

    public function getGrandTotalProperty(): float
    {
        return max(0, round($this->subtotal + $this->gstTotal - $this->loyaltyDiscount, 2));
    }

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function submit()
    {
        abort_unless(Auth::user()?->can('sale.create'), 403);

        $this->validate([
            'customerId' => 'required|exists:customers,id',
        ]);
        if (empty($this->cart)) {
            $this->addError('cart', 'Add at least one item.');
            return;
        }

        $sale = Sale::create([
            'customer_id' => $this->customerId,
            'invoice_number' => 'RESV-' . now()->format('YmdHis') . '-' . $this->customerId,
            'type' => 'sale',
            'cgst' => round($this->gstTotal / 2, 2),
            'sgst' => round($this->gstTotal / 2, 2),
            'igst' => 0,
            'discount' => $this->loyaltyDiscount,
            'payment_modes' => $this->paymentModes,
            'total' => $this->grandTotal,
            'confirmed_by_accountant' => false,
            'created_by' => Auth::id(),
        ]);

        foreach ($this->cart as $itemId => $line) {
            $sale->items()->attach($itemId, ['price_at_sale' => $line['price']]);
        }

        // Reserve the items now — they stay out of live availability from
        // this point, but only become 'sold' once an admin verifies the
        // sale in SaleVerificationQueue. Per-item update (not a mass
        // whereIn) so each item's own activity-log timeline picks this up.
        Item::whereKey(array_keys($this->cart))->get()->each(fn ($item) => $item->update(['status' => 'reserved']));

        // Points actually redeemed on this sale (loyaltyDiscount already
        // floors this against min_redeemable_points) — log the debit and
        // apply it to the customer's balance. This was previously computed
        // for the discount but never actually deducted anywhere, which
        // would have let points be re-used on the next sale — fixed here
        // while building the Loyalty Points Ledger, since both read the
        // same loyalty_transactions table.
        $redeemedPoints = min($this->loyaltyPointsUsed, $this->customerObject?->loyalty_points ?? 0);
        if ($this->loyaltyDiscount > 0 && $redeemedPoints > 0) {
            LoyaltyTransaction::create([
                'customer_id' => $this->customerId,
                'points' => -$redeemedPoints,
                'reason' => 'redeemed on sale',
                'related_sale_id' => $sale->id,
            ]);
            Customer::whereKey($this->customerId)->decrement('loyalty_points', $redeemedPoints);
        }

        $this->dispatch('toast', message: "Sale #{$sale->id} reserved — awaiting admin verification before it becomes final.", type: 'success');
        $this->reset(['customerId', 'customerSearch', 'cart', 'loyaltyPointsUsed', 'paymentModes']);
        $this->paymentModes = [['mode' => 'cash', 'amount' => 0]];
    }

    public function render()
    {
        return view('livewire.sales.new-sale', [
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'itemResults' => $this->itemSearch
                ? Item::where('status', 'in_stock')
                    ->where(fn ($q) => $q->where('huid_code', 'like', "%{$this->itemSearch}%")->orWhere('internal_code', 'like', "%{$this->itemSearch}%")->orWhere('category', 'like', "%{$this->itemSearch}%"))
                    ->limit(8)->get()
                : collect(),
        ])->layout('components.layouts.app', ['title' => 'New Sale — Radharani Jewellery']);
    }
}
