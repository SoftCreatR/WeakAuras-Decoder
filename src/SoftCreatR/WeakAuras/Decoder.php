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

/** Decode legacy and current WeakAuras import strings and emit !WA:2! strings. */
final class Decoder
{
    public const int FORMAT_LEGACY = 0;
    public const int FORMAT_ACE_DEFLATE = 1;
    public const int FORMAT_CURRENT = 2;

    private const int MAX_EXPORT_LENGTH = 16777216;

    /** Return the decoded WeakAuras transmission envelope. */
    public static function decode(string $encodedAura): array
    {
        return self::decodeLossless($encodedAura)->toArray();
    }

    /** Preserve Lua key types and shared or cyclic table references. */
    public static function decodeLossless(string $encodedAura): LuaTable
    {
        $encodedAura = trim($encodedAura);

        if ($encodedAura === '' || strlen($encodedAura) > self::MAX_EXPORT_LENGTH) {
            throw new InvalidArgumentException('Empty or oversized WeakAuras import string.');
        }

        if (str_starts_with($encodedAura, '!WA:')) {
            if (!preg_match('/^!WA:(\d+)!([a-zA-Z0-9()]+)$/D', $encodedAura, $matches)) {
                throw new InvalidArgumentException('Malformed WeakAuras export prefix.');
            }

            if ($matches[1] !== '2') {
                throw new InvalidArgumentException('Unsupported WeakAuras export format version ' . $matches[1] . '.');
            }
            $payload = $matches[2];
            $serializer = 2;
        } elseif (str_starts_with($encodedAura, '!')) {
            $payload = substr($encodedAura, 1);
            $serializer = 1;
        } else {
            $payload = $encodedAura;
            $serializer = 0;
        }

        if ($serializer === 0) {
            if (!preg_match('/^[a-zA-Z0-9()]+$/D', $payload)) {
                throw new InvalidArgumentException('Invalid legacy WeakAuras export string.');
            }
            $value = AceSerializer::decode(LegacyDecoder::decodeSerialized($payload));
        } else {
            $compressed = PrintCodec::decode($payload);
            $serialized = @gzinflate($compressed, self::MAX_EXPORT_LENGTH);

            if ($serialized === false) {
                throw new InvalidArgumentException('Could not decompress WeakAuras export.');
            }
            $value = $serializer === 2 ? LibSerialize::decode($serialized) : AceSerializer::decode($serialized);
        }

        if (!$value instanceof LuaTable || $value->get('m') !== 'd' || !$value->get('d') instanceof LuaTable) {
            throw new InvalidArgumentException('The export does not contain a WeakAuras display.');
        }

        return $value;
    }

    /** Historical API: return the first decoded value inside an outer array. */
    public static function decodeAura(string $encodedAura): array
    {
        return [self::decode($encodedAura)];
    }

    /** Encode an envelope, defaulting to the format used by current WeakAuras. */
    public static function encode(array|LuaTable $aura, int $format = self::FORMAT_CURRENT): string
    {
        $value = $aura instanceof LuaTable ? $aura : LuaTable::fromArray($aura);

        if ($value->get('m') !== 'd' || !$value->get('d') instanceof LuaTable) {
            throw new InvalidArgumentException('Expected a WeakAuras display envelope with m and d fields.');
        }

        if ($format === self::FORMAT_LEGACY) {
            return PrintCodec::encode(LegacyEncoder::encodeSerialized(AceSerializer::encode($value)));
        }

        if ($format !== self::FORMAT_ACE_DEFLATE && $format !== self::FORMAT_CURRENT) {
            throw new InvalidArgumentException('Unsupported WeakAuras export format version ' . $format . '.');
        }
        $serialized = $format === self::FORMAT_CURRENT ? LibSerialize::encode($value) : AceSerializer::encode($value);
        $compressed = gzdeflate($serialized, 9);

        if ($compressed === false) {
            throw new InvalidArgumentException('Could not compress WeakAuras export.');
        }

        return ($format === self::FORMAT_CURRENT ? '!WA:2!' : '!') . PrintCodec::encode($compressed);
    }

    /** Re-encode a legacy export without changing the aura's addon-era fields. */
    public static function convert(string $encodedAura, int $format = self::FORMAT_CURRENT): string
    {
        return self::encode(self::decodeLossless($encodedAura), $format);
    }

    /** Locate values stored under a named field, including embedded Lua code. */
    public static function dumpLuaCode(array $array, string $dangerKey): array
    {
        $result = [];
        $walk = static function (array $values, array $path) use (&$walk, &$result, $dangerKey): void {
            foreach ($values as $key => $value) {
                $current = [...$path, $key];

                if ($key === $dangerKey) {
                    $result[] = ['path' => implode('.', $current), 'value' => $value];
                }

                if (is_array($value)) {
                    $walk($value, $current);
                }
            }
        };
        $walk($array, []);

        return $result;
    }
}
