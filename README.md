# rocket-mailer/rocket-mailer-bundle

Bundle Symfony pour les applications qui intègrent [Rocket Mailer](https://github.com/fayouz/rocket-mailer). Il convient aux applications Symfony (Twig) et aux backends API Platform d'un front Nuxt, Vue ou React :

- l'endpoint `POST /rocket-mailer/token`, qui fournit un jeton d'embed pour l'utilisateur connecté ;
- `RocketMailerClient` : envoyer un email ou un template avec ses variables, téléverser une pièce jointe, lister les templates ;
- la fonction Twig `rocket_mailer_composer()`, qui affiche le composeur embarqué.

> Ce dépôt est un miroir en lecture seule de `integrations/symfony` dans [fayouz/rocket-mailer](https://github.com/fayouz/rocket-mailer). Ouvrez les issues et les pull requests là-bas.

## Installation

```bash
composer config repositories.rocket-mailer vcs https://github.com/fayouz/rocket-mailer-symfony
composer require rocket-mailer/rocket-mailer-bundle:dev-main
```

```php
// config/bundles.php
RocketMailer\Bundle\RocketMailerBundle::class => ['all' => true],
```

```yaml
# config/packages/rocket_mailer.yaml
rocket_mailer:
    url: '%env(ROCKET_MAILER_URL)%'                      # https://mailer.exemple.com
    app_token: '%env(ROCKET_MAILER_APP_TOKEN)%'          # rma_… (secret)
    application_id: '%env(ROCKET_MAILER_APPLICATION_ID)%'
    # api_url: http://mailer:80                          # optionnel : URL interne

# config/routes/rocket_mailer.yaml
rocket_mailer:
    resource: '@RocketMailerBundle/config/routes.php'
    prefix: /api        # pour passer derrière le firewall de votre API (JWT) ; omettez-le pour une app Twig
```

L'endpoint agit en tant que l'utilisateur connecté : `getEmail()` de votre classe User, sinon son identifiant s'il s'agit d'un email. Sinon, décorez ou remplacez l'alias `RocketMailer\Bundle\Security\UserEmailResolverInterface`.

## Twig

```twig
{{ rocket_mailer_composer({
    to: [client.email],
    template: '0199…',
    variables: { client: { prenom: client.firstName } },
}) }}
```

## Client

```php
public function remind(Quote $quote, RocketMailerClient $mailer): Response
{
    $pdf = $mailer->uploadAttachment($this->getUser()->getEmail(), $this->pdf->renderToFile($quote), 'devis.pdf'); // chemin du fichier
    $mailer->sendEmail($this->getUser()->getEmail(), [
        'to' => [$quote->getClient()->getEmail()],
        'template' => $mailer->findTemplate($this->getUser()->getEmail(), 'Relance devis')['id'],
        'variables' => ['client' => ['prenom' => $quote->getClient()->getFirstName()], 'devis' => ['numero' => $quote->getNumber()]],
        'attachments' => [$pdf['id']],
    ]);
    // ...
}
```

Les erreurs de l'API (variables manquantes, adresse refusée…) lèvent une `RocketMailerException` avec `statusCode` et le message de l'API.

## Développement

```bash
composer install
vendor/bin/phpunit
```
