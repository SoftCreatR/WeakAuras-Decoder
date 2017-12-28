<?php
namespace SoftCreatR\Tests\WeakAuras;

use PHPUnit\Framework\TestCase;
use SoftCreatR\WeakAuras\Decoder;

class DecoderTest extends TestCase
{
    private $decoded = '';

    protected function setUp()
    {
        $encoded = 'd0JPcaGAjrTlPQETKQoTspMsnxvmBsomvDtPITPG(Mc8xPq7KuyVODtL9lj5NsLgMqzCsbxwvdvurdwsLHtQ6GcXPev4ykY5i' .
                   'LwOKqxxWIfvLLlYdfvXtjwgPYZLyIIQ0uHAYumDWIKsnkjbptH8ojr2Ou0xfQ2SIA7IsFwLMLOsnnPkFxkzKsknwrLmArX4LK' .
                   '6KskUfPORjKoVc1Hevv3Ms(nK5eXu8etGsHIU(r7prXIIHIHykMTOx5hJ)Qbk1(kfQQ6IJskt(P4THn7Jt79(PoiZtn0CkgfV' .
                   'nSixHykfWNaIPuqbNHsno4tZE7)eXy66kN4VV(iHKVC68M1FeuWzkN8GA1bHw3Tp6EPyqnwqbNH5JaL1TaXumOgTdfazXksXo' .
                   'uaKfLCQ8JfuWzAUQVcTRXbFA2B)NteJPRRCoXFF9rcjF5C68M1Fock4mLZjUh(uEti99r3B7C3wAMR25OseiqXSLkhC2WM9P4' .
                   'vkkRHsTVsHQQU4OKYqP1AGmSzdJInkAmTt6AyymTd0QJZA2BafiT37NiMsjdQvxhLsguRUUi2WM9jqXfSWP9E)uHAmrjZV3ma' .
                   'BoCq0OJ02BymT6MgeD0qoRzpTuuiVHykGx9oqhXuSckyjMabkGx9oGykwbfSetGafZopV2bfmMykwbfSetGaLK3(jMIvqblXe' .
                   'iqjHupXuSckyjMabk6ZQh8Q3betXkOGLyceOSUBcf0tmfRGcwIjqGYS3gwKJykwbfSetGabkP)UihES(Nc8vdeOKLAm1t3ebs' ;

        $decoded = Decoder::decodeAura($encoded);
        $this->decoded = $decoded[0];
    }

    protected function tearDown()
    {
        $this->decoded = '';
    }

    public function testStringHasWeakAurasVersionNumber()
    {
        $this->assertEquals('2.5.1', $this->decoded['s']);
    }

    public function testStringHasStringVersionNumber()
    {
        $this->assertEquals(1421, $this->decoded['v']);
    }

    public function testStringHasAuraName()
    {
        $this->assertEquals('Hello World', $this->decoded['d']['id']);
    }

    public function testStringHasAuraType()
    {
        $this->assertContains('text', $this->decoded['d']['regionType'], true);
    }

    public function testStringHasCustomCode()
    {
        $dangerKeys = [
            'custom', 'customDuration', 'customName',
            'customIcon', 'customTexture', 'customStacks',
            'translateFunc', 'alphaFunc', 'scaleFunc',
            'rotateFunc', 'colorFunc', 'customText'
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
}
