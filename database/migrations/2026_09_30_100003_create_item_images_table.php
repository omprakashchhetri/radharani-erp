<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catalogue photos of a piece (not movement/dispatch documentation photos,
// which stay on movements.photo_path). Stored via PhotoCompressionService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_images');
    }
};
