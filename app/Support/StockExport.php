<?php
namespace App\Support;

use App\Models\Stock\Item;

/** The columns of a stock export: one row per piece, with the batch it was entered in. */
class StockExport
{
    public const HEADER = ['Internal code', 'HUID', 'Category', 'Metal', 'Purity', 'Weight (g)', 'Net weight (g)', 'Making type', 'Making value', 'Stone value', 'Description', 'Status', 'Entered', 'Packet', 'Box'];

    public static function row(Item $i): array
    {
        return [
            $i->internal_code ?? '', $i->huid_code ?? '', $i->category, ucfirst((string) $i->metal), $i->purity, (float) $i->weight,
            $i->net_weight !== null ? (float) $i->net_weight : '', str_replace('_', ' ', (string) $i->making_type), (float) $i->making_value, (float) $i->stone_value,
            $i->description ?? '', str_replace('_', ' ', (string) $i->status), $i->created_at?->format('Y-m-d H:i') ?? '',
            $i->packet?->code ?? '', $i->packet?->box?->code ?? '',
        ];
    }
}
