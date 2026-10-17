<?php

namespace Tests\Feature\Movement;

use App\Livewire\Exchange\NewEntry;
use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Movement\RateLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The exchange summary shows the pure metal and an indicative value next to the weight after deduction.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=ExchangeSummaryTest
 */
class ExchangeSummaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->actingAs(User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.'));
    }

    public function test_the_summary_shows_pure_metal_after_the_deduction_and_an_indicative_value(): void
    {
        RateLog::create(['metal' => 'gold', 'purity' => '22K', 'rate' => 6000, 'source' => 'manual', 'updated_by' => auth()->id()]);
        $percent = ExchangeDeductionPreset::percentFor('gold', 91.6);
        $customer = Customer::create(['name' => 'Exchange Summary', 'phone' => (string) random_int(6000000000, 9999999999)]);

        $c = Livewire::test(NewEntry::class)->set('customerId', $customer->id)->set('metal', 'gold')->set('grossWeight', '10.500')->call('next')
            ->set('netWeight', '10.000')->call('next')
            ->set('purityTest1', '91.5')->set('purityTest2', '91.7')->call('next');

        $tx = ExchangeTransaction::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(91.6, (float) $tx->purity_averaged);
        $this->assertEquals(round(10 * (1 - $percent / 100), 3), (float) $tx->deductable_weight);
        $this->assertEquals(9.16, $tx->pureWeight());
        $this->assertEquals(round(9.16 * (1 - $percent / 100), 3), $tx->payablePureWeight());
        $this->assertEquals(round((float) $tx->deductable_weight * 6000, 2), $tx->indicativeValue());

        $c->assertSee('Net payable weight (pure gold)')->assertSee('Alloy weight after deduction')->assertSee('Indicative value');
        $this->assertStringContainsString('Net payable weight (pure gold): ' . $tx->payablePureWeight(), $c->get('summaryText'));
        $this->assertLessThan((float) $tx->net_weight, $tx->payablePureWeight(), 'purity brings the payable weight below the net weight');
    }

    public function test_there_is_no_indicative_value_without_a_rate_for_the_carat(): void
    {
        $customer = Customer::create(['name' => 'No Rate', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $tx = ExchangeTransaction::create(['customer_id' => $customer->id, 'metal' => 'gold', 'gross_weight' => 10, 'net_weight' => 9.5, 'purity_averaged' => 76.2, 'preset_deduction_percent' => 2, 'deductable_weight' => 9.31, 'stage' => 'tested', 'created_by' => auth()->id()]);

        $this->assertNull($tx->indicativeValue());
        $this->assertNotNull($tx->pureWeight());
    }
}
