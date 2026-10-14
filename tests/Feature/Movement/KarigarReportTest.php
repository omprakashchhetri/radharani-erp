<?php

namespace Tests\Feature\Movement;

use App\Models\Customer\Customer;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Orders\Order;
use App\Models\Purchase\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The printed karigar job sheet carries the photo of what went out and the customer's reference images.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=KarigarReportTest
 */
class KarigarReportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->actingAs(User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.'));
        Storage::fake('public');
    }

    private function batch(?Order $order = null, ?string $photo = null): KarigarRawBatch
    {
        return KarigarRawBatch::create(['vendor_id' => Vendor::create(['name' => 'Report Karigar', 'type' => 'karigar'])->id, 'weight_out' => 12, 'metal' => 'gold',
            'description' => 'Report job', 'pieces_expected' => 1, 'order_id' => $order?->id, 'photo_path' => $photo, 'status' => 'dispatched', 'user_id' => auth()->id()]);
    }

    public function test_the_sheet_shows_the_photo_and_the_orders_reference_images(): void
    {
        Storage::disk('public')->put('movements/karigar/issued.webp', 'x');
        Storage::disk('public')->put('orders/reference/ref1.webp', 'x');
        $customer = Customer::create(['name' => 'Sheet Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $order = Order::create(['customer_id' => $customer->id, 'product_description' => 'Sheet necklace', 'sourcing' => 'karigar', 'estimated_value' => 0, 'status' => 'placed', 'created_by' => auth()->id()]);
        $order->images()->create(['path' => 'orders/reference/ref1.webp', 'created_by' => auth()->id()]);
        $order->images()->create(['path' => 'orders/reference/gone.webp', 'created_by' => auth()->id()]); // file no longer on disk

        $html = $this->get(route('movements.karigar.print', $this->batch($order, 'movements/karigar/issued.webp')))->assertOk()->getContent();

        $this->assertStringContainsString('storage/movements/karigar/issued.webp', $html);
        $this->assertStringContainsString('storage/orders/reference/ref1.webp', $html);
        $this->assertStringNotContainsString('gone.webp', $html);
        $this->assertStringContainsString('Sheet Customer', $html);
        $this->assertStringContainsString('Sheet necklace', $html);
    }

    public function test_the_sheet_has_no_photo_section_when_there_is_nothing_to_show(): void
    {
        $this->get(route('movements.karigar.print', $this->batch()))->assertOk()->assertDontSee('Photo of what went out')->assertDontSee('reference images');
    }
}
