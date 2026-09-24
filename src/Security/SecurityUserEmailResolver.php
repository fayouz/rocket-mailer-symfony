<?php

namespace RocketMailer\Bundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Default: the identifier of the logged-in Symfony user, when it is an email.
 * Override the UserEmailResolverInterface alias when your user identifier is not the email.
 */
final class SecurityUserEmailResolver implements UserEmailResolverInterface
{
    public function __construct(private readonly ?TokenStorageInterface $tokenStorage = null)
    {
    }

    public function currentUserEmail(): ?string
    {
        $user = $this->tokenStorage?->getToken()?->getUser();
        if (null === $user) {
            return null;
        }
        if (method_exists($user, 'getEmail') && \is_string($email = $user->getEmail()) && '' !== $email) {
            return $email;
        }
        $identifier = $user->getUserIdentifier();

        return false !== filter_var($identifier, \FILTER_VALIDATE_EMAIL) ? $identifier : null;
    }
}
