<?php

namespace App\Engine;

/**
 * Sandboxed price formula evaluator (no eval). Twin of engine/js/src/formula.js — keep the
 * grammar, functions and rounding identical; both run tests/engine/fixtures.
 *
 * Booleans are 1 and 0. Division or modulo by zero yields 0.
 */
final class Formula
{
    public const FUNCTIONS = ['min', 'max', 'round', 'ceil', 'floor', 'abs', 'if'];

    private const MAX_LENGTH = 500;

    private const MAX_NODES = 200;

    /** @var list<array{t:string,v:mixed}> */
    private array $tokens = [];

    private int $pos = 0;

    private int $nodes = 0;

    public static function roundHalfUp(float $value, int $digits = 0): float
    {
        $factor = 10 ** $digits;
        $sign = $value < 0 ? -1 : 1;

        return ($sign * floor(abs($value) * $factor + 0.5)) / $factor;
    }

    /** @return array<string,mixed> the parsed tree */
    public static function parse(string $source): array
    {
        $parser = new self;
        $parser->tokens = self::tokenize($source);

        $tree = $parser->orExpr();
        if ($parser->pos !== count($parser->tokens)) {
            throw new FormulaError('Unexpected '.$parser->tokens[$parser->pos]['v']);
        }

        return $tree;
    }

    /** @param array<string,mixed>|string $source */
    public static function evaluate(array|string $source, array $vars = []): float
    {
        $value = self::run(is_string($source) ? self::parse($source) : $source, $vars);
        if (! is_finite($value)) {
            throw new FormulaError('Formula result is not a number');
        }

        return $value;
    }

    /** @return list<string> variable names a formula reads */
    public static function variables(string $source): array
    {
        $out = [];
        $walk = function (array $n) use (&$walk, &$out): void {
            match ($n['k']) {
                'var' => $out[$n['n']] = true,
                'bin' => [$walk($n['l']), $walk($n['r'])],
                'un' => $walk($n['e']),
                'call' => array_map($walk, $n['a']),
                default => null,
            };
        };
        $walk(self::parse($source));

        return array_keys($out);
    }

    /** @return list<array{t:string,v:mixed}> */
    private static function tokenize(string $source): array
    {
        if (strlen($source) > self::MAX_LENGTH) {
            throw new FormulaError('Formula is too long');
        }
        $tokens = [];
        $i = 0;
        $n = strlen($source);
        while ($i < $n) {
            $c = $source[$i];
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $i++;

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $n && ctype_digit($source[$i + 1]))) {
                $j = $i;
                while ($j < $n && (ctype_digit($source[$j]) || $source[$j] === '.')) {
                    $j++;
                }
                $text = substr($source, $i, $j - $i);
                if (substr_count($text, '.') > 1) {
                    throw new FormulaError('Bad number '.$text);
                }
                $tokens[] = ['t' => 'num', 'v' => (float) $text];
                $i = $j;

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $n && (ctype_alnum($source[$j]) || $source[$j] === '_')) {
                    $j++;
                }
                $tokens[] = ['t' => 'id', 'v' => substr($source, $i, $j - $i)];
                $i = $j;

