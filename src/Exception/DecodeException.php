<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Exception;

class DecodeException extends \RuntimeException implements Base62xException
{
    public const REASON = 'Cannot Decode';
    public const CODE = 0;

    public function __construct(?\Throwable $previous = null, ?string $detailedReason = null)
    {
        $message = static::REASON;
        if ($detailedReason !== null && $detailedReason !== '') {
            $message .= ': '.$detailedReason;
        }
        parent::__construct($message, static::CODE, $previous);
    }
}
