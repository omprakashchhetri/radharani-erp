<?php

namespace Tests\Feature\Movement;

use App\Livewire\Admin\CustomerManager;
use App\Livewire\Customers\CustomerForm;
use App\Livewire\Exchange\NewEntry;
use App\Livewire\Orders\NewOrderEntry;
use App\Livewire\Pricing\DailyRateEntry;
use App\Livewire\Sales\NewSale;
use App\Livewire\Stock\ItemForm;
use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Movement\RateLog;
use App\Models\Movement\RawMetalEntry;
use App\Models\Sales\Sale;
use App\Models\User;
use App\Rules\Gstin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 9 Karat in every carat list, and the one shared add-customer form with GSTIN. Needs the MySQL dev database:
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=CaratsAndCustomersTest
 */
class CaratsAndCustomersTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
    }

    private function phone(): string
    {
        return (string) random_int(6000000000, 9999999999);
    }

    // ---------------------------------------------------------------- 9 Karat

    public function test_9k_is_in_every_list_that_reads_the_carats(): void
    {
        $this->assertContains('9K', RateLog::CARATS['gold']);
        $this->assertSame(RateLog::CARATS, ItemForm::PURITIES, 'item purity is built from the one carat list');
        $this->assertSame('9K', ExchangeDeductionPreset::nearestCarat('gold', 38));
        $this->assertSame('9K', RawMetalEntry::caratFor('gold', 37.5));
        $this->assertEquals(9, RawMetalEntry::fineWeight('gold', '9K', 24));
        $this->assertTrue(ExchangeDeductionPreset::where('metal', 'gold')->where('purity', '9K')->exists());
    }

    public function test_the_daily_rates_page_asks_for_a_9k_rate_and_a_rate_can_be_saved(): void
    {
        Livewire::test(DailyRateEntry::class)->assertSee('9K');

        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 2900, 'source' => 'manual', 'updated_by' => $this->user->id]);
        $this->assertEquals(2900, RateLog::latestFor('gold', '9K')->rate);
    }

    // ---------------------------------------------------------------- GSTIN

    public function test_the_gstin_rule_accepts_a_real_number_and_cleans_it_up(): void
    {
        $check = fn ($v) => Validator::make(['g' => $v], ['g' => ['nullable', new Gstin]])->passes();

        $this->assertTrue($check('19ABCDE1234F1Z5'));
        $this->assertTrue($check(' 19abcde1234f1z5 '), 'lower case and spaces are tidied, not rejected');
        $this->assertTrue($check(''));
        $this->assertTrue($check(null));
        $this->assertFalse($check('19ABCDE1234F1Z'));
        $this->assertFalse($check('ABCDE1234F'));
        $this->assertFalse($check('19ABCDE1234F1X5'));
        $this->assertSame('19ABCDE1234F1Z5', Gstin::normalize(' 19abcde 1234f1z5'));
    }

    public function test_a_customer_keeps_its_gstin_upper_case_or_null(): void
    {
        $c = Customer::create(['name' => 'Gstin Customer', 'phone' => $this->phone(), 'gstin' => '19abcde1234f1z5']);
        $this->assertSame('19ABCDE1234F1Z5', $c->fresh()->gstin);

        $c->update(['gstin' => '  ']);
        $this->assertNull($c->fresh()->gstin);
    }

    public function test_the_administration_page_rejects_a_bad_gstin(): void
    {
        Livewire::test(CustomerManager::class)->call('create')->set('name', 'Admin Gstin')->set('phone', $this->phone())->set('gstin', 'NOTAGSTIN')
            ->call('save')->assertHasErrors('gstin');
        $this->assertFalse(Customer::where('name', 'Admin Gstin')->exists());
    }

    // ---------------------------------------------------------------- the shared form

    public function test_the_form_takes_what_was_typed_as_a_phone_or_a_name(): void
    {
        Livewire::test(CustomerForm::class)->call('open', name: '98300 12345')->assertSet('phone', '98300 12345')->assertSet('name', '')
            ->call('open', name: 'Anita Roy')->assertSet('name', 'Anita Roy')->assertSet('phone', '')->assertSet('show', true);
    }

    public function test_the_form_adds_a_customer_with_gstin_and_announces_it(): void
    {
        $phone = $this->phone();

        $c = Livewire::test(CustomerForm::class)->call('open', status: 'order_pending')
            ->set('name', 'Shared Form Customer')->set('phone', "+91 {$phone}")->set('gstin', '19abcde1234f1z5')->set('address', 'Kolkata')
            ->call('save')->assertHasNoErrors();

        $customer = Customer::where('phone', $phone)->firstOrFail();
        $this->assertSame('19ABCDE1234F1Z5', $customer->gstin);
        $this->assertSame('order_pending', $customer->status);
        $c->assertDispatched('customer-saved', id: $customer->id)->assertSet('show', false);
    }

    public function test_the_form_checks_the_phone_and_the_gstin(): void
    {
        Livewire::test(CustomerForm::class)->call('open')->set('name', 'X')->set('phone', '12345')->call('save')->assertHasErrors('phone');
        Livewire::test(CustomerForm::class)->call('open')->set('name', 'X')->set('phone', $this->phone())->set('gstin', '19ABCDE1234F1Z')->call('save')->assertHasErrors('gstin');
    }

    public function test_a_phone_already_in_the_system_picks_that_customer_instead_of_adding_a_second(): void
    {
        $existing = Customer::create(['name' => 'Already Here', 'phone' => $this->phone()]);
        $before = Customer::count();

        Livewire::test(CustomerForm::class)->call('open')->set('name', 'Someone Else')->set('phone', $existing->phone)->call('save')
            ->assertDispatched('customer-saved', id: $existing->id);

        $this->assertSame($before, Customer::count());
    }

    public function test_the_sale_the_order_and_the_exchange_each_pick_the_saved_customer(): void
    {
        $c = Customer::create(['name' => 'Picked By Event', 'phone' => $this->phone()]);

        Livewire::test(NewSale::class)->dispatch('customer-saved', id: $c->id)->assertSet('customerId', $c->id);
        Livewire::test(NewOrderEntry::class)->dispatch('customer-saved', id: $c->id)->assertSet('customerId', $c->id);
        Livewire::test(NewEntry::class)->dispatch('customer-saved', id: $c->id)->assertSet('customerId', $c->id);
    }

    public function test_the_three_screens_carry_the_new_customer_button_and_the_form(): void
    {
        foreach ([NewSale::class, NewOrderEntry::class, NewEntry::class] as $screen) {
            Livewire::test($screen)->assertSee('New customer')->assertSeeHtml('New customer');
        }
    }

    // ---------------------------------------------------------------- the bill

    public function test_the_bill_prints_the_customers_gstin_when_there_is_one(): void
    {
        $with = Customer::create(['name' => 'Bill With Gstin', 'phone' => $this->phone(), 'gstin' => '19ABCDE1234F1Z5']);
        $without = Customer::create(['name' => 'Bill Without', 'phone' => $this->phone()]);
        $make = fn ($customer) => Sale::create(['customer_id' => $customer->id, 'invoice_number' => 'RESV-TEST-' . $customer->id, 'type' => 'sale',
            'total' => 1000, 'confirmed_by_accountant' => false, 'created_by' => $this->user->id]);

        $this->get(route('sales.bill', $make($with)))->assertOk()->assertSee('GSTIN 19ABCDE1234F1Z5');
        $this->get(route('sales.bill', $make($without)))->assertOk()->assertDontSee('GSTIN');
    }
}
