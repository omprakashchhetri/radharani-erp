<?php
namespace App\Services;

class InsufficientAdvance extends \RuntimeException
{
    public function __construct(public readonly float $left)
    {
        parent::__construct('This customer only has ₹' . number_format($left, 2) . ' of advance left.');
    }
}
