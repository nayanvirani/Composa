<?php

namespace Tests\Unit\Engine;

use App\Engine\DefinitionValidator;
use App\Engine\Engine;
use App\Support\StarterTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Every starter template is publishable and prices its default answers. */
class StarterTemplatesTest extends TestCase
{
    public static function templates(): array
    {
        $out = [];
        foreach (StarterTemplates::all() as $t) {
            $out[$t['key']] = [json_decode(json_encode($t), true)];
        }

        return $out;
    }

    #[DataProvider('templates')]
    public function test_template_is_valid(array $template): void
    {
        $this->assertSame([], DefinitionValidator::validate($template['definition']), $template['key']);

        $r = Engine::evaluate($template['definition'], [], ['base_price_cents' => 10000]);
        $this->assertGreaterThanOrEqual(10000 - 800, $r['price']['unit_cents']);
    }
}
