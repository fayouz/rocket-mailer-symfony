<?php

namespace RocketMailer\Bundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ rocket_mailer_composer({ to: [client.email], template: 'id', variables: { client: { prenom: client.firstName } } }) }}
 * {{ rocket_mailer_composer(draft, { token_url: path('rocket_mailer_embed_token'), attributes: { id: 'composer' } }) }}
 */
final class RocketMailerExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $url,
        private readonly string $applicationId,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('rocket_mailer_composer', $this->composer(...), ['is_safe' => ['html']])];
    }

    /**
     * @param array<string, mixed>                                                                      $draft
     * @param array{token_url?: string, min_height?: int, attributes?: array<string, string>} $options
     */
    public function composer(array $draft = [], array $options = []): string
    {
        $e = static fn (string $value) => htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $attributes = [
            'application-id' => $this->applicationId,
            'base-url' => $this->url,
            'token-url' => $options['token_url'] ?? '/rocket-mailer/token',
        ] + ($options['attributes'] ?? []);
        if (isset($options['min_height'])) {
            $attributes['min-height'] = (string) $options['min_height'];
        }
        if ($draft) {
            $attributes['draft'] = json_encode($draft, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= ' '.$e((string) $name).'="'.$e((string) $value).'"';
        }

        return \sprintf('<script src="%s/embed.js" defer></script><rocket-mailer-composer%s></rocket-mailer-composer>', $e($this->url), $html);
    }
}
