<?php

namespace RocketMailer\Bundle\Tests;

use PHPUnit\Framework\TestCase;
use RocketMailer\Bundle\RocketMailerClient;
use RocketMailer\Bundle\RocketMailerException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class RocketMailerClientTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private function client(JsonMockResponse ...$responses): RocketMailerClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses) {
            $this->requests[] = compact('method', 'url', 'options');

            return array_shift($responses);
        });

        return new RocketMailerClient($http, 'https://mailer.example.com/', 'rma_secret');
    }

    private function header(int $index, string $name): ?string
    {
        foreach ($this->requests[$index]['options']['headers'] as $header) {
            if (str_starts_with(strtolower($header), strtolower($name).':')) {
                return trim(substr($header, \strlen($name) + 1));
            }
        }

        return null;
    }

    public function testEmbedTokenActsAsTheUserWithTheApplicationSecret(): void
    {
        $token = $this->client(new JsonMockResponse(['token' => 'jwt', 'expiresAt' => '2026-09-24T12:15:00+00:00']))
            ->createEmbedToken('alice@example.org');

        self::assertSame('jwt', $token->token);
        self::assertSame('https://mailer.example.com/api/embed/token', $this->requests[0]['url']);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame('Bearer rma_secret', $this->header(0, 'Authorization'));
        self::assertSame('alice@example.org', $this->header(0, 'X-Impersonate-User'));
    }

    public function testSendEmailTurnsIdsIntoIris(): void
    {
        $email = $this->client(new JsonMockResponse(['id' => 'e1', 'status' => 'queued'], ['http_code' => 202]))
            ->sendEmail('alice@example.org', [
                'to' => ['claire@client.example'],
                'template' => '0199-tpl',
                'mailbox' => '0199-box',
                'variables' => ['client' => ['prenom' => 'Claire']],
                'attachments' => ['0199-att', '/api/attachments/0199-other'],
            ]);

        self::assertSame('queued', $email['status']);
        $body = json_decode($this->requests[0]['options']['body'], true);
        self::assertSame('/api/email_templates/0199-tpl', $body['template']);
        self::assertSame('/api/mailboxes/0199-box', $body['mailbox']);
        self::assertSame(['/api/attachments/0199-att', '/api/attachments/0199-other'], $body['attachments']);
        self::assertSame(['client' => ['prenom' => 'Claire']], $body['variables']);
    }

    public function testErrorsCarryTheApiMessage(): void
    {
        $client = $this->client(
            new JsonMockResponse(['detail' => 'Missing template variables: devis.numero.'], ['http_code' => 422]),
            new JsonMockResponse(['violations' => [['propertyPath' => 'to', 'message' => 'This value should not be blank.']]], ['http_code' => 422]),
        );

        try {
            $client->sendEmail('alice@example.org', ['to' => ['x@example.com'], 'template' => 't']);
            self::fail('Expected an exception');
        } catch (RocketMailerException $e) {
            self::assertSame(422, $e->statusCode);
            self::assertStringContainsString('Missing template variables: devis.numero.', $e->getMessage());
        }

        $this->expectExceptionMessage('to: This value should not be blank.');
        $client->sendEmail('alice@example.org', ['to' => []]);
    }

    public function testFindTemplateByName(): void
    {
        $client = $this->client(new JsonMockResponse([['id' => 't1', 'name' => 'Bienvenue'], ['id' => 't2', 'name' => 'Relance devis']]));

        self::assertSame('t2', $client->findTemplate('alice@example.org', 'Relance devis')['id']);
        self::assertStringEndsWith('/api/email_templates?itemsPerPage=200', $this->requests[0]['url']);
    }

    public function testUploadAttachmentSendsAMultipartFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rm');
        file_put_contents($file, '%PDF-1.4 test');
        $attachment = $this->client(new JsonMockResponse(['id' => 'a1', 'filename' => 'devis.pdf', 'mimeType' => 'application/pdf', 'size' => 13], ['http_code' => 201]))
            ->uploadAttachment('alice@example.org', $file, 'devis.pdf', 'application/pdf');
        unlink($file);

        self::assertSame('a1', $attachment['id']);
        self::assertStringStartsWith('multipart/form-data; boundary=', $this->header(0, 'Content-Type'));
    }
}
