<?php

namespace Tests\Feature\Movement;

use App\Livewire\Exchange\AccountsValuation;
use App\Livewire\Exchange\ExchangeList;
use App\Livewire\Exchange\TransactionDetail;
use App\Livewire\Orders\NewOrderEntry;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Sales\NewSale;
use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeApplication;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Movement\Movement;
use App\Models\Orders\Order;
use App\Models\Sales\SalePayment;
use App\Models\Stock\Item;
use App\Models\User;
use App\Services\ExchangeCredit;
use App\Services\InsufficientExchangeCredit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The value of a settled exchange as credit: spent in a sale, put towards an order, or paid out in cash,
 * and never spent twice. Needs the MySQL dev database:
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=ExchangeCreditTest
 */
class ExchangeCreditTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Customer $customer;
    private ExchangeCredit $credit;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
        $this->credit = app(ExchangeCredit::class);
        $this->customer = Customer::create(['name' => 'Exchange Credit Customer', 'phone' => '9' . random_int(100000000, 999999999)]);
    }

    private function settled(float $value, ?Customer $customer = null): ExchangeTransaction
    {
        return ExchangeTransaction::create(['customer_id' => ($customer ?? $this->customer)->id, 'metal' => 'gold', 'gross_weight' => 10, 'net_weight' => 9.5,
            'purity_averaged' => 91.6, 'stage' => 'settled', 'final_value' => $value, 'settled_by' => $this->user->id, 'settled_at' => now(), 'created_by' => $this->user->id]);
    }

    private function counterPiece(string $code): Item
    {
        $item = Item::create(['packet_id' => $this->packetId(), 'internal_code' => $code, 'metal' => 'gold', 'category' => 'Ring', 'purity' => '22K', 'making_value' => 0, 'weight' => 3, 'status' => 'in_stock']);
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        return $item;
    }

    // ---------------------------------------------------------------- the credit

    public function test_credit_is_what_is_left_and_can_not_be_spent_twice(): void
    {
        $tx = $this->settled(10000);
        $this->assertEquals(10000, $tx->creditRemaining());

        $this->credit->apply($tx->id, 'cash', null, 4000, 'part in cash');
        $this->assertEquals(6000, $tx->fresh()->creditRemaining());

        $this->expectException(InsufficientExchangeCredit::class);
        $this->credit->apply($tx->id, 'cash', null, 6000.01);
    }

    public function test_an_unsettled_exchange_has_no_credit(): void
    {
        $tx = $this->settled(5000);
        $tx->update(['stage' => 'tested']);

        $this->assertEquals(0, $tx->fresh()->creditRemaining());
        $this->assertCount(0, $this->credit->available($this->customer->id));
    }

    // ---------------------------------------------------------------- a sale

    public function test_a_sale_started_from_an_exchange_pays_with_the_credit_and_the_balance_in_cash(): void
    {
        $tx = $this->settled(1000);
        $piece = $this->counterPiece('XC001');

        $c = Livewire::withQueryParams(['exchange' => $tx->id])->test(NewSale::class);
        $this->assertSame($this->customer->id, $c->get('customerId'));

        $c->call('addItem', $piece->id)->call('next')->call('next');
        $this->assertSame(4, $c->get('step'));

        $lines = $c->get('payments');
        $this->assertSame('exchange', $lines[0]['mode']);
        $this->assertEquals(1000, $lines[0]['amount']);
        $this->assertEquals($c->get('total') - 1000, $lines[1]['amount']);
        $this->assertEqualsWithDelta($c->get('total'), collect($c->get('payments'))->sum(fn ($p) => (float) $p['amount']), 0.005);

        // The page itself shows the bill as fully paid (a stale cached figure once showed the whole bill as the balance).
        $this->assertMatchesRegularExpression('/Balance after these payments<\/span><span[^>]*>₹0\.00/', $c->html());

        $c->call('next')->call('submit')->assertHasNoErrors();

        $payment = SalePayment::where('exchange_transaction_id', $tx->id)->firstOrFail();
        $this->assertSame('exchange', $payment->mode);
        $this->assertEquals(1000, $payment->amount);
        $this->assertEquals(0, $tx->fresh()->creditRemaining());
        $this->assertDatabaseHas('exchange_applications', ['exchange_transaction_id' => $tx->id, 'kind' => 'sale', 'applied_id' => $payment->sale_id]);
    }

    public function test_credit_bigger_than_the_bill_is_capped_and_the_rest_stays(): void
    {
        $tx = $this->settled(50000000);
        $piece = $this->counterPiece('XC002');

        $c = Livewire::withQueryParams(['exchange' => $tx->id])->test(NewSale::class)->call('addItem', $piece->id)->call('next')->call('next');
        $total = $c->get('total');

        $this->assertEquals($total, $c->get('payments')[0]['amount']);
        $this->assertCount(1, array_filter($c->get('payments'), fn ($p) => ($p['amount'] ?? '') !== ''));

        $c->call('next')->call('submit')->assertHasNoErrors();
        $this->assertEqualsWithDelta(50000000 - $total, $tx->fresh()->creditRemaining(), 0.01);
    }

    public function test_any_sale_offers_the_customers_credit_and_it_can_not_be_spent_twice(): void
    {
        $tx = $this->settled(1000);
        $first = $this->counterPiece('XC003');
        $second = $this->counterPiece('XC004');

        $a = Livewire::test(NewSale::class)->set('customerId', $this->customer->id)->call('next')->call('addItem', $first->id)->call('next')->call('next');
        $a->assertSee('exchange credit from exchange #' . $tx->id)->call('useCredit', $tx->id);
        $this->assertSame('exchange', collect($a->get('payments'))->firstWhere('exchange_id', $tx->id)['mode']);

        // A second sale is started before the first is saved; the first one saves and takes the credit.
        $b = Livewire::test(NewSale::class)->set('customerId', $this->customer->id)->call('next')->call('addItem', $second->id)->call('next')->call('next')->call('useCredit', $tx->id);
        $a->call('next')->call('submit')->assertHasNoErrors();
        $this->assertEquals(0, $tx->fresh()->creditRemaining());

        $b->call('submit')->assertHasErrors();
        $this->assertSame(1, SalePayment::where('exchange_transaction_id', $tx->id)->count());
    }

    public function test_exchange_credit_of_another_customer_can_not_be_used(): void
    {
        $other = Customer::create(['name' => 'Somebody Else', 'phone' => '9' . random_int(100000000, 999999999)]);
        $tx = $this->settled(1000, $other);
        $piece = $this->counterPiece('XC005');

        $c = Livewire::test(NewSale::class)->set('customerId', $this->customer->id)->call('next')->call('addItem', $piece->id)->call('next')->call('next');
        $c->set('payments', [['mode' => 'exchange', 'exchange_id' => $tx->id, 'amount' => '500']])->call('next')->assertHasErrors('payments.0.amount');
        $this->assertSame(0, SalePayment::where('exchange_transaction_id', $tx->id)->count());
    }

    // ---------------------------------------------------------------- an order

    private function placeOrder(ExchangeTransaction $tx, $creditUse = null): Order
    {
        $c = Livewire::withQueryParams(['exchange' => $tx->id])->test(NewOrderEntry::class)
            ->set('productDescription', 'Order paid with exchange credit')->set('sourcing', 'karigar')->call('next');
        $c->call('next');
        $this->assertSame(3, $c->get('step'));
        if ($creditUse !== null) {
            $c->set('creditUse', $creditUse);
        }
        $c->call('submit')->assertHasNoErrors();

        return Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    public function test_an_order_started_from_an_exchange_takes_the_credit_as_its_advance(): void
    {
        $tx = $this->settled(40000);

        $order = $this->placeOrder($tx);

        $this->assertEquals(40000, $order->advance_amount);
        $this->assertEquals(40000, $this->credit->orderCredit($order->id));
        $this->assertEquals(0, $tx->fresh()->creditRemaining());
    }

    public function test_an_order_can_take_part_of_the_credit_and_the_rest_of_the_advance_is_other_money(): void
    {
        $tx = $this->settled(40000);

        $c = Livewire::withQueryParams(['exchange' => $tx->id])->test(NewOrderEntry::class)
            ->set('productDescription', 'Part credit')->set('sourcing', 'karigar')->call('next')->call('next')
            ->set('depositAmount', '25000')->set('creditUse', '15000')->call('submit')->assertHasNoErrors();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(25000, $order->advance_amount);
        $this->assertEquals(15000, $this->credit->orderCredit($order->id));
        $this->assertEquals(25000, $tx->fresh()->creditRemaining());

        Livewire::withQueryParams(['exchange' => $tx->id])->test(NewOrderEntry::class)
            ->set('productDescription', 'Too much')->set('sourcing', 'karigar')->call('next')->call('next')
            ->set('depositAmount', '10000')->set('creditUse', '30000')->call('submit')->assertHasErrors('creditUse');
        $this->assertNotNull($c);
    }

    public function test_cancelling_an_order_returns_the_credit_unless_a_manager_keeps_it(): void
    {
        $tx = $this->settled(40000);
        $order = $this->placeOrder($tx);

        Livewire::test(OrderDetail::class, ['order' => $order])->call('cancel');
        $this->assertEquals(40000, $tx->fresh()->creditRemaining());
        $this->assertEquals(0, $this->credit->orderCredit($order->id));

        $tx2 = $this->settled(30000);
        $order2 = $this->placeOrder($tx2);
        Livewire::test(OrderDetail::class, ['order' => $order2])->set('returnCredit', false)->call('cancel');
        $this->assertEquals(0, $tx2->fresh()->creditRemaining());
    }

    // ---------------------------------------------------------------- the exchange pages

    public function test_settling_goes_to_the_exchange_page_and_the_credit_can_be_paid_out_in_cash(): void
    {
        $tx = ExchangeTransaction::create(['customer_id' => $this->customer->id, 'metal' => 'gold', 'gross_weight' => 10, 'net_weight' => 9.5,
            'purity_averaged' => 91.6, 'stage' => 'tested', 'created_by' => $this->user->id]);

        Livewire::test(AccountsValuation::class)->set('settleTransactionId', $tx->id)->set('finalValue', 20000)->call('settle')
            ->assertRedirect(route('exchange.transactions.show', $tx));

        $page = Livewire::test(TransactionDetail::class, ['transaction' => $tx->fresh()]);
        $page->assertSee('What next?')->assertSee('Use in a sale')->assertSee('Use for a new order');

        $page->call('openCash')->assertSet('cashAmount', '20000')->set('cashAmount', 5000)->call('payCash')->assertHasNoErrors();
        $this->assertEquals(15000, $tx->fresh()->creditRemaining());
        $this->assertDatabaseHas('exchange_applications', ['exchange_transaction_id' => $tx->id, 'kind' => 'cash', 'amount' => 5000]);

        $page->call('openCash')->set('cashAmount', 15000.01)->call('payCash')->assertHasErrors('cashAmount');
    }

    public function test_the_list_shows_the_credit_left_on_a_settled_exchange(): void
    {
        $tx = $this->settled(12345);

        Livewire::test(ExchangeList::class)->set('show', 'settled')->set('search', 'Exchange Credit Customer')->assertSee('₹12,345 credit');
        $this->credit->apply($tx->id, 'cash', null, 12345);
        Livewire::test(ExchangeList::class)->set('show', 'settled')->set('search', 'Exchange Credit Customer')->assertDontSee('credit');
        $this->assertSame(1, ExchangeApplication::where('exchange_transaction_id', $tx->id)->count());
    }
}
