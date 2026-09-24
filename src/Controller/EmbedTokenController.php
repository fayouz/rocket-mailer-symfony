<?php

namespace RocketMailer\Bundle\Controller;

use RocketMailer\Bundle\RocketMailerClient;
use RocketMailer\Bundle\Security\UserEmailResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * POST /rocket-mailer/token: embed token for the logged-in user, called by the composer (token-url).
 * The user comes from YOUR session, never from the request.
 */
final class EmbedTokenController
{
    public function __construct(
        private readonly RocketMailerClient $client,
        private readonly UserEmailResolverInterface $users,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $email = $this->users->currentUserEmail();
        if (null === $email) {
            return new JsonResponse(['error' => 'Not logged in.'], 401);
        }

        $token = $this->client->createEmbedToken($email);

        return new JsonResponse(['token' => $token->token, 'expiresAt' => $token->expiresAt->format(\DATE_ATOM)], 200, ['Cache-Control' => 'no-store']);
    }
}
