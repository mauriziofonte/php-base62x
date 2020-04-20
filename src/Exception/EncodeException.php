<?php

namespace Mfonte\Base62x\Exception;

class EncodeException extends \RuntimeException
{
    const REASON = 'Base62x Encode error';
    const CODE = 0;

    public function __construct()
    {
        parent::__construct(static::REASON, static::CODE);
    }
}
