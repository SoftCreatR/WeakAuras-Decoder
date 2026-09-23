# WeakAuras Decoder

A PHP library with no third-party requirements for reading and writing WeakAuras (1 & 2) import strings. It decodes the complete transmission envelope, including grouped children, custom Lua source, version metadata, and any other stored fields. The library only reads source strings; it never executes embedded Lua.

Requires PHP 8.3–8.6 and the standard zlib extension. Install it with `composer require softcreatr/weakauras-decoder`.

## Formats

| Export prefix | Encoding | Compression | Serialization | Addon examples |
| --- | --- | --- | --- | --- |
| none | WeakAuras six-bit alphabet | LibCompress (uncompressed, LZW, or Huffman) | AceSerializer-3.0 | 1.4.7.9, 2.1.0.1, 2.5.1, 2.7.0 |
| `!` | LibDeflate printable alphabet | raw Deflate | AceSerializer-3.0 | 2.8.0, 2.10.0 |
| `!WA:2!` | LibDeflate printable alphabet | raw Deflate | LibSerialize | 3.0.0, 4.0.0, 5.0.0, 5.21.0 |

The number after `!WA:` is the export format version, not the addon version. The decoded envelope's `s` field contains the addon version and `v` contains its transmission schema version. The unprefixed codec also handles the tested WeakAuras 1.4.7.9 group. Unsupported future export versions produce an explicit exception.

The format mapping follows [WeakAuras 2.5.1](https://github.com/WeakAuras/WeakAuras2/blob/2.5.1/WeakAuras/Transmission.lua), [2.8.0](https://github.com/WeakAuras/WeakAuras2/blob/2.8.0/WeakAuras/Transmission.lua), and [current Transmission.lua](https://github.com/WeakAuras/WeakAuras2/blob/main/WeakAuras/Transmission.lua). The old codec methods follow [LibCompress](https://github.com/OpenPrograms/LibCompress/blob/master/LibCompress.lua).

## Decode and convert

```php
<?php

require 'vendor/autoload.php';

use SoftCreatR\WeakAuras\Decoder;

$export = trim(file_get_contents('aura.txt'));
$envelope = Decoder::decode($export);

echo $envelope['s'];                // WeakAuras addon version
echo $envelope['d']['id'];          // Root display name
$children = $envelope['c'] ?? [];  // Grouped displays, if present

$currentFormat = Decoder::convert($export); // !WA:2!...
```

`Decoder::decodeAura($export)` remains available for older callers and returns `[$envelope]`. `Decoder::encode($envelope)` emits a `!WA:2!` string by default. To write an unprefixed WA1-era string, use `Decoder::encode($envelope, Decoder::FORMAT_LEGACY)` or `Decoder::convert($export, Decoder::FORMAT_LEGACY)`. `Decoder::FORMAT_ACE_DEFLATE` writes the intermediate `!` format. The legacy writer chooses LibCompress LZW or its uncompressed method; it does not yet write Huffman streams. `Decoder::dumpLuaCode($envelope, 'custom')` finds fields by name and returns their paths and values.

`convert()` changes the transport format while retaining the decoded aura fields. The AceSerializer-based legacy formats cannot represent cyclic/shared table references or integers beyond Lua's exact double range, so those conversions fail explicitly. It does not rewrite obsolete triggers, load conditions, or custom Lua to newer addon schemas. Whether a historic aura works in a current game client depends on WeakAuras' own import migration and the aura's content.

## Lossless Lua tables

PHP arrays cannot distinguish every Lua key type, and they cannot express a cyclic table graph. For exports containing those values, use `decodeLossless()` and inspect the returned `LuaTable` objects. Each table exposes `entries()` as ordered `[key, value]` pairs and `get('field')` for string keys. Shared table references retain object identity. You can pass the result directly to `encode()`.

```php
$table = Decoder::decodeLossless($export);
$display = $table->get('d');
$roundTrip = Decoder::encode($table);
```

`decode()` returns ordinary PHP arrays for normal aura data. It throws with a suggestion to use `decodeLossless()` if a key, cycle, or shared table reference would lose information during conversion.

## Development

Run `composer install`, then `vendor/bin/phpunit`. The suite includes a [WeakAuras 1.4.7.9 thirteen-child group](https://pastebin.com/iM8DKdPk), a [2.1.0.1 nine-child group](https://pastebin.com/c19FZ6VY), the original 2.5.1 export and its supplied `!WA:2!` counterpart, and a [5.21.0 five-child group](https://wago.io/n7l5uN3YM). The corresponding fixtures are in `tests/fixtures/`. Run `php-cs-fixer fix --config=.php-cs-fixer.dist.php` to apply the repository's style rules.

The tests verify PHP decoding and data-preserving re-encoding. They do not install an aura into World of Warcraft or exercise WeakAuras' in-game migration code.

## License

The library code is licensed under the [ISC License](LICENSE). The third-party WeakAuras export fixtures are test data from their linked sources above; their creators retain their rights, and the library's ISC grant does not apply to those strings.
