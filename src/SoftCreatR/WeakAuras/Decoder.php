<?php
namespace SoftCreatR\WeakAuras;

final class Decoder
{
    public static function decodeAura($encodedAura)
    {
        $decoded = self::decode7Bit($encodedAura);
        $decompressed = self::decompressHuffman($decoded);
        $deserialized = self::deserialize($decompressed);

        self::ksortRecursive($deserialized);

        return $deserialized;
    }

    public static function dumpLuaCode($array, $dangerKey)
    {
        $ret = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($array),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $key => $value) {
            if ($key === $dangerKey) {
                $keys = [$key];

                for ($i = $iterator->getDepth() - 1; $i >= 0; $i--) {
                    array_unshift($keys, $iterator->getSubIterator($i)->key());
                }

                $ret[] = [
                    'path' => implode('.', $keys),
                    'value' => $value
                ];
            }
        }

        return $ret;
    }

    private static function ksortRecursive(&$array, $sort_flags = SORT_REGULAR)
    {
        if (!is_array($array)) {
            return false;
        }

        ksort($array, $sort_flags);

        foreach ($array as &$arr) {
            self::ksortRecursive($arr, $sort_flags);
        }

        return true;
    }

    private static function uRShift($a, $b)
    {
        if ($b === 0) {
            return $a;
        }

        return ($a >> $b) & ~ (1 << (8 * PHP_INT_SIZE - 1) >> ($b - 1));
    }

    private static function deserializeStringHelper($escape)
    {
        if ($escape < "~\x7A") {
            return chr(ord($escape[1]) - 64);
        } elseif ($escape == "~\x7A") {
            return "\x1E";
        } elseif ($escape == "~\x7B") {
            return "\x7F";
        } elseif ($escape == "~\x7C") {
            return "\x7E";
        } elseif ($escape == "~\x7D") {
            return "\x5E";
        } else {
            throw new \Exception(
                'deserializeStringHelper got called for "' . $escape . '"?!?'
            );
        }
    }

    private static function deserializeNumberHelper($number)
    {
        if ($number == "-1.#INF" || $number == "-inf") {
            return -INF;
        } elseif ($number == "1.#INF" || $number == "inf") {
            return INF;
        } else {
            return $number - 0;
        }
    }

    private static function decode7Bit($str)
    {
        $b64ToByte = $bit8 = [];
        $ch = $i = $bitfieldLength = $bitfield = 0;

        $b64ToByte = array_flip(array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9), ['(', ')']));

        for ($i = 0; $i < strlen($str); $i += 4) {
            $a = isset($b64ToByte[$str[$i]]) ? $b64ToByte[$str[$i]] : 0;
            $b = isset($b64ToByte[$str[$i + 1]]) ? $b64ToByte[$str[$i + 1]] : 0;
            $c = isset($b64ToByte[$str[$i + 2]]) ? $b64ToByte[$str[$i + 2]] : 0;
            $d = isset($b64ToByte[$str[$i + 3]]) ? $b64ToByte[$str[$i + 3]] : 0;

            $bitfield = ($d << 18) + ($c << 12) + ($b << 6) + $a;

            $bit8[] = $bitfield & 255;
            $bit8[] = ($bitfield >> 8) & 255;
            $bit8[] = $bitfield >> 16;
        }

        return $bit8;
    }

    private static function decompressHuffman($compressed)
    {
        $bitfield = $bitfieldLength = 0;
        $map = $r = [];
        $i = 5;

        if ($compressed[0] == 1) {
            array_shift($compressed);

            $l = '';

            foreach ($compressed as $s) {
                $l .= chr($s);
            }

            return $l;
        } elseif ($compressed[0] != 3) {
            throw new \Exception(
                'Unknown compression codec (' . $a[0] . ')'
            );
        }

        $numSymbols = $compressed[1] + 1;
        $origSize = ($compressed[4] << 16) + ($compressed[3] << 8) + $compressed[2];

        if ($origSize === 0) {
            return '';
        }

        for ($n = 0; $n < $numSymbols; $n++) {
            $bitfield += $compressed[$i] << $bitfieldLength;
            $i++;
            $bitfieldLength += 8;
            $symbol = $bitfield & 255;
            $bitfield = self::uRShift($bitfield, 8);
            $bitfieldLength -= 8;

            do {
                $bitfield += $compressed[$i] << $bitfieldLength;
                $i++;
                $bitfieldLength += 8;
            } while (!($bitfield & self::uRShift($bitfield, 1)) && is_array($compressed) && $i < count($compressed));

            if (is_array($compressed) && $i >= count($compressed)) {
                throw new \Exception(
                    'Incomplete symbol table'
                );
            }

            for ($cut = 0, $l = 0, $code = 0; (self::uRShift($bitfield, $cut) & 3) < 3; $cut++, $l++) {
                if (self::uRShift($bitfield, $cut) & 1) {
                    $code += 1 << $l;
                    $cut++;
                }
            }

            if (!isset($map[$l])) {
                $map[$l] = [];
            }

            $map[$l][$code] = $symbol;
            $bitfield = self::uRShift($bitfield, ($cut + 2));
            $bitfieldLength -= ($cut + 2);
        }

        while (is_array($compressed) && $i <= count($compressed) && $bitfieldLength <= 32) {
            do {
                for ($l = $bitfieldLength - 7; $l <= $bitfieldLength; $l++) {
                    if (isset($map[$l])) {
                        $key = $bitfield & ((1 << $l) - 1);

                        if (isset($map[$l][$key])) {
                            $r[] = chr($map[$l][$key]);
                            $bitfield = self::uRShift($bitfield, $l);
                            $bitfieldLength -= $l;
                            $l = 0;

                            break;
                        }
                    }
                }
            } while ($l < $bitfieldLength);

            if (is_array($compressed) && $i == count($compressed)) {
                break;
            }

            $bitfield += $compressed[$i] << $bitfieldLength;
            $i++;
            $bitfieldLength += 8;
        }

        if ($bitfieldLength > 32) {
            throw new \Exception(
                'Single-symbol encoding is too long'
            );
        }

        return implode('', $r);
    }

    private static function deserializeValue(&$iter, $ctl, $data)
    {
        $res = null;

        if (!$ctl) {
            throw new \Exception(
                'Supplied data misses AceSerializer terminator ("^^")'
            );
        }

        if ($ctl == '^^') {
            return;
        }

        if ($ctl == "^S") {
            $res = preg_replace_callback(
                "|~.|i",
                function ($val) {
                    return self::deserializeStringHelper($val[0]);
                },
                $data
            );
        } elseif ($ctl == "^N") {
            $res = self::deserializeNumberHelper($data);

            if (!is_int($res) && !is_float($res)) {
                throw new \Exception(
                    'Invalid serialized number: "' . $data . '"'
                );
            }
        } elseif ($ctl == "^F") {
            $r = $iter->next();

            if ($r['ctl'] != "^f") {
                throw new \Exception(
                    'Invalid serialized floating-point number, expected "^f", not "' . $r['ctl'] . '"'
                );
            }

            $m = intval($data);
            $e = intval($r['data']);

            if (!($m && $e)) {
                throw new \Exception(
                    'Invalid serialized floating-point number, expected mantissa and exponent,' .
                    'got "' . strval($m) . '" and "' . strval($e) . '"'
                );
            }

            $res = $m * pow(2, $e);
        } elseif ($ctl == "^B") {
            $res = true;
        } elseif ($ctl == "^b") {
            $res = false;
        } elseif ($ctl == "^Z") {
            $res = null;
        } elseif ($ctl == "^T") {
            $res = [];

            while (true) {
                $r = $iter->next();

                if ($r['ctl'] == "^t") {
                    break;
                }

                $k = self::deserializeValue($iter, $r['ctl'], $r['data']);

                if ($k === null) {
                    throw new \Exception(
                        'Invalid AceSerializer table format (no table end marker1)'
                    );
                }

                if (is_numeric($k)) {
                    $k--;
                }

                $r = $iter->next();
                $v = self::deserializeValue($iter, $r['ctl'], $r['data']);

                if ($v === null) {
                    throw new \Exception(
                        'Invalid AceSerializer table format (no table end marker2)'
                    );
                }

                $res[$k] = $v;
            }
        } else {
            throw new \Exception(
                'Invalid AceSerializer control code "' . $ctl . '"'
            );
        }

        return $res;
    }

    private static function deserialize($str)
    {
        $str = str_replace('/[\x01-\x20\x7F]/', '', $str);
        $iter = new Iter($str);
        $r = $iter->next();

        if ($r == null || $r['ctl'] != "^1") {
            return;
        }

        try {
            $result = [];
            $r = null;

            while ($r = $iter->next()) {
                $res = self::deserializeValue($iter, $r['ctl'], $r['data']);

                if (!empty($res)) {
                    $result[] = $res;
                }
            }

            return $result;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    
    /**
    * Prevent creation of Decoder objects.
    */
    private function __construct()
    {
        // does nothing
    }
}
