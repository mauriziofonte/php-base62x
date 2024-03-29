# PHP Base62x Library

This library can be used to encode strings in **Base62x** format.

The reference implementation of _Base62x_ has been taken by this repository: [https://github.com/wadelau/Base62x](https://github.com/wadelau/Base62x)

Stating the original author repository, **Base62x is an alternative approach to Base 64 without symbols in output.**

_Base62x_ is an **non-symbolic Base64 encoding scheme**. It can be used safely in computer file systems, programming languages for data exchange, internet communication systems, etc, and it is an ideal substitute and successor of many variants of Base64 encoding scheme.

This repository is a wrapper around **wadelau/Base62x** repository, and is specifically crafted for PHP, with composer support.

It can be integrated into any framework, like Laravel, to enable _Base62x_ support out of the box.

[![Latest Stable Version](https://poser.pugx.org/mfonte/base62x/v/stable)](https://packagist.org/packages/mfonte/base62x)
[![Total Downloads](https://poser.pugx.org/mfonte/base62x/downloads)](https://packagist.org/packages/mfonte/base62x)
[![Build Status](https://scrutinizer-ci.com/g/mauriziofonte/php-base62x/badges/build.png?b=master)](https://scrutinizer-ci.com/g/mauriziofonte/php-base62x/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mauriziofonte/php-base62x/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mauriziofonte/php-base62x/)

## Installation

Simple enough.

`composer require mfonte/base62x`

Required environment:

1. PHP **>= 7.2**
2. `gzip` module, for gzip compression support
3. `openssl` module, for encryption support

### Use cases

Base64 is notoriously bad to be used in _GET_ strings. With this library, you can safely encode (and compress) and pass your data via **GET parameters**.

Another use case is when you have to **communicate binary data** from one server to another.

Many Apache modules (like Mod Security) will do some strange things, already seen in the wild, while passing raw binary data or complex jugglary in server2server communication.

Sure, you can use native `base64_encode()` and `base64_decode()` on both ends, but using this library not only you're encoding data, **you can also compress and encrypt it on the go!**.

### Basic Usage

Usage is simple enough with a nice, expressive API:

```php
<?php
use Mfonte\Base62x\Base62x;

$string = 'this is some string that needs to be encoded';
$encoded = Base62x::encode($string)->get();
$decoded = Base62x::decode($encoded)->get();

```

### Real use case scenario

A useful example of **real use case scenario**, that I've used on some projects, is passing json context data from an HTML page to an Ajax worker.

```php
<?php

// create an ajaxToken to be included in the <head> of the document
$encryptedAjaxToken = Base62x::encode('secure_ajax_token')->encrypt(config('app.key'))->compress()->get();

// create a context of data to be passed to the Ajax worker
$contextData = ['route' => 'homepage', 'pagination' => ['currPage' => 1, 'maxPages' => 2]];
$encryptedContextData = Base62x::encode($contextData)->encrypt(config('app.key'))->compress()->get();

$html = <<<EOT
<head>
<meta charset="utf-8">
...
...
<script type="text/javascript">
const ajaxConfig = {
    url: 'https://www.example.com/ajaxRequest',
    token: '$encryptedAjaxToken',
    context: '$encryptedContextData'
};

function ajaxRequest() {
    $.post(ajaxConfig.url, Object.assign({
        method: "whatever",
    }, ajaxConfig)).done(function(response) {
        // logic
    });
}
</script>
</head>
EOT;
```

The data lifecycle from the frontend to the Ajax backend **is fully encrypted, and doesn't break HTML apart**, as Base62x strings are based on an alphabet that is compatible with embedding them in plain JS strings like I've shown in the provided example.

In the Ajax backend **you can validate the token**, collect the context data, and reply only if the setup is correct:

```php
<?php

try {
    $ajaxToken = (array_key_exists('token', $_POST)) ? $_POST['token'] : '';
    $context = (array_key_exists('context', $_POST)) ? $_POST['context'] : '';
    $decodedAjaxToken = Base62x::decode($ajaxToken)->decrypt(config('app.key'))->get();
    $decodedContext = Base62x::decode($context)->decrypt(config('app.key'))->get();

    if($ajaxToken === 'secure_ajax_token' && is_array($context)) {
        // checks are OK, proceed with your logic
    }

    // fallback: handle not authorized
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['status' => false, 'error' => 401]);
}
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
2. **Huffman** via a dedicated PHP Huffman implementation. **This compression method is currently not working. Please, do not use it in production environments.**

These parameters can be passed to the `compress()` method like so:

```php
<?php
use Mfonte\Base62x\Base62x;

$payload = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

try {
    $gzip_default = Base62x::encode($payload)->compress()->get(); // alias of compress('gzip', 'gzip')
    $gzip_gzip_encoded = Base62x::encode($payload)->compress('gzip', 'gzip')->get();
    $gzip_zlib_encoded = Base62x::encode($payload)->compress('gzip', 'zlib')->get();
    $gzip_deflate_encoded = Base62x::encode($payload)->compress('gzip', 'deflate')->get();

    // you DON'T NEED to call decompress() because the compression method is "saved" inside the
    // encoded payload into a "magic string"
    $decoded = Base62x::decode($gzip_default)->get();
}
catch(Exception $ex) {
    // One Exception of Mfonte/Base62x/Exception/EncodeException
    // or Mfonte/Base62x/Exception/InvalidParam
    // or Mfonte/Base62x/Exception/CompressionException
}
```

### Encryption

As of version 1.2 this library support **encryption**.

The encryption **can be chained** with compression.

```php
<?php
use Mfonte\Base62x\Base62x;

$payload = 'my_payload';
$key = 'a_very_secret_string';

try {
    $encoded_and_crypted = Base62x::encode($payload)->encrypt($key)->get();
    $encoded_and_compressed_and_crypted = Base62x::encode($payload)->encrypt($key)->compress()->get();

    // to perform decryption, you must pass in the original $key
    $decrypted = Base62x::decode($payload)->decrypt($key)->get();
}
catch(Exception $ex) {
    // One Exception of Mfonte/Base62x/Exception/EncodeException
    // or Mfonte/Base62x/Exception/InvalidParam
    // or Mfonte/Base62x/Exception/CompressionException
    // or Mfonte/Base62x/Exception/CryptException
}
```

### Testing

Simply run `composer install` over this module's installation directory.

Then, run `composer test` to run all the tests.

Some tests **may fail** as **testEncodingWithAllAvailableEncryptionAlgorithms** performs a check over all available **openssl_get_cipher_methods()** installed on your environment. A possible example of failure is `Encryption method "id-aes128-CCM" is either unsupported in your PHP installation or not a valid encryption algorithm.`

### TODO

- [ ] Fix **Huffman compression**
- [ ] Add **Bzip2 compression**

### Contributing

If you want to contribute to this project, please use php-cs-fixer to format your code to PSR standards and rules
specified in the configuration file `.php-cs-fixer.dist.php` provided in this repository.
Thank you!

### Thank you's

A big thank you goes to wadelau _[Wade Lau at ufqi.com]_ for implementing such an useful encoding pattern.
