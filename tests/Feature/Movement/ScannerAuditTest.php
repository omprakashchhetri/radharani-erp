<?php

namespace Tests\Feature\Movement;

use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Every field that takes a code has a camera button, and a scanned QR sticker (a link) finds its piece in every search box.
 *   DB_CONNECTION=mysql DB_DATABASE=radharani_erp php artisan test --filter=ScannerAuditTest
 */
class ScannerAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Needs the MySQL database; see the note at the top of this class.');
        }
    }

    /** The code fields found on 16 Oct and the camera button each one now has. */
    public function test_the_code_fields_that_lacked_a_camera_button_have_one(): void
    {
        $fields = [
            'orders/new-order-entry' => ['oe-existing'],
            'installments/scheme-list' => ['sl-reserve'],
            'movement/hallmark-desk' => ['hk-lh-', 'hk-nr-'],
            'stock/item-form' => ['f-ph'],
            'pricing/pricing-rules' => ['pr-prod'],
        ];
        foreach ($fields as $view => $ids) {
            $html = file_get_contents(resource_path("views/livewire/{$view}.blade.php"));
            foreach ($ids as $id) {
                $this->assertMatchesRegularExpression('/(<x-ui\.scan-button[^>]*target="#' . preg_quote($id, '/') . '|<x-ui\.search-input[^>]*scan[^>]*id="' . preg_quote($id, '/') . '|<x-ui\.search-input[^>]*id="' . preg_quote($id, '/') . '"[^>]*scan)/', $html, "{$view}: #{$id} has no camera button");
            }
        }
    }

    public function test_a_scanned_sticker_link_finds_the_piece_a_packet_and_a_box(): void
    {
        $box = Box::create(['code' => 'SB' . random_int(1000, 9999)]);
        $packet = Packet::create(['code' => 'SP' . random_int(1000, 9999), 'box_id' => $box->id]);
        $inPacket = Item::create(['packet_id' => $packet->id, 'internal_code' => 'SA' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Ring', 'purity' => '22K', 'weight' => 2, 'making_value' => 0, 'status' => 'in_stock']);
        $other = Item::create(['packet_id' => $this->packetId(), 'internal_code' => 'SB' . random_int(100, 999), 'metal' => 'gold', 'category' => 'Ring', 'purity' => '22K', 'weight' => 2, 'making_value' => 0, 'status' => 'in_stock']);

        $itemQr = QrCode::create(['target_type' => 'item', 'target_id' => $inPacket->id, 'code' => 'ITEMQR1']);
        QrCode::create(['target_type' => 'packet', 'target_id' => $packet->id, 'code' => 'PKTQR1']);
        QrCode::create(['target_type' => 'box', 'target_id' => $box->id, 'code' => 'BOXQR1']);

        foreach (['ITEMQR1', 'https://erp.example.com/stock/q/ITEMQR1', 'PKTQR1', 'https://erp.example.com/stock/q/PKTQR1/', 'BOXQR1', 'http://localhost/stock/q/boxqr1'] as $scanned) {
            $ids = Item::searchAnything($scanned)->pluck('id');
            $this->assertTrue($ids->contains($inPacket->id), "{$scanned} did not find the piece");
            $this->assertFalse($ids->contains($other->id), "{$scanned} found an unrelated piece");
        }
        $this->assertNotNull($itemQr);
    }

    public function test_the_scanner_turns_a_sticker_link_into_its_code_for_a_field(): void
    {
        $js = file_get_contents(resource_path('views/components/layouts/partials/scanner.blade.php'));

        $this->assertStringContainsString("if (this.mode !== 'go') code = code.replace(", $js);
    }
}
