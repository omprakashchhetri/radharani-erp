<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rule #3: every write needs a real user_id. `created_by` covers the send
// action, but the return action (RefineryBatchReturn::submit) had nothing
// to stamp — the row's only user attribution stayed frozen at whoever sent
// it, even after a different staff member recorded the return.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
        });
    }
};
