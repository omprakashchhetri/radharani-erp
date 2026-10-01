<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website listing fields on the physical piece. One listing = one tagged
// piece (never a grouped "design"), so nothing here duplicates stock data.
// net_weight and stone_value also feed PricingService: metal value is
// charged on net weight when it's known, and stones are their own line.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('net_weight', 8, 3)->nullable()->after('weight');
            $table->string('stones', 120)->nullable()->after('making_value');
            $table->decimal('stone_value', 12, 2)->default(0)->after('stones');

            $table->boolean('show_on_website')->default(false)->after('status')->index();
            $table->string('web_name', 120)->nullable()->after('show_on_website');
            $table->string('slug', 160)->nullable()->unique()->after('web_name');
            $table->text('web_description')->nullable()->after('slug');
            $table->foreignId('storefront_collection_id')->nullable()->after('web_description')
                ->constrained('storefront_collections')->nullOnDelete();
            $table->json('audiences')->nullable()->after('storefront_collection_id');
            $table->json('occasions')->nullable()->after('audiences');
            $table->string('dimensions', 120)->nullable()->after('occasions');
            $table->string('size_type', 20)->nullable()->after('dimensions');
            $table->string('size_label', 20)->nullable()->after('size_type');
            $table->boolean('is_bestseller')->default(false)->after('size_label');
            $table->timestamp('listed_at')->nullable()->after('is_bestseller');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_collection_id');
            $table->dropUnique(['slug']);
            $table->dropIndex(['show_on_website']);
            $table->dropColumn([
                'net_weight', 'stones', 'stone_value', 'show_on_website', 'web_name', 'slug',
                'web_description', 'audiences', 'occasions', 'dimensions', 'size_type',
                'size_label', 'is_bestseller', 'listed_at',
            ]);
        });
    }
};
