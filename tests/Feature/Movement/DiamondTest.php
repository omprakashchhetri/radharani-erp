<?php

namespace Tests\Feature\Movement;

use App\Livewire\Stock\ItemForm;
use App\Livewire\Stock\ItemDetail;
use App\Models\Customer\Customer;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Diamond pieces: the diamond weight, cut and summary number, kept on the piece and shown where it is read.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=DiamondTest
 */
class DiamondTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->actingAs($this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.'));
    }

    private function category(string $name): ItemCategory
    {
        return ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => $name], ['sort_order' => 99, 'is_active' => true]);
    }

    private function form(ItemCategory $category)
    {
        return Livewire::test(ItemForm::class)->call('open')
            ->set('metal', 'gold')->set('categoryId', $category->id)->set('purity', '18K')->set('weight', 6)->set('net_weight', 5)
            ->set('making_type', 'flat_per_piece')->set('making_value', 0)->set('stone_value', 20000);
    }

    public function test_diamond_is_a_category_under_gold(): void
    {
        $category = ItemCategory::where('metal', 'gold')->where('name', 'Diamond')->first();

        $this->assertNotNull($category);
        $this->assertTrue($category->isDiamond());
        $this->assertFalse($this->category('Ring')->isDiamond());
        $this->assertTrue(ItemCategory::nameIsDiamond('Diamond rings'));
    }

    public function test_the_form_asks_for_the_diamond_fields_only_for_diamond_pieces(): void
    {
        $this->form($this->category('Ring'))->assertDontSee('Summary number')->assertDontSee('Diamond weight');
        $this->form($this->category('Diamond'))->assertSee('Summary number')->assertSee('Diamond weight')->assertSee('Gold weight')->assertSee('Diamond value');
    }

    public function test_a_diamond_piece_keeps_its_carat_cut_and_summary_number(): void
    {
        $this->form($this->category('Diamond'))->set('diamond_carat', '0.75')->set('diamond_cut', 'Round brilliant')->set('diamond_summary_no', 'SUM-2041')
            ->call('save')->assertHasNoErrors();

        $item = Item::latest('id')->firstOrFail();
        $this->assertEquals(0.75, $item->diamond_carat);
        $this->assertSame('Round brilliant', $item->diamond_cut);
        $this->assertSame('SUM-2041', $item->diamond_summary_no);
        $this->assertEquals(5, $item->net_weight, 'the gold weight is the net weight');
        $this->assertEquals(20000, $item->stone_value, 'the diamond value is the stone value');
    }

    public function test_moving_a_piece_out_of_diamond_clears_the_diamond_fields(): void
    {
        $this->form($this->category('Diamond'))->set('diamond_carat', '0.5')->set('diamond_summary_no', 'X1')->call('save')->assertHasNoErrors();
        $item = Item::latest('id')->firstOrFail();

        Livewire::test(ItemForm::class)->call('open', id: $item->id)->set('categoryId', $this->category('Ring')->id)->call('save')->assertHasNoErrors();

        $item->refresh();
        $this->assertNull($item->diamond_carat);
        $this->assertNull($item->diamond_summary_no);
    }

    public function test_the_diamond_fields_are_checked(): void
    {
        $this->form($this->category('Diamond'))->set('diamond_carat', '-1')->call('save')->assertHasErrors('diamond_carat');
        $this->form($this->category('Diamond'))->set('diamond_carat', 'abc')->call('save')->assertHasErrors('diamond_carat');
    }

    public function test_the_price_breakup_names_the_diamond(): void
    {
        $item = Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'DM' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Diamond', 'purity' => '18K', 'making_value' => 0, 'weight' => 6, 'net_weight' => 5,
            'stone_value' => 20000, 'diamond_carat' => 0.75, 'diamond_cut' => 'Princess', 'status' => 'in_stock']);

        $lines = app(PricingService::class)->breakupLines(app(PricingService::class)->breakdown($item));
        $diamond = collect($lines)->firstWhere('label', 'Diamond');

        $this->assertNotNull($diamond);
        $this->assertSame('0.75 ct · Princess', $diamond['note']);
        $this->assertEquals(20000, $diamond['amount']);
        $this->assertNull(collect($lines)->firstWhere('label', 'Stones'));
    }

    public function test_the_item_page_and_the_bill_show_the_diamond_details(): void
    {
        $item = Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'DM' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Diamond', 'purity' => '18K', 'making_value' => 0, 'weight' => 6,
            'diamond_carat' => 0.75, 'diamond_cut' => 'Princess', 'diamond_summary_no' => 'SUM-77', 'status' => 'in_stock']);

        Livewire::test(ItemDetail::class, ['item' => $item])->assertSee('Diamond weight')->assertSee('0.75 ct')->assertSee('Princess')->assertSee('SUM-77');

        $customer = Customer::create(['name' => 'Diamond Buyer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $sale = Sale::create(['customer_id' => $customer->id, 'invoice_number' => 'RESV-DM-' . $customer->id, 'type' => 'sale', 'total' => 1, 'confirmed_by_accountant' => false, 'created_by' => $this->user->id]);
        $sale->items()->attach($item->id, ['price_at_sale' => 1]);

        $this->get(route('sales.bill', $sale))->assertOk()->assertSee('0.75 ct diamond, Princess')->assertSee('Summary no. SUM-77');
    }
}
