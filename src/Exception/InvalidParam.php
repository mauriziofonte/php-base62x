<?php

namespace Mfonte\Base62x\Exception;

class InvalidParam extends \InvalidArgumentException
{
    public const REASON = 'Invalid param "#PARAM#" passed in method "#METHOD#" on class "#CLASS#" ';
    public const CODE = 0;

    public function __construct($param, $method, $class, $detailedReason = null)
    {
        $reason = str_replace(['#PARAM#', '#METHOD#', '#CLASS#'], [$param, $method, $class], static::REASON);
        if ($detailedReason) {
            $reason .= ': '.$detailedReason;
        }
        parent::__construct($reason, static::CODE);
    }
}
