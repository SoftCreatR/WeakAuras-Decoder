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

/** LibCompress transport used before the !WA:2! export format. */
final class LegacyDecoder
{
    public static function decodeSerialized(string $encoded): string
    {
        $compressed = PrintCodec::decode($encoded);
        $length = strlen($compressed);

        if ($length < 1) {
            throw new InvalidArgumentException('Empty LibCompress payload.');
        }

        $method = ord($compressed[0]);

        if ($method === 1) {
            return substr($compressed, 1);
        }

        if ($method === 2) {
            return self::decodeLzw($compressed);
        }

        if ($length < 5 || $method !== 3) {
            throw new InvalidArgumentException('Unsupported LibCompress codec.');
        }

        $symbolCount = ord($compressed[1]) + 1;
        $originalSize = ord($compressed[2]) | (ord($compressed[3]) << 8) | (ord($compressed[4]) << 16);

        if ($originalSize > 16777216) {
            throw new InvalidArgumentException('Legacy aura exceeds the 16 MiB limit.');
        }

        if ($originalSize === 0) {
            return '';
        }

        $offset = 5;
        $bits = 0;
        $bitCount = 0;
        $codes = [];
        $maxCodeLength = 0;

        for ($i = 0; $i < $symbolCount; ++$i) {
            self::fill($compressed, $offset, $bits, $bitCount, 8);

            $symbol = $bits & 255;
            $bits >>= 8;
            $bitCount -= 8;

            // LibCompress uses a pair of one-bits to end each codeword.
            do {
                self::fill($compressed, $offset, $bits, $bitCount, $bitCount + 8);
            } while (($bits & ($bits >> 1)) === 0);

            $cut = 0;
            $codeLength = 0;
            $code = 0;

            while ((($bits >> $cut) & 3) !== 3) {
                if ($cut >= 40) {
                    throw new InvalidArgumentException('Invalid LibCompress symbol table.');
                }

                if (($bits >> $cut) & 1) {
                    $code |= 1 << $codeLength;
                    ++$cut;
                }

                ++$cut;
                ++$codeLength;
            }

            $codes[$codeLength][$code] = $symbol;
            $maxCodeLength = max($maxCodeLength, $codeLength);
            $bits >>= $cut + 2;
            $bitCount -= $cut + 2;
        }

        if ($maxCodeLength < 1 || $maxCodeLength > 32) {
            throw new InvalidArgumentException('Invalid LibCompress code length.');
        }

        $output = '';

        while (strlen($output) < $originalSize) {
            $found = false;

            for ($codeLength = 1; $codeLength <= $maxCodeLength; ++$codeLength) {
                self::fill($compressed, $offset, $bits, $bitCount, $codeLength);
                $code = $bits & ((1 << $codeLength) - 1);

                if (isset($codes[$codeLength][$code])) {
                    $output .= chr($codes[$codeLength][$code]);
                    $bits >>= $codeLength;
                    $bitCount -= $codeLength;
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                throw new InvalidArgumentException('Invalid LibCompress Huffman code.');
            }
        }

        return $output;
    }

    private static function decodeLzw(string $compressed): string
    {
        $offset = 1;
        $length = strlen($compressed);

        if ($offset === $length) {
            throw new InvalidArgumentException('Empty LibCompress LZW payload.');
        }

        $first = self::readLzwCode($compressed, $offset);

        if ($first > 255) {
            throw new InvalidArgumentException('Invalid first LibCompress LZW code.');
        }

        $dictionary = [];
        $nextCode = 256;
        $previous = chr($first);
        $output = $previous;

        while ($offset < $length) {
            $code = self::readLzwCode($compressed, $offset);

            if ($code < 256) {
                $entry = chr($code);
            } elseif (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode) {
                $entry = $previous . $previous[0];
            } else {
                throw new InvalidArgumentException('Invalid LibCompress LZW code.');
            }

            if (strlen($entry) > 16777216 - strlen($output)) {
                throw new InvalidArgumentException('Legacy aura exceeds the 16 MiB limit.');
            }

            $output .= $entry;
            $dictionary[$nextCode++] = $previous . $entry[0];
            $previous = $entry;
        }

        return $output;
    }

    private static function readLzwCode(string $data, int &$offset): int
    {
        $first = ord($data[$offset++]);

        if ($first <= 249) {
            return $first;
        }

        $digits = 256 - $first;

        if ($digits > strlen($data) - $offset) {
            throw new InvalidArgumentException('Truncated LibCompress LZW code.');
        }

        $code = 0;

        for ($i = $offset + $digits - 1; $i >= $offset; --$i) {
            $digit = ord($data[$i]);

            if ($digit === 0) {
                throw new InvalidArgumentException('Invalid LibCompress LZW digit.');
            }

            $code = $code * 255 + $digit - 1;
        }

        $offset += $digits;

        return $code;
    }

    private static function fill(string $data, int &$offset, int &$bits, int &$bitCount, int $required): void
    {
        while ($bitCount < $required) {
            if (!isset($data[$offset])) {
                throw new InvalidArgumentException('Truncated LibCompress payload.');
            }
            $bits |= ord($data[$offset++]) << $bitCount;
            $bitCount += 8;
        }
    }
}
