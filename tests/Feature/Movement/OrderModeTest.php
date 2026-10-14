<?php

namespace Tests\Feature\Movement;

use App\Livewire\Movement\HallmarkDesk;
use App\Livewire\Movement\KarigarDesk;
use App\Models\Customer\Customer;
use App\Models\Movement\HallmarkBatch;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\KarigarReceipt;
use App\Models\Orders\Order;
use App\Models\Purchase\Vendor;
use App\Models\Stock\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Karigar and Hallmarking opened from a custom order work on that one order, with what the order
 * already says filled in. Like the other movement tests these need the MySQL dev database:
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=OrderModeTest
 */
class OrderModeTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Order $order;
    private Vendor $karigar;
    private Vendor $centre;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->user = User::first() ?? User::factory()->create();
        $this->actingAs($this->user);

        $category = ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => 'Necklace'], ['sort_order' => 1, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Order Mode Customer', 'phone' => '9' . random_int(100000000, 999999999)]);
        $this->order = Order::create([
            'customer_id' => $customer->id, 'product_description' => 'Gold necklace for the test', 'category' => $category->name,
            'metal' => 'gold', 'estimated_weight' => 22, 'estimated_value' => 100000, 'sourcing' => 'karigar',
            'status' => 'confirmed', 'created_by' => $this->user->id,
        ]);
        $this->karigar = Vendor::create(['name' => 'Order Mode Karigar', 'type' => 'karigar']);
        $this->centre = Vendor::create(['name' => 'Order Mode Centre', 'type' => 'hallmark_center']);
    }

    private function batch(array $extra = []): KarigarRawBatch
    {
        return KarigarRawBatch::create($extra + [
            'vendor_id' => $this->karigar->id, 'weight_out' => 22, 'metal' => 'gold', 'description' => 'Gold necklace for the test',
            'pieces_expected' => 2, 'order_id' => $this->order->id, 'status' => 'dispatched', 'user_id' => $this->user->id,
        ]);
    }

    public function test_karigar_opened_from_an_order_fills_the_form_from_the_order(): void
    {
        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(KarigarDesk::class);

        $this->assertTrue($c->get('orderMode'));
        $this->assertSame('issue', $c->get('tab'));
        $this->assertSame('Gold necklace for the test', $c->get('description'));
        $this->assertSame('gold', $c->get('metal'));
        $this->assertEquals(22, $c->get('estimatedWeight'));
        $this->assertSame(['Necklace'], $c->get('categories'));
    }

    public function test_karigar_order_mode_hides_the_general_tabs(): void
    {
        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(KarigarDesk::class);

        $c->call('setTab', 'payments');
        $this->assertSame('issue', $c->get('tab'));
        $c->call('setTab', 'receive');
        $this->assertSame('receive', $c->get('tab'));
    }

    public function test_karigar_order_mode_goes_straight_to_the_open_batch_and_lists_only_its_own(): void
    {
        $mine = $this->batch();
        $this->batch(['order_id' => null, 'description' => 'Somebody else']);

        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(KarigarDesk::class);

        $this->assertSame('receive', $c->get('tab'));
        $this->assertSame($mine->id, $c->get('batchId'));
        $c->assertSee('Gold necklace for the test')->assertDontSee('Somebody else');
    }

    public function test_without_an_order_the_karigar_desk_is_unchanged(): void
    {
        $c = Livewire::test(KarigarDesk::class);

        $this->assertFalse($c->get('orderMode'));
        $c->call('setTab', 'payments');
        $this->assertSame('payments', $c->get('tab'));
    }

    public function test_a_karigar_receipt_for_hallmarking_opens_hallmarking_ticked_with_its_order(): void
    {
        $receipt = KarigarReceipt::create(['batch_id' => $this->batch()->id, 'pieces' => 2, 'weight_received' => 21.5,
            'disposition' => 'hallmark', 'user_id' => $this->user->id]);

        $c = Livewire::withQueryParams(['receipt' => $receipt->id])->test(HallmarkDesk::class);

        $this->assertTrue($c->get('orderMode'));
        $this->assertSame($this->order->id, $c->get('orderId'));
        $this->assertSame([$receipt->id], $c->get('karigarReceiptIds'));
        $this->assertSame('Gold necklace for the test', $c->get('description'));
        $this->assertSame('order', $c->get('source'));
    }

    public function test_hallmarking_order_mode_lists_only_this_orders_karigar_receipts(): void
    {
        $other = KarigarReceipt::create(['batch_id' => $this->batch(['order_id' => null, 'description' => 'Other job'])->id, 'pieces' => 5,
            'weight_received' => 9, 'disposition' => 'hallmark', 'user_id' => $this->user->id]);
        $mine = KarigarReceipt::create(['batch_id' => $this->batch()->id, 'pieces' => 2, 'weight_received' => 21.5,
            'disposition' => 'hallmark', 'user_id' => $this->user->id]);

        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(HallmarkDesk::class);

        $c->assertSee('21.500 g')->assertDontSee('9.000 g');
        $this->assertNotNull($other);
        $this->assertSame([], $c->get('karigarReceiptIds'));
        $this->assertNotNull($mine);
    }

    public function test_hallmark_receive_is_filled_in_from_the_order_with_one_row_per_piece(): void
    {
        $batch = HallmarkBatch::create(['vendor_id' => $this->centre->id, 'source' => 'order', 'order_id' => $this->order->id,
            'description' => 'Gold necklace for the test', 'pieces_counted' => 2, 'weight_counted' => 21.5, 'huid_expected' => 2,
            'status' => 'dispatched', 'user_id' => $this->user->id]);

        $c = Livewire::withQueryParams(['order' => $this->order->id])->test(HallmarkDesk::class);

        $this->assertSame($batch->id, $c->get('batchId'));
        $this->assertSame('receive', $c->get('tab'));
        $this->assertSame('gold', $c->get('metal'));
        $this->assertCount(2, $c->get('newRows'));
        $this->assertSame(ItemCategory::where('metal', 'gold')->where('name', 'Necklace')->value('id'), $c->get('categoryId'));
    }

    public function test_the_order_path_has_a_review_step_for_pieces_waiting_for_an_admin(): void
    {
        $steps = collect($this->order->fresh()->pathSteps())->pluck('step')->all();

        $this->assertSame(['karigar', 'hallmark', 'review', 'sales'], $steps);
    }
}
