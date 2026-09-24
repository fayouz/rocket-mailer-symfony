<?php

namespace RocketMailer\Bundle\Tests;

use PHPUnit\Framework\TestCase;
use RocketMailer\Bundle\RocketMailerBundle;
use RocketMailer\Bundle\RocketMailerClient;
use RocketMailer\Bundle\Security\SecurityUserEmailResolver;
use RocketMailer\Bundle\Security\UserEmailResolverInterface;
use RocketMailer\Bundle\Twig\RocketMailerExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new RocketMailerBundle()];
    }

    public static function httpClient(): MockHttpClient
    {
        return new MockHttpClient(static fn () => new JsonMockResponse(['token' => 'jwt', 'expiresAt' => '2026-09-24T12:15:00+00:00']));
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/rocket-mailer-bundle-test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/rocket-mailer-bundle-test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'http_method_override' => false, 'secret' => 'test']);
        $container->extension('rocket_mailer', [
            'url' => 'https://mailer.example.com',
            'app_token' => 'rma_secret',
            'application_id' => '0199-app',
        ]);
        $container->services()->set('http_client', MockHttpClient::class)->public()
            ->factory([self::class, 'httpClient']);
        $container->services()->set('security.token_storage', TokenStorage::class)->public();
        $container->services()->set('logger', \Psr\Log\NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@RocketMailerBundle/config/routes.php')->prefix('/api');
    }
}

final class BundleTest extends TestCase
{
    public function testTokenEndpointActsAsTheLoggedInUser(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(RocketMailerClient::class, $container->get(RocketMailerClient::class));
        self::assertInstanceOf(SecurityUserEmailResolver::class, $container->get(UserEmailResolverInterface::class));

        $response = $kernel->handle(Request::create('/api/rocket-mailer/token', 'POST'));
        self::assertSame(401, $response->getStatusCode(), 'nobody logged in');

        $container->get('security.token_storage')->setToken(new UsernamePasswordToken(new InMemoryUser('alice@example.org', null), 'main'));
        $response = $kernel->handle(Request::create('/api/rocket-mailer/token', 'POST'));
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('jwt', json_decode((string) $response->getContent(), true)['token']);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        self::assertSame(405, $kernel->handle(Request::create('/api/rocket-mailer/token', 'GET'))->getStatusCode());
    }

    public function testTwigHelperRendersTheWebComponent(): void
    {
        $html = (new RocketMailerExtension('https://mailer.example.com', '0199-app'))
            ->composer(['to' => ['claire@client.example'], 'subject' => 'Devis "42" <b>'], ['token_url' => '/api/rocket-mailer/token', 'attributes' => ['id' => 'composer']]);

        self::assertStringContainsString('<script src="https://mailer.example.com/embed.js" defer></script>', $html);
        self::assertStringContainsString('application-id="0199-app"', $html);
        self::assertStringContainsString('token-url="/api/rocket-mailer/token"', $html);
        self::assertStringContainsString('id="composer"', $html);
        // The draft is JSON in an attribute: quotes and markup are escaped.
        self::assertStringContainsString('draft="{&quot;to&quot;:[&quot;claire@client.example&quot;],&quot;subject&quot;:&quot;Devis \&quot;42\&quot; &lt;b&gt;&quot;}"', $html);
    }
}
