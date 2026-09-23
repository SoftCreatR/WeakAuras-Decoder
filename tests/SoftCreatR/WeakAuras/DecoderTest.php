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

namespace SoftCreatR\Tests\WeakAuras;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SoftCreatR\WeakAuras\AceSerializer;
use SoftCreatR\WeakAuras\Decoder;
use SoftCreatR\WeakAuras\LegacyDecoder;
use SoftCreatR\WeakAuras\LegacyEncoder;
use SoftCreatR\WeakAuras\LuaTable;
use SoftCreatR\WeakAuras\PrintCodec;

class DecoderTest extends TestCase
{
    private string|array $decoded = '';
    private string $encoded = '';

    protected function setUp(): void
    {
        $encoded = 'd0JPcaGAjrTlPQETKQoTspMsnxvmBsomvDtPITPG(Mc8xPq7KuyVODtL9lj5NsLgMqzCsbxwvdvurdwsLHtQ6GcXPev4ykY5i'
                   . 'LwOKqxxWIfvLLlYdfvXtjwgPYZLyIIQ0uHAYumDWIKsnkjbptH8ojr2Ou0xfQ2SIA7IsFwLMLOsnnPkFxkzKsknwrLmArX4LK'
                   . '6KskUfPORjKoVc1Hevv3Ms(nK5eXu8etGsHIU(r7prXIIHIHykMTOx5hJ)Qbk1(kfQQ6IJskt(P4THn7Jt79(PoiZtn0CkgfV'
                   . 'nSixHykfWNaIPuqbNHsno4tZE7)eXy66kN4VV(iHKVC68M1FeuWzkN8GA1bHw3Tp6EPyqnwqbNH5JaL1TaXumOgTdfazXksXo'
                   . 'uaKfLCQ8JfuWzAUQVcTRXbFA2B)NteJPRRCoXFF9rcjF5C68M1Fock4mLZjUh(uEti99r3B7C3wAMR25OseiqXSLkhC2WM9P4'
                   . 'vkkRHsTVsHQQU4OKYqP1AGmSzdJInkAmTt6AyymTd0QJZA2BafiT37NiMsjdQvxhLsguRUUi2WM9jqXfSWP9E)uHAmrjZV3ma'
                   . 'BoCq0OJ02BymT6MgeD0qoRzpTuuiVHykGx9oqhXuSckyjMabkGx9oGykwbfSetGafZopV2bfmMykwbfSetGaLK3(jMIvqblXe'
                   . 'iqjHupXuSckyjMabk6ZQh8Q3betXkOGLyceOSUBcf0tmfRGcwIjqGYS3gwKJykwbfSetGabkP)UihES(Nc8vdeOKLAm1t3ebs' ;

        $this->encoded = $encoded;
        $decoded = Decoder::decodeAura($encoded);
        $this->decoded = $decoded[0];
    }

    protected function tearDown(): void
    {
        $this->decoded = '';
    }

    public function testStringHasWeakAurasVersionNumber(): void
    {
        $this->assertEquals('2.5.1', $this->decoded['s']);
    }

    public function testStringHasStringVersionNumber(): void
    {
        $this->assertEquals(1421, $this->decoded['v']);
    }

    public function testStringHasAuraName(): void
    {
        $this->assertEquals('Hello World', $this->decoded['d']['id']);
    }

    public function testStringHasAuraType(): void
    {
        $this->assertSame('text', $this->decoded['d']['regionType']);
    }

    public function testStringHasCustomCode(): void
    {
        $dangerKeys = [
            'custom', 'customDuration', 'customName',
            'customIcon', 'customTexture', 'customStacks',
            'translateFunc', 'alphaFunc', 'scaleFunc',
            'rotateFunc', 'colorFunc', 'customText',
        ];

        $luaCodes = [];

        foreach ($dangerKeys as $k) {
            $customizations = Decoder::dumpLuaCode($this->decoded, $k);

            foreach ($customizations as $customization) {
                $customization['value'] = trim($customization['value']);

                if (!empty($customization['value'])) {
                    $luaCodes[] = $customization;
                }
            }
        }

        $this->assertNotEmpty($luaCodes);
    }

    public function testCurrentExportFixture(): void
    {
        $encoded = preg_replace('/\s+/', '', file_get_contents(__DIR__ . '/../../fixtures/wa2-current.txt'));
        $decoded = Decoder::decode($encoded);

        $this->assertSame('2.5.1', $decoded['s']);
        $this->assertSame('Hello World', $decoded['d']['id']);
        $this->assertSame('text', $decoded['d']['regionType']);
        $this->assertTrue($decoded['d']['actions']['init']['do_custom']);
    }

    public function testLegacyExportConvertsToCurrentWireFormat(): void
    {
        $converted = Decoder::convert($this->encoded);

        $this->assertStringStartsWith('!WA:2!', $converted);
        $this->assertEqualsCanonicalizing($this->decoded, Decoder::decode($converted));
    }

    public function testCurrentExportCanBeWrittenAsLegacyAndAceDeflate(): void
    {
        $current = preg_replace('/\s+/', '', file_get_contents(__DIR__ . '/../../fixtures/wa2-current.txt'));
        $expected = Decoder::decode($current);

        foreach ([Decoder::FORMAT_LEGACY, Decoder::FORMAT_ACE_DEFLATE] as $format) {
            $encoded = Decoder::convert($current, $format);
            $this->assertStringStartsNotWith('!WA:', $encoded);
            $this->assertSame($format === Decoder::FORMAT_ACE_DEFLATE, str_starts_with($encoded, '!'));
            $this->assertSame($expected, Decoder::decode($encoded));
        }

        $this->assertStringStartsWith('!WA:2!', Decoder::encode($expected));
    }

