<?php
namespace App\Support;

use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;

// Movements point at items, packets or boxes polymorphically. These helpers
// batch-load whatever a list of movements refers to (three queries, not one
// per row) and turn each into the code / detail / link the screens show.
class Trackables
{
    /** @return array{item: \Illuminate\Support\Collection, packet: \Illuminate\Support\Collection, box: \Illuminate\Support\Collection} */
    public static function load(iterable $rows): array
    {
        $ids = ['item' => [], 'packet' => [], 'box' => []];
        foreach ($rows as $row) {
            $type = is_array($row) ? $row['type'] : $row->trackable_type;
            $id = is_array($row) ? $row['id'] : $row->trackable_id;
            if (isset($ids[$type])) {
                $ids[$type][] = $id;
            }
        }

        return [
            'item' => $ids['item'] ? Item::whereIn('id', array_unique($ids['item']))->get()->keyBy('id') : collect(),
            'packet' => $ids['packet'] ? Packet::withCount('items')->whereIn('id', array_unique($ids['packet']))->get()->keyBy('id') : collect(),
            'box' => $ids['box'] ? Box::withCount('packets')->whereIn('id', array_unique($ids['box']))->get()->keyBy('id') : collect(),
        ];
    }

    /** @return array{code: string, detail: string, url: ?string, model: mixed} */
    public static function describe(array $loaded, string $type, int $id): array
    {
        $model = $loaded[$type][$id] ?? null;

        if (! $model) {
            return ['code' => ucfirst($type) . " #{$id}", 'detail' => 'No longer in the system', 'url' => null, 'model' => null];
        }

        return match ($type) {
            'item' => [
                'code' => $model->label,
                'detail' => trim($model->category . ' · ' . number_format((float) $model->weight, 3) . ' g'),
                'url' => route('stock.items.show', $model),
                'model' => $model,
            ],
            'packet' => [
                'code' => $model->code,
                'detail' => 'Packet · ' . $model->items_count . ' ' . \Illuminate\Support\Str::plural('piece', $model->items_count),
                'url' => route('stock.packets.show', $model),
                'model' => $model,
            ],
            'box' => [
                'code' => $model->code,
                'detail' => 'Box · ' . $model->packets_count . ' ' . \Illuminate\Support\Str::plural('packet', $model->packets_count),
                'url' => route('stock.boxes.show', $model),
                'model' => $model,
            ],
        };
    }
}
