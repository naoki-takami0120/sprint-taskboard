<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Redis;

trait FlushesRedis
{
    protected function setUpFlushesRedis(): void
    {
        Redis::flushdb();
    }
}
