<?php

namespace Tests\Feature\Movement;

use App\Livewire\Movement\VaultCounterMove;
use App\Models\Location;
use App\Models\Movement\Movement;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\User;
use App\Support\StockLookup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class VaultCounterMoveTest extends TestCase
{
    // The migrations use MySQL-only statements, so these run against the MySQL dev database, each test
    // inside a transaction that is rolled back. Run them with:
    //   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=VaultCounterMoveTest
    use DatabaseTransactions;

    private Location $vault;
    private Location $counter;

    /** Ids of the rows a test creates, so assertions ignore whatever the dev database already holds. */
    private array $made = ['box' => [], 'packet' => [], 'item' => []];

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }

        $this->vault = Location::vault() ?? Location::create(['name' => 'Vault', 'type' => 'vault', 'sort_order' => 0, 'is_active' => true]);
        $this->counter = Location::defaultFloor() ?? Location::create(['name' => 'Counter 1', 'type' => 'counter', 'sort_order' => 1, 'is_active' => true]);
        $this->actingAs(User::first() ?? User::factory()->create());
    }

    private function box(string $code): Box
    {
        return tap(Box::create(['code' => $code]), fn ($m) => $this->made['box'][] = $m->id);
    }

    private function packet(string $code, ?Box $box = null): Packet
    {
        return tap(Packet::create(['code' => $code, 'box_id' => $box?->id]), fn ($m) => $this->made['packet'][] = $m->id);
    }

    private function item(string $code, ?Packet $packet = null): Item
    {
        $item = new Item(['internal_code' => $code, 'category' => 'Ring', 'purity' => '22K', 'making_value' => 0, 'weight' => 2, 'status' => 'in_stock', 'packet_id' => $packet?->id]);
        $item->save();
        $this->made['item'][] = $item->id;

        return $item;
    }

    private function movedKeys(): array
    {
        return Movement::orderBy('id')->get()
            ->filter(fn ($m) => in_array($m->trackable_id, $this->made[$m->trackable_type] ?? []))
            ->map(fn ($m) => $m->movement_type . ':' . $m->trackable_type . ':' . $m->trackable_id)->values()->all();
    }

    public function test_a_packet_code_with_spaces_moves_only_that_packet_not_a_box_named_like_one_of_its_words(): void
    {
        $box = $this->box('RING');
        $gents = $this->packet('GENTS RING', $box);
        $this->packet('RING DEFAULT', $box);

        Livewire::test(VaultCounterMove::class)
            ->call('scan', 'GENTS RING')
            ->call('confirmMove');

        $this->assertSame(['vault_out:packet:' . $gents->id], $this->movedKeys());
    }

    public function test_a_packet_whose_code_starts_with_a_box_code_is_still_only_the_packet(): void
    {
        $box = $this->box('RING');
        $packet = $this->packet('RING DEFAULT', $box);

        Livewire::test(VaultCounterMove::class)
            ->call('scan', 'ring default')
            ->call('confirmMove');

        $this->assertSame(['vault_out:packet:' . $packet->id], $this->movedKeys());
    }

    public function test_several_boxes_pasted_together_are_still_split(): void
    {
        $a = $this->box('BOX-A');
        $b = $this->box('BOX-B');

        $component = Livewire::test(VaultCounterMove::class)->call('scan', "BOX-A BOX-B");
        $this->assertCount(2, $component->get('tray'));

        $component->call('confirmMove');
        $moved = $this->movedKeys();
        sort($moved);
        $expected = ['vault_out:box:' . $a->id, 'vault_out:box:' . $b->id];
        sort($expected);
        $this->assertSame($expected, $moved);
    }

    public function test_lines_and_commas_split_codes_that_contain_spaces(): void
    {
        $this->packet('GENTS RING');
        $this->packet('LADIES FINGER RING');

        $component = Livewire::test(VaultCounterMove::class)->call('scan', "GENTS RING\nLADIES FINGER RING");
        $this->assertCount(2, $component->get('tray'));
    }

    public function test_an_unknown_code_that_contains_a_box_code_word_finds_nothing(): void
    {
        $this->box('RING');

        $component = Livewire::test(VaultCounterMove::class)->call('scan', 'FOO RING');

        $this->assertCount(0, $component->get('tray'));
        $this->assertSame('error', $component->get('feedback')['tone']);
    }

    public function test_split_codes_tries_the_whole_input_before_splitting(): void
    {
        $this->box('RING');
        $this->packet('GENTS RING');
        $resolves = fn ($c) => StockLookup::container($c);

        $this->assertSame(['GENTS RING'], StockLookup::splitCodes('GENTS RING', $resolves));
        $this->assertSame(['FOO RING'], StockLookup::splitCodes('FOO RING', $resolves));
        $this->assertSame(['RING', 'GENTS RING'], StockLookup::splitCodes('RING, GENTS RING', $resolves));
        $this->assertSame([], StockLookup::splitCodes('   ', $resolves));
    }

    public function test_a_packet_whose_box_is_already_on_the_counter_is_not_sent_again(): void
    {
        $box = $this->box('BOX-1');
        $packet = $this->packet('PKT-1', $box);
        Movement::create(['trackable_type' => 'box', 'trackable_id' => $box->id, 'movement_type' => 'vault_out',
            'location_id' => $this->counter->id, 'user_id' => auth()->id()]);

        $component = Livewire::test(VaultCounterMove::class)->call('scan', 'PKT-1');

        $this->assertCount(0, $component->get('tray'));
        $this->assertStringContainsString('as part of BOX-1', $component->get('feedback')['message']);
        $this->assertSame(['vault_out:box:' . $box->id], $this->movedKeys());
        $this->assertNotNull($packet);
    }

    public function test_sending_a_box_warns_when_a_packet_of_it_is_already_out_on_its_own(): void
    {
        $box = $this->box('BOX-1');
        $packet = $this->packet('PKT-1', $box);
        Movement::create(['trackable_type' => 'packet', 'trackable_id' => $packet->id, 'movement_type' => 'vault_out',
            'location_id' => $this->counter->id, 'user_id' => auth()->id()]);

        $component = Livewire::test(VaultCounterMove::class)->call('scan', 'BOX-1');

        $this->assertCount(1, $component->get('tray'));
        $this->assertSame('warning', $component->get('feedback')['tone']);
        $this->assertStringContainsString('1 packet or piece in it is already on the counter', $component->get('feedback')['message']);
    }

    public function test_confirming_the_same_tray_twice_writes_the_move_once(): void
    {
        $packet = $this->packet('PKT-1', $this->box('BOX-1'));

        $component = Livewire::test(VaultCounterMove::class)->call('scan', 'PKT-1');
        $tray = $component->get('tray');
        $component->call('confirmMove');
        $component->set('tray', $tray)->call('confirmMove');

        $this->assertSame(['vault_out:packet:' . $packet->id], $this->movedKeys());
    }

    public function test_the_most_recent_vault_move_among_piece_packet_and_box_decides_where_a_piece_is(): void
    {
        $box = $this->box('BOX-1');
        $packet = $this->packet('PKT-1', $box);
        $item = $this->item('AAAAA', $packet);
        $user = auth()->id();
        $move = fn ($type, $id, $kind) => Movement::create(['trackable_type' => $type, 'trackable_id' => $id,
            'movement_type' => $kind, 'location_id' => $kind === 'vault_out' ? $this->counter->id : $this->vault->id, 'user_id' => $user]);

        $this->assertTrue(Location::isInVault($item));

        $move('box', $box->id, 'vault_out');
        $this->assertFalse(Location::isInVault($item));
        $this->assertSame($this->counter->id, Location::itemLocations()[$item->id]);

        $move('box', $box->id, 'vault_in');
        $this->assertTrue(Location::isInVault($item));
        $this->assertSame($this->vault->id, Location::itemLocations()[$item->id]);

        $move('packet', $packet->id, 'vault_out');
        $this->assertFalse(Location::isInVault($item));

        $move('packet', $packet->id, 'vault_in');
        $this->assertTrue(Location::isInVault($item));
    }
}
