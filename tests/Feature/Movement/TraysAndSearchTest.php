<?php

namespace Tests\Feature\Movement;

use App\Livewire\Admin\LocationManager;
use App\Livewire\Dashboard;
use App\Livewire\Movement\VaultCounterMove;
use App\Livewire\Reports\LocationDetail;
use App\Livewire\Reports\LocationReport;
use App\Models\Location;
use App\Models\Movement\Movement;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Searching as well as scanning on Vault to Counter, and trays inside the vault.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=TraysAndSearchTest
 */
class TraysAndSearchTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Location $vault;
    private Location $tray;
    private Location $counter;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
        $this->actingAs($this->user = User::role('owner')->first() ?? $this->markTestSkipped('Needs the seeded owner user.'));

        $this->vault = Location::vault() ?? Location::create(['name' => 'Vault', 'type' => 'vault', 'sort_order' => 0, 'is_active' => true]);
        $this->counter = Location::defaultFloor() ?? Location::create(['name' => 'Counter 1', 'type' => 'counter', 'sort_order' => 1, 'is_active' => true]);
        $this->tray = Location::create(['name' => 'Test Tray A', 'type' => 'tray', 'parent_id' => $this->vault->id, 'sort_order' => 90, 'is_active' => true]);
    }

    private function piece(string $code, string $category = 'Zebra ring'): Item
    {
        return Item::create(['internal_code' => $code, 'metal' => 'gold', 'category' => $category, 'purity' => '22K', 'making_value' => 0, 'weight' => 3, 'status' => 'in_stock']);
    }

    private function movedTo(Item $item, string $type, ?int $location = null): Movement
    {
        return Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => $type, 'location_id' => $location, 'user_id' => $this->user->id]);
    }

    // ---------------------------------------------------------------- search

    public function test_typing_finds_pieces_packets_and_boxes_and_picking_one_adds_it_like_a_scan(): void
    {
        $item = $this->piece('ZB901');
        $box = Box::create(['code' => 'ZEBRA BOX']);
        $packet = Packet::create(['code' => 'ZEBRA PKT', 'box_id' => $box->id]);

        $c = Livewire::test(VaultCounterMove::class)->set('find', 'zebra');
        $found = collect($c->viewData('found'));

        $this->assertEqualsCanonicalizing(['box:ZEBRA BOX', 'packet:ZEBRA PKT', 'item:ZB901'], $found->map(fn ($f) => $f['type'] . ':' . $f['label'])->all());

        $c->call('addFromSearch', 'packet', $packet->id)->assertSet('find', '');
        $tray = $c->get('tray');
        $this->assertSame([['packet', $packet->id]], array_map(fn ($t) => [$t['type'], $t['id']], $tray), 'only the packet is added, never its box');
        $this->assertNotNull($item);
    }

    public function test_search_needs_two_letters_skips_sold_pieces_and_says_when_nothing_matches(): void
    {
        $sold = $this->piece('SD901', 'Zebra bangle');
        $sold->update(['status' => 'sold']);

        $c = Livewire::test(VaultCounterMove::class);
        $this->assertSame([], $c->set('find', 'z')->viewData('found'));
        $this->assertSame([], $c->set('find', 'zebra bangle')->viewData('found'), 'a sold piece is not offered');
        $c->set('find', 'nothing like this at all')->assertSee('Nothing matches');
    }

    public function test_a_piece_picked_from_the_list_goes_through_the_same_checks_as_a_scan(): void
    {
        $out = $this->piece('ZB902');
        $out->update(['status' => 'dispatched']);

        $c = Livewire::test(VaultCounterMove::class)->call('addFromSearch', 'item', $out->id);

        $this->assertSame([], $c->get('tray'));
        $this->assertSame('error', $c->get('feedback')['tone']);
    }

    public function test_scanning_clears_what_was_typed(): void
    {
        $this->piece('ZB903');

        Livewire::test(VaultCounterMove::class)->set('find', 'ZB90')->call('scan', 'ZB903')->assertSet('find', '');
    }

    // ---------------------------------------------------------------- trays

    public function test_the_owner_adds_a_tray_and_it_sits_in_the_vault(): void
    {
        Livewire::test(LocationManager::class)->call('create')->set('name', 'Test Tray B')->set('type', 'tray')->call('save')->assertHasNoErrors();

        $tray = Location::where('name', 'Test Tray B')->firstOrFail();
        $this->assertSame($this->vault->id, $tray->parent_id);
        $this->assertTrue($tray->isTray());
        $this->assertSame([$this->tray->id, $tray->id], $this->vault->trays()->pluck('id')->sort()->values()->all());
    }

    public function test_a_place_with_trays_in_it_must_stay_the_vault(): void
    {
        Livewire::test(LocationManager::class)->call('edit', $this->vault->id)->set('type', 'counter')->call('save')->assertHasErrors('type');
        $this->assertSame('vault', $this->vault->fresh()->type);
    }

    public function test_trays_are_not_places_to_take_pieces_out_to(): void
    {
        $this->assertNotContains($this->tray->id, Location::floor()->pluck('id')->all());
        $this->assertNotSame($this->tray->id, Location::defaultFloor()?->id);

        $names = Livewire::test(VaultCounterMove::class)->viewData('locations')->pluck('name');
        $this->assertNotContains('Test Tray A', $names);
    }

    public function test_pieces_are_put_on_a_tray_on_the_way_back_to_the_vault(): void
    {
        $item = $this->piece('TR901');

        $c = Livewire::test(VaultCounterMove::class)->call('setDirection', 'to_vault')->assertSee('Put on a tray')
            ->set('vaultTrayId', $this->tray->id)->call('scan', 'TR901');
        $this->assertNull($c->get('tray')[0]['warning'], 'a piece that never left can go on a tray without a warning');
        $c->call('confirmMove');

        $move = Movement::where('trackable_type', 'item')->where('trackable_id', $item->id)->latest('id')->firstOrFail();
        $this->assertSame('vault_in', $move->movement_type);
        $this->assertSame($this->tray->id, $move->location_id);
        $this->assertTrue(Location::isInVault($item), 'a piece on a tray is still in the vault');
        $this->assertSame($this->tray->id, Location::itemLocations()[$item->id]);
    }

    public function test_back_from_the_counter_without_a_tray_is_just_the_vault(): void
    {
        $item = $this->piece('TR902');
        $this->movedTo($item, 'vault_out', $this->counter->id);

        Livewire::test(VaultCounterMove::class)->call('setDirection', 'to_vault')->call('scan', 'TR902')->call('confirmMove');

        $this->assertSame($this->vault->id, Location::itemLocations()[$item->id]);
    }

    public function test_a_piece_already_on_the_tray_is_not_moved_there_again_and_can_change_tray(): void
    {
        $item = $this->piece('TR903');
        $other = Location::create(['name' => 'Test Tray C', 'type' => 'tray', 'parent_id' => $this->vault->id, 'sort_order' => 91, 'is_active' => true]);
        $this->movedTo($item, 'vault_in', $this->tray->id);

        $same = Livewire::test(VaultCounterMove::class)->call('setDirection', 'to_vault')->set('vaultTrayId', $this->tray->id)->call('scan', 'TR903');
        $this->assertSame([], $same->get('tray'));
        $this->assertStringContainsString('Already on Test Tray A', $same->get('feedback')['message']);

        Livewire::test(VaultCounterMove::class)->call('setDirection', 'to_vault')->set('vaultTrayId', $other->id)->call('scan', 'TR903')->call('confirmMove');
        $this->assertSame($other->id, Location::itemLocations()[$item->id]);
    }

    public function test_confirming_the_same_tray_twice_writes_it_once(): void
    {
        $item = $this->piece('TR904');

        $c = Livewire::test(VaultCounterMove::class)->call('setDirection', 'to_vault')->set('vaultTrayId', $this->tray->id)->call('scan', 'TR904');
        $tray = $c->get('tray');
        $c->call('confirmMove');
        $c->set('tray', $tray)->call('confirmMove');

        $this->assertSame(1, Movement::where('trackable_type', 'item')->where('trackable_id', $item->id)->where('movement_type', 'vault_in')->count());
    }

    public function test_a_piece_on_a_tray_can_go_out_to_the_counter_and_comes_back_to_the_vault(): void
    {
        $item = $this->piece('TR905');
        $this->movedTo($item, 'vault_in', $this->tray->id);

        Livewire::test(VaultCounterMove::class)->call('scan', 'TR905')->call('confirmMove');
        $this->assertSame($this->counter->id, Location::itemLocations()[$item->id]);
        $this->assertFalse(Location::isInVault($item));
    }

    public function test_the_vault_report_includes_its_trays_and_lists_them(): void
    {
        $onTray = $this->piece('TR906');
        $this->movedTo($onTray, 'vault_in', $this->tray->id);
        $loose = $this->piece('TR907');

        Livewire::test(LocationReport::class)->assertSee('Test Tray A')->assertSee('including');

        Livewire::test(LocationDetail::class, ['location' => $this->vault])->assertSee('Trays')->assertSee('Test Tray A')->assertSee('TR907')->assertDontSee('TR906');
        Livewire::test(LocationDetail::class, ['location' => $this->tray])->assertSee('TR906')->assertDontSee('TR907');
        $this->assertNotNull($loose);
    }

    public function test_the_dashboard_does_not_count_a_tray_as_the_counter(): void
    {
        $item = $this->piece('TR908');
        $this->movedTo($item, 'vault_in', $this->tray->id);

        Livewire::test(Dashboard::class)->assertOk()->assertDontSee('Test Tray A');
    }
}
