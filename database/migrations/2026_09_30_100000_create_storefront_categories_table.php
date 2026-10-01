<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website categories ("Earrings", "Bangles"...). items.category stays free
// text for the stock side; stock_categories lists which of those strings
// roll up into this website category (e.g. Bangles <- Bangle, Chudi), so
// staff never re-tag stock just to publish it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 60);
            $table->string('blurb', 255)->nullable();
            $table->string('image')->nullable();
            $table->json('stock_categories')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('in_menu')->default(false); // one of the links in the site's category bar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_categories');
    }
};
