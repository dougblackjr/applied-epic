<?php

declare(strict_types=1);

namespace Tns\Epic\Exceptions;

/**
 * Thrown when the OAuth2 client-credentials token exchange fails — bad
 * credentials, an unreachable token endpoint, or a malformed token response.
 */
final class AuthException extends AppliedEpicException
{
}
