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

/** LibSerialize's version 1 wire format, used by !WA:2! exports. */
final class LibSerialize
{
    private string $input = '';
    private int $offset = 0;
    private int $nodes = 0;
    private array $stringRefs = [];
    private array $tableRefs = [];
    private array $writtenTableRefs = [];
    private int $writtenTableCount = 0;

    public static function decode(string $input): mixed
    {
        if (strlen($input) > 16777216) {
            throw new InvalidArgumentException('Serialized aura exceeds the 16 MiB limit.');
        }

        $codec = new self();
        $codec->input = $input;
        $version = $codec->readInt(1);

        if ($version !== 1 && $version !== 2) {
            throw new InvalidArgumentException('Unsupported LibSerialize version.');
        }

        $value = $codec->readValue(0);

        if ($codec->offset !== strlen($input)) {
            throw new InvalidArgumentException('Unexpected trailing serialized values.');
        }

        return $value;
    }

    public static function encode(mixed $value): string
    {
        $codec = new self();
        $codec->input = "\x01";
        $codec->writeValue($value, 0);

        if (strlen($codec->input) > 16777216) {
            throw new InvalidArgumentException('Serialized aura exceeds the 16 MiB limit.');
        }

        return $codec->input;
    }

    private function read(int $length): string
    {
        if ($length < 0 || $length > strlen($this->input) - $this->offset) {
            throw new InvalidArgumentException('Truncated LibSerialize payload.');
        }

        $value = substr($this->input, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    private function readInt(int $length): int
    {
        $bytes = $this->read($length);
        $number = 0;

        for ($i = 0; $i < $length; ++$i) {
            $number = ($number << 8) | ord($bytes[$i]);
        }

        return $number;
    }

    private function readString(int $length): string
    {
        $value = $this->read($length);

        if ($length > 2) {
            $this->stringRefs[] = $value;
        }

        return $value;
    }

    private function readTable(int $arrayCount, int $mapCount, int $depth): LuaTable
    {
        if ($arrayCount + $mapCount > 1000000 - $this->nodes) {
            throw new InvalidArgumentException('LibSerialize table is too large.');
        }

        $result = new LuaTable($arrayCount);
        $this->tableRefs[] = $result;

        for ($i = 0; $i < $arrayCount; ++$i) {
            $result->set($i + 1, $this->readValue($depth + 1));
        }

        for ($i = 0; $i < $mapCount; ++$i) {
            $key = $this->readValue($depth + 1);

            $result->set($key, $this->readValue($depth + 1));
        }

        return $result;
    }

    private function readValue(int $depth): mixed
    {
        if ($depth > 128 || ++$this->nodes > 1000000) {
            throw new InvalidArgumentException('LibSerialize nesting or value limit exceeded.');
        }

        $tag = $this->readInt(1);

        if (($tag & 1) === 1) {
            return ($tag - 1) >> 1;
        }

        if (($tag & 3) === 2) {
            $kind = ($tag >> 2) & 3;
            $count = $tag >> 4;

            return match ($kind) {
                0 => $this->readString($count),
                1 => $this->readTable(0, $count, $depth),
                2 => $this->readTable($count, 0, $depth),
                3 => $this->readTable(($count & 3) + 1, ($count >> 2) + 1, $depth),
            };
        }

        if (($tag & 7) === 4) {
            $packed = ($this->readInt(1) << 8) | $tag;

            return ($tag & 8) ? -(($packed - 12) >> 4) : (($packed - 4) >> 4);
        }

        if (($tag & 7) !== 0) {
            throw new InvalidArgumentException('Invalid LibSerialize type tag.');
        }

        $kind = $tag >> 3;

        if ($kind === 0) {
            return null;
        }

        if ($kind >= 1 && $kind <= 8) {
            $size = [1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 4, 7 => 7, 8 => 7][$kind];
            $value = $this->readInt($size);

            return $kind % 2 === 0 ? -$value : $value;
        }

        if ($kind === 9) {
            return unpack('E', $this->read(8))[1];
        }

        if ($kind === 10 || $kind === 11) {
            $number = $this->read($this->readInt(1));

            if (!is_numeric($number)) {
                throw new InvalidArgumentException('Invalid serialized number.');
            }

            $integer = filter_var($number, FILTER_VALIDATE_INT);
            $value = str_contains($number, '.') || stripos($number, 'e') !== false || $integer === false
                ? (float) $number
                : $integer;

            return $kind === 11 ? -$value : $value;
        }

        if ($kind === 12) {
            return true;
        }

        if ($kind === 13) {
            return false;
        }

        if ($kind >= 14 && $kind <= 16) {
            return $this->readString($this->readInt($kind - 13));
        }

        if ($kind >= 17 && $kind <= 19) {
            return $this->readTable(0, $this->readInt($kind - 16), $depth);
        }

        if ($kind >= 20 && $kind <= 22) {
            return $this->readTable($this->readInt($kind - 19), 0, $depth);
        }

        if ($kind >= 23 && $kind <= 25) {
            $size = $kind - 22;

            return $this->readTable($this->readInt($size), $this->readInt($size), $depth);
        }

        if ($kind >= 26 && $kind <= 28) {
            $index = $this->readInt($kind - 25) - 1;

            if (!array_key_exists($index, $this->stringRefs)) {
                throw new InvalidArgumentException('Invalid string reference.');
            }

            return $this->stringRefs[$index];
        }

        if ($kind >= 29 && $kind <= 31) {
            $index = $this->readInt($kind - 28) - 1;

            if (!array_key_exists($index, $this->tableRefs)) {
                throw new InvalidArgumentException('Invalid table reference.');
            }

            return $this->tableRefs[$index];
        }

        throw new InvalidArgumentException('Unknown LibSerialize type tag.');
    }

    private function writeInt(int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; --$i) {
            $this->input .= chr(($value >> ($i * 8)) & 0xff);
        }
    }

    private function writeCount(int $type, int $count): void
    {
        if ($count < 16) {
            $this->writeInt(($count << 4) | ($type << 2) | 2, 1);

            return;
        }

        $size = $count < 256 ? 1 : ($count < 65536 ? 2 : 3);

        if ($count > 16777215) {
            throw new InvalidArgumentException('LibSerialize collection is too large.');
        }

        $this->writeInt((($type === 0 ? 13 : ($type === 1 ? 16 : 19)) + $size) << 3, 1);
        $this->writeInt($count, $size);
    }

    private function writeValue(mixed $value, int $depth): void
    {
        if ($depth > 128 || ++$this->nodes > 1000000) {
            throw new InvalidArgumentException('LibSerialize nesting or value limit exceeded.');
        }

        if ($value === null) {
            $this->writeInt(0, 1);

            return;
        }

        if (is_bool($value)) {
            $this->writeInt($value ? 96 : 104, 1);

            return;
        }

        if (is_int($value)) {
            if ($value >= 0 && $value < 128) {
                $this->writeInt($value * 2 + 1, 1);

                return;
            }

            if ($value > -4096 && $value < 4096) {
                $number = abs($value) * 16 + ($value < 0 ? 12 : 4);
                $this->writeInt($number & 255, 1);
                $this->writeInt($number >> 8, 1);

                return;
            }

            if ($value > 9007199254740992 || $value < -9007199254740992) {
                $number = (string) $value;
                $this->writeInt(80, 1);
                $this->writeInt(strlen($number), 1);
                $this->input .= $number;

                return;
            }

            $magnitude = abs($value);
            $size = $magnitude < 65536 ? 2 : ($magnitude < 16777216 ? 3 : ($magnitude < 4294967296 ? 4 : 7));
            $type = [2 => 1, 3 => 3, 4 => 5, 7 => 7][$size] + ($value < 0 ? 1 : 0);
            $this->writeInt($type << 3, 1);
            $this->writeInt($magnitude, $size);

            return;
        }

        if (is_float($value)) {
            $this->writeInt(72, 1);
            $this->input .= pack('E', $value);

            return;
        }

        if (is_string($value)) {
            $length = strlen($value);

            if ($length > 2 && isset($this->stringRefs[$value])) {
                $index = $this->stringRefs[$value];
                $size = $index < 256 ? 1 : ($index < 65536 ? 2 : 3);
                $this->writeInt((25 + $size) << 3, 1);
                $this->writeInt($index, $size);

                return;
            }

            $this->writeCount(0, $length);
            $this->input .= $value;

            if ($length > 2) {
                $this->stringRefs[$value] = count($this->stringRefs) + 1;
            }

            return;
        }

        if ($value instanceof LuaTable) {
            $id = spl_object_id($value);

            if (isset($this->writtenTableRefs[$id])) {
                $index = $this->writtenTableRefs[$id];
                $size = $index < 256 ? 1 : ($index < 65536 ? 2 : 3);
                $this->writeInt((28 + $size) << 3, 1);
                $this->writeInt($index, $size);

                return;
            }

            $this->writtenTableRefs[$id] = ++$this->writtenTableCount;
            $entries = $value->entries();
            $arrayCount = $value->arrayCount();
            $mapCount = count($entries) - $arrayCount;

            if ($mapCount < 0) {
                throw new InvalidArgumentException('Invalid Lua table array count.');
            }

            if ($arrayCount > 0 && $mapCount > 0) {
                if ($arrayCount <= 4 && $mapCount <= 4) {
                    $count = ($mapCount - 1) * 4 + $arrayCount - 1;
                    $this->writeInt(($count << 4) | 14, 1);
                } else {
                    $size = max($arrayCount, $mapCount) < 256 ? 1 : (max($arrayCount, $mapCount) < 65536 ? 2 : 3);
                    $this->writeInt((22 + $size) << 3, 1);
                    $this->writeInt($arrayCount, $size);
                    $this->writeInt($mapCount, $size);
                }
            } else {
                $this->writeCount($arrayCount > 0 ? 2 : 1, count($entries));
            }

            foreach ($entries as $i => [$key, $item]) {
                if ($i >= $arrayCount) {
                    $this->writeValue($key, $depth + 1);
                } elseif ($key !== $i + 1) {
                    throw new InvalidArgumentException('Invalid Lua table array key.');
                }

                $this->writeValue($item, $depth + 1);
            }

            return;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('Only LuaTable, PHP arrays, and scalar values can be serialized.');
        }

        ++$this->writtenTableCount;

        $list = array_is_list($value);
        $this->writeCount($list ? 2 : 1, count($value));

        foreach ($value as $key => $item) {
            if (!$list) {
                $this->writeValue($key, $depth + 1);
            }

            $this->writeValue($item, $depth + 1);
        }
    }
}
