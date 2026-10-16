<?php

namespace Tests\Feature\Movement;

use App\Livewire\Stock\ProductSummary;
use App\Livewire\Stock\UnassignedItems;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A piece with no packet is on hold: not stock, not for sale, not on the website, until it is put in a packet.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=OnHoldTest
 */
class OnHoldTest extends TestCase
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

    private function piece(array $extra = []): Item
    {
        return Item::create($extra + ['internal_code' => 'OH' . random_int(1000, 9999), 'metal' => 'gold', 'category' => 'Ring', 'purity' => '22K', 'weight' => 3, 'making_value' => 0, 'status' => 'in_stock']);
    }

    public function test_a_piece_with_no_packet_is_on_hold_and_a_packet_makes_it_stock(): void
    {
        $piece = $this->piece();
        $this->assertSame('on_hold', $piece->fresh()->status);

        $piece->update(['packet_id' => $this->packetId()]);
        $this->assertSame('in_stock', $piece->fresh()->status);

        $piece->update(['packet_id' => null]);
        $this->assertSame('on_hold', $piece->fresh()->status);
    }

    public function test_other_statuses_are_left_alone(): void
    {
        $this->assertSame('pending_review', $this->piece(['status' => 'pending_review'])->fresh()->status);
        $this->assertSame('dispatched', $this->piece(['status' => 'dispatched'])->fresh()->status);
    }

    public function test_an_on_hold_piece_is_not_on_the_website_or_in_the_product_view(): void
    {
        $held = $this->piece(['show_on_website' => true, 'web_name' => 'Held ring']);
        $stocked = $this->piece(['packet_id' => $this->packetId(), 'show_on_website' => true, 'web_name' => 'Stocked ring']);

        $site = Item::onWebsite()->pluck('id');
        $this->assertFalse($site->contains($held->id));
        $this->assertTrue($site->contains($stocked->id));

        Livewire::test(ProductSummary::class)->assertSee('on hold');
    }

    public function test_the_page_edits_the_selection_and_checks_the_purity_against_the_metal(): void
    {
        $a = $this->piece(['purity' => '']);
        $b = $this->piece(['purity' => '  ']);

        $c = Livewire::test(UnassignedItems::class)->set('selected', [(string) $a->id, (string) $b->id])->call('openEdit');
        $c->set('editMetal', 'gold')->set('editPurity', '99.99')->call('saveEdit')->assertHasErrors('editPurity');
        $this->assertSame('', (string) $a->fresh()->purity);

        $c->set('editPurity', '18K')->call('saveEdit')->assertHasNoErrors();
        $this->assertSame('18K', $a->fresh()->purity);
        $this->assertSame('18K', $b->fresh()->purity);
        $this->assertSame('on_hold', $a->fresh()->status, 'editing does not move it into stock');
    }

    public function test_the_batch_edit_can_set_the_category_from_the_tree(): void
    {
        $a = $this->piece();
        $cat = ItemCategory::firstOrCreate(['metal' => 'gold', 'name' => 'Bangle']);

        Livewire::test(UnassignedItems::class)->set('selected', [(string) $a->id])->call('openEdit')
            ->set('editMetal', 'gold')->set('editCategoryId', $cat->id)->call('saveEdit')->assertHasNoErrors();

        $this->assertSame('Bangle', $a->fresh()->category);
        $this->assertSame($cat->id, $a->fresh()->category_id);
    }

    public function test_an_empty_edit_is_refused(): void
    {
        $a = $this->piece();

        Livewire::test(UnassignedItems::class)->set('selected', [(string) $a->id])->call('openEdit')->call('saveEdit')->assertHasErrors('editMetal');
    }

    public function test_selected_pieces_can_be_deleted_but_a_sold_one_is_kept(): void
    {
        $gone = $this->piece();
        $sold = $this->piece(['status' => 'sold']);

        Livewire::test(UnassignedItems::class)->set('selected', [(string) $gone->id, (string) $sold->id])->call('deleteSelected')->assertSet('selected', []);

        $this->assertNull(Item::find($gone->id));
        $this->assertNotNull(Item::find($sold->id));
    }

    public function test_moving_to_a_packet_makes_the_pieces_stock(): void
    {
        $a = $this->piece();

        Livewire::test(UnassignedItems::class)->set('selected', [(string) $a->id])->set('moveToPacketId', $this->packetId())->call('moveSelected');

        $this->assertSame('in_stock', $a->fresh()->status);
    }

    public function test_the_incomplete_filter_and_flag(): void
    {
        $bare = $this->piece(['purity' => '']);
        $full = $this->piece();

        $this->assertSame(['purity'], $bare->missingDetails());
        $this->assertSame([], $full->missingDetails());

        Livewire::test(UnassignedItems::class)->set('incompleteOnly', true)
            ->assertSee($bare->label)->assertDontSee($full->label)->assertSee('Incomplete: purity');
    }
}
