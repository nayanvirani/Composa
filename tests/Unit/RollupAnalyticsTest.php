<?php

namespace Tests\Unit;

use App\Jobs\RollupAnalytics;
use PHPUnit\Framework\TestCase;

class RollupAnalyticsTest extends TestCase
{
    public function test_percentile_uses_nearest_rank(): void
    {
        $this->assertNull(RollupAnalytics::percentile([], 75));
        $this->assertSame(900, RollupAnalytics::percentile([900], 75));
        $this->assertSame(300, RollupAnalytics::percentile([400, 100, 300, 200], 75));
        $this->assertSame(8, RollupAnalytics::percentile(range(1, 10), 75));
    }
}