    public function testLegacyWriterEscapesStringsAndKeepsFloatingPointValues(): void
    {
        $aura = [
            'm' => 'd',
            'd' => ['id' => "Control \0\x1e\x7f^~ \n", 'weight' => -0.0, 'opacity' => 1.0],
        ];

        $this->assertSame($aura, Decoder::decode(Decoder::encode($aura, Decoder::FORMAT_LEGACY)));
    }

    public function testLegacyLzwAndUncompressedMethods(): void
    {
        $this->assertSame("\x01xyz", LegacyEncoder::encodeSerialized('xyz'));

        $serialized = AceSerializer::encode(LuaTable::fromArray(['m' => 'd', 'd' => ['id' => str_repeat('Repeat', 30)]]));
        $compressed = LegacyEncoder::encodeSerialized($serialized);

        $this->assertSame(2, ord($compressed[0]));
        $this->assertSame($serialized, LegacyDecoder::decodeSerialized(PrintCodec::encode($compressed)));

        // A reference to the code being added is the special LZW case.
        $this->assertSame('AAA', LegacyDecoder::decodeSerialized(PrintCodec::encode("\x02A\xfe\x02\x02")));
    }

    public function testVersionOnePrintFormat(): void
    {
        $serialized = '^1^T^Sm^Sd^Sd^T^Sid^SHello^t^t^^';
        $encoded = '!' . PrintCodec::encode(gzdeflate($serialized));

        $this->assertSame('Hello', Decoder::decode($encoded)['d']['id']);
    }

    public function testAceValuesSurviveConversionWithoutPhpKeyCoercion(): void
    {
        $serialized = '^1^T^Sm^Sd^Sd^T^Sid^SHello^Sflag^b^Sspecial^T'
            . '^N1^Snumber^S1^Sstring^B^Sboolean^t^t^t^^';
        $encoded = '!' . PrintCodec::encode(gzdeflate($serialized));
        $display = Decoder::decodeLossless($encoded)->get('d');
        $special = $display->get('special');

        $this->assertFalse($display->get('flag'));
        $this->assertSame([1, 'number'], $special->entries()[0]);
        $this->assertSame(['1', 'string'], $special->entries()[1]);
        $this->assertSame([true, 'boolean'], $special->entries()[2]);

        $converted = Decoder::decodeLossless(Decoder::convert($encoded))->get('d')->get('special');
        $this->assertSame($special->entries(), $converted->entries());
    }

    public function testMalformedOrUnsupportedFormatFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decoder::decode('!WA:3!abcd');
    }

    public function testComplexWeakAurasFiveGroupRoundTripsWithoutFieldLoss(): void
    {
        $encoded = trim(file_get_contents(dirname(__DIR__, 2) . '/fixtures/wa5-complex.txt'));
        $decoded = Decoder::decode($encoded);

        $this->assertSame('5.21.0', $decoded['s']);
        $this->assertSame(2000, $decoded['v']);
        $this->assertSame('Kaze MRT Timers', $decoded['d']['id']);
        $this->assertCount(5, $decoded['c']);
        $this->assertSame($decoded, Decoder::decode(Decoder::convert($encoded)));
        $this->assertSame($decoded, Decoder::decode(Decoder::convert($encoded, Decoder::FORMAT_LEGACY)));
    }

    #[DataProvider('historicalGroupFixtures')]
    public function testHistoricalVersionGroupsRoundTrip(
        string $fixture,
        string $addonVersion,
        int $schemaVersion,
        string $displayId,
        int $children,
    ): void {
        $encoded = trim(file_get_contents(dirname(__DIR__, 2) . '/fixtures/' . $fixture));
        $decoded = Decoder::decode($encoded);

        $this->assertSame($addonVersion, $decoded['s']);
        $this->assertSame($schemaVersion, $decoded['v']);
        $this->assertSame($displayId, $decoded['d']['id']);
        $this->assertCount($children, $decoded['c']);
        $this->assertSame($decoded, Decoder::decode(Decoder::convert($encoded)));
        $this->assertSame($decoded, Decoder::decode(Decoder::convert($encoded, Decoder::FORMAT_LEGACY)));
    }

    public static function historicalGroupFixtures(): iterable
    {
        yield 'WeakAuras 1.4.7.9' => ['wa1-1.4.7.9-group.txt', '1.4.7.9', 1400, 'Amber-Shaper 2', 13];
        yield 'WeakAuras 2.1.0.1' => ['wa2-2.1.0.1-group.txt', '2.1.0.1', 1421, 'DK-Any', 9];
    }

    public function testLosslessTablesRetainTypedKeysAndCycles(): void
    {
        $special = new LuaTable();
        $special->set(1, 'number key');
        $special->set('1', 'string key');
        $special->set(true, 'boolean key');
        $special->set('large', PHP_INT_MAX);
        $special->set('self', $special);

        $display = new LuaTable();
        $display->set('id', 'Special');
        $display->set('special', $special);

        $aura = new LuaTable();
        $aura->set('m', 'd');
        $aura->set('d', $display);

        $encoded = Decoder::encode($aura);
        $decoded = Decoder::decodeLossless($encoded);
        $restored = $decoded->get('d')->get('special');

        $this->assertSame(1, $restored->entries()[0][0]);
        $this->assertSame('1', $restored->entries()[1][0]);
        $this->assertTrue($restored->entries()[2][0]);
        $this->assertSame(PHP_INT_MAX, $restored->get('large'));
        $this->assertSame($restored, $restored->get('self'));
        $this->assertSame($encoded, Decoder::encode($decoded));
    }
}
