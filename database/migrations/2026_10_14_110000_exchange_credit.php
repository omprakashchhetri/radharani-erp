<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What happens to the value of a settled exchange (11 Oct meeting): it becomes credit the customer can
 * spend in a sale, put towards a new order, or take in cash. exchange_applications is insert-only; the
 * credit left is the exchange's final value less what has been applied. A cancelled order's credit comes
 * back as a negative row, never by editing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_transaction_id')->constrained('exchange_transactions');
            $table->enum('kind', ['sale', 'order', 'cash']);
            $table->unsignedBigInteger('applied_id')->nullable(); // the sale or order; null for cash
            $table->decimal('amount', 12, 2);                      // negative when credit comes back
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['exchange_transaction_id', 'created_at']);
            $table->index(['kind', 'applied_id']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank','exchange') NOT NULL");
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->foreignId('exchange_transaction_id')->nullable()->after('mode')->constrained('exchange_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('sale_payments')->where('mode', 'exchange')->delete();
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exchange_transaction_id');
        });
        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank') NOT NULL");
        Schema::dropIfExists('exchange_applications');
    }
};
