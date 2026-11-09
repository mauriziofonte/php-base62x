<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Exception;

class CryptException extends \RuntimeException implements Base62xException
{
    public const REASON = 'Crypt Exception';
    public const CODE = 0;

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct('Exception in encryption algorithm: '.$reason, static::CODE, $previous);
    }
}
