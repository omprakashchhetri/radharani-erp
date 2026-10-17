<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 17 October: money a customer pays in with no order behind it (the Poila Boishakh "halkhata" is the example).
// It stays on the customer's account until a sale uses it. Insert-only: received rows are positive, uses and refunds negative.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->enum('kind', ['received', 'used', 'refund']);
            $table->decimal('amount', 12, 2);                       // + received, - used or refunded
            $table->enum('mode', ['cash', 'upi', 'card', 'bank', 'cheque'])->nullable(); // how it was paid in (received rows)
            $table->string('occasion', 80)->nullable();              // e.g. Halkhata
            $table->unsignedBigInteger('sale_id')->nullable();       // the sale that used it
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank','cheque','exchange','advance') NOT NULL");
    }

    public function down(): void
    {
        DB::table('sale_payments')->where('mode', 'advance')->delete();
        DB::statement("ALTER TABLE sale_payments MODIFY mode ENUM('cash','upi','card','bank','cheque','exchange') NOT NULL");
        Schema::dropIfExists('customer_advances');
    }
};
