<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A sale keeps the part of its GST that is on the hallmarking charge, so the bill can print the two parts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('gst_hallmark_amount', 12, 2)->default(0)->after('gst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('gst_hallmark_amount');
        });
    }
};
