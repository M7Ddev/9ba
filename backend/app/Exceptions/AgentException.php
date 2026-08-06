<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An agent failure carrying a short, stable error code.
 *
 * The frontend never shows a raw exception message — it receives the code and
 * looks up a translated sentence in its own i18n file. That keeps the API
 * language-agnostic and stops internal details leaking to the browser.
 *
 * Codes: MISSING_KEY, INVALID_KEY, RATE_LIMIT, MODEL_NOT_FOUND, NETWORK,
 *        TIMEOUT, SERVER, BAD_JSON, EMPTY_RESPONSE, UNKNOWN
 */
class AgentException extends RuntimeException
{
    /**
     * Note: the property is `errorCode`, not `code`. \Exception already declares
     * a non-readonly `$code`, and redeclaring it as a promoted readonly property
     * is a fatal error in PHP 8.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $httpStatus = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }
}
