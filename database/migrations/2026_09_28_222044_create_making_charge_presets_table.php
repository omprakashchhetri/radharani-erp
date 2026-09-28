<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('making_charge_presets', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->unique();
            $table->enum('type', ['percentage', 'flat_per_piece', 'flat_per_gram']);
            $table->decimal('value', 10, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('making_charge_presets');
    }
};
