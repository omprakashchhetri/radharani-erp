<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Karigar Return records the weight that came back and the loss, entered by
// hand (#6), for all three karigar sub-flows. Tagged pieces keep that on their
// karigar_in movement and customer-material jobs already had weight_in /
// weight_loss; raw-material batches had nowhere to put it.
// Rule #3: the return is often recorded by a different person than the
// dispatch, so both tables get a returned_by alongside their user_id (same
// fix refinery_batches received).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->decimal('weight_returned', 8, 3)->nullable()->after('actual_return');
            $table->decimal('weight_loss', 8, 3)->nullable()->after('weight_returned');
            $table->foreignId('returned_by')->nullable()->after('user_id')->constrained('users');
        });

        Schema::table('customer_material_jobs', function (Blueprint $table) {
            $table->foreignId('returned_by')->nullable()->after('user_id')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('customer_material_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
        });

        Schema::table('karigar_raw_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['weight_returned', 'weight_loss']);
        });
    }
};
