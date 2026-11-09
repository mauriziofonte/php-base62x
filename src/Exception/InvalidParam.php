<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Exception;

class InvalidParam extends \InvalidArgumentException implements Base62xException
{
    public const REASON = 'Invalid param "#PARAM#" passed in method "#METHOD#" on class "#CLASS#"';
    public const CODE = 0;

    public function __construct(string $param, string $method, string $class, ?string $detailedReason = null)
    {
        $reason = str_replace(['#PARAM#', '#METHOD#', '#CLASS#'], [$param, $method, $class], static::REASON);
        if ($detailedReason !== null && $detailedReason !== '') {
            $reason .= ': '.$detailedReason;
        }
        parent::__construct($reason, static::CODE);
    }
}
