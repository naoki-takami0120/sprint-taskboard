<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait CountsQueries
{
    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $action();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
