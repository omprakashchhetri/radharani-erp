<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trays inside the vault (11 Oct meeting): a tray is a location of type "tray" whose parent is the vault, so the
 * vault can show what is on each tray. Putting a piece on a tray is a vault_in movement whose location is the tray.
 * Everything not on a tray is simply in the vault, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE locations MODIFY type ENUM('vault','counter','display','other','tray') NOT NULL DEFAULT 'counter'");
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('type')->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('movements')->whereIn('location_id', DB::table('locations')->where('type', 'tray')->select('id'))
            ->update(['location_id' => DB::table('locations')->where('type', 'vault')->orderBy('id')->value('id')]);
        DB::table('locations')->where('type', 'tray')->delete();
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
        DB::statement("ALTER TABLE locations MODIFY type ENUM('vault','counter','display','other') NOT NULL DEFAULT 'counter'");
    }
};
