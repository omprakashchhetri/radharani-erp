<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** A packet for pieces a test needs "in stock": a piece with no packet is on hold. */
    protected function packetId(): int
    {
        return \App\Models\Stock\Packet::create(['code' => 'TP' . random_int(100000, 999999)])->id;
    }
}
