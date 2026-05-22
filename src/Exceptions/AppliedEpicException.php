<?php

declare(strict_types=1);

namespace Tns\Epic\Exceptions;

/**
 * Base type for every exception thrown by this package. Catch this to handle
 * any failure originating from the client.
 */
class AppliedEpicException extends \RuntimeException
{
}
