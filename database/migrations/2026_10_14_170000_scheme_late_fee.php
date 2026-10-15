<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Late payments on the monthly scheme (11 Oct meeting). The schedule is fixed by the start date: the first
 * instalment is due on it and each next one a month after. A payment records the instalment it covers, the
 * date that was due and any late fee, which is typed in, never worked out. A scheme can carry a usual late
 * fee to start the field with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installment_schemes', function (Blueprint $table) {
            $table->decimal('late_fee', 10, 2)->default(0)->after('monthly_amount');
        });
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('installment_no')->nullable()->after('amount');
            $table->date('due_on')->nullable()->after('installment_no');
            $table->decimal('late_fee', 10, 2)->default(0)->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('installment_payments', function (Blueprint $table) {
            $table->dropColumn(['installment_no', 'due_on', 'late_fee']);
        });
        Schema::table('installment_schemes', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }
};
