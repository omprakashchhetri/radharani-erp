<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Diamond is a metal of its own from 17 October; give it a starter list of subcategories (the owner edits it under Categories).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['Ring', 'Earrings', 'Pendant', 'Necklace', 'Bracelet'] as $i => $name) {
            DB::table('item_categories')->updateOrInsert(
                ['metal' => 'diamond', 'name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('item_categories')->where('metal', 'diamond')->whereIn('name', ['Ring', 'Earrings', 'Pendant', 'Necklace', 'Bracelet'])->delete();
    }
};
