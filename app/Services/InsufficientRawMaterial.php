<?php
namespace App\Services;

use App\Models\Movement\RawMetalEntry;
use RuntimeException;

/** A holder was asked to give up more metal than it has. The message is written to be shown to staff. */
class InsufficientRawMaterial extends RuntimeException
{
    public function __construct(public array $leg, public float $available)
    {
        $holder = ['shop' => 'in the shop', 'karigar' => 'with that karigar', 'refinery' => 'at the refinery'][$leg['holder']] ?? 'there';
        $kind = strtolower(RawMetalEntry::KINDS[$leg['kind']] ?? $leg['kind']);

        parent::__construct('Only ' . number_format($available, 3) . " g of {$leg['metal']} {$leg['carat']} ({$kind}) is {$holder}.");
    }
}
