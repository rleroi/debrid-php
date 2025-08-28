<?php

declare(strict_types=1);

namespace RLeroi\Debrid\Exceptions;

use Exception;

/**
 * Base exception class for all debrid-related errors.
 * 
 * This is the parent class for all specific debrid exceptions.
 * Catch this to handle any debrid-related error, or catch specific
 * exception types for more targeted error handling.
 * 
 * @see AuthenticationException
 * @see TorrentNotFoundException
 * @see FileNotFoundException
 * @see TorrentNotReadyException
 * @see InvalidMagnetException
 * @see RateLimitException
 */
class DebridException extends Exception
{
}