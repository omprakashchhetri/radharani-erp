<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // A piece with no packet is on hold: it is not counted as stock, shown on the website or sold until it is
    // put in a packet. Pieces already in stock with no packet go on hold now; Item::saving keeps it so from here.
    public function up(): void
    {
        DB::statement("ALTER TABLE items MODIFY status ENUM('in_stock','dispatched','sold','pending_review','reserved','on_hold') NOT NULL DEFAULT 'in_stock'");
        DB::table('items')->where('status', 'in_stock')->whereNull('packet_id')->update(['status' => 'on_hold']);
    }

    public function down(): void
    {
        DB::table('items')->where('status', 'on_hold')->update(['status' => 'in_stock']);
        DB::statement("ALTER TABLE items MODIFY status ENUM('in_stock','dispatched','sold','pending_review','reserved') NOT NULL DEFAULT 'in_stock'");
    }
};
