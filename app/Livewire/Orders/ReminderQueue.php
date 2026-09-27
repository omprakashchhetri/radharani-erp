<?php
namespace App\Livewire\Orders;

use App\Models\Orders\Order;
use Livewire\Component;

class ReminderQueue extends Component
{
    public function markSent(int $id)
    {
        // No dedicated "reminder sent" flag on orders — the PendingNotification
        // row created when the order was marked ready is the source of truth
        // for send/verify state (see OrderDetail::markReady()).
        \App\Models\Notification\PendingNotification::where('related_type', 'order')
            ->where('related_id', $id)
            ->where('status', 'pending')
            ->latest()
            ->first()
            ?->markSent(auth()->user());

        $this->dispatch('toast', message: 'Marked as sent.', type: 'success');
    }

    public function verify(int $id)
    {
        Order::where('id', $id)->update(['status' => 'delivered']);

        $this->dispatch('toast', message: 'Order marked collected — admin verified.', type: 'success');
    }

    public function render()
    {
        $ready = Order::where('status', 'ready')->with('customer')->latest()->get();

        $notifications = \App\Models\Notification\PendingNotification::where('related_type', 'order')
            ->whereIn('related_id', $ready->pluck('id'))
            ->get()
            ->groupBy('related_id');

        $ready->each(function ($order) use ($notifications) {
            $order->notificationSent = $notifications->get($order->id, collect())->contains('status', 'sent');
        });

        return view('livewire.orders.reminder-queue', ['ready' => $ready])->layout('components.layouts.app', ['title' => 'Ready-for-Collection Reminders — Radharani Jewellery']);
    }
}
