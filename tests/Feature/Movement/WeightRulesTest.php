<?php

namespace Tests\Feature\Movement;

use App\Livewire\Pricing\PricingRules;
use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Stock\Item;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Charges by weight band (11 Oct meeting). Uses 9K gold at a rate of its own so the sums are exact.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=WeightRulesTest
 */
class WeightRulesTest extends TestCase
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

        RateLog::create(['metal' => 'gold', 'purity' => '9K', 'rate' => 1000, 'source' => 'manual', 'updated_by' => $this->user->id]);
        // Only this test's rules should price a 9K piece.
        PricingRule::whereIn('kind', ['making', 'additional', 'discount', 'hallmark'])->update(['active' => false]);
    }

    private function piece(float $weight, ?float $net = null, string $category = 'Ring'): Item
    {
        return Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'WR' . random_int(100, 999), 'metal' => 'gold', 'category' => $category, 'purity' => '9K',
            'making_value' => 0, 'weight' => $weight, 'net_weight' => $net, 'status' => 'in_stock']);
    }

    private function rule(array $o): PricingRule
    {
        return PricingRule::create($o + ['kind' => 'making', 'calc' => 'per_gram', 'value' => 10, 'active' => true, 'created_by' => $this->user->id]);
    }

    private function making(Item $item): float
    {
        app(PricingService::class)->forgetRules();

        return app(PricingService::class)->breakdown($item)['making'];
    }

    public function test_a_weight_band_includes_its_bottom_and_excludes_its_top(): void
    {
        $this->rule(['scope' => 'weight_range', 'min_weight' => 5, 'max_weight' => 10, 'value' => 100, 'calc' => 'per_piece']);

        $this->assertEquals(0, $this->making($this->piece(4.999)));
        $this->assertEquals(100, $this->making($this->piece(5)));
        $this->assertEquals(100, $this->making($this->piece(9.999)));
        $this->assertEquals(0, $this->making($this->piece(10)));
    }

    public function test_the_band_is_measured_on_net_weight_when_it_is_known(): void
    {
        $this->rule(['scope' => 'weight_range', 'min_weight' => 5, 'max_weight' => 10, 'value' => 100, 'calc' => 'per_piece']);

        $this->assertEquals(100, $this->making($this->piece(12, 7)), 'gross 12 g but net 7 g is in the band');
        $this->assertEquals(0, $this->making($this->piece(7, 11)), 'gross 7 g but net 11 g is not');
    }

    public function test_one_end_can_be_left_open(): void
    {
        $this->rule(['scope' => 'weight_range', 'min_weight' => 20, 'value' => 7, 'calc' => 'per_piece']);
        $this->rule(['scope' => 'weight_range', 'max_weight' => 2, 'value' => 3, 'calc' => 'per_piece']);

        $this->assertEquals(7, $this->making($this->piece(500)));
        $this->assertEquals(3, $this->making($this->piece(1.5)));
        $this->assertEquals(0, $this->making($this->piece(10)));
    }

    public function test_the_most_specific_rule_still_wins(): void
    {
        $this->rule(['scope' => 'all', 'kind' => 'making', 'value' => 1, 'calc' => 'per_piece']);
        $this->rule(['scope' => 'metal', 'metal' => 'gold', 'value' => 2, 'calc' => 'per_piece']);
        $this->rule(['scope' => 'weight_range', 'min_weight' => 5, 'max_weight' => 10, 'value' => 3, 'calc' => 'per_piece']);
        $this->rule(['scope' => 'category', 'category' => 'Chain', 'value' => 4, 'calc' => 'per_piece']);

        $this->assertEquals(3, $this->making($this->piece(6)), 'weight band beats metal');
        $this->assertEquals(4, $this->making($this->piece(6, null, 'Chain')), 'category beats weight band');
    }

    public function test_an_older_price_range_rule_keeps_working(): void
    {
        $this->rule(['scope' => 'price_range', 'min_value' => 0, 'max_value' => 100000, 'value' => 9, 'calc' => 'per_piece']);

        $this->assertEquals(9, $this->making($this->piece(6)));
    }

    public function test_a_weight_rule_can_be_added_on_the_page_and_a_price_range_can_not(): void
    {
        $page = Livewire::test(PricingRules::class)->call('create')
            ->set('scope', 'weight_range')->set('minWeight', 5)->set('maxWeight', 10)->set('calc', 'per_gram')->set('value', 12)->call('save')->assertHasNoErrors();

        $saved = PricingRule::where('scope', 'weight_range')->latest('id')->first();
        $this->assertEquals(5, $saved->min_weight);
        $this->assertEquals(10, $saved->max_weight);
        $this->assertSame('Weight 5 g to 10 g', $saved->target());

        $page->call('create')->set('scope', 'price_range')->set('minValue', 100)->set('value', 5)->call('save')->assertHasErrors('scope');
    }

    public function test_the_weight_range_form_checks_its_ends(): void
    {
        Livewire::test(PricingRules::class)->call('create')->set('scope', 'weight_range')->set('value', 5)->call('save')->assertHasErrors('minWeight');
        Livewire::test(PricingRules::class)->call('create')->set('scope', 'weight_range')->set('minWeight', 10)->set('maxWeight', 5)->set('value', 5)->call('save')->assertHasErrors('maxWeight');
    }

    public function test_an_older_price_range_rule_can_still_be_edited_and_the_page_warns_about_it(): void
    {
        $old = $this->rule(['scope' => 'price_range', 'min_value' => 0, 'max_value' => 1000, 'value' => 9, 'calc' => 'per_piece']);

        Livewire::test(PricingRules::class)->assertSee('still set for a price range')
            ->call('edit', $old->id)->set('value', 11)->call('save')->assertHasNoErrors();

        $this->assertEquals(11, $old->fresh()->value);
    }
}
