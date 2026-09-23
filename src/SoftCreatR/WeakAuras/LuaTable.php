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

/** A Lua table with typed keys and object identity preserved. */
final class LuaTable
{
    /** @var list<array{0: mixed, 1: mixed}> */
    private array $entries = [];

    public function __construct(private readonly int $arrayCount = 0) {}

    public function set(mixed $key, mixed $value): void
    {
        $this->entries[] = [$key, $value];
    }

    /** @return list<array{0: mixed, 1: mixed}> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function arrayCount(): int
    {
        return $this->arrayCount;
    }

    public function get(string $key): mixed
    {
        foreach ($this->entries as [$entryKey, $value]) {
            if ($entryKey === $key) {
                return $value;
            }
        }

        return null;
    }

    public static function fromArray(array $values): self
    {
        $list = array_is_list($values);
        $table = new self($list ? count($values) : 0);

        foreach ($values as $key => $value) {
            $table->set($list ? $key + 1 : $key, is_array($value) ? self::fromArray($value) : $value);
        }

        return $table;
    }

    /** Convert to familiar PHP arrays when doing so does not discard keys or cycles. */
    public function toArray(): array
    {
        $active = new WeakMap();
        $seen = new WeakMap();

        return $this->convert($active, $seen);
    }

    private function convert(WeakMap $active, WeakMap $seen): array
    {
        if (isset($active[$this])) {
            throw new InvalidArgumentException('A cyclic Lua table cannot be represented as a PHP array. Use decodeLossless().');
        }

        if (isset($seen[$this])) {
            throw new InvalidArgumentException('A shared Lua table reference would lose identity in a PHP array. Use decodeLossless().');
        }

        $active[$this] = true;
        $seen[$this] = true;
        $result = [];

        foreach ($this->entries as [$key, $value]) {
            if (!is_int($key) && !is_string($key)) {
                throw new InvalidArgumentException('A Lua table key cannot be represented as a PHP array key. Use decodeLossless().');
            }

            if (is_string($key) && preg_match('/^(0|-?[1-9]\d*)$/D', $key)) {
                throw new InvalidArgumentException('A numeric string Lua key would change type in PHP. Use decodeLossless().');
            }

            if (is_int($key) && $key >= 1 && $key <= $this->arrayCount) {
                --$key;
            }

            if (array_key_exists($key, $result)) {
                throw new InvalidArgumentException('Lua table keys collide in a PHP array. Use decodeLossless().');
            }

            $result[$key] = $value instanceof self ? $value->convert($active, $seen) : $value;
        }

        unset($active[$this]);

        return $result;
    }
}
