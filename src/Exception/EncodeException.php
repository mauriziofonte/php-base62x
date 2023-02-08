<?php

namespace Mfonte\Base62x\Exception;

class EncodeException extends \RuntimeException
{
    public const REASON = 'Base62x Encode error';
    public const CODE = 0;

    public function __construct()
    {
        parent::__construct(static::REASON, static::CODE);
    }
}
