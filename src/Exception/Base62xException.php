<?php

declare(strict_types=1);

namespace Mfonte\Base62x\Exception;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Catch it to handle any library error at once:
 *
 *     try { ... } catch (\Mfonte\Base62x\Exception\Base62xException $e) { ... }
 */
interface Base62xException extends \Throwable
{
}
