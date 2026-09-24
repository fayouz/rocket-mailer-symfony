<?php

namespace RocketMailer\Bundle;

use RocketMailer\Bundle\Model\EmbedToken;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rocket Mailer API, with the application secret. Every call acts as a Rocket Mailer user ($asUser, an email):
 * the application must be allowed to act as a user ("Peut agir en tant qu'utilisateur").
 */
class RocketMailerClient
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $http,
        string $baseUrl,
        #[\SensitiveParameter] private readonly string $appToken,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** Short-lived token for the embedded composer (see the token endpoint of this bundle). */
    public function createEmbedToken(string $asUser): EmbedToken
    {
        $data = $this->request('POST', '/api/embed/token', $asUser);

        return new EmbedToken($data['token'], new \DateTimeImmutable($data['expiresAt']));
    }

    /**
     * Sends an email without the composer.
     *
     * @param array{
     *     to: list<string>, cc?: list<string>, bcc?: list<string>, from?: string,
     *     subject?: string, htmlBody?: string,
     *     template?: string, variables?: array<mixed>, attachments?: list<string>,
     * } $email template: id or IRI (subject and htmlBody then default to the template's); attachments: ids or IRIs
     *
     * @return array<string, mixed> the queued email (id, status, subject…)
     */
    public function sendEmail(string $asUser, array $email): array
    {
        if (isset($email['template'])) {
            $email['template'] = self::iri('email_templates', $email['template']);
        }
        if (isset($email['attachments'])) {
            $email['attachments'] = array_map(static fn (string $id) => self::iri('attachments', $id), $email['attachments']);
        }

        return $this->request('POST', '/api/emails', $asUser, ['json' => $email]);
    }

    /**
     * Uploads a file for $asUser, e.g. a generated PDF, then pass its id to the composer (draft.attachments) or to sendEmail().
     *
     * @return array{id: string, filename: string, mimeType: string, size: int}
     */
    public function uploadAttachment(string $asUser, string $path, ?string $filename = null, ?string $mimeType = null): array
    {
        $form = new FormDataPart(['file' => DataPart::fromPath($path, $filename, $mimeType)]);

        return $this->request('POST', '/api/attachments', $asUser, [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
        ]);
    }

    /** @return list<array<string, mixed>> the templates $asUser can use (their own and the shared ones) */
    public function templates(string $asUser): array
    {
        return $this->request('GET', '/api/email_templates?itemsPerPage=200', $asUser);
    }

    /** @return array<string, mixed>|null */
    public function findTemplate(string $asUser, string $name): ?array
    {
        foreach ($this->templates($asUser) as $template) {
            if ($template['name'] === $name) {
                return $template;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function email(string $asUser, string $id): array
    {
        return $this->request('GET', '/api/emails/'.rawurlencode($id), $asUser);
    }

    private static function iri(string $collection, string $value): string
    {
        return str_starts_with($value, '/api/') ? $value : '/api/'.$collection.'/'.rawurlencode($value);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    private function request(string $method, string $path, string $asUser, array $options = []): array
    {
        $options['headers'] = ($options['headers'] ?? []) + [
            'Authorization' => 'Bearer '.$this->appToken,
            'X-Impersonate-User' => $asUser,
            'Accept' => 'application/json',
        ];

        try {
            $response = $this->http->request($method, $this->baseUrl.$path, $options);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            throw new RocketMailerException('Rocket Mailer is unreachable: '.$e->getMessage(), 0, null, $e);
        }

        if ($status >= 400) {
            $violations = array_map(static fn (array $v) => ($v['propertyPath'] ? $v['propertyPath'].': ' : '').$v['message'], $data['violations'] ?? []);
            $message = $violations ? implode("\n", $violations) : ($data['detail'] ?? $data['message'] ?? 'HTTP '.$status);

            throw new RocketMailerException(\sprintf('Rocket Mailer refused %s %s: %s', $method, strtok($path, '?'), $message), $status, $data);
        }

        return $data;
    }
}
