## PHP Base62x Library

This library can be used to encode strings in **Base62x** format.

The reference implementation of Base62x has been taken by this repository: https://github.com/wadelau/Base62x

Stating the original author repository, **Base62x is an alternative approach to Base 64 without symbols in output.**

Base62x is an **non-symbolic Base64 encoding scheme**. It can be used safely in computer file systems, programming languages for data exchange, internet communication systems, etc, and it is an ideal substitute and successor of many variants of Base64 encoding scheme.

This repository is a wrapper around **wadelau/Base62x** repository, and is specifically crafted for PHP, with composer support.

It can be integrated into any framework, like Laravel, to enable Base62x support out of the box.

[![Latest Stable Version](https://poser.pugx.org/mfonte/base62x/v/stable)](https://packagist.org/packages/mfonte/base62x)
[![Total Downloads](https://poser.pugx.org/mfonte/base62x/downloads)](https://packagist.org/packages/mfonte/base62x)
[![Coverage Status](https://coveralls.io/repos/github/mfonte/base62x/badge.svg)](https://coveralls.io/github/mfonte/base62x)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mattiabasone/PagOnline/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mfonte/base62x/?branch=master)

### Use cases

Base64 is notoriously bad to be used in _GET_ strings. With this library, you can safely encode (and compress) and pass your data via **GET parameters**.

Another use case is when you have to **communicate binary data** from one server to another.

Many Apache modules (like Mod Security) will do some strange things, already seen in the wild, while passing raw binary data or complex jugglary in server2server communication.

Sure, you can use native `base64_encode()` and `base64_decode()` on both ends, but using this library not only you're encoding data, **you can also compress it on the go!**.

### Basic Usage

Usage is simple enough with a nice, expressive API:

```php
<?php
use Mfonte\Base62x\Base62x;

$string = 'this is some string that needs to be encoded';
$encoded = Base62x::encode($string)->get();
$decoded = Base62x::decode($encoded)->get();

```

### The library supports binary streams

It means that you can encode binary data, for example raw JPEGs or raw audio files.

```php
<?php
use Mfonte\Base62x\Base62x;
use GuzzleHttp\Client;

// on one machine
$payload = file_get_contents('a_nice_image.jpg');
$encoded_payload = Base62x::encode($payload)->get();

$client = new Client(['base_uri' => 'https://example.com/']);
$response = $client->post('postRequest/', [
    'form_params' => [
        'encoded_image' => $encoded_payload
    ]
]);

...

// on another machine
$encoded_payload = $request->get('encoded_image');
$image = Base62x::decode($encoded_payload)->get();
file_put_contents($image, 'a_nice_image_clone.jpg');

```

### The library supports compressed Base62x encoding!

While encoding in Base62x, you can also compress the payload.

This is expecially useful if you're planning on sending huge arrays or huge data via GET or POST requests, that you need to be sure it would not trigger errors from one machine to another, or from one url to another.

**Important note**: while decoding and decompressing, you **don't need to specify the original compression algorithm**, because this information is saved in a "magic string" at the beginning of the encoded payload.
So, if you don't have control over another machine where your payloads can end, you don't have to worry to communicate the other side what type of compression you're actually using. The `decode()` method will automagically take care of that.

```php
<?php
use Mfonte\Base62x\Base62x;

$payload = ['foo' => 'bar', 'bar' => ['foo1', 'foo2', 'foo3', 'fooman' => 'foobar'], 'barfoo' => [1,2,3,4,5,6,7,8,9,10]];
try {
    $encoded_payload = Base62x::encode($payload)->compress()->get();
}
catch(Exception $ex) {
    // One Exception of Mfonte/Base62x/Exception/EncodeException
    // or Mfonte/Base62x/Exception/InvalidParam
    // or Mfonte/Base62x/Exception/CompressionException
}

$final_url = 'https://example.com/decodetest/?data=' . $encoded_payload;

...

// on the "decodetest" url ...
$encoded_payload = (array_key_exists('data', $_GET) && strlen($_GET['data'])) ? $_GET['data'] : null;
if(!empty($encoded_payload)) {
    try {
        // in the $decoded variable, you'll automagically get the original Array
        $decoded = Base62x::decode($encoded_payload)->get();
    }
    catch(Exception $ex) {
        // One Exception of Mfonte/Base62x/Exception/DecodeException or Mfonte/Base62x/Exception/InvalidParam
    }
}
```

### Available compression methods

1. **Gzip**, encoding `zlib`, `deflate`, or `gzip`. Without any further instruction, the `compress()` method defaults to `gzip/zlib`
2. **Huffman** via a dedicated PHP Huffman implementation

These parameters can be passed to the `compress()` method like so:

```php
<?php
use Mfonte\Base62x\Base62x;

$payload = 'averylongstringaverylongstringaverylongstringaverylongstringaverylongstringaverylongstring...';
try {
    $gzip_zlib_encoded = Base62x::encode($payload)->compress('gzip', 'zlib')->get();
    $gzip_deflate_encoded = Base62x::encode($payload)->compress('gzip', 'deflate')->get();
    $gzip_gzip_encoded = Base62x::encode($payload)->compress('gzip', 'gzip')->get();
    $huffman_encoded = Base62x::encode($payload)->compress('huffman')->get();
}
catch(Exception $ex) {
    // One Exception of Mfonte/Base62x/Exception/EncodeException
    // or Mfonte/Base62x/Exception/InvalidParam
    // or Mfonte/Base62x/Exception/CompressionException
}
```

### TODO

-   [ ] Add **Bzip2 compression**
-   [ ] Add **Encrytion** over encoded payloads

### Contributing

If you want to contribute to this project, please use php-cs-fixer to format your code to PSR standards and rules
specified in the configuration file `.php_cs.dist` provided in this repository.
Thank you!

### Thank you's

A big thank you goes to wadelau _[Wade Lau at ufqi.com]_ for implementing such an useful encoding pattern.
