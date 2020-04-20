<?php

namespace Mfonte\Base62x\Exception;

class DecodeException extends \RuntimeException
{
    const REASON = 'Cannot Decode';
    const CODE = 0;

    public function __construct()
    {
        parent::__construct(static::REASON, static::CODE);
    }
}
