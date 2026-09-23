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

/** Write a LibCompress payload using its LZW or uncompressed method. */
final class LegacyEncoder
{
    public static function encodeSerialized(string $serialized): string
    {
        if (strlen($serialized) > 16777216) {
            throw new InvalidArgumentException('Legacy aura exceeds the 16 MiB limit.');
        }

        if ($serialized === '') {
            return "\x01";
        }

        $dictionary = [];

        for ($i = 0; $i < 256; ++$i) {
            $dictionary[chr($i)] = $i;
        }

        $nextCode = 256;
        $output = "\x02";
        $previous = '';

        for ($i = 0, $length = strlen($serialized); $i < $length; ++$i) {
            $character = $serialized[$i];
            $candidate = $previous . $character;

            if (isset($dictionary[$candidate])) {
                $previous = $candidate;

                continue;
            }

            $dictionary[$candidate] = $nextCode++;
            $output .= self::writeCode($dictionary[$previous]);
            $previous = $character;
        }

        $output .= self::writeCode($dictionary[$previous]);

        return strlen($output) < strlen($serialized) + 1 ? $output : "\x01" . $serialized;
    }

    private static function writeCode(int $code): string
    {
        $digits = [];

        do {
            $digits[] = $code % 255;
            $code = intdiv($code, 255);
        } while ($code > 0);

        if (count($digits) === 1 && $digits[0] > 0 && $digits[0] < 250) {
            return chr($digits[0]);
        }

        $result = chr(256 - count($digits));

        foreach ($digits as $digit) {
            $result .= chr($digit + 1);
        }

        return $result;
    }
}
