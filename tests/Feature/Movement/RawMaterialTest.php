<?php

namespace Tests\Feature\Movement;

use App\Livewire\Exchange\AccountsValuation;
use App\Livewire\Exchange\RefineryBatchReturn;
use App\Livewire\Exchange\RefineryBatchSend;
use App\Livewire\Movement\KarigarDesk;
use App\Livewire\Stock\RawMaterial;
use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeTransaction;
use App\Models\Exchange\RefineryBatch;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\RawMetalEntry;
use App\Models\Purchase\Vendor;
use App\Models\Stock\ItemCategory;
use App\Models\User;
use App\Services\InsufficientRawMaterial;
use App\Services\RawMaterialLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The raw-material ledger and every workflow that posts to it. Uses 14K gold and 99.9 silver, which the
 * dev data does not touch, so balances can be asserted exactly. Needs the MySQL dev database:
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=RawMaterialTest
 */
class RawMaterialTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private RawMaterialLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.');
        $this->actingAs($this->user);
        $this->ledger = app(RawMaterialLedger::class);
        Storage::fake('public');
    }

    private function shop(string $kind = 'refined', string $metal = 'gold', string $carat = '14K'): float
    {
        return $this->ledger->balance($this->ledger->shop($metal, $carat, $kind));
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg', 200, 200);
    }

    // ---------------------------------------------------------------- the ledger

    public function test_a_transfer_is_two_rows_with_one_id_and_a_holder_can_not_go_negative(): void
    {
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 10, 'adjustment', null, 'test');
        $karigar = RawMaterialLedger::leg('karigar', 999001, 'gold', '14K', 'refined');

        [$out, $in] = $this->ledger->transfer($this->ledger->shop('gold', '14K'), $karigar, 4, 'karigar_advance', 1);

        $this->assertEquals(-4, $out->weight);
        $this->assertEquals(4, $in->weight);
        $this->assertSame($out->transfer_id, $in->transfer_id);
        $this->assertEquals(6, $this->shop());
        $this->assertEquals(4, $this->ledger->balance($karigar));

        $this->expectException(InsufficientRawMaterial::class);
        $this->ledger->release($this->ledger->shop('gold', '14K'), 7, 'karigar_payment', 1);
    }

    public function test_a_refused_move_writes_nothing(): void
    {
        $before = RawMetalEntry::count();
        try {
            $this->ledger->transfer($this->ledger->shop('gold', '14K'), RawMaterialLedger::leg('karigar', 999002, 'gold', '14K', 'refined'), 1, 'karigar_advance', 1);
            $this->fail('Expected the move to be refused.');
        } catch (InsufficientRawMaterial $e) {
            $this->assertStringContainsString('Only 0.000 g of gold 14K', $e->getMessage());
        }
        $this->assertSame($before, RawMetalEntry::count());
    }

    public function test_a_measured_purity_becomes_a_standard_carat_or_keeps_its_own_figure(): void
    {
        $this->assertSame('22K', RawMetalEntry::caratFor('gold', 91.6));
        $this->assertSame('22K', RawMetalEntry::caratFor('gold', 91.4));
        $this->assertSame('24K', RawMetalEntry::caratFor('gold', 99.9));
        $this->assertSame('18K', RawMetalEntry::caratFor('gold', 75));
        $this->assertSame('76.2%', RawMetalEntry::caratFor('gold', 76.2));
        $this->assertSame('92.5', RawMetalEntry::caratFor('silver', 92.4));
        $this->assertSame('99.9', RawMetalEntry::caratFor('silver', 99.95));
        $this->assertSame('64%', RawMetalEntry::caratFor('silver', 64));
        $this->assertSame('-', RawMetalEntry::caratFor('titanium', 99));
        $this->assertSame('-', RawMetalEntry::caratFor('gold', null));
        $this->assertSame('22K', RawMetalEntry::normalizeCarat(' 22k '));
        $this->assertEquals(9.167, RawMetalEntry::fineWeight('gold', '22K', 10));
        $this->assertEquals(7.62, RawMetalEntry::fineWeight('gold', '76.2%', 10));
        $this->assertNull(RawMetalEntry::fineWeight('gold', '-', 10));
    }

    // ---------------------------------------------------------------- exchange and refinery

    public function test_settling_an_exchange_adds_old_metal_to_the_shop_once(): void
    {
        $customer = Customer::create(['name' => 'Raw Material Customer', 'phone' => '9' . random_int(100000000, 999999999)]);
        $tx = ExchangeTransaction::create(['customer_id' => $customer->id, 'metal' => 'gold', 'gross_weight' => 12, 'net_weight' => 11.5,
            'purity_averaged' => 76.2, 'stage' => 'tested', 'created_by' => $this->user->id]);

        Livewire::test(AccountsValuation::class)->set('settleTransactionId', $tx->id)->set('finalValue', 40000)->call('settle');

        $this->assertEquals(11.5, $this->shop('old_gold', 'gold', '76.2%'));
        $this->assertEquals(0, $this->shop('refined', 'gold', '76.2%'));
        $this->assertTrue($this->ledger->hasPosted('exchange', $tx->id));
        $this->assertSame('settled', $tx->fresh()->stage);
    }

    public function test_the_refinery_takes_old_metal_from_stock_and_gives_refined_metal_back_with_the_loss_visible(): void
    {
        $this->ledger->receive($this->ledger->shop('gold', '14K', 'scrap'), 20, 'adjustment', null, 'test scrap');

        Livewire::test(RefineryBatchSend::class)
            ->set('source', 'gold|14K|scrap')->set('weight', 8)->set('photo', $this->photo())->call('submit')->assertHasNoErrors();

        $batch = RefineryBatch::latest('id')->first();
        $this->assertSame('14K', $batch->purity);
        $this->assertEquals(12, $this->shop('scrap'));
        $this->assertEquals(8, $this->ledger->balance(RawMaterialLedger::leg('refinery', $batch->id, 'gold', '14K', 'scrap')));

        Livewire::test(RefineryBatchReturn::class)
            ->set('batchId', $batch->id)->set('refinedWeight', 5.2)->set('refinedPurity', 99.9)->call('submit')->assertHasNoErrors();

        $this->assertEquals(0, $this->ledger->balance(RawMaterialLedger::leg('refinery', $batch->id, 'gold', '14K', 'scrap')));
        $this->assertEqualsWithDelta(5.2, $this->shop('refined', 'gold', '24K') - $this->balanceBefore24K(), 0.0001);
    }

    private function balanceBefore24K(): float
    {
        // 24K refined that was in the shop before this test's return: everything except the refinery_return row.
        return (float) RawMetalEntry::where('holder_type', 'shop')->where('kind', 'refined')->where('metal', 'gold')->where('purity', '24K')
            ->where('source_type', '!=', 'refinery_return')->sum('weight');
    }

    public function test_the_refinery_will_not_send_more_than_the_shop_has(): void
    {
        $this->ledger->receive($this->ledger->shop('silver', '99.9', 'old_gold'), 3, 'adjustment', null, 'test');

        Livewire::test(RefineryBatchSend::class)
            ->set('source', 'silver|99.9|old_gold')->set('weight', 5)->set('photo', $this->photo())->call('submit')->assertHasErrors('weight');

        $this->assertEquals(3, $this->shop('old_gold', 'silver', '99.9'));
    }

    public function test_a_refinery_batch_from_before_the_module_can_still_be_returned(): void
    {
        $batch = RefineryBatch::create(['metal' => 'gold', 'weight' => 4, 'status' => 'sent', 'sent_at' => now(), 'created_by' => $this->user->id]);
        $this->ledger->receive(RawMaterialLedger::leg('refinery', $batch->id, 'gold', null, 'scrap'), 4, 'refinery_out', $batch->id, 'Opening');

        Livewire::test(RefineryBatchReturn::class)->set('batchId', $batch->id)->set('refinedWeight', 3.1)->set('refinedPurity', 99.9)->call('submit')->assertHasNoErrors();

        $this->assertSame('returned', $batch->fresh()->status);
        $this->assertEquals(0, $this->ledger->balance(RawMaterialLedger::leg('refinery', $batch->id, 'gold', null, 'scrap')));
    }

    // ---------------------------------------------------------------- karigar

    private function issueWithAdvance(Vendor $karigar, float $advance): KarigarRawBatch
    {
        Livewire::test(KarigarDesk::class)
            ->set('vendorId', $karigar->id)->set('metal', 'gold')->set('description', 'Raw material test')->set('piecesExpected', 2)
            ->set('estimatedWeight', 10)->set('withAdvance', true)->set('advanceMetalWeight', $advance)->set('advanceMetalPurity', '14K')
            ->set('photo', $this->photo())->call('issue')->assertHasNoErrors();

        return KarigarRawBatch::where('vendor_id', $karigar->id)->latest('id')->firstOrFail();
    }

    public function test_a_metal_advance_moves_metal_from_the_shop_to_the_karigar_and_receipts_use_it_up(): void
    {
        $karigar = Vendor::create(['name' => 'Raw Material Karigar', 'type' => 'karigar']);
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 30, 'adjustment', null, 'test');
        $held = fn ($batch) => $this->ledger->heldForBatch($karigar->id, $batch->id, 'gold', '14K');

        $batch = $this->issueWithAdvance($karigar, 10);
        $this->assertEquals(20, $this->shop());
        $this->assertEquals(10, $held($batch));

        $category = ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => 'Necklace'], ['sort_order' => 1, 'is_active' => true]);
        Livewire::test(KarigarDesk::class)->call('selectBatch', $batch->id)
            ->set('weights', '3.5 4.0')->set('lossWeight', 0.3)->set('receiveCategoryId', $category->id)->set('receivePurity', '14K')
            ->set('photo', $this->photo())->call('receive')->assertHasNoErrors();

        // 3.5 + 4.0 received and 0.3 lost were used out of the 10 g he held.
        $this->assertEquals(2.2, round($held($batch), 3));
        $this->assertEquals(20, $this->shop());
    }

    public function test_receipts_never_use_more_than_the_karigar_still_holds(): void
    {
        $karigar = Vendor::create(['name' => 'Raw Material Karigar', 'type' => 'karigar']);
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 30, 'adjustment', null, 'test');
        $batch = $this->issueWithAdvance($karigar, 3);

        $category = ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => 'Necklace'], ['sort_order' => 1, 'is_active' => true]);
        Livewire::test(KarigarDesk::class)->call('selectBatch', $batch->id)
            ->set('weights', '4 5')->set('receiveCategoryId', $category->id)->set('receivePurity', '14K')
            ->set('photo', $this->photo())->call('receive')->assertHasNoErrors();

        $this->assertEquals(0, $this->ledger->heldForBatch($karigar->id, $batch->id, 'gold', '14K'));
    }

    public function test_a_metal_payment_comes_out_of_the_shop_and_is_refused_when_short(): void
    {
        $karigar = Vendor::create(['name' => 'Raw Material Karigar', 'type' => 'karigar']);
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 5, 'adjustment', null, 'test');

        Livewire::test(KarigarDesk::class)->set('payVendorId', $karigar->id)->set('payKind', 'metal')->set('payMetal', 'gold')
            ->set('payPurity', '14K')->set('payWeight', 2)->call('pay')->assertHasNoErrors();
        $this->assertEquals(3, $this->shop());

        Livewire::test(KarigarDesk::class)->set('payVendorId', $karigar->id)->set('payKind', 'metal')->set('payMetal', 'gold')
            ->set('payPurity', '14K')->set('payWeight', 4)->call('pay')->assertHasErrors('payWeight');
        $this->assertEquals(3, $this->shop());
    }

    public function test_an_advance_can_not_use_old_metal_only_refined(): void
    {
        $karigar = Vendor::create(['name' => 'Raw Material Karigar', 'type' => 'karigar']);
        $this->ledger->receive($this->ledger->shop('gold', '14K', 'old_gold'), 30, 'adjustment', null, 'test');

        Livewire::test(KarigarDesk::class)
            ->set('vendorId', $karigar->id)->set('metal', 'gold')->set('description', 'x')->set('piecesExpected', 1)->set('estimatedWeight', 5)
            ->set('withAdvance', true)->set('advanceMetalWeight', 5)->set('advanceMetalPurity', '14K')->set('photo', $this->photo())
            ->call('issue')->assertHasErrors('advanceMetalWeight');
    }

    // ---------------------------------------------------------------- the page

    public function test_unused_metal_can_come_back_from_a_karigar_and_old_metal_can_be_reclassified(): void
    {
        $karigar = Vendor::create(['name' => 'Raw Material Karigar', 'type' => 'karigar']);
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 30, 'adjustment', null, 'test');
        $batch = $this->issueWithAdvance($karigar, 10);

        $page = Livewire::test(RawMaterial::class);
        $key = "{$karigar->id}|{$batch->id}|gold|14K";
        $page->call('openForm', 'karigar')->set('heldKey', $key)->set('returnWeight', 4)->call('returnFromKarigar')->assertHasNoErrors();
        $this->assertEquals(24, $this->shop());
        $this->assertEquals(6, $this->ledger->heldForBatch($karigar->id, $batch->id, 'gold', '14K'));

        $page->call('openForm', 'karigar')->set('heldKey', $key)->set('returnWeight', 9)->call('returnFromKarigar')->assertHasErrors('returnWeight');

        $this->ledger->receive($this->ledger->shop('gold', '76.2%', 'old_gold'), 10, 'exchange', 999003, 'test');
        $page->call('openForm', 'reclassify')->set('fromKey', 'gold|76.2%|old_gold')->set('reclassWeight', 10)->set('reclassCarat', '14K')
            ->set('reclassNote', 'assayed')->call('reclassify')->assertHasNoErrors();
        $this->assertEquals(0, $this->shop('old_gold', 'gold', '76.2%'));
        $this->assertEquals(34, $this->shop());
    }

    public function test_an_adjustment_needs_a_reason_and_can_not_take_out_more_than_there_is(): void
    {
        $page = Livewire::test(RawMaterial::class)->call('openForm', 'adjust')
            ->set('adjustMetal', 'silver')->set('adjustCarat', '99.9')->set('adjustKind', 'refined')->set('adjustWeight', 5);

        $page->call('adjust')->assertHasErrors('adjustNote');
        $page->set('adjustNote', 'opening balance')->call('adjust')->assertHasNoErrors();
        $this->assertEquals(5, $this->shop('refined', 'silver', '99.9'));

        $page->call('openForm', 'adjust')->set('adjustDirection', 'remove')->set('adjustMetal', 'silver')->set('adjustCarat', '99.9')
            ->set('adjustWeight', 9)->set('adjustNote', 'correction')->call('adjust')->assertHasErrors('adjustWeight');
        $this->assertEquals(5, $this->shop('refined', 'silver', '99.9'));
    }

    public function test_only_managers_can_use_the_actions_on_the_page(): void
    {
        $viewer = User::create(['name' => 'Raw Material Viewer', 'email' => 'rmviewer' . random_int(1000, 9999) . '@example.test', 'password' => bcrypt('x')]);
        $viewer->givePermissionTo('stock.manage');
        $this->actingAs($viewer);

        Livewire::test(RawMaterial::class)->assertOk()->call('openForm', 'adjust')->assertForbidden();
    }

    public function test_the_page_lists_the_entries_and_exports_them(): void
    {
        $this->ledger->receive($this->ledger->shop('gold', '14K'), 7.5, 'adjustment', null, 'visible in the ledger');

        Livewire::test(RawMaterial::class)->assertSee('visible in the ledger')->assertSee('+7.500 g')
            ->set('holder', 'karigar')->assertDontSee('visible in the ledger')
            ->set('holder', 'shop')->call('export')->assertFileDownloaded();
    }
}
