<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The automatic round-off to a whole rupee is a line of its own on the bill (the client's sheet).
    // Older sales have none: 0 keeps their totals reading as they were.
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('round_off', 8, 2)->default(0)->after('gst_hallmark_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('round_off');
        });
    }
};
