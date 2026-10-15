<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Raw material becomes its own module (11 Oct meeting): every ledger row says who holds the metal
 * (the shop, a karigar, the refinery) and what state it is in (refined, old gold from an exchange,
 * scrap). A move between holders is two rows sharing a transfer_id.
 *
 * The ledger stays append-only. History is brought up to date by inserting rows, never by editing
 * old ones: each past karigar advance gets the matching row for the karigar, past receipts get the
 * metal they used up, and a refinery batch still out gets an opening row at the refinery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_metal_entries', function (Blueprint $table) {
            $table->enum('holder_type', ['shop', 'karigar', 'refinery'])->default('shop')->after('weight');
            $table->unsignedBigInteger('holder_id')->nullable()->after('holder_type');
            $table->enum('kind', ['refined', 'old_gold', 'scrap'])->default('refined')->after('holder_id');
            $table->string('transfer_id', 36)->nullable()->after('kind');
            $table->index(['holder_type', 'holder_id', 'metal', 'purity', 'kind'], 'rme_balance_idx');
        });

        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->string('purity', 10)->nullable()->after('metal');
            $table->enum('kind', ['old_gold', 'scrap'])->default('scrap')->after('purity');
        });

        $this->backfill();

        $permission = Permission::firstOrCreate(['name' => 'rawmaterial.manage', 'guard_name' => 'web']);
        foreach (['owner', 'manager'] as $name) {
            Role::where('name', $name)->first()?->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function backfill(): void
    {
        $now = now();

        // A karigar's advance left the shop; the karigar now holds it.
        $advances = DB::table('raw_metal_entries')->where('source_type', 'karigar_advance')->where('weight', '<', 0)->get();
        foreach ($advances as $a) {
            $vendorId = DB::table('karigar_raw_batches')->where('id', $a->source_id)->value('vendor_id');
            if (! $vendorId) {
                continue;
            }
            DB::table('raw_metal_entries')->insert([
                'metal' => $a->metal, 'purity' => $a->purity, 'weight' => -$a->weight, 'holder_type' => 'karigar', 'holder_id' => $vendorId,
                'kind' => 'refined', 'source_type' => 'karigar_advance', 'source_id' => $a->source_id, 'note' => $a->note,
                'user_id' => $a->user_id, 'created_at' => $a->created_at, 'updated_at' => $a->created_at,
            ]);
        }

        // What the karigar has already turned into received pieces is no longer held.
        $batches = DB::table('karigar_raw_batches')->where('advance_metal_weight', '>', 0)->get();
        foreach ($batches as $b) {
            $used = (float) DB::table('karigar_receipts')->where('batch_id', $b->id)->selectRaw('COALESCE(SUM(weight_received + weight_loss), 0) as w')->value('w');
            $consumed = round(min($used, (float) $b->advance_metal_weight), 3);
            if ($consumed <= 0) {
                continue;
            }
            DB::table('raw_metal_entries')->insert([
                'metal' => $b->metal, 'purity' => $b->advance_metal_purity ?: '-', 'weight' => -$consumed, 'holder_type' => 'karigar', 'holder_id' => $b->vendor_id,
                'kind' => 'refined', 'source_type' => 'karigar_used', 'source_id' => $b->id, 'note' => 'Used in pieces received before the raw-material module',
                'user_id' => $b->user_id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Scrap still at the refinery: an opening row there (it was never recorded as leaving the shop).
        foreach (DB::table('refinery_batches')->where('status', 'sent')->get() as $r) {
            DB::table('raw_metal_entries')->insert([
                'metal' => $r->metal, 'purity' => '-', 'weight' => $r->weight, 'holder_type' => 'refinery', 'holder_id' => $r->id,
                'kind' => 'scrap', 'source_type' => 'refinery_out', 'source_id' => $r->id, 'note' => 'Opening: sent before the raw-material module',
                'user_id' => $r->created_by, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('raw_metal_entries')->where('holder_type', '!=', 'shop')->orWhereIn('source_type', ['karigar_used', 'refinery_out', 'refinery_return', 'exchange', 'karigar_return', 'reclassify'])->delete();

        Schema::table('refinery_batches', function (Blueprint $table) {
            $table->dropColumn(['purity', 'kind']);
        });
        Schema::table('raw_metal_entries', function (Blueprint $table) {
            $table->dropIndex('rme_balance_idx');
            $table->dropColumn(['holder_type', 'holder_id', 'kind', 'transfer_id']);
        });
        Permission::where('name', 'rawmaterial.manage')->delete();
    }
};
