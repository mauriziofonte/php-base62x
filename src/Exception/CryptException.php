<?php

namespace Mfonte\Base62x\Exception;

class CryptException extends \RuntimeException
{
    const REASON = 'Crypt Exception';
    const CODE = 0;

    public function __construct(string $reason)
    {
        parent::__construct('Exception in encryption algorithm : '.$reason, static::CODE);
    }
}