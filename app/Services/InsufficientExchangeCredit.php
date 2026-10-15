<?php
namespace App\Services;

use RuntimeException;

/** More exchange credit was asked for than the exchange has left. The message is written to be shown to staff. */
class InsufficientExchangeCredit extends RuntimeException
{
    public function __construct(public int $exchangeId, public float $available)
    {
        parent::__construct('Exchange #' . $exchangeId . ' has only ₹' . number_format($available, 2) . ' of credit left.');
    }
}
