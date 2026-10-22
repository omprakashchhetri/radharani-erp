<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 17 October: metal can go to the refinery from outside the shop's stock, and extra metal that
// comes back can be added to the shop's stock.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->string('origin', 10)->default('stock')->after('kind');       // stock | outside
            $table->string('outside_note')->nullable()->after('origin');          // whose metal / where from
            $table->decimal('extra_weight', 10, 3)->nullable()->after('result_weight');
        });
    }

    public function down(): void
    {
        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->dropColumn(['origin', 'outside_note', 'extra_weight']);
        });
    }
};
