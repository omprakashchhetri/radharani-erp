<?php
namespace Database\Seeders;

use App\Models\Stock\Item;
use App\Models\Stock\ItemImage;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontCollection;
use Illuminate\Database\Seeder;

/**
 * Website demo content, mirroring the sample catalogue the storefront design
 * was built with (database/seeders/data/storefront-demo.json, exported from
 * the rr-web-ui repo's data.js): 9 categories, 6 collections and 27 listed
 * pieces. Images are Unsplash URLs, which StorefrontImage passes through —
 * real listings use photos uploaded through PhotoCompressionService.
 *
 * Local/testing only, and safe to re-run (keyed on slugs).
 */
class StorefrontDemoSeeder extends Seeder
{
    // Website category slug => stock category strings that roll up into it,
    // plus the stock category new demo pieces are filed under.
    private const CATEGORY_MAP = [
        'earrings' => [['Earring', 'Jhumka'], 'Earrings'],
        'rings' => [['Ring'], 'Ring'],
        'necklaces' => [['Necklace', 'Haar'], 'Necklace'],
        'bangles' => [['Bangle', 'Chudi', 'Kada'], 'Bangle'],
        'bracelets' => [['Bracelet'], 'Bracelet'],
        'pendants' => [['Pendant'], 'Pendant'],
        'mangalsutra' => [[], 'Mangalsutra'],
        'nose-pins' => [['Nose pin', 'Nath'], 'Nose pin'],
        'chains' => [['Chain'], 'Chain'],
    ];

    private const METAL_BY_PURITY = ['22K' => 'gold', '18K' => 'gold', '92.5' => 'silver', '950' => 'platinum'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('StorefrontDemoSeeder only runs in local/testing.');

            return;
        }

        $data = json_decode(file_get_contents(__DIR__.'/data/storefront-demo.json'), true);
        $unsplash = fn (string $id) => 'https://images.unsplash.com/photo-'.$id;

        foreach ($data['categories'] as $i => $c) {
            StorefrontCategory::updateOrCreate(['slug' => $c['slug']], [
                'name' => $c['name'],
                'blurb' => $c['blurb'],
                'image' => $unsplash($c['img']),
                'stock_categories' => self::CATEGORY_MAP[$c['slug']][0] ?? [],
                'sort_order' => $i,
                'in_menu' => in_array($c['slug'], ['earrings', 'rings', 'necklaces', 'bangles', 'mangalsutra'], true),
                'is_active' => true,
            ]);
        }

        $collections = [];
        foreach ($data['collections'] as $i => $c) {
            $collections[$c['slug']] = StorefrontCollection::updateOrCreate(['slug' => $c['slug']], [
                'name' => $c['name'],
                'blurb' => $c['blurb'],
                'image' => $unsplash($c['img']),
                'sort_order' => $i,
                'is_active' => true,
            ])->id;
        }

        foreach ($data['products'] as $i => $p) {
            $metal = self::METAL_BY_PURITY[$p['purity']] ?? 'gold';
            $existing = Item::where('slug', $p['id'])->first();

            $fields = [
                'metal' => $metal,
                'category' => self::CATEGORY_MAP[$p['category']][1] ?? ucfirst($p['category']),
                'purity' => $p['purity'],
                'weight' => $p['grossWt'],
                'net_weight' => $p['netWt'] != $p['grossWt'] ? $p['netWt'] : null,
                'making_type' => 'percentage',
                'making_value' => $p['makingPct'],
                'stones' => $p['stones'] === 'None' ? null : $p['stones'],
                'stone_value' => $p['stoneValue'],
                'show_on_website' => true,
                'web_name' => $p['name'],
                'slug' => $p['id'],
                'web_description' => $p['description'],
                'description' => mb_substr($p['name'], 0, 100),
                'storefront_collection_id' => $p['collection'] ? ($collections[$p['collection']] ?? null) : null,
                'audiences' => $p['for'],
                'occasions' => $p['occasions'],
                'dimensions' => $p['dims'],
                'size_type' => $p['sizeType'],
                'size_label' => ['ring' => '12', 'bangle' => '2.4', 'chain' => '20 in'][$p['sizeType']] ?? null,
                'is_bestseller' => $p['isBestseller'],
                // Keep the design's "New" badges: recent for new pieces, older otherwise.
                'listed_at' => $p['isNew'] ? now()->subDays(2 + $i % 9) : now()->subDays(60 + $i * 3),
            ];

            if ($existing) {
                $existing->update($fields);
                $item = $existing;
            } else {
                // Gold pieces carry a HUID (6 characters); silver/platinum get an internal code.
                $huid = $metal === 'gold' ? $this->demoHuid() : null;
                $item = Item::create($fields + [
                    'huid_code' => $huid,
                    'internal_code' => $huid ? null : Item::generateInternalCode(),
                    'status' => 'in_stock',
                ]);
            }

            $item->images()->delete();
            foreach ($p['images'] as $n => $imageId) {
                ItemImage::create(['item_id' => $item->id, 'path' => $unsplash($imageId), 'sort_order' => $n]);
            }
        }

        $this->command?->info('Storefront demo content seeded: '.count($data['products']).' pieces.');
    }

    private function demoHuid(): string
    {
        do {
            $huid = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
        } while (Item::where('huid_code', $huid)->exists());

        return $huid;
    }
}
