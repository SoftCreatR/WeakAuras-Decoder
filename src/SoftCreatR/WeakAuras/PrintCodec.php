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

/** The six-bit alphabet shared by WeakAuras and LibDeflate's print codec. */
final class PrintCodec
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789()';

    public static function decode(string $input): string
    {
        if ($input === '' || strlen($input) % 4 === 1 || strspn($input, self::ALPHABET) !== strlen($input)) {
            throw new InvalidArgumentException('Invalid WeakAuras printable encoding.');
        }

        $output = '';
        $length = strlen($input);

        for ($offset = 0; $offset < $length; $offset += 4) {
            $count = min(4, $length - $offset);
            $bits = 0;

            for ($i = 0; $i < $count; ++$i) {
                $bits |= strpos(self::ALPHABET, $input[$offset + $i]) << ($i * 6);
            }

            for ($i = 0; $i < intdiv($count * 6, 8); ++$i) {
                $output .= chr(($bits >> ($i * 8)) & 0xff);
            }
        }

        return $output;
    }

    public static function encode(string $input): string
    {
        $output = '';
        $length = strlen($input);

        for ($offset = 0; $offset < $length; $offset += 3) {
            $count = min(3, $length - $offset);
            $bits = 0;

            for ($i = 0; $i < $count; ++$i) {
                $bits |= ord($input[$offset + $i]) << ($i * 8);
            }

            for ($i = 0; $i < (int) ceil($count * 8 / 6); ++$i) {
                $output .= self::ALPHABET[($bits >> ($i * 6)) & 63];
            }
        }

        return $output;
    }
}
