<?php

namespace Mfonte\Base62x\Exception;

class CryptException extends \RuntimeException
{
    public const REASON = 'Crypt Exception';
    public const CODE = 0;

    public function __construct(string $reason)
    {
        parent::__construct('Exception in encryption algorithm : '.$reason, static::CODE);
    }
}
