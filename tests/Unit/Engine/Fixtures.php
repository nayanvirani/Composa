<?php

namespace Tests\Unit\Engine;

/** Loads tests/engine/fixtures/*.json and resolves "@id" references, like engine/js/test/fixtures.js. */
final class Fixtures
{
    public static function dir(): string
    {
        return dirname(__DIR__, 2).'/engine';
    }

    /** @return list<array<string,mixed>> */
    public static function cases(): array
    {
        $cases = [];
        $files = glob(self::dir().'/fixtures/*.json') ?: [];
        sort($files);
        foreach ($files as $path) {
            foreach (json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) as $case) {
                $cases[] = $case + ['file' => basename($path)];
            }
        }
        $byId = [];
        foreach ($cases as $c) {
            if (isset($c['id'])) {
                $byId[$c['id']] = $c;
            }
        }
        $resolve = function (mixed $value, string $key) use (&$resolve, $byId): mixed {
            if (! is_string($value) || ! str_starts_with($value, '@')) {
                return $value;
            }
            $ref = $byId[substr($value, 1)] ?? throw new \RuntimeException('Unknown fixture reference '.$value);

            return $resolve($ref[$key] ?? [], $key);
        };

        return array_map(fn ($c) => ['definition' => $resolve($c['definition'], 'definition'), 'context' => $resolve($c['context'] ?? [], 'context')] + $c, $cases);
    }

    public static function key(array $case): string
    {
        return $case['file'].' :: '.$case['name'];
    }
}
