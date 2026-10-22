<?php

namespace Tests\Unit\Engine;

use App\Engine\Engine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The server engine meets every hand-written expectation in tests/engine/fixtures. */
class EngineFixturesTest extends TestCase
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
    public function test_fixture(array $case): void
    {
        $r = Engine::evaluate($case['definition'], $case['answers'] ?? [], $case['context'] ?? []);
        $e = $case['expect'] ?? [];

        if (array_key_exists('valid', $e)) {
            $this->assertSame($e['valid'], $r['valid'], 'valid '.json_encode($r['errors']));
        }
        foreach (['unit_cents', 'total_cents', 'options_cents', 'formula_cents', 'markup_percent', 'base_cents'] as $k) {
            if (array_key_exists($k, $e)) {
                $this->assertEquals($e[$k], $r['price'][$k], $k);
            }
        }
        if (isset($e['errors'])) {
            $this->assertSame($e['errors'], array_column($r['errors'], 'code'));
        }
        if (array_key_exists('variant_id', $e)) {
            $this->assertSame($e['variant_id'], $r['variant_id']);
        }
        if (isset($e['visible_steps'])) {
            $this->assertSame($e['visible_steps'], $r['visible_steps']);
        }
        foreach ($e['answers'] ?? [] as $k => $v) {
            $this->assertEquals($v, $r['answers'][$k] ?? null, 'answer '.$k);
        }
        foreach ($e['absent_answers'] ?? [] as $k) {
            $this->assertArrayNotHasKey($k, $r['answers'], 'answer '.$k.' should be absent');
        }
        foreach ($e['hidden_options'] ?? [] as $f => $opts) {
            foreach ($opts as $o) {
                $this->assertFalse($r['fields'][$f]['options'][$o]['visible'], "$f:$o hidden");
            }
        }
        foreach ($e['disabled_options'] ?? [] as $f => $opts) {
            foreach ($opts as $o) {
                $this->assertTrue($r['fields'][$f]['options'][$o]['disabled'], "$f:$o disabled");
            }
        }
        foreach ($e['enabled_options'] ?? [] as $f => $opts) {
            foreach ($opts as $o) {
                $this->assertFalse($r['fields'][$f]['options'][$o]['disabled'], "$f:$o enabled");
            }
        }
        if (isset($e['layers'])) {
            $this->assertSame($e['layers'], array_column($r['layers'], 'id'));
        }
        foreach ($e['layer_images'] ?? [] as $id => $img) {
            $layer = collect($r['layers'])->firstWhere('id', $id);
            $this->assertSame($img, $layer['image'] ?? null);
        }
    }
}
