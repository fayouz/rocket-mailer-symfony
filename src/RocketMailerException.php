<?php

namespace RocketMailer\Bundle;

/** Rocket Mailer refused the call (validation, missing variables, rights…) or could not be reached. */
final class RocketMailerException extends \RuntimeException
{
    /** @param array<mixed>|null $response */
    public function __construct(string $message, public readonly int $statusCode = 0, public readonly ?array $response = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }
}
