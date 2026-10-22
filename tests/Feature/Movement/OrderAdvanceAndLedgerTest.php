<?php

namespace Tests\Feature\Movement;

use App\Livewire\Admin\CustomerDetail;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Sales\NewSale;
use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\ExchangeCredit;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Advance money can be added to an order at any time, and the customer's ledger shows exchanges and advances.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=OrderAdvanceAndLedgerTest
 */
class OrderAdvanceAndLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
        $this->customer = Customer::create(['name' => 'Advance Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
    }

    private function order(float $advance = 1000, string $status = 'placed'): Order
    {
        return Order::create(['customer_id' => $this->customer->id, 'product_description' => 'Advance necklace', 'sourcing' => 'karigar', 'estimated_value' => 50000,
            'advance_amount' => $advance, 'status' => $status, 'created_by' => $this->user->id]);
    }

    public function test_advance_can_be_added_in_parts_after_the_order_is_placed(): void
    {
        $order = $this->order(1000);

        $c = Livewire::test(OrderDetail::class, ['order' => $order])
            ->set('advanceAmount', '4000')->set('advanceMode', 'upi')->set('advanceNote', 'Second visit')->call('addAdvance')->assertHasNoErrors()
            ->set('advanceAmount', '2500.50')->set('advanceMode', 'cash')->call('addAdvance')->assertHasNoErrors();

        $this->assertEquals(7500.50, $order->fresh()->advancePaid());
        $this->assertEquals(42499.50, $order->fresh()->advanceBalance());
        $this->assertSame(2, $order->payments()->count());
        $this->assertEquals(1000, (float) $order->fresh()->advance_amount, 'what was paid at the start is left as it was');
        $c->assertSee('Second visit')->assertSee('₹7,500.50');
    }

    public function test_a_bad_amount_or_mode_is_refused_and_a_closed_order_takes_no_advance(): void
    {
        $order = $this->order();

        Livewire::test(OrderDetail::class, ['order' => $order])->set('advanceAmount', '0')->call('addAdvance')->assertHasErrors('advanceAmount');
        Livewire::test(OrderDetail::class, ['order' => $order])->set('advanceAmount', '10')->set('advanceMode', 'exchange')->call('addAdvance')->assertHasErrors('advanceMode');

        $order->update(['status' => 'cancelled']);
        Livewire::test(OrderDetail::class, ['order' => $order->fresh()])->set('advanceAmount', '10')->call('addAdvance')->assertStatus(422);
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_the_sale_started_from_the_order_carries_every_advance_in_its_own_mode(): void
    {
        $order = $this->order(1000);
        $order->payments()->create(['amount' => 4000, 'mode' => 'upi', 'user_id' => $this->user->id]);

        $payments = Livewire::test(NewSale::class, ['orderId' => $order->id])->get('payments');

        $this->assertCount(2, $payments);
        $this->assertSame(['cash', 'upi'], array_column($payments, 'mode'));
        $this->assertEquals(5000, array_sum(array_column($payments, 'amount')));
    }

    public function test_the_ledger_shows_exchanges_advances_and_the_credit_left_without_moving_the_balance(): void
    {
        $order = $this->order(1000);
        $order->payments()->create(['amount' => 4000, 'mode' => 'upi', 'user_id' => $this->user->id]);
        $tx = ExchangeTransaction::create(['customer_id' => $this->customer->id, 'metal' => 'gold', 'gross_weight' => 10.5, 'net_weight' => 10, 'purity_averaged' => 91.6,
            'preset_deduction_percent' => 2, 'deductable_weight' => 9.8, 'stage' => 'settled', 'final_value' => 50000, 'settled_by' => $this->user->id, 'settled_at' => now(), 'created_by' => $this->user->id]);
        app(ExchangeCredit::class)->apply($tx->id, 'cash', null, 5000.0, 'paid out');

        $ledger = app(LedgerService::class)->forCustomer($this->customer);
        $labels = $ledger['rows']->pluck('label')->implode("\n");

        $this->assertStringContainsString("Exchange #{$tx->id} received", $labels);
        $this->assertStringContainsString("Exchange #{$tx->id} settled", $labels);
        $this->assertStringContainsString('₹5,000.00 paid out in cash', $labels);
        $this->assertStringContainsString("Advance ₹1,000.00 received on order #{$order->id}", $labels);
        $this->assertStringContainsString('₹4,000.00 received on order', $labels);
        $this->assertSame([0.0, 0.0, 0.0], $ledger['totals'], 'notes do not change what was billed or paid');
        $this->assertSame(['Exchange credit available to spend: ₹45,000.00'], $ledger['notes']);

        Livewire::test(CustomerDetail::class, ['customer' => $this->customer, 'tab' => 'ledger'])->assertSee("Exchange #{$tx->id} settled")->assertSee('Exchange credit available');
        $this->get(route('ledgers.customer.download', [$this->customer, 'pdf']))->assertOk();
        $this->get(route('ledgers.customer.download', [$this->customer, 'xlsx']))->assertOk();
    }
}
