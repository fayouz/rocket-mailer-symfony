<?php

namespace RocketMailer\Bundle\Security;

/** Email of the Rocket Mailer user the current request acts as; null when nobody is logged in. */
interface UserEmailResolverInterface
{
    public function currentUserEmail(): ?string;
}
