<?php

// Import in config/routes/rocket_mailer.yaml:
//   rocket_mailer:
//       resource: '@RocketMailerBundle/config/routes.php'
//       prefix: /api   # optional: put it behind your API firewall
use RocketMailer\Bundle\Controller\EmbedTokenController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('rocket_mailer_embed_token', '/rocket-mailer/token')
        ->controller(EmbedTokenController::class)
        ->methods(['POST']);
};
