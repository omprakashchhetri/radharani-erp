<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Charges are set for a weight band, not a price range (11 Oct meeting). Older price-range rules keep
 * working until they are re-entered by weight; nothing is converted, because there is no honest way to
 * turn rupees into grams.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pricing_rules MODIFY scope ENUM('product','category','price_range','weight_range','metal','all') NOT NULL");
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->decimal('min_weight', 10, 3)->nullable()->after('max_value');   // grams, inclusive
            $table->decimal('max_weight', 10, 3)->nullable()->after('min_weight');  // grams, exclusive
        });
    }

    public function down(): void
    {
        DB::table('pricing_rules')->where('scope', 'weight_range')->delete();
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['min_weight', 'max_weight']);
        });
        DB::statement("ALTER TABLE pricing_rules MODIFY scope ENUM('product','category','price_range','metal','all') NOT NULL");
    }
};
