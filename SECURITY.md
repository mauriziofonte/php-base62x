# Security Policy

## Supported versions

Only the latest release receives security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report them privately through
[GitHub Security Advisories](https://github.com/mauriziofonte/php-base62x/security/advisories/new),
including a description of the issue, the affected versions and, if possible, a proof of concept.
You will receive an answer as soon as possible, and a fix will be released before the details are made public.

## Scope and known limitations

Base62x is an **encoding**, not a security mechanism. For confidentiality and integrity, use the authenticated
encryption layer:

```php
Base62x::encode($payload)->encrypt($key, Base62x::CIPHER_XCHACHA20_POLY1305)->get();          // high-entropy keys
Base62x::encode($payload)->encrypt($password, Base62x::CIPHER_XCHACHA20_POLY1305_ARGON2ID)->get(); // human passwords
```

It uses XChaCha20-Poly1305 (libsodium) with a random salt and nonce per payload, so wrong keys and tampered payloads
are always detected.

The OpenSSL ciphers (the default of `encrypt()`, for backward compatibility) have known limitations:

- the cipher text is **not authenticated** (no MAC): a tampered payload may decrypt to garbage instead of failing;
- printable keys are turned into a binary key with a single SHA-256 round, which is **not** a password-based key
  derivation function.

Prefer authenticated encryption for any new code.

Decoding never instantiates objects: serialized objects nested in arrays come back as `__PHP_Incomplete_Class`.
