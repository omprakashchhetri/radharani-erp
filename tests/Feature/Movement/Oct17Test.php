<?php

namespace Tests\Feature\Movement;

use App\Livewire\Admin\CustomerDetail;
use App\Livewire\Exchange\RefineryBatchReturn;
use App\Livewire\Exchange\RefineryBatchSend;
use App\Livewire\Movement\KarigarDesk;
use App\Livewire\Sales\NewSale;
use App\Livewire\Stock\BulkImport;
use App\Livewire\Stock\RawMaterial;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAdvance;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\KarigarReceipt;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Purchase\Vendor;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\User;
use App\Rules\Huid;
use App\Services\AdvanceService;
use App\Services\LedgerService;
use App\Services\PricingService;
use App\Services\RawMaterialLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The 17 October feedback: HUID length, diamond as a metal, import rules, opening balances, refinery from
 * outside, advances with no order, karigar item lines, and ledgers with one event per line.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test tests/Feature/Movement/Oct17Test.php
 */
class Oct17Test extends TestCase
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
        Storage::fake('public');
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg', 200, 200);
    }

    // ---------------------------------------------------------------- HUID

    public function test_a_huid_is_at_most_six_letters_or_digits(): void
    {
        $this->assertTrue(Huid::isValid(null));
        $this->assertTrue(Huid::isValid('ab12c3'));
        $this->assertTrue(Huid::isValid('A1'));
        $this->assertFalse(Huid::isValid('HUID778812'));
        $this->assertFalse(Huid::isValid('AB-123'));
    }

    // ---------------------------------------------------------------- metals

    public function test_diamond_is_a_metal_and_titanium_and_platinum_are_gone(): void
    {
        $this->assertArrayHasKey('diamond', RateLog::CARATS);
        $this->assertArrayNotHasKey('titanium', RateLog::CARATS);
        $this->assertArrayNotHasKey('platinum', RateLog::CARATS);
        $this->assertContains('Default', RateLog::CARATS['silver']);
        $this->assertArrayNotHasKey('diamond', RateLog::rateCarats(), 'a diamond has no daily rate');
        $this->assertArrayNotHasKey('diamond', \App\Livewire\Pricing\DailyRateEntry::class ? RateLog::rateCarats() : []);
    }

    public function test_a_diamond_piece_is_priced_at_its_mrp(): void
    {
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
        app(PricingService::class)->forgetRules();
        $piece = Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'DM' . random_int(100, 999), 'metal' => 'diamond', 'category' => 'Ring',
            'purity' => '18K', 'making_value' => 0, 'weight' => 3.2, 'mrp' => 45000, 'status' => 'in_stock']);

        $b = app(PricingService::class)->breakdown($piece);
        $this->assertEquals(45000, $b['metal_value']);
        $this->assertEquals(45000, $b['total']);
        $this->assertSame('Diamond (MRP)', app(PricingService::class)->breakupLines($b)[0]['label']);
    }

    // ---------------------------------------------------------------- import

    public function test_an_import_lands_unassigned_with_the_batch_category_and_purity_and_flags_a_bad_purity(): void
    {
        $c = Livewire::test(BulkImport::class);
        $c->set('headers', ['Weight', 'Purity'])->set('rows', [['2.5', ''], ['3.1', '22K'], ['4.0', 'Grade 5']])->set('mapping', [0 => 'weight', 1 => 'purity'])
            ->set('defaultMetal', 'gold')->set('defaultCategory', 'Oct17 Batch Ring')->set('defaultPurity', '18K')
            ->set('defaultMakingValue', 0)->call('confirmMapping');

        $rows = $c->get('reviewRows');
        $this->assertSame('18K', $rows[0]['data']['purity'], 'a blank cell takes the batch purity');
        $this->assertSame('22K', $rows[1]['data']['purity'], 'a cell in the sheet wins');
        $this->assertSame('Oct17 Batch Ring', $rows[0]['data']['category']);
        $this->assertNotEmpty($rows[2]['errors'], 'a purity the metal does not have is refused');

        $c->call('confirmImport');
        $created = Item::whereIn('id', $c->get('createdIds'))->get();
        $this->assertCount(2, $created);
        $this->assertTrue($created->every(fn ($i) => $i->packet_id === null && $i->status === 'on_hold'), 'every import is unassigned');
    }

    // ---------------------------------------------------------------- raw material

    public function test_an_opening_balance_adds_to_the_shop_with_who_did_it(): void
    {
        $ledger = app(RawMaterialLedger::class);
        $before = $ledger->balance($ledger->shop('gold', '9K'));

        Livewire::test(RawMaterial::class)->call('openForm', 'opening')
            ->set('adjustMetal', 'gold')->set('adjustCarat', '9K')->set('adjustKind', 'refined')->set('adjustWeight', 12.5)->set('adjustNote', 'Opening stock')
            ->call('adjust')->assertHasNoErrors();

        $this->assertEquals($before + 12.5, $ledger->balance($ledger->shop('gold', '9K')));
        $row = \App\Models\Movement\RawMetalEntry::where('source_type', 'opening')->latest('id')->firstOrFail();
        $this->assertSame($this->user->id, (int) $row->user_id);
    }

    public function test_refinery_can_take_metal_from_outside_and_extra_metal_comes_back_into_stock(): void
    {
        $ledger = app(RawMaterialLedger::class);
        $shopBefore = $ledger->balance($ledger->shop('gold', '24K'));

        Livewire::test(RefineryBatchSend::class)->set('origin', 'outside')
            ->set('outsideMetal', 'gold')->set('outsideCarat', '14K')->set('outsideKind', 'scrap')->set('outsideNote', 'A customer brought it')
            ->set('weight', 6)->set('photo', $this->photo())->call('submit')->assertHasNoErrors();

        $batch = RefineryBatch::latest('id')->firstOrFail();
        $this->assertSame('outside', $batch->origin);
        $this->assertEquals(6, $ledger->balance(RawMaterialLedger::leg('refinery', $batch->id, 'gold', '14K', 'scrap')));

        Livewire::test(RefineryBatchReturn::class)->set('batchId', $batch->id)->set('refinedWeight', 3)->set('refinedPurity', 99.9)
            ->set('extraWeight', 1.25)->set('extraNote', 'extra gold')->call('submit')->assertHasNoErrors();

        $this->assertEquals(1.25, (float) $batch->fresh()->extra_weight);
        $this->assertEqualsWithDelta($shopBefore + 3 + 1.25, $ledger->balance($ledger->shop('gold', '24K')), 0.0001);
    }

    // ---------------------------------------------------------------- advance

    public function test_an_advance_is_received_without_an_order_and_spent_on_a_sale_once(): void
    {
        $customer = Customer::create(['name' => 'Halkhata Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        Livewire::test(CustomerDetail::class, ['customer' => $customer])->call('openAdvance')
            ->set('advanceAmount', 5000)->set('advanceMode', 'cash')->set('advanceOccasion', 'Halkhata')->call('receiveAdvance')->assertHasNoErrors();

        $service = app(AdvanceService::class);
        $this->assertEquals(5000, $service->balance($customer->id));

        $sale = Sale::create(['customer_id' => $customer->id, 'invoice_number' => 'RESV-T17-' . $customer->id, 'type' => 'sale', 'total' => 8000, 'created_by' => $this->user->id]);
        $service->useOnSale($customer->id, $sale->id, 3000);
        $this->assertEquals(2000, $service->balance($customer->id));

        $this->expectException(\App\Services\InsufficientAdvance::class);
        $service->useOnSale($customer->id, $sale->id, 2500);
    }

    public function test_the_advance_can_be_used_as_a_payment_line_on_a_new_sale(): void
    {
        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
        app(PricingService::class)->forgetRules();
        $customer = Customer::create(['name' => 'Advance Buyer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        app(AdvanceService::class)->receive($customer->id, 4000, 'upi', 'Halkhata');
        $piece = Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'AD' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Ring', 'purity' => '9K', 'making_value' => 0, 'weight' => 5, 'status' => 'in_stock']);
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $piece->id, 'movement_type' => 'vault_out', 'user_id' => $this->user->id]);

        $c = Livewire::test(NewSale::class)->set('customerId', $customer->id)->call('next')->call('addItem', $piece->id)->call('next')->call('next');
        $c->call('useAdvance');
        $this->assertSame('advance', $c->get('payments')[0]['mode']);
        $this->assertEquals(4000, (float) $c->get('payments')[0]['amount']);

        $c->call('next')->call('submit')->assertHasNoErrors();
        $this->assertEquals(0, app(AdvanceService::class)->balance($customer->id));
        $sale = Sale::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(4000, (float) $sale->payments()->where('mode', 'advance')->sum('amount'));
    }

    // ---------------------------------------------------------------- karigar lines and ledgers

    public function test_a_batch_can_list_its_items_line_by_line_and_the_totals_follow(): void
    {
        $karigar = Vendor::create(['name' => 'Lines Karigar', 'type' => 'karigar']);
        Livewire::test(KarigarDesk::class)
            ->set('vendorId', $karigar->id)->set('metal', 'gold')->set('description', 'Wedding set')
            ->call('addLine')->set('lines.0.description', 'Necklace')->set('lines.0.pieces', 1)->set('lines.0.weight', '12.5')
            ->call('addLine')->set('lines.1.description', 'Earrings')->set('lines.1.pieces', 2)->set('lines.1.weight', '6.25')
            ->set('photo', $this->photo())->call('issue')->assertHasNoErrors();

        $batch = KarigarRawBatch::where('vendor_id', $karigar->id)->firstOrFail();
        $this->assertSame(3, (int) $batch->pieces_expected);
        $this->assertEquals(18.75, (float) $batch->weight_out);
        $this->assertSame(['Necklace', 'Earrings'], $batch->lines()->orderBy('id')->pluck('description')->all());
    }

    public function test_the_karigar_ledger_has_one_line_for_the_issue_and_one_for_each_receipt(): void
    {
        $karigar = Vendor::create(['name' => 'Ledger Karigar', 'type' => 'karigar']);
        $batch = KarigarRawBatch::create(['vendor_id' => $karigar->id, 'weight_out' => 10, 'metal' => 'gold', 'description' => 'x', 'pieces_expected' => 4, 'status' => 'dispatched', 'user_id' => $this->user->id]);
        KarigarReceipt::create(['batch_id' => $batch->id, 'pieces' => 1, 'weight_received' => 2, 'weight_loss' => 0.1, 'disposition' => 'stock', 'user_id' => $this->user->id]);
        KarigarReceipt::create(['batch_id' => $batch->id, 'pieces' => 3, 'weight_received' => 6, 'weight_loss' => 0.2, 'disposition' => 'stock', 'user_id' => $this->user->id]);

        $ledger = app(LedgerService::class)->forParty($karigar);
        $this->assertCount(3, $ledger['rows']);
        [$issue, $first, $second] = $ledger['rows']->all();
        $this->assertSame([4, null], [$issue['cells'][0], $issue['cells'][1]], 'pieces out on the issue line only');
        $this->assertSame([null, 1], [$first['cells'][0], $first['cells'][1]], 'pieces in on a receipt line only');
        $this->assertSame([null, 3], [$second['cells'][0], $second['cells'][1]]);
        $this->assertSame([4, 4], [$ledger['totals'][0], $ledger['totals'][1]]);
    }

    public function test_the_exchange_confirmed_order_status_reads_assigned(): void
    {
        $this->assertStringContainsString("'confirmed' => 'Assigned'", file_get_contents(app_path('Livewire/Orders/OrderDetail.php')));
    }

    public function test_the_pages_that_changed_render(): void
    {
        Livewire::test(BulkImport::class)->set('headers', ['Weight'])->set('rows', [['1']])->set('mapping', [0 => 'weight'])->set('step', 2)
            ->assertSee('Every import lands as unassigned items');
        Livewire::test(\App\Livewire\Orders\NewOrderEntry::class)->set('step', 2)->set('sourcing', 'stock')->assertSee('Add item');
        Livewire::test(RefineryBatchSend::class)->assertSee('From outside');
        Livewire::test(RefineryBatchReturn::class)->assertSee('Extra metal received');
        Livewire::test(RawMaterial::class)->assertSee('Opening balance');
        Livewire::test(KarigarDesk::class)->assertSee('Add item');
    }

    public function test_the_exchange_summary_offers_to_proceed_with_a_sale(): void
    {
        $customer = Customer::create(['name' => 'Proceed Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $c = Livewire::test(\App\Livewire\Exchange\NewEntry::class)->set('customerId', $customer->id)->set('metal', 'gold')->set('grossWeight', '3.5')->call('next')
            ->set('netWeight', '3.41')->call('next')->set('purityTest1', '60')->set('purityTest2', '60')->call('next')->call('next');
        $c->assertSee('Proceed with Sale');
        $tx = \App\Models\Exchange\ExchangeTransaction::where('customer_id', $customer->id)->firstOrFail();
        $this->assertLessThan(2.1, $tx->payablePureWeight(), '3.41 g at 60% is about 2 g of pure metal, not 3.41 g');

        Livewire::withQueryParams(['exchange' => $tx->id])->test(NewSale::class)->assertSet('customerId', $customer->id)->assertSet('step', 2);
    }
}
