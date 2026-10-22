<?php

namespace Tests\Unit\Engine;

use App\Engine\Engine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The server engine returns exactly what the browser engine returns for every fixture
 * (tests/engine/snapshots.json, written by `npm --prefix engine/js run snapshot`). This is the
 * guarantee that the price a shopper sees is the price the server signs.
 */
class EngineParityTest extends TestCase
{
    public static function cases(): array
    {
        $out = [];
        foreach (Fixtures::cases() as $case) {
            $out[Fixtures::key($case)] = [$case];
        }

        return $out;
    }

    #[DataProvider('cases')]
    public function test_matches_browser_engine(array $case): void
    {
        $path = Fixtures::dir().'/snapshots.json';
        $snapshots = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $key = Fixtures::key($case);
        $this->assertArrayHasKey($key, $snapshots, 'Snapshot missing — run npm --prefix engine/js run snapshot');

        $php = Engine::evaluate($case['definition'], $case['answers'] ?? [], $case['context'] ?? []);

        $this->assertSame(self::normalise($snapshots[$key]), self::normalise($php));
    }

    /** JSON round-trip with numbers as canonical strings and objects sorted, so 15 and 15.0 compare equal. */
    private static function normalise(mixed $v): mixed
    {
        if (is_array($v)) {
            if ($v === []) {
                return [];
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map([self::class, 'normalise'], $v);
        }
        if (is_int($v) || is_float($v)) {
            return 'n:'.rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        }

        return $v;
    }
}
