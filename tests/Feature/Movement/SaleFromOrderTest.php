<?php

namespace Tests\Feature\Movement;

use App\Livewire\Movement\PendingReviewQueue;
use App\Livewire\Sales\NewSale;
use App\Models\Customer\Customer;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Orders\Order;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The sale for a made-to-order piece starts with that order's pieces in the cart, and the payment step
 * opens with the balance due. Needs the MySQL dev database:
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=SaleFromOrderTest
 */
class SaleFromOrderTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Customer $customer;
    private Order $order;
    private KarigarRawBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);

        $this->customer = Customer::create(['name' => 'Sale From Order Customer', 'phone' => '9' . random_int(100000000, 999999999)]);
        $this->order = Order::create(['customer_id' => $this->customer->id, 'product_description' => 'Made for the sale test', 'metal' => 'gold',
            'estimated_value' => 50000, 'sourcing' => 'karigar', 'status' => 'confirmed', 'created_by' => $this->user->id]);
        $this->batch = KarigarRawBatch::create(['vendor_id' => Vendor::create(['name' => 'Sale Test Karigar', 'type' => 'karigar'])->id, 'weight_out' => 10,
            'metal' => 'gold', 'description' => 'Made for the sale test', 'pieces_expected' => 2, 'order_id' => $this->order->id,
            'status' => 'returned', 'user_id' => $this->user->id]);
    }

    private function piece(string $code, string $status, ?int $batchId = null): Item
    {
        return Item::create(['internal_code' => $code, 'metal' => 'gold', 'category' => 'Ring', 'purity' => '22K', 'making_value' => 0, 'weight' => 3,
            'status' => $status, 'source_karigar_batch_id' => $batchId]);
    }

    public function test_the_sale_for_an_order_starts_with_the_orders_confirmed_pieces_in_the_cart(): void
    {
        $ready = $this->piece('SO001', 'in_stock', $this->batch->id);
        $waiting = $this->piece('SO002', 'pending_review', $this->batch->id);
        $other = $this->piece('SO003', 'in_stock');

        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(NewSale::class);

        $this->assertSame([$ready->id], array_keys($c->get('cart')));
        $this->assertSame($this->customer->id, $c->get('customerId'));
        $c->assertSee('1 piece')->assertSee('still waiting for an admin');
        $this->assertNotNull($waiting);
        $this->assertNotNull($other);
    }

    public function test_the_payment_step_opens_with_the_balance_due_and_follows_the_bill(): void
    {
        $piece = $this->piece('SO004', 'in_stock');
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $piece->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        $c = Livewire::test(NewSale::class)
            ->set('customerId', $this->customer->id)->call('next')
            ->call('addItem', $piece->id)->call('next')
            ->set('extras', [['name' => 'Making', 'amount' => '5000']])->call('next');

        $this->assertSame(4, $c->get('step'));
        $this->assertEquals($c->get('total'), $c->get('payments')[0]['amount']); // the whole bill, GST included

        // Back to the charges: the untouched line follows the new total.
        $c->call('goToStep', 3)->set('extras', [['name' => 'Making', 'amount' => '7000']])->call('next');
        $this->assertEquals($c->get('total'), $c->get('payments')[0]['amount']);
        $this->assertGreaterThan(7000 + $c->get('subtotal'), $c->get('total'), 'GST is on top of the pieces and the extra charge');

        // An amount staff typed is never overwritten.
        $c->set('payments.0.amount', '2000')->call('goToStep', 3)->call('next');
        $this->assertSame('2000', (string) $c->get('payments')[0]['amount']);
    }

    public function test_confirm_and_sell_confirms_the_piece_and_opens_the_orders_sale(): void
    {
        $piece = $this->piece('SO005', 'pending_review', $this->batch->id);

        Livewire::test(PendingReviewQueue::class)
            ->call('confirmAndSell', $piece->id)
            ->assertRedirect(route('sales.new', ['order' => $this->order->id]));

        $this->assertSame('in_stock', $piece->fresh()->status);
    }
}
