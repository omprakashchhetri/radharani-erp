<?php
namespace App\Livewire\Orders;

use App\Models\Notification\PendingNotification;
use App\Models\Orders\Order;
use App\Services\ExchangeCredit;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class OrderDetail extends Component
{
    public Order $order;

    // On cancelling: give back the exchange credit the advance was paid with. Only a manager can untick it.
    public bool $returnCredit = true;

    public function mount(Order $order)
    {
        $this->order = $order->load('customer', 'stockItem', 'convertedSale', 'creator', 'images', 'karigarBatches', 'hallmarkBatches');
    }

    public function confirm()
    {
        $this->order->update(['status' => 'confirmed']);
        $this->order->refresh();
    }

    public function markReady()
    {
        $this->order->update(['status' => 'ready']);
        $this->order->refresh();

        \App\Support\MessageTemplates::queue('order_ready', $this->order->customer, \App\Support\MessageTemplates::orderReady($this->order), 'order', $this->order->id);
    }

    public function deliver()
    {
        $this->order->update(['status' => 'delivered']);
        $this->order->refresh();
    }

    public function cancel()
    {
        $this->order->update(['status' => 'cancelled']);
        $this->order->refresh();

        $credit = app(ExchangeCredit::class)->orderCredit($this->order->id);
        $returnIt = $this->returnCredit || ! auth()->user()?->can('exchange.manage');
        if ($credit > 0 && $returnIt) {
            app(ExchangeCredit::class)->returnOrderCredit($this->order->id, "Order #{$this->order->id} cancelled, credit returned");
            $this->dispatch('toast', message: '₹' . number_format($credit) . ' of exchange credit is back with the customer.', type: 'success');
        }
    }

    public function getExchangeCreditProperty(): float
    {
        return app(ExchangeCredit::class)->orderCredit($this->order->id);
    }

    /**
     * Real, timestamped lifecycle history — built from the activity log
     * (Order logs `status` via LogsActivity) rather than a fabricated
     * timeline, since every status change here genuinely did happen at a
     * specific moment and by a specific user.
     */
    public function getTimelineProperty(): array
    {
        $labels = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'ready' => 'Ready for collection', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
        $tones = ['placed' => 'gold', 'confirmed' => 'gold', 'ready' => 'gold', 'delivered' => 'in', 'cancelled' => 'out'];
        $icons = ['placed' => 'clipboard', 'confirmed' => 'check-circle', 'ready' => 'gift', 'delivered' => 'check-circle', 'cancelled' => 'x-circle'];

        $events = collect([[
            'at' => $this->order->created_at,
            'tone' => $tones['placed'],
            'icon' => $icons['placed'],
            'title' => $labels['placed'],
            'link' => null,
            'meta' => [],
            'user' => $this->order->creator?->name,
            'note' => null,
            'photo' => null,
        ]]);

        $changes = Activity::with('causer')
            ->where('subject_type', Order::class)
            ->where('subject_id', $this->order->id)
            ->where('event', 'updated')
            ->oldest()
            ->get()
            ->filter(fn ($a) => array_key_exists('status', $a->attribute_changes['attributes'] ?? []));

        foreach ($changes as $a) {
            $status = $a->attribute_changes['attributes']['status'];

            $events->push([
                'at' => $a->created_at,
                'tone' => $tones[$status] ?? 'neutral',
                'icon' => $icons[$status] ?? 'circle',
                'title' => $labels[$status] ?? ucfirst($status),
                'link' => null,
                'meta' => [],
                'user' => $a->causer?->name,
                'note' => null,
                'photo' => null,
            ]);
        }

        return $events->all();
    }

    public function getConfirmationMessageProperty(): string
    {
        $o = $this->order;

        return "Radharani Jewellery Works — Order Confirmation\n"
            . "Dear {$o->customer?->name}, your order for \"{$o->product_description}\" (₹" . number_format((float) $o->estimated_value) . ") is confirmed.\n"
            . ($o->rate_locked ? "Rate locked at order value.\n" : "Rate will apply at delivery.\n")
            . "Track status anytime on your portal.";
    }

    public function render()
    {
        return view('livewire.orders.order-detail')->layout('components.layouts.app', ['title' => 'Order Detail — Radharani Jewellery']);
    }
}
