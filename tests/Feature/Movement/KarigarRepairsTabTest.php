<?php

namespace Tests\Feature\Movement;

use App\Livewire\Movement\KarigarDesk;
use App\Livewire\Movement\KarigarReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Repairs & customer metal tab of the Karigar page embeds the older Karigar Return component. Both used to
 * keep their tab in the same address key (`tab`), so opening Receive and then Repairs crashed it.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=KarigarRepairsTabTest
 */
class KarigarRepairsTabTest extends TestCase
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

    public function test_the_repairs_tab_opens_from_its_address_and_after_visiting_receive(): void
    {
        foreach (['repairs', 'receive', 'payments', 'batches', 'issue'] as $tab) {
            $this->get(route('movements.karigar', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('movements.karigar', ['tab' => 'repairs']))->assertOk()->assertSee('Receive back');
    }

    public function test_the_embedded_component_ignores_the_host_pages_tab(): void
    {
        $desk = Livewire::withQueryParams(['tab' => 'repairs'])->test(KarigarDesk::class);
        $desk->assertSet('tab', 'repairs')->assertSee('Receive back');

        Livewire::withQueryParams(['tab' => 'receive'])->test(KarigarReturn::class)->assertSet('tab', 'pieces')->assertOk();
    }

    public function test_the_embedded_component_keeps_its_own_tab_in_its_own_key(): void
    {
        Livewire::withQueryParams(['rtab' => 'customer'])->test(KarigarReturn::class)->assertSet('tab', 'customer');
        Livewire::withQueryParams(['rtab' => 'nonsense'])->test(KarigarReturn::class)->assertSet('tab', 'pieces')->assertOk();
    }

    public function test_the_old_karigar_return_address_goes_to_the_receive_tab(): void
    {
        $this->get('/movements/karigar-return')->assertRedirect('/movements/karigar?tab=receive');
    }
}
