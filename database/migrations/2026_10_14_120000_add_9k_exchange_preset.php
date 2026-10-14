<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 9 Karat joins the carats (11 Oct meeting). Its exchange deduction starts at 14K's figure; the owner can change it on the daily rates page. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('exchange_deduction_presets')->where('metal', 'gold')->where('purity', '9K')->exists()) {
            return;
        }
        $percent = DB::table('exchange_deduction_presets')->where('metal', 'gold')->where('purity', '14K')->value('percent') ?? 2.00;
        DB::table('exchange_deduction_presets')->insert(['metal' => 'gold', 'purity' => '9K', 'percent' => $percent, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('exchange_deduction_presets')->where('metal', 'gold')->where('purity', '9K')->delete();
    }
};
