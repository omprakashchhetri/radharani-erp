<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diamond jewellery (11 Oct meeting): a Diamond subcategory under gold, and fields on the piece for the
 * diamond weight in carats, its cut and a summary number. The gold weight is the piece's net weight and
 * the diamond's value goes in its stone value, so pricing needs nothing new.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('diamond_carat', 8, 3)->nullable()->after('stone_value');
            $table->string('diamond_cut', 40)->nullable()->after('diamond_carat');
            $table->string('diamond_summary_no', 60)->nullable()->after('diamond_cut');
        });

        if (! DB::table('item_categories')->where('metal', 'gold')->where('name', 'Diamond')->exists()) {
            DB::table('item_categories')->insert([
                'metal' => 'gold', 'name' => 'Diamond', 'sort_order' => (int) DB::table('item_categories')->where('metal', 'gold')->max('sort_order') + 1,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['diamond_carat', 'diamond_cut', 'diamond_summary_no']);
        });
        DB::table('item_categories')->where('metal', 'gold')->where('name', 'Diamond')->whereNotExists(
            fn ($q) => $q->selectRaw(1)->from('items')->whereColumn('items.category_id', 'item_categories.id')
        )->delete();
    }
};
