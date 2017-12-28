[![Travis](https://img.shields.io/travis/SoftCreatR/WeakAuras-Decoder.svg?style=for-the-badge)](https://travis-ci.org/SoftCreatR/WeakAuras-Decoder) [![Discord](https://img.shields.io/discord/350291929222873099.svg?style=for-the-badge&colorB=7289DA)](https://discord.gg/hS2yuQC)

# WeakAuras Decoder

WeakAuras is a powerful and flexible framework for World Of Warcraft that allows you to display highly customizable graphics on your screen to indicate buffs, debuffs, and a whole host of similar types of information. It was originally meant to be a lightweight replacement for Power Auras, but it now incorporates many features that Power Auras does not, while still remaining more efficient and easy to use.

Created auras can be exported and shared all over the net. But there's a catch: All you get is an encoded string, that is used to be imported in the WeakAuras addon. This encoded string could possibly contain custom code which may be supposed to destroy your gaming experience by stealing gold from your character, spamming other players, etc.

The __WeakAuras Decoder__ is a PHP class that gives you the ability to convert these strings back in a human readable format. It is mostly a port of WeakAuras' Lua code that does literally the same.

## Requirements

- PHP 5.5 or newer
- HTTP server with PHP support (eg: Apache, Nginx, Caddy)
- [Composer](https://getcomposer.org)

## Installation

Require this package, with [Composer](https://getcomposer.org/), in the root directory of your project:

```bash
$ composer require softcreatr/weakauras-decoder
```

## Usage / Examples

#### decodeAura

Decodes an encoded WeakAuras string into an array.

```php
<?php
require "vendor/autoload.php";

use SoftCreatR\WeakAuras\Decoder;

// Encoded WeakAuras string
$encoded = 'd0JPcaGAjrTlPQETKQoTspMsnxvmBsomvDtPITPG(Mc8xPq7KuyVODtL9lj5NsLgMqzCsbxwvdvurdwsLHtQ6GcXPev4ykY5i' .
           'LwOKqxxWIfvLLlYdfvXtjwgPYZLyIIQ0uHAYumDWIKsnkjbptH8ojr2Ou0xfQ2SIA7IsFwLMLOsnnPkFxkzKsknwrLmArX4LK' .
           '6KskUfPORjKoVc1Hevv3Ms(nK5eXu8etGsHIU(r7prXIIHIHykMTOx5hJ)Qbk1(kfQQ6IJskt(P4THn7Jt79(PoiZtn0CkgfV' .
           'nSixHykfWNaIPuqbNHsno4tZE7)eXy66kN4VV(iHKVC68M1FeuWzkN8GA1bHw3Tp6EPyqnwqbNH5JaL1TaXumOgTdfazXksXo' .
           'uaKfLCQ8JfuWzAUQVcTRXbFA2B)NteJPRRCoXFF9rcjF5C68M1Fock4mLZjUh(uEti99r3B7C3wAMR25OseiqXSLkhC2WM9P4' .
           'vkkRHsTVsHQQU4OKYqP1AGmSzdJInkAmTt6AyymTd0QJZA2BafiT37NiMsjdQvxhLsguRUUi2WM9jqXfSWP9E)uHAmrjZV3ma' .
           'BoCq0OJ02BymT6MgeD0qoRzpTuuiVHykGx9oqhXuSckyjMabkGx9oGykwbfSetGafZopV2bfmMykwbfSetGaLK3(jMIvqblXe' .
           'iqjHupXuSckyjMabk6ZQh8Q3betXkOGLyceOSUBcf0tmfRGcwIjqGYS3gwKJykwbfSetGabkP)UihES(Nc8vdeOKLAm1t3ebs' ;

// Perform decode
$decoded = Decoder::decodeAura($encoded);

// Print the result
var_dump($decoded[0]);
```

#### dumpLuaCode

Dumps custom Lua codes into an array.

```php
<?php
require "vendor/autoload.php";

use SoftCreatR\WeakAuras\Decoder;

// Encoded WeakAuras string
$encoded = 'd0JPcaGAjrTlPQETKQoTspMsnxvmBsomvDtPITPG(Mc8xPq7KuyVODtL9lj5NsLgMqzCsbxwvdvurdwsLHtQ6GcXPev4ykY5i' .
           'LwOKqxxWIfvLLlYdfvXtjwgPYZLyIIQ0uHAYumDWIKsnkjbptH8ojr2Ou0xfQ2SIA7IsFwLMLOsnnPkFxkzKsknwrLmArX4LK' .
           '6KskUfPORjKoVc1Hevv3Ms(nK5eXu8etGsHIU(r7prXIIHIHykMTOx5hJ)Qbk1(kfQQ6IJskt(P4THn7Jt79(PoiZtn0CkgfV' .
           'nSixHykfWNaIPuqbNHsno4tZE7)eXy66kN4VV(iHKVC68M1FeuWzkN8GA1bHw3Tp6EPyqnwqbNH5JaL1TaXumOgTdfazXksXo' .
           'uaKfLCQ8JfuWzAUQVcTRXbFA2B)NteJPRRCoXFF9rcjF5C68M1Fock4mLZjUh(uEti99r3B7C3wAMR25OseiqXSLkhC2WM9P4' .
           'vkkRHsTVsHQQU4OKYqP1AGmSzdJInkAmTt6AyymTd0QJZA2BafiT37NiMsjdQvxhLsguRUUi2WM9jqXfSWP9E)uHAmrjZV3ma' .
           'BoCq0OJ02BymT6MgeD0qoRzpTuuiVHykGx9oqhXuSckyjMabkGx9oGykwbfSetGafZopV2bfmMykwbfSetGaLK3(jMIvqblXe' .
           'iqjHupXuSckyjMabk6ZQh8Q3betXkOGLyceOSUBcf0tmfRGcwIjqGYS3gwKJykwbfSetGabkP)UihES(Nc8vdeOKLAm1t3ebs' ;

// Perform decode
$decoded = Decoder::decodeAura($encoded);
$decoded = $decoded[0];

// "Danger keys" are used to identify custom code
$dangerKeys = [
    'custom', 'customDuration', 'customName',
    'customIcon', 'customTexture', 'customStacks',
    'translateFunc', 'alphaFunc', 'scaleFunc',
    'rotateFunc', 'colorFunc', 'customText'
];

// Perform some magic
$luaCodes = [];

foreach ($dangerKeys as $k) {
    $customizations = Decoder::dumpLuaCode($decoded, $k);

    foreach ($customizations as $customization) {
        $customization['value'] = trim($customization['value']);

        if (!empty($customization['value'])) {
            $luaCodes[] = $customization;
        }
    }
}

// Print the result
echo "Decoded string:\n\n";
var_dump($decoded);

echo "\n\nCustom code (if there is any):\n\n";
var_dump($luaCodes);
```

License
----

[![license](https://img.shields.io/github/license/SoftCreatR/weakauras-decoder.svg?style=for-the-badge)](https://github.com/SoftCreatR/weakauras-decoder/blob/master/LICENSE)


**Free Software, Hell Yeah!**