                continue;
            }
            $two = substr($source, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['t' => 'op', 'v' => $two];
                $i += 2;

                continue;
            }
            if (str_contains('+-*/%^()<>!,', $c)) {
                $tokens[] = ['t' => 'op', 'v' => $c];
                $i++;

                continue;
            }
            throw new FormulaError('Unexpected character '.$c);
        }

        return $tokens;
    }

    private function isOp(string $v): bool
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t !== null && $t['t'] === 'op' && $t['v'] === $v;
    }

    private function node(array $n): array
    {
        if (++$this->nodes > self::MAX_NODES) {
            throw new FormulaError('Formula is too complex');
        }

        return $n;
    }

    private function expect(string $v): void
    {
        if (! $this->isOp($v)) {
            throw new FormulaError('Expected '.$v);
        }
        $this->pos++;
    }

    /** @param list<string> $ops */
    private function binary(callable $next, array $ops): array
    {
        $left = $next();
        while (($t = $this->tokens[$this->pos] ?? null) !== null && $t['t'] === 'op' && in_array($t['v'], $ops, true)) {
            $this->pos++;
            $left = $this->node(['k' => 'bin', 'op' => $t['v'], 'l' => $left, 'r' => $next()]);
        }

        return $left;
    }

    private function orExpr(): array
    {
        return $this->binary(fn () => $this->andExpr(), ['||']);
    }

    private function andExpr(): array
    {
        return $this->binary(fn () => $this->eqExpr(), ['&&']);
    }

    private function eqExpr(): array
    {
        return $this->binary(fn () => $this->cmpExpr(), ['==', '!=']);
    }

    private function cmpExpr(): array
    {
        return $this->binary(fn () => $this->addExpr(), ['<', '<=', '>', '>=']);
    }

    private function addExpr(): array
    {
        return $this->binary(fn () => $this->mulExpr(), ['+', '-']);
    }

    private function mulExpr(): array
    {
        return $this->binary(fn () => $this->unary(), ['*', '/', '%']);
    }

    private function unary(): array
    {
        if ($this->isOp('-') || $this->isOp('!')) {
            $op = $this->tokens[$this->pos++]['v'];

            return $this->node(['k' => 'un', 'op' => $op, 'e' => $this->unary()]);
        }

        return $this->pow();
    }

    private function pow(): array
    {
        $base = $this->primary();
        if ($this->isOp('^')) {
            $this->pos++;

            return $this->node(['k' => 'bin', 'op' => '^', 'l' => $base, 'r' => $this->unary()]);
        }

        return $base;
    }

    private function primary(): array
    {
        $tok = $this->tokens[$this->pos] ?? null;
        if ($tok === null) {
            throw new FormulaError('Unexpected end of formula');
        }
        if ($tok['t'] === 'num') {
            $this->pos++;

            return $this->node(['k' => 'num', 'v' => $tok['v']]);
        }
        if ($tok['t'] === 'id') {
            $this->pos++;
            if ($this->isOp('(')) {
                $this->pos++;
                if (! in_array($tok['v'], self::FUNCTIONS, true)) {
                    throw new FormulaError('Unknown function '.$tok['v']);
                }
                $args = [];
                if (! $this->isOp(')')) {
                    $args[] = $this->orExpr();
                    while ($this->isOp(',')) {
                        $this->pos++;
                        $args[] = $this->orExpr();
                    }
                }
                $this->expect(')');

                return $this->node(['k' => 'call', 'f' => $tok['v'], 'a' => $args]);
            }

            return $this->node(['k' => 'var', 'n' => $tok['v']]);
        }
        if ($tok['t'] === 'op' && $tok['v'] === '(') {
            $this->pos++;
            $inner = $this->orExpr();
            $this->expect(')');

            return $inner;
        }
        throw new FormulaError('Unexpected '.$tok['v']);
    }

    private static function run(array $n, array $vars): float
    {
        switch ($n['k']) {
            case 'num':
                return (float) $n['v'];
            case 'var':
                if (! array_key_exists($n['n'], $vars)) {
                    throw new FormulaError('Unknown variable '.$n['n']);
                }

                return is_numeric($vars[$n['n']]) ? (float) $vars[$n['n']] : 0.0;
            case 'un':
                $v = self::run($n['e'], $vars);

                return $n['op'] === '-' ? -$v : ($v != 0 ? 0.0 : 1.0);
            case 'bin':
                $a = self::run($n['l'], $vars);
                $b = self::run($n['r'], $vars);

                return match ($n['op']) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0 ? 0.0 : $a / $b,
                    '%' => $b == 0 ? 0.0 : fmod($a, $b),
                    '^' => $a ** $b,
                    '<' => $a < $b ? 1.0 : 0.0,
                    '<=' => $a <= $b ? 1.0 : 0.0,
                    '>' => $a > $b ? 1.0 : 0.0,
                    '>=' => $a >= $b ? 1.0 : 0.0,
                    '==' => $a == $b ? 1.0 : 0.0,
                    '!=' => $a != $b ? 1.0 : 0.0,
                    '&&' => ($a != 0 && $b != 0) ? 1.0 : 0.0,
                    '||' => ($a != 0 || $b != 0) ? 1.0 : 0.0,
                };
            case 'call':
                $args = array_map(fn ($x) => self::run($x, $vars), $n['a']);

                return match ($n['f']) {
                    'min' => $args ? (float) min($args) : throw new FormulaError('min needs arguments'),
                    'max' => $args ? (float) max($args) : throw new FormulaError('max needs arguments'),
                    'round' => self::roundHalfUp($args[0] ?? 0.0, (int) ($args[1] ?? 0)),
                    'ceil' => ceil($args[0] ?? 0.0),
                    'floor' => floor($args[0] ?? 0.0),
                    'abs' => abs($args[0] ?? 0.0),
                    'if' => count($args) === 3 ? ($args[0] != 0 ? $args[1] : $args[2]) : throw new FormulaError('if needs 3 arguments'),
                };
        }
        throw new FormulaError('Bad formula');
    }
}
