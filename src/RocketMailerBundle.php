<?php

namespace RocketMailer\Bundle;

use RocketMailer\Bundle\Controller\EmbedTokenController;
use RocketMailer\Bundle\Security\SecurityUserEmailResolver;
use RocketMailer\Bundle\Security\UserEmailResolverInterface;
use RocketMailer\Bundle\Twig\RocketMailerExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * rocket_mailer:
 *     url: '%env(ROCKET_MAILER_URL)%'                        # public URL of Rocket Mailer
 *     api_url: null                                          # server-to-server URL, if different
 *     app_token: '%env(ROCKET_MAILER_APP_TOKEN)%'            # rma_…, server only
 *     application_id: '%env(ROCKET_MAILER_APPLICATION_ID)%'
 */
final class RocketMailerBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('url')->isRequired()->cannotBeEmpty()->info('Public URL of Rocket Mailer (serves embed.js and the composer)')->end()
                ->scalarNode('api_url')->defaultNull()->info('Server-to-server URL of Rocket Mailer, if different from "url"')->end()
                ->scalarNode('app_token')->isRequired()->cannotBeEmpty()->info('Application secret (rma_…): never sent to the browser')->end()
                ->scalarNode('application_id')->isRequired()->cannotBeEmpty()->info('Id of the application declared in Rocket Mailer')->end()
            ->end();
    }

    /** @param array{url: string, api_url: ?string, app_token: string, application_id: string} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(RocketMailerClient::class)
            ->args([service('http_client'), $config['api_url'] ?? $config['url'], $config['app_token']]);

        $services->set(SecurityUserEmailResolver::class)
            ->args([service('security.token_storage')->nullOnInvalid()]);
        // Override this alias to map your users to Rocket Mailer users (e.g. a user with a separate email field).
        $services->alias(UserEmailResolverInterface::class, SecurityUserEmailResolver::class);

        $services->set(EmbedTokenController::class)
            ->args([service(RocketMailerClient::class), service(UserEmailResolverInterface::class)])
            ->tag('controller.service_arguments');

        if (class_exists(\Twig\Extension\AbstractExtension::class)) {
            $services->set(RocketMailerExtension::class)
                ->args([rtrim($config['url'], '/'), $config['application_id']])
                ->tag('twig.extension');
        }
    }
}
