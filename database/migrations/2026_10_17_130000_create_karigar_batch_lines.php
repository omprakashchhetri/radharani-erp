<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 17 October: a batch sent to a karigar can list its items line by line (what the piece is, how many, how heavy).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karigar_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karigar_raw_batch_id')->constrained('karigar_raw_batches')->cascadeOnDelete();
            $table->string('description', 150);
            $table->unsignedInteger('pieces')->default(1);
            $table->decimal('weight', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karigar_batch_lines');
    }
};
