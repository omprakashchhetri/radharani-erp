<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 17 October: titanium and platinum are gone, diamond is a metal of its own (a piece with a fixed MRP).
// Development data only: rows that used the removed metals are cleared or detached before the columns narrow.
return new class extends Migration
{
    private const REMOVED = ['titanium', 'platinum'];

    public function up(): void
    {
        if (Schema::hasTable('items')) {
            DB::table('items')->whereIn('metal', self::REMOVED)->update(['metal' => null, 'deleted_at' => now()]);
        }
        foreach (['rate_logs', 'exchange_deduction_presets', 'item_categories', 'pricing_rules', 'orders', 'karigar_raw_batches', 'customer_material_jobs', 'exchange_transactions', 'refinery_batches', 'raw_metal_entries'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'metal')) {
                $nullable = $this->isNullable($table);
                if ($nullable) {
                    DB::table($table)->whereIn('metal', self::REMOVED)->update(['metal' => null]);
                } else {
                    DB::table($table)->whereIn('metal', self::REMOVED)->delete();
                }
            }
        }

        foreach ($this->tables() as $table) {
            $this->setEnum($table, "'gold','silver','diamond'");
        }

        Schema::table('items', function (Blueprint $t) {
            $t->decimal('mrp', 12, 2)->nullable()->after('stone_value');   // diamond pieces are priced at their MRP
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn('mrp'));
        foreach ($this->tables() as $table) {
            $this->setEnum($table, "'gold','silver','titanium','platinum'");
        }
    }

    private function tables(): array
    {
        return collect(DB::select("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'metal' AND DATA_TYPE = 'enum'"))
            ->pluck('TABLE_NAME')->all();
    }

    private function isNullable(string $table): bool
    {
        return DB::selectOne("SELECT IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'metal'", [$table])->n === 'YES';
    }

    private function setEnum(string $table, string $values): void
    {
        $col = DB::selectOne("SELECT IS_NULLABLE n, COLUMN_DEFAULT d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'metal'", [$table]);
        $null = $col->n === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col->d !== null ? ' DEFAULT ' . DB::getPdo()->quote($col->d) : '';
        DB::statement("ALTER TABLE `{$table}` MODIFY `metal` ENUM({$values}) {$null}{$default}");
    }
};
