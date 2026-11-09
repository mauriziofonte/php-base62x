<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Encoding;

use Mfonte\Base62x\Exception\DecodeException;

/**
 * Low-level, binary safe Base62x encoder/decoder.
 *
 * Port of the reference implementation by Wade Lau (Wadelau@ufqi.com):
 *
 * @see https://github.com/wadelau/Base62x
 * @see https://ieeexplore.ieee.org/document/6020065
 *
 * Two output flavors exist:
 *  - "ASCII" (asctype 1): used when the input is made of printable-ish ASCII bytes only. Bytes are kept as-is,
 *    except symbols and control chars, that are escaped as "x" + one alphanumeric char. A trailing "x" marks it.
 *  - "binary" (asctype 0): every 3 bytes become 4 symbols of 6 bits; symbols 61, 62 and 63 are escaped as "x1",
 *    "x2" and "x3".
 *
 * @internal This class is not part of the public API: use \Mfonte\Base62x\Base62x
 */
class Base62x
{
    public const XTAG = 'x';
    public const ENCD = '-enc';
    public const DECD = '-dec';
    public const DEBG = '-v';
    public const CVTN = '-n';
    public const b62x = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b',
        'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
        'q', 'r', 's', 't', 'u', 'v', 'w', 'y', 'z', '1', '2', '3', 'x', ];
    public const bpos = 60; // 0-60 chars
    public const xpos = 64; // b62x[64] = 'x'
    public const ascmax = 127;
    public const asclist = ['4', '5', '6', '7', '8', '9', '0',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
        'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b',
        'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
        'q', 'r', 's', 't', 'u', 'v', 'w', 'y', 'z', ]; // 58
    public const max_safe_base = 36;
    public const base59 = 59;

    /**
     * Byte ranges [start, end) of the ASCII bytes that are escaped in the "ASCII" flavor, mapped in order to asclist.
     */
    private const ASCII_ESCAPED_RANGES = [[0, 17], [21, 28], [32, 48], [58, 65], [91, 97], [123, self::ascmax + 1]];

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var bool
     */
    public $isdebug = false;

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var int
     */
    public $i = 0;

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var int
     */
    public $codetype = 0;

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var array<int|string, int>
     */
    public static $rb62x = [];

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var array<int, string>
     */
    public $ascidx = [];

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var array<int|string, int>
     */
    public $ascrlist = [];

    /**
     * @deprecated unused, kept for backward compatibility
     *
     * @var float
     */
    public static $ver = 0.9;

    /**
     * Encodes a (binary) string, or converts a number from base $ibase to Base62x when $ibase is given.
     *
     * @param string|int|null $input
     * @param int|null        $ibase Input base (2, 8, 10, 16, 32...) for numeric conversion
     *
     * @return string|int|null
     */
    public static function encode($input, $ibase = null)
    {
        if ($input === null || $input === '') {
            return $input;
        }

        if ($ibase !== null && $ibase > 0) {
            $decimal = self::xx2dec((string) $input, (int) $ibase, self::reverseAlphabet());

            return self::dec2xx($decimal, self::xpos, self::b62x);
        }

        $input = (string) $input;

        return self::isAsciiEncodable($input) ? self::encodeAscii($input) : self::encodeBinary($input);
    }

    /**
     * Decodes a Base62x string, or converts a Base62x number to base $obase when $obase is given.
     *
     * @param string|int|null $input
     * @param int|null        $obase Output base (2, 8, 10, 16, 32...) for numeric conversion
     *
     * @throws DecodeException if the input is not valid Base62x
     *
     * @return string|int|null
     */
    public static function decode($input, $obase = null)
    {
        if ($input === null || $input === '') {
            return $input;
        }

        if ($obase !== null && $obase > 0) {
            $decimal = self::xx2dec((string) $input, self::xpos, self::reverseAlphabet());

            return self::dec2xx($decimal, (int) $obase, self::b62x);
        }

        $input = (string) $input;

        return substr($input, -1) === self::XTAG ? self::decodeAscii($input) : self::decodeBinary($input);
    }

    /**
     * Converts a number from base $ibase to base 10.
     *
     * @param string|int             $inum
     * @param int                    $ibase
     * @param array<int|string, int> $ridx  Reverse alphabet (char => value)
     *
     * @return string|int|float
     */
    public static function xx2dec($inum, $ibase, $ridx)
    {
        $inum = (string) $inum;
        $ibase = (int) $ibase;

        if ($ibase <= self::max_safe_base) {
            return base_convert($inum, $ibase, 10);
        }

        if ($ibase > self::base59 && $ibase < self::xpos) {
            // base 60, 61, 62: "x", "y" and "z" are digits
            $ridx['x'] = 59;
            $ridx['y'] = 60;
            $ridx['z'] = 61;
        }

        $digits = array_reverse(str_split($inum));
        $count = \count($digits);
        $isBase62x = ($ibase === self::xpos);
        $onum = 0;
        $xnum = 0;
        for ($i = 0; $i < $count; ++$i) {
            if ($isBase62x && isset($digits[$i + 1]) && $digits[$i + 1] === self::XTAG) {
                $value = self::bpos + ($ridx[$digits[$i]] ?? 0);
                ++$xnum;
                ++$i;
            } else {
                $value = $ridx[$digits[$i]] ?? 0;
            }
            if ($value >= $ibase) {
                $value = $ibase - 1;
            }
            $onum += $value * $ibase ** ($i - $xnum);
        }

        if (strpos((string) $onum, 'E') !== false) {
            // lost precision due to a too large number
            return number_format((float) $onum);
        }

        return $onum;
    }

    /**
     * Converts a number from base 10 to base $obase.
     *
     * @param string|int|float  $inum
     * @param int               $obase
     * @param array<int,string> $idx   Alphabet (value => char)
     *
     * @return string
     */
    public static function dec2xx($inum, $obase, $idx)
    {
        $obase = (int) $obase;

        if ($obase <= self::max_safe_base) {
            return base_convert((string) $inum, 10, $obase);
        }

        $isBase62x = false;
        if ($obase > self::base59 && $obase < self::xpos) {
            $idx[59] = 'x';
            $idx[60] = 'y';
            $idx[61] = 'z';
        } elseif ($obase === self::xpos) {
            $isBase62x = true;
        }
        $maxPos = $isBase62x ? self::bpos : self::bpos + 1; // cover all 0-61 chars

        $inum = (float) $inum;
        $digits = [];
        while ($inum >= $obase) {
            $b = (int) $inum % $obase;
            $inum = floor($inum / $obase);
            self::appendDigit($digits, $b, $maxPos, $idx);
        }
        if ($inum > 0) {
            self::appendDigit($digits, (int) $inum, $maxPos, $idx);
        }

        return implode('', array_reverse($digits));
    }

    /**
     * Whether the "ASCII" flavor can be used for the input.
     * NOTE: the first byte is deliberately not checked against the control chars ranges, as in the reference implementation.
     */
    private static function isAsciiEncodable(string $input): bool
    {
        if (\ord($input[0]) > self::ascmax) {
            return false;
        }

        $length = \strlen($input);
        for ($i = 1; $i < $length; ++$i) {
            $byte = \ord($input[$i]);
            if ($byte > self::ascmax
                || ($byte > 16 && $byte < 21) // DC1-4
                || ($byte > 27 && $byte < 32)) { // FS, GS, RS, US
                return false;
            }
        }

        return true;
    }

    private static function encodeAscii(string $input): string
    {
        $escapes = self::asciiEscapes();
        $output = '';
        $length = \strlen($input);
        for ($i = 0; $i < $length; ++$i) {
            $byte = \ord($input[$i]);
            if (isset($escapes[$byte])) {
                $output .= self::XTAG.$escapes[$byte];
            } elseif ($input[$i] === self::XTAG) {
                $output .= self::XTAG.self::XTAG;
            } else {
                $output .= $input[$i];
            }
        }

        // the "ASCII" flavor has a tag "x" appended
        return $output.self::XTAG;
    }

    /**
     * @throws DecodeException
     */
    private static function decodeAscii(string $input): string
    {
        $unescapes = array_flip(self::asciiEscapes());
        $output = '';
        $length = \strlen($input) - 1; // strip the trailing tag
        for ($i = 0; $i < $length; ++$i) {
            if ($input[$i] !== self::XTAG) {
                $output .= $input[$i];
                continue;
            }

            if (++$i >= $length) {
                throw new DecodeException();
            }
            if ($input[$i] === self::XTAG) {
                $output .= self::XTAG;
            } elseif (isset($unescapes[$input[$i]])) {
                $output .= \chr($unescapes[$input[$i]]);
            } else {
                throw new DecodeException();
            }
        }

        return $output;
    }

    public static function encodeBinary(string $input): string
    {
        $output = '';
        $length = \strlen($input);
        for ($i = 0; $i < $length; $i += 3) {
            $b0 = \ord($input[$i]);
            $remaining = $length - $i;
            if ($remaining > 2) {
                $b1 = \ord($input[$i + 1]);
                $b2 = \ord($input[$i + 2]);
                $symbols = [$b0 >> 2, (($b0 & 0x03) << 4) | ($b1 >> 4), (($b1 & 0x0F) << 2) | ($b2 >> 6), $b2 & 0x3F];
            } elseif ($remaining === 2) {
                $b1 = \ord($input[$i + 1]);
                $symbols = [$b0 >> 2, (($b0 & 0x03) << 4) | ($b1 >> 4), $b1 & 0x0F];
            } else {
                $symbols = [$b0 >> 2, $b0 & 0x03];
            }

            foreach ($symbols as $symbol) {
                $output .= $symbol > self::bpos ? self::XTAG.self::b62x[$symbol] : self::b62x[$symbol];
            }
        }

        return $output;
    }

    /**
     * @throws DecodeException
     */
    public static function decodeBinary(string $input): string
    {
        $reverse = self::reverseAlphabet();
        $output = '';
        $length = \strlen($input);
        $i = 0;
        while ($i < $length) {
            if ($length - $i < 2) {
                // a single trailing character cannot encode a full byte: the input is malformed
                throw new DecodeException();
            }

            // read up to 4 symbols of 6 bits
            $symbols = [];
            while (\count($symbols) < 4 && $i < $length) {
                $char = $input[$i++];
                if ($char === self::XTAG) {
                    $next = $input[$i++] ?? '';
                    if ($next !== '1' && $next !== '2' && $next !== '3') {
                        throw new DecodeException();
                    }
                    $symbols[] = self::bpos + (int) $next;
                } elseif (isset($reverse[$char])) {
                    $symbols[] = $reverse[$char];
                } else {
                    throw new DecodeException();
                }
            }

            $output .= self::symbolsToBytes($symbols);
        }

        return $output;
    }

    /**
     * @param list<int> $symbols 1 to 4 symbols of 6 bits
     */
    private static function symbolsToBytes(array $symbols): string
    {
        switch (\count($symbols)) {
            case 4:
                return \chr(($symbols[0] << 2 | $symbols[1] >> 4) & 0xFF)
                    .\chr((($symbols[1] << 4) & 0xF0) | ($symbols[2] >> 2))
                    .\chr((($symbols[2] << 6) & 0xFF) | $symbols[3]);
            case 3:
                return \chr(($symbols[0] << 2 | $symbols[1] >> 4) & 0xFF)
                    .\chr(((($symbols[1] << 4) & 0xF0) | $symbols[2]) & 0xFF);
            case 2:
                return \chr(($symbols[0] << 2 | $symbols[1]) & 0xFF);
            default:
                return \chr($symbols[0]);
        }
    }

    /**
     * The escaped ASCII bytes of the "ASCII" flavor, mapped to their escape char.
     *
     * @return array<int, string>
     */
    private static function asciiEscapes(): array
    {
        $escapes = [];
        $index = 0;
        foreach (self::ASCII_ESCAPED_RANGES as [$start, $end]) {
            for ($byte = $start; $byte < $end; ++$byte) {
                $escapes[$byte] = self::asclist[$index++];
            }
        }

        return $escapes;
    }

    /**
     * The reverse Base62x alphabet (char => value), omitting the "x1", "x2", "x3" escape values.
     *
     * @return array<int|string, int>
     */
    private static function reverseAlphabet(): array
    {
        $reverse = [];
        for ($i = 0; $i <= self::xpos; ++$i) {
            if ($i <= self::bpos || $i === self::xpos) {
                $reverse[self::b62x[$i]] = $i;
            }
        }

        return $reverse;
    }

    /**
     * @param list<string>       $digits
     * @param array<int, string> $idx
     */
    private static function appendDigit(array &$digits, int $value, int $maxPos, array $idx): void
    {
        if ($value <= $maxPos) {
            $digits[] = $idx[$value];
        } else {
            $digits[] = $idx[$value - self::bpos];
            $digits[] = self::XTAG;
        }
    }
}
