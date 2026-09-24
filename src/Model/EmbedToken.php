<?php

namespace RocketMailer\Bundle\Model;

final class EmbedToken
{
    public function __construct(
        public readonly string $token,
        public readonly \DateTimeImmutable $expiresAt,
    ) {
    }
}
