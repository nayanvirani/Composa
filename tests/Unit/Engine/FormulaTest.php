<?php

namespace Tests\Unit\Engine;

use App\Engine\Formula;
use App\Engine\FormulaError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Same cases as engine/js/test/formula.test.js (tests/engine/formula-cases.json). */
class FormulaTest extends TestCase
{
    public static function cases(): array
    {
        $out = [];
        foreach (json_decode((string) file_get_contents(Fixtures::dir().'/formula-cases.json'), true) as $c) {
            $out[$c['expr'].' '.json_encode($c['vars'] ?? [])] = [$c];
        }

        return $out;
    }

    #[DataProvider('cases')]
    public function test_formula(array $c): void
    {
        if (! empty($c['error'])) {
            $this->expectException(FormulaError::class);
            Formula::evaluate($c['expr'], $c['vars'] ?? []);

            return;
        }
        $this->assertEquals($c['value'], Formula::evaluate($c['expr'], $c['vars'] ?? []));
    }

    public function test_lists_variables(): void
    {
        $vars = Formula::variables('round(weight_g * metal_rate, 2) + if(rush, 10, 0)');
        sort($vars);
        $this->assertSame(['metal_rate', 'rush', 'weight_g'], $vars);
    }
}
