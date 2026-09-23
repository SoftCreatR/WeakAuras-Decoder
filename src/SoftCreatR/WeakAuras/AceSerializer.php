<?php

/*
 * Copyright (c) 2017-present, Sascha Greuel <hello@1-2.dev> and Contributors
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace SoftCreatR\WeakAuras;

use InvalidArgumentException;
use WeakMap;

/** AceSerializer-3.0 reader for unprefixed and ! WeakAuras exports. */
final class AceSerializer
{
    private int $offset = 0;
    private int $nodes = 0;

    private function __construct(private readonly string $input) {}

    public static function decode(string $input): LuaTable
    {
        if (strlen($input) > 16777216) {
            throw new InvalidArgumentException('AceSerializer payload exceeds the 16 MiB limit.');
        }

        $reader = new self($input);
        [$type] = $reader->token();

        if ($type !== '1') {
            throw new InvalidArgumentException('Unsupported AceSerializer header.');
        }

        $value = $reader->value(0);
        [$end] = $reader->token();

        if (!$value instanceof LuaTable || $end !== '^' || $reader->offset !== strlen($input)) {
            throw new InvalidArgumentException('Invalid AceSerializer terminator or root value.');
        }

        return $value;
    }

    public static function encode(LuaTable $value): string
    {
        $seen = new WeakMap();
        $nodes = 0;
        $result = '^1' . self::writeValue($value, $seen, $nodes, 0) . '^^';

        if (strlen($result) > 16777216) {
            throw new InvalidArgumentException('AceSerializer payload exceeds the 16 MiB limit.');
        }

        return $result;
    }

    private static function writeValue(mixed $value, WeakMap $seen, int &$nodes, int $depth): string
    {
        if ($depth > 128 || ++$nodes > 1000000) {
            throw new InvalidArgumentException('AceSerializer nesting or value limit exceeded.');
        }

        if (is_string($value)) {
            return '^S' . preg_replace_callback('/[\x00-\x20\x5e\x7e\x7f]/', static function (array $match): string {
                $byte = ord($match[0]);

                return match ($byte) {
                    30 => '~z',
                    94 => '~}',
                    126 => '~|',
                    127 => '~{',
                    default => '~' . chr($byte + 64),
                };
            }, $value);
        }

        if (is_int($value)) {
            if ($value > 9007199254740992 || $value < -9007199254740992) {
                throw new InvalidArgumentException('AceSerializer cannot preserve integers beyond Lua double precision.');
            }

            return '^N' . $value;
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                throw new InvalidArgumentException('AceSerializer cannot preserve NaN.');
            }

            if (is_finite($value)) {
                $number = sprintf('%.17g', $value);

                return '^N' . (strpbrk($number, '.eE') === false ? $number . '.0' : $number);
            }

            return '^N' . ($value === INF ? 'inf' : '-inf');
        }

        if (is_bool($value)) {
            return $value ? '^B' : '^b';
        }

        if ($value === null) {
            return '^Z';
        }

        if (!$value instanceof LuaTable) {
            throw new InvalidArgumentException('AceSerializer only accepts Lua tables and scalar values.');
        }

        if (isset($seen[$value])) {
            throw new InvalidArgumentException('AceSerializer cannot preserve shared or cyclic table references.');
        }

        $seen[$value] = true;
        $result = '^T';

        foreach ($value->entries() as [$key, $item]) {
            $result .= self::writeValue($key, $seen, $nodes, $depth + 1);
            $result .= self::writeValue($item, $seen, $nodes, $depth + 1);
        }

        return $result . '^t';
    }

    /** @return array{string, string} */
    private function token(): array
    {
        if (($this->input[$this->offset] ?? null) !== '^' || !isset($this->input[$this->offset + 1])) {
            throw new InvalidArgumentException('Truncated AceSerializer token.');
        }

        $type = $this->input[$this->offset + 1];
        $start = $this->offset + 2;
        $end = strpos($this->input, '^', $start);
        $end = $end === false ? strlen($this->input) : $end;
        $this->offset = $end;

        return [$type, substr($this->input, $start, $end - $start)];
    }

    private function value(int $depth): mixed
    {
        if ($depth > 128 || ++$this->nodes > 1000000) {
            throw new InvalidArgumentException('AceSerializer nesting or value limit exceeded.');
        }

        [$type, $data] = $this->token();

        return match ($type) {
            'S' => $this->string($data),
            'N' => $this->number($data),
            'F' => $this->float($data),
            'B' => true,
            'b' => false,
            'Z' => null,
            'T' => $this->table($depth),
            default => throw new InvalidArgumentException('Unknown AceSerializer value type ' . $type . '.'),
        };
    }

    private function table(int $depth): LuaTable
    {
        $entries = [];

        while (substr($this->input, $this->offset, 2) !== '^t') {
            $key = $this->value($depth + 1);
            $value = $this->value($depth + 1);
            $entries[] = [$key, $value];
        }

        $this->token();

        $numeric = [];
        $other = [];

        foreach ($entries as [$key, $value]) {
            if (is_int($key) && $key > 0) {
                $numeric[$key] = $value;
            } else {
                $other[] = [$key, $value];
            }
        }

        $arrayCount = 0;

        while (array_key_exists($arrayCount + 1, $numeric)) {
            ++$arrayCount;
        }

        $table = new LuaTable($arrayCount);

        for ($i = 1; $i <= $arrayCount; ++$i) {
            $table->set($i, $numeric[$i]);
            unset($numeric[$i]);
        }

        foreach ($numeric as $key => $value) {
            $table->set($key, $value);
        }

        foreach ($other as [$key, $value]) {
            $table->set($key, $value);
        }

        return $table;
    }

    private function string(string $data): string
    {
        return preg_replace_callback('/~./s', static function (array $match): string {
            $escaped = ord($match[0][1]);

            return match ($escaped) {
                122 => "\x1e",
                123 => "\x7f",
                124 => '~',
                125 => '^',
                default => $escaped >= 64 && $escaped <= 96
                    ? chr($escaped - 64)
                    : throw new InvalidArgumentException('Invalid AceSerializer string escape.'),
            };
        }, $data);
    }

    private function number(string $data): int|float
    {
        if ($data === '-1.#INF' || $data === '-inf') {
            return -INF;
        }

        if ($data === '1.#INF' || $data === 'inf') {
            return INF;
        }

        if (!is_numeric($data)) {
            throw new InvalidArgumentException('Invalid AceSerializer number.');
        }

        $integer = filter_var($data, FILTER_VALIDATE_INT);

        return strpbrk($data, '.eE') === false && $integer !== false ? $integer : (float) $data;
    }

    private function float(string $mantissa): float
    {
        [$type, $exponent] = $this->token();

        if ($type !== 'f' || !is_numeric($mantissa) || !is_numeric($exponent)) {
            throw new InvalidArgumentException('Invalid AceSerializer floating-point number.');
        }

        return (float) $mantissa * (2 ** (int) $exponent);
    }
}
