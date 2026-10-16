<?php

namespace Tests\Feature\Movement;

use App\Livewire\Sales\NewSale;
use App\Models\Customer\Customer;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Pricing\TaxSetting;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The bill follows the client's Excel sheet, in its order: metal, making (percentage, per piece or per gram),
 * the hallmarking charge for pieces with a HUID only, GST 3% on metal + making and 18% on the hallmarking
 * charge, additional charges added after the GST (not taxed), the automatic round-off to a whole rupee on
 * its own line, and last the discount off that rounded total.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=ExcelPricingTest
 */
class ExcelPricingTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
        $this->pricing = app(PricingService::class);
        $this->pricing->forgetRules();

        TaxSetting::current()->update(['jewellery_percent' => 3, 'hallmark_percent' => 18]);
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
        PricingRule::create(['kind' => 'hallmark', 'scope' => 'all', 'calc' => 'per_piece', 'value' => 100, 'active' => true, 'created_by' => $this->user->id]);
    }

    private function making(string $calc, float $value): void
    {
        PricingRule::create(['kind' => 'making', 'scope' => 'all', 'calc' => $calc, 'value' => $value, 'active' => true, 'created_by' => $this->user->id]);
        $this->pricing->forgetRules();
    }

    private function piece(float $weight, bool $huid = true): Item
    {
        return Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'XL' . random_int(100, 999), 'huid_code' => $huid ? 'H' . random_int(10000, 99999) : null, 'metal' => 'gold', 'category' => 'Ring',
            'purity' => '9K', 'making_value' => 0, 'weight' => $weight, 'status' => 'in_stock']);
    }

    private function bill(Item $item, float $extras = 0, string $type = 'flat', float $value = 0): array
    {
        return $this->pricing->billTotals([$this->pricing->breakdown($item)], $extras, $type, $value, 3, 18);
    }

    public function test_sheet_one_making_as_a_percentage(): void
    {
        $this->making('percentage', 12);
        // 10.333 g x 1000 = 10,333.00; making 12% = 1,239.96; hallmarking 100
        $b = $this->pricing->breakdown($this->piece(10.333));
        $this->assertEquals(10333.00, $b['metal_value']);
        $this->assertEquals(1239.96, $b['making']);
        $this->assertEquals(100, $b['huid_charge']);
        $this->assertEquals(11572.96, $b['taxable']);

        $t = $this->bill($this->piece(10.333), 0, 'flat', 38);
        $this->assertEquals(347.19, $t['gst_jewellery']);   // 3% of 11,572.96
        $this->assertEquals(18, $t['gst_hallmark']);        // 18% of 100
        $this->assertEquals(365.19, $t['gst']);
        $this->assertEqualsWithDelta($t['gst'], $t['cgst'] + $t['sgst'], 0.001, 'CGST and SGST add back to the GST');
        $this->assertEquals(12038.15, $t['gross']);
        $this->assertEquals(-0.15, $t['round_off']);
        $this->assertEquals(12038, $t['rounded_gross']);
        $this->assertEquals(12000, $t['total'], 'the discount comes off the rounded total');
    }

    public function test_sheet_two_making_per_piece(): void
    {
        $this->making('per_piece', 800);
        // 5 g x 1000 = 5,000; making 800; hallmarking 100; GST 3% on 5,800 = 174, 18% on 100 = 18
        $t = $this->bill($this->piece(5), 0, 'flat', 192);

        $this->assertEquals(174, $t['gst_jewellery']);
        $this->assertEquals(18, $t['gst_hallmark']);
        $this->assertEquals(6092, $t['gross']);
        $this->assertEquals(0, $t['round_off']);
        $this->assertEquals(5900, $t['total']);
    }

    public function test_sheet_three_making_per_gram_and_additional_charges_come_after_the_gst(): void
    {
        $this->making('per_gram', 60);
        // 4 g x 1000 = 4,000; making 4 x 60 = 240; hallmarking 100; GST 3% on 4,240 = 127.20, 18% on 100 = 18
        $t = $this->bill($this->piece(4), 50, 'flat', 0);

        $this->assertEquals(127.20, $t['gst_jewellery']);
        $this->assertEquals(4240, $t['jewellery_base'], 'the 50 additional charge is not taxed');
        $this->assertEquals(4535.20, $t['gross']);          // 4,340 + 145.20 + 50
        $this->assertEquals(-0.20, $t['round_off']);
        $this->assertEquals(4535, $t['total']);
    }

    public function test_a_piece_without_a_huid_has_no_hallmarking_charge_or_hallmarking_gst(): void
    {
        $this->making('per_piece', 500);
        $b = $this->pricing->breakdown($this->piece(10, false));

        $this->assertEquals(0, $b['huid_charge']);
        $t = $this->bill($this->piece(10, false));
        $this->assertEquals(0, $t['gst_hallmark']);
        $this->assertEquals(10815, $t['total']);   // 10,500 + 3% = 315
    }

    public function test_a_percentage_discount_and_a_final_price_both_come_off_the_rounded_total(): void
    {
        $this->making('per_piece', 500);
        $piece = $this->piece(10, false);

        $this->assertEquals(10274.25, $this->bill($piece, 0, 'percent', 5)['total']);   // 10,815 less 5% (540.75)
        $this->assertEquals(10000, $this->bill($piece, 0, 'final', 10000)['total']);
        $this->assertEquals(815, $this->bill($piece, 0, 'final', 10000)['adjustment']);
        $this->assertEquals(10815, $this->bill($piece, 0, 'final', 99999)['total'], 'a final price above the bill is no discount');
        $this->assertEquals(0, $this->bill($piece, 0, 'flat', 99999)['total'], 'a discount can not go below nothing');
    }

    public function test_the_sale_keeps_the_round_off_and_the_bill_shows_it(): void
    {
        $this->making('percentage', 12);
        $piece = $this->piece(10.333);
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $piece->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);
        $customer = Customer::create(['name' => 'Sheet Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);

        $c = Livewire::test(NewSale::class)->set('customerId', $customer->id)->call('next')->call('addItem', $piece->id)
            ->call('next')->set('extras', [['name' => 'Packing', 'amount' => '50']])->call('next');
        // 12,038.15 + 50 = 12,088.15 -> 12,088 with a round-off of -0.15
        $c->set('adjustType', 'final')->set('adjustValue', '12000');
        $this->assertEquals(12000, $c->get('total'));
        $this->assertEquals(88, $c->get('adjustment'));
        $this->assertEqualsWithDelta(12000, collect($c->get('payments'))->sum(fn ($p) => (float) $p['amount']), 0.005, 'the payment line follows the adjustment');

        $c->call('next')->call('submit')->assertHasNoErrors();
        $sale = Sale::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(12000, $sale->total);
        $this->assertEquals(-0.15, (float) $sale->round_off);
        $this->assertEquals(88, (float) $sale->discount);
        $this->assertSame('flat', $sale->adjustment_type, 'a final price is kept as the discount it comes to');
        $this->assertEquals(365.19, (float) $sale->gst_amount);
        $this->assertEquals(0, $sale->balance);
        $this->assertEquals(10333 + 1239.96 + 100, (float) $sale->items()->first()->pivot->price_at_sale);

        $this->get(route('sales.bill', $sale))->assertOk()->assertSee('Round off')->assertSee('Packing')->assertSee('CGST 1.5% + SGST 1.5%');
        $this->get(route('sales.invoice', $sale))->assertOk()->assertSee('Round off');
    }
}
