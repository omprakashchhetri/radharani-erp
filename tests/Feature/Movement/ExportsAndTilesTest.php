<?php

namespace Tests\Feature\Movement;

use App\Livewire\Reports\DailyLogbook;
use App\Livewire\Stock\HuidExchange;
use App\Livewire\Stock\ItemManager;
use App\Livewire\Stock\UnassignedItems;
use App\Models\Customer\Customer;
use App\Models\Movement\Movement;
use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Stock\EntryBatch;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Batch-wise CSV exports, the daybook CSV, the Inventory link tiles and the sidebar that keeps its place.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=ExportsAndTilesTest
 */
class ExportsAndTilesTest extends TestCase
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

    private function piece(string $code, ?EntryBatch $batch = null, array $extra = []): Item
    {
        return Item::create($extra + ['internal_code' => $code, 'metal' => 'gold', 'category' => 'Exportable', 'purity' => '22K', 'making_value' => 0, 'weight' => 2.5,
            'status' => 'in_stock', 'entry_batch_id' => $batch?->id]);
    }

    private function batch(): EntryBatch
    {
        return EntryBatch::create(['kind' => 'import', 'user_id' => $this->user->id]);
    }

    /** What a download response would send, read back as CSV rows. */
    private function csv(StreamedResponse $response): array
    {
        ob_start();
        $response->sendContent();
        $out = ob_get_clean();

        return array_map('str_getcsv', array_filter(preg_split('/\R/', ltrim($out, "\xEF\xBB\xBF"))));
    }

    // ---------------------------------------------------------------- batch exports

    public function test_one_batch_of_unassigned_items_exports_as_a_csv_with_only_its_own_pieces(): void
    {
        $batch = $this->batch();
        $this->piece('EXP01', $batch);
        $this->piece('EXP02', $batch);
        $this->piece('EXP03', $this->batch());

        $component = Livewire::test(UnassignedItems::class);
        $key = 'b' . $batch->id;
        $component->call('exportBatch', $key)->assertFileDownloaded();

        $rows = $this->csv($component->instance()->exportBatch($key));
        $this->assertSame('Internal code', $rows[0][0]);
        $this->assertEqualsCanonicalizing(['EXP01', 'EXP02'], array_column(array_slice($rows, 1), 0));
        $this->assertSame('Exportable', $rows[1][2]);
        $this->assertSame('2.5', $rows[1][5]);
    }

    public function test_exporting_a_batch_that_is_not_there_is_refused(): void
    {
        Livewire::test(UnassignedItems::class)->call('exportBatch', 'b999999999')->assertStatus(404);
    }

    public function test_exporting_everything_says_which_batch_each_piece_is_from(): void
    {
        $this->piece('EXP10', $this->batch());
        $this->piece('EXP11');

        $rows = $this->csv(Livewire::test(UnassignedItems::class)->set('search', 'EXP1')->instance()->exportAll());

        $this->assertSame('Batch', $rows[0][0]);
        $this->assertEqualsCanonicalizing(['EXP10', 'EXP11'], array_column(array_slice($rows, 1), 1));
        $this->assertStringStartsWith('Import', collect(array_slice($rows, 1))->firstWhere(1, 'EXP10')[0]);
        $this->assertStringStartsWith('Entered', collect(array_slice($rows, 1))->firstWhere(1, 'EXP11')[0]);
    }

    public function test_the_huid_page_exports_one_batch_as_excel_or_csv(): void
    {
        $batch = $this->batch();
        $this->piece('HX01', $batch);
        $this->piece('HX02', $batch);
        $this->piece('HX03', $this->batch());

        $page = Livewire::test(HuidExchange::class)->set('scope', 'all')->set('batch', 'b' . $batch->id);
        $this->assertSame(2, $page->viewData('inScope'));
        $this->assertContains('b' . $batch->id, array_column($page->viewData('batchOptions'), 'key'));

        $rows = $this->csv($page->instance()->exportFullCsv());
        $this->assertSame(['Internal code', 'HUID', 'Category'], array_slice($rows[0], 0, 3));
        $this->assertEqualsCanonicalizing(['HX01', 'HX02'], array_column(array_slice($rows, 1), 0));

        $page->call('exportFull')->assertFileDownloaded();
        $this->assertGreaterThan(2, Livewire::test(HuidExchange::class)->set('scope', 'all')->set('batch', '')->viewData('inScope'), 'with no batch chosen it is every piece');
    }

    public function test_pieces_entered_one_by_one_can_be_exported_by_the_minute_they_were_entered(): void
    {
        Carbon::setTestNow('2026-03-02 11:15:30');
        $a = $this->piece('HM01');
        $b = $this->piece('HM02');
        Carbon::setTestNow('2026-03-02 11:40:00');
        $this->piece('HM03');
        Carbon::setTestNow();

        $page = Livewire::test(HuidExchange::class)->set('scope', 'all')->set('batch', 'm202603021115');
        $rows = $this->csv($page->instance()->exportFullCsv());

        $this->assertEqualsCanonicalizing(['HM01', 'HM02'], array_column(array_slice($rows, 1), 0));
        $this->assertNotNull($a->id + $b->id);
    }

    // ---------------------------------------------------------------- the daybook

    private function dayWithASale(): array
    {
        $customer = Customer::create(['name' => 'Daybook Customer', 'phone' => (string) random_int(6000000000, 9999999999)]);
        $sale = Sale::create(['customer_id' => $customer->id, 'invoice_number' => 'RESV-DB-' . $customer->id, 'type' => 'sale', 'total' => 5000, 'confirmed_by_accountant' => false, 'created_by' => $this->user->id]);
        SalePayment::create(['sale_id' => $sale->id, 'mode' => 'cash', 'amount' => 3000, 'user_id' => $this->user->id]);
        SalePayment::create(['sale_id' => $sale->id, 'mode' => 'upi', 'amount' => 500, 'user_id' => $this->user->id]);
        $item = $this->piece('DBK01');
        Movement::create(['trackable_type' => 'item', 'trackable_id' => $item->id, 'movement_type' => 'vault_out', 'purpose_label' => 'Counter display', 'user_id' => $this->user->id]);

        return [$customer, $sale];
    }

    public function test_the_daybook_exports_the_day_as_a_csv_with_sale_totals_and_what_was_paid(): void
    {
        $this->dayWithASale();

        $rows = $this->csv(Livewire::test(DailyLogbook::class)->instance()->export());

        $this->assertSame(['Date', 'Time', 'Type', 'Event', 'By / Customer', 'Sale total (₹)', 'Paid (₹)', 'Note'], $rows[0]);
        $sale = collect($rows)->firstWhere(2, 'Sale');
        $this->assertSame(now()->toDateString(), $sale[0]);
        $this->assertSame('Daybook Customer', $sale[4]);
        $this->assertSame(['5000', '3500', 'Reserved'], [$sale[5], $sale[6], $sale[7]]);
        $this->assertNotNull(collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Movement' && str_contains($r[3], 'Counter display')));
    }

    public function test_the_day_export_follows_the_search_and_a_range_export_does_not(): void
    {
        $this->dayWithASale();

        $day = Livewire::test(DailyLogbook::class)->set('search', 'Daybook Customer');
        $this->assertCount(1, array_filter($this->csv($day->instance()->export()), fn ($r) => ($r[2] ?? '') === 'Sale' && $r[4] === 'Daybook Customer'));
        $this->assertNull(collect($this->csv($day->instance()->export()))->first(fn ($r) => ($r[2] ?? '') === 'Movement'), 'the search left the movements out');

        $range = Livewire::test(DailyLogbook::class)->set('search', 'Daybook Customer')->set('exportFrom', now()->subDay()->toDateString())->set('exportTo', now()->toDateString());
        $this->assertNotNull(collect($this->csv($range->instance()->exportRange()))->first(fn ($r) => ($r[2] ?? '') === 'Movement'));
    }

    public function test_a_range_must_end_after_it_begins(): void
    {
        Livewire::test(DailyLogbook::class)->set('exportFrom', '2026-10-10')->set('exportTo', '2026-10-01')->call('exportRange')->assertHasErrors('exportTo');
    }

    // ---------------------------------------------------------------- tiles and the sidebar

    public function test_the_inventory_page_has_link_tiles_for_the_stock_pages(): void
    {
        Livewire::test(ItemManager::class)->assertSee('Boxes & Packets')->assertSee('Unassigned items')->assertSee('Raw Material')->assertSee('Stock Audit')
            ->assertSee('Vault ↔ Counter')->assertSeeHtml(route('stock.raw-material'))->assertSeeHtml(route('stock.unassigned'));
    }

    public function test_the_tiles_only_show_pages_this_person_may_open_and_counts_are_real(): void
    {
        $this->piece('TILE01');
        $staff = User::create(['name' => 'Tile Staff', 'email' => 'tile' . random_int(1000, 9999) . '@example.test', 'password' => bcrypt('x')]);
        $staff->givePermissionTo('stock.manage');
        $this->actingAs($staff);

        $links = collect(Livewire::test(ItemManager::class)->viewData('links'));

        $this->assertNull($links->firstWhere('label', 'Pending Review'));
        $this->assertNull($links->firstWhere('label', 'Stock Audit'));
        $this->assertNull($links->firstWhere('label', 'Categories'));
        $this->assertNotNull($links->firstWhere('label', 'Boxes & Packets'));
        $this->assertSame(Item::whereNull('packet_id')->where('status', '!=', 'sold')->count(), $links->firstWhere('label', 'Unassigned items')['count']);
    }

    public function test_every_page_carries_the_script_that_keeps_the_sidebar_where_it_was(): void
    {
        $html = $this->get(route('stock.items'))->assertOk()->getContent();

        $this->assertStringContainsString('id="sidebar-nav"', $html);
        $this->assertStringContainsString('rj.sidebar.scroll', $html);
    }
}
