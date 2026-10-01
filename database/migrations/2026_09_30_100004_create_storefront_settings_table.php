<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shop details shown on the website (WhatsApp number, phone, address,
// hours, map, social links). Key/value so a new field never needs a
// migration; always read through StorefrontSetting::get().
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
