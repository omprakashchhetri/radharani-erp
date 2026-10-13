<?php
namespace App\Models;

use App\Models\Movement\Movement;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An owner-managed place in the shop: Vault, Counter 1, Display... Anything
 * not recorded as out of the vault is in the Vault location.
 */
class Location extends Model
{
    use LogsActivity;

    public const TYPES = ['vault' => 'Vault', 'counter' => 'Counter', 'display' => 'Display', 'other' => 'Other'];

    protected $fillable = ['name', 'type', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'type', 'sort_order', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('stock');
    }

    public function movements()
    {
        return $this->hasMany(Movement::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function vault(): ?self
    {
        return static::where('type', 'vault')->orderBy('id')->first();
    }

    /** Where a vault_out with no recorded location (older rows) is taken to have gone. */
    public static function defaultFloor(): ?self
    {
        return static::active()->where('type', '!=', 'vault')->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Is this piece recorded as in the vault? The most recent vault movement among the piece itself,
     * its packet and its box decides; no movement at all means it never left the vault.
     */
    public static function isInVault(Item $item): bool
    {
        $last = static::latestVaultMove('item', $item->id);

        return ! $last || $last->movement_type === 'vault_in';
    }

    /**
     * The most recent vault-pair movement that applies to a piece, packet or box: its own, or that of
     * the packet / box it sits in. Most recent wins, so a packet sent out on its own after its box
     * was returned (or the other way round) is read correctly.
     */
    public static function latestVaultMove(string $type, int $id): ?Movement
    {
        $chain = [[$type, $id]];
        if ($type === 'item' && ($packetId = Item::withTrashed()->whereKey($id)->value('packet_id'))) {
            $chain[] = ['packet', $packetId];
            $type = 'packet';
            $id = $packetId;
        }
        if ($type === 'packet' && ($boxId = Packet::withTrashed()->whereKey($id)->value('box_id'))) {
            $chain[] = ['box', $boxId];
        }

        return Movement::whereIn('movement_type', Movement::PAIRS['vault'])
            ->where(function ($q) use ($chain) {
                foreach ($chain as [$t, $i]) {
                    $q->orWhere(fn ($w) => $w->where('trackable_type', $t)->where('trackable_id', $i));
                }
            })
            ->latest('id')->first();
    }

    /** Record a piece as moved from the vault to the counter (the first counter unless told otherwise). */
    public static function sendToFloor(Item $item, ?int $locationId = null): Movement
    {
        return Movement::create([
            'trackable_type' => 'item',
            'trackable_id' => $item->id,
            'movement_type' => 'vault_out',
            'location_id' => $locationId ?? static::defaultFloor()?->id,
            'purpose_label' => 'Counter display',
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'weight_at_dispatch' => (float) $item->weight,
        ]);
    }

    /**
     * Who is out of the vault right now and where: latest vault-pair movement per
     * box / packet / piece that is a vault_out. Keyed "type:id".
     *
     * @return \Illuminate\Support\Collection<string, array{type: string, id: int, location_id: ?int, since: \Illuminate\Support\Carbon, user_id: ?int}>
     */
    public static function floorEntries()
    {
        $default = static::defaultFloor()?->id;
        $latest = Movement::selectRaw('MAX(id)')->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');

        return Movement::whereIn('id', $latest)->where('movement_type', 'vault_out')
            ->get(['trackable_type', 'trackable_id', 'location_id', 'created_at', 'user_id'])
            ->mapWithKeys(fn ($m) => [$m->trackable_type . ':' . $m->trackable_id => [
                'type' => $m->trackable_type,
                'id' => (int) $m->trackable_id,
                'location_id' => $m->location_id ?? $default,
                'since' => $m->created_at,
                'user_id' => $m->user_id,
            ]]);
    }

    /**
     * Pieces (in stock) by the location they are at: the most recent vault move among the piece,
     * its packet and its box decides (a vault_out means that move's place, a vault_in means the
     * vault), and no move at all is the vault. Returns [item_id => location_id].
     */
    public static function itemLocations(): array
    {
        $default = static::defaultFloor()?->id;
        $vault = static::vault()?->id;
        $packetBox = Packet::pluck('box_id', 'id');

        $latest = Movement::selectRaw('MAX(id)')->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');
        $moves = Movement::whereIn('id', $latest)
            ->get(['id', 'trackable_type', 'trackable_id', 'movement_type', 'location_id'])
            ->keyBy(fn ($m) => $m->trackable_type . ':' . $m->trackable_id);

        $out = [];
        Item::where('status', 'in_stock')->get(['id', 'packet_id'])->each(function ($item) use ($moves, $packetBox, $vault, $default, &$out) {
            $box = $item->packet_id ? ($packetBox[$item->packet_id] ?? null) : null;
            $last = collect([
                $moves->get('item:' . $item->id),
                $item->packet_id ? $moves->get('packet:' . $item->packet_id) : null,
                $box ? $moves->get('box:' . $box) : null,
            ])->filter()->sortByDesc('id')->first();

            $out[$item->id] = $last && $last->movement_type === 'vault_out' ? ($last->location_id ?? $default) : $vault;
        });

        return $out;
    }
}
