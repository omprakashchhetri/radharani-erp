<?php

namespace Tests\Feature\Movement;

use App\Livewire\Pricing\PriceSimulator;
use App\Livewire\Pricing\PricingRules;
use App\Livewire\Sales\NewSale;
use App\Models\Customer\Customer;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Pricing\TaxSetting;
use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Stock\Item;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * GST as a choice on the bill, the price breakup, and cheque as a payment mode. A 9K piece at a rate of
 * its own with rules of its own, so every figure is exact: 10 g at 1000 = 10,000, making 500 and hallmarking
 * 100 make 10,600; GST 3% on 10,500 is 315 and 18% on 100 is 18, so 333, and the bill is 10,933.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=GstTest
 */
class GstTest extends TestCase
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

        TaxSetting::current()->update(['jewellery_percent' => 3, 'hallmark_percent' => 18]);
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
        PricingRule::create(['kind' => 'making', 'scope' => 'all', 'calc' => 'per_piece', 'value' => 500, 'active' => true, 'created_by' => $this->user->id]);
        PricingRule::create(['kind' => 'hallmark', 'scope' => 'all', 'calc' => 'per_piece', 'value' => 100, 'active' => true, 'created_by' => $this->user->id]);
        $this->customer = Customer::create(['name' => 'Gst Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
    }

    private function counterPiece(): Item
    {
        $item = Item::create(['internal_code' => 'GS' . random_int(100, 999), 'huid_code' => 'H' . random_int(10000, 99999), 'metal' => 'gold', 'category' => 'Ring', 'purity' => '9K', 'making_value' => 0, 'weight' => 10, 'status' => 'in_stock']);
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        return $item;
    }

    private function toItems(Item $piece)
    {
        return Livewire::test(NewSale::class)->set('customerId', $this->customer->id)->call('next')->call('addItem', $piece->id);
    }

    // ---------------------------------------------------------------- the sums

    public function test_gst_is_the_jewellery_rate_on_everything_but_hallmarking_and_the_hallmark_rate_on_hallmarking(): void
    {
        $b = app(PricingService::class)->breakdown($this->counterPiece());
        $this->assertEquals(10600, $b['total']);

        $gst = app(PricingService::class)->gstOnItem($b, 3, 18);
        $this->assertEquals(10500, $gst['jewellery_base']);
        $this->assertEquals(100, $gst['hallmark_base']);
        $this->assertEquals(315, $gst['gst_jewellery']);
        $this->assertEquals(18, $gst['gst_hallmark']);
        $this->assertEquals(333, $gst['gst']);
    }

    public function test_on_a_bill_the_gst_is_worked_piece_by_piece_and_a_discount_can_not_push_it_below_zero(): void
    {
        $b = ['total' => 10600.0, 'taxable' => 10500.0, 'huid_charge' => 100.0];

        $gst = app(PricingService::class)->gstOnBill([$b, $b], 3, 18);

        $this->assertEquals(21000, $gst['jewellery_base']);
        $this->assertEquals(630, $gst['gst_jewellery']);
        $this->assertEquals(36, $gst['gst_hallmark']);
        $this->assertEquals(0, app(PricingService::class)->gstOnBill([$b], 0, 0)['gst']);
    }

    public function test_the_breakup_lines_add_up_to_the_price(): void
    {
        $b = app(PricingService::class)->breakdown($this->counterPiece());
        $lines = app(PricingService::class)->breakupLines($b);

        $this->assertEquals($b['total'], round(array_sum(array_column($lines, 'amount')), 2));
        $this->assertSame(['Gold value', 'Making charge', 'Hallmarking'], array_column($lines, 'label'));
        $this->assertSame('10.000 g at ₹1,000.00 a gram', $lines[0]['note']);
    }

    // ---------------------------------------------------------------- the sale

    public function test_a_bill_starts_with_the_owners_rates_and_the_total_includes_gst(): void
    {
        $c = $this->toItems($this->counterPiece());

        $this->assertSame('3', $c->get('gstJewellery'));
        $this->assertSame('18', $c->get('gstHallmark'));
        $this->assertEquals(10600, $c->get('subtotal'));
        $this->assertEquals(333, $c->get('gst'));
        $this->assertEquals(10933, $c->get('total'));
        $c->assertSee('Price breakup')->assertSee('GST on this bill');
    }

    public function test_the_rates_can_be_changed_or_set_to_zero_on_the_bill(): void
    {
        $c = $this->toItems($this->counterPiece());

        $c->set('gstJewellery', '0');
        $this->assertEquals(18, $c->get('gst'));
        $this->assertEquals(10618, $c->get('total'));

        $c->set('gstHallmark', '0');
        $this->assertEquals(0, $c->get('gst'));
        $this->assertEquals(10600, $c->get('total'));

        $c->set('gstJewellery', '5');
        $this->assertEquals(525, $c->get('gst'));

        $c->set('gstJewellery', '150')->call('next')->assertHasErrors('gstJewellery');
    }

    public function test_the_sale_keeps_the_rates_and_the_gst_it_was_made_at_and_the_bill_prints_them(): void
    {
        $piece = $this->counterPiece();
        $c = $this->toItems($piece)->call('next')->call('next');

        $this->assertEqualsWithDelta(10933, collect($c->get('payments'))->sum(fn ($p) => (float) $p['amount']), 0.005, 'payment opens with the whole bill, GST included');
        $c->call('next')->call('submit')->assertHasNoErrors();

        $sale = Sale::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(10933, $sale->total);
        $this->assertEquals(333, $sale->gst_amount);
        $this->assertEquals(18, $sale->gst_hallmark_amount);
        $this->assertEquals(3, $sale->gst_jewellery_percent);
        $this->assertEquals(18, $sale->gst_hallmark_percent);
        $this->assertEquals(10600, $sale->items()->first()->pivot->price_at_sale);
        $this->assertEquals(0, $sale->balance);

        // The owner changes the rates later: the bill already made does not move.
        TaxSetting::current()->update(['jewellery_percent' => 5]);
        $this->get(route('sales.bill', $sale))->assertOk()->assertSee('CGST 1.5% + SGST 1.5% on jewellery')->assertSee('CGST 9% + SGST 9% on hallmarking')->assertSee('₹315.00')->assertSee('₹18.00');
    }

    public function test_a_bill_at_zero_gst_prints_no_gst_lines(): void
    {
        $c = $this->toItems($this->counterPiece())->set('gstJewellery', '0')->set('gstHallmark', '0')->call('next')->call('next')->call('next')->call('submit')->assertHasNoErrors();

        $sale = Sale::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(10600, $sale->total);
        $this->assertEquals(0, $sale->gst_amount);
        $this->get(route('sales.bill', $sale))->assertOk()->assertDontSee('GST ');
        $this->assertNotNull($c);
    }

    // ---------------------------------------------------------------- cheque

    public function test_cheque_is_a_payment_mode_with_its_details_kept(): void
    {
        $this->assertArrayHasKey('cheque', SalePayment::MODES);

        $c = $this->toItems($this->counterPiece())->call('next')->call('next');
        $c->set('payments.0.mode', 'cheque')->set('payments.0.note', 'Cheque 000123, SBI')->assertSee('Cheque details');
        $c->call('next')->call('submit')->assertHasNoErrors();

        $payment = SalePayment::whereHas('sale', fn ($q) => $q->where('customer_id', $this->customer->id))->firstOrFail();
        $this->assertSame('cheque', $payment->mode);
        $this->assertSame('Cheque 000123, SBI', $payment->note);
        $this->get(route('sales.bill', $payment->sale))->assertOk()->assertSee('Paid by Cheque');
    }

    // ---------------------------------------------------------------- the owner's settings and the other screens

    public function test_the_owner_sets_the_default_rates_on_the_pricing_page(): void
    {
        $page = Livewire::test(PricingRules::class)->call('setKind', 'gst')->assertSee('GST rates');

        $page->set('gstJewellery', '5')->set('gstHallmark', '12')->call('saveGst')->assertHasNoErrors();
        $this->assertEquals([5, 12], [(float) TaxSetting::current()->jewellery_percent, (float) TaxSetting::current()->hallmark_percent]);
        $this->assertEquals([5.0, 12.0], app(PricingService::class)->gstRates());

        $page->set('gstJewellery', '101')->call('saveGst')->assertHasErrors('gstJewellery');
        $page->set('gstJewellery', 'abc')->call('saveGst')->assertHasErrors('gstJewellery');
    }

    public function test_the_simulator_shows_the_gst(): void
    {
        Livewire::test(PriceSimulator::class)->set('metal', 'gold')->set('purity', '9K')->set('weight', 10)->assertSee('Price before GST')->assertSee('Total with GST');
    }
}
