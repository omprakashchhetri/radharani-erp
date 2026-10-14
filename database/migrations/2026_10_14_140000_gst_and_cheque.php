<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GST is back, as a choice (11 Oct meeting): 3% on the jewellery value and 18% on the hallmarking charge by
 * default, either can be set to another rate or 0 on the bill. The owner sets the defaults. A sale keeps
 * the rates it was billed at and the GST amount, so a later change never alters a past bill. Cheque joins
 * the payment modes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('jewellery_percent', 5, 2)->default(3);
            $table->decimal('hallmark_percent', 5, 2)->default(18);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
        DB::table('tax_settings')->insert(['jewellery_percent' => 3, 'hallmark_percent' => 18, 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('gst_jewellery_percent', 5, 2)->default(0)->after('discount');
            $table->decimal('gst_hallmark_percent', 5, 2)->default(0)->after('gst_jewellery_percent');
            $table->decimal('gst_amount', 12, 2)->default(0)->after('gst_hallmark_percent');
        });

        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank','cheque','exchange') NOT NULL");
    }

    public function down(): void
    {
        DB::table('sale_payments')->where('mode', 'cheque')->update(['mode' => 'bank']);
        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank','exchange') NOT NULL");
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['gst_jewellery_percent', 'gst_hallmark_percent', 'gst_amount']);
        });
        Schema::dropIfExists('tax_settings');
    }
};
