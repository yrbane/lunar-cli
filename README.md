# Lunar CLI

Framework CLI PHP autonome avec commandes, helpers console et rendu de tableaux.

## Installation

```bash
composer require lunar/cli
```

## Utilisation rapide

### Creer une commande

```php
<?php

use Lunar\Cli\AbstractCommand;
use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper;

#[Command(name: 'hello:world', description: 'Affiche un message de bienvenue')]
class HelloWorldCommand extends AbstractCommand
{
    public function execute(array $args): int
    {
        if ($this->wantsHelp($args)) {
            ConsoleHelper::info($this->getHelp());
            return 0;
        }

        $name = $this->getFirstPositionalArgument($args) ?? 'World';
        ConsoleHelper::success("Hello, {$name}!");

        return 0;
    }

    public function getHelp(): string
    {
        return <<<HELP
        Affiche un message de bienvenue.

        Usage:
          hello:world [name]

        Arguments:
          name    Nom a saluer (defaut: World)

        Options:
          --help  Affiche cette aide
        HELP;
    }
}
```

### Creer l'application

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Lunar\Cli\Application;

$app = new Application('Mon Application', '1.0.0');

// Enregistrer des commandes depuis un repertoire
$app->registerCommandsFromDirectory(__DIR__ . '/src/Command', 'App\\Command');

// Ou enregistrer une commande manuellement
$app->addCommand('test:run', new TestCommand(), 'Execute les tests');

exit($app->run());
```

## Fonctionnalites

### ConsoleHelper

Utilitaires pour l'affichage en console :

```php
use Lunar\Cli\Helper\ConsoleHelper;

// Messages colores
ConsoleHelper::success("Operation reussie !");
ConsoleHelper::error("Une erreur s'est produite");
ConsoleHelper::info("Information importante");
ConsoleHelper::warning("Attention !");

// Titres
ConsoleHelper::title("Mon Application");
ConsoleHelper::subtitle("Section");

// Interaction
$name = ConsoleHelper::ask("Votre nom ?", "Anonyme");
$password = ConsoleHelper::askHidden("Mot de passe ?");
$confirmed = ConsoleHelper::confirm("Continuer ?", true);

// Progression
for ($i = 0; $i <= 100; $i++) {
    ConsoleHelper::progressBar($i, 100);
    usleep(10000);
}

// Tableaux simples
ConsoleHelper::table(
    ['Nom', 'Email', 'Role'],
    [
        ['Alice', 'alice@example.com', 'Admin'],
        ['Bob', 'bob@example.com', 'User'],
    ]
);
```

### TableRenderer

Rendu avance de tableaux :

```php
use Lunar\Cli\Helper\TableRenderer;

$rows = [
    ['Commande' => 'cache:clear', 'Description' => 'Vide le cache'],
    ['Commande' => 'user:create', 'Description' => 'Cree un utilisateur'],
];

TableRenderer::renderSingleTable($rows, [
    'borderColor' => '35',
    'headerColor' => '1;35',
    'rowColor' => '0;37',
    'showHeaders' => true,
]);
```

### AbstractCommand

Classe de base avec methodes utilitaires :

```php
use Lunar\Cli\AbstractCommand;

class MyCommand extends AbstractCommand
{
    public function execute(array $args): int
    {
        // Verifier si --help est demande
        if ($this->wantsHelp($args)) {
            return 0;
        }

        // Recuperer le premier argument positionnel
        $file = $this->getFirstPositionalArgument($args);

        // Parser les arguments nommes (--key=value)
        $namedArgs = $this->parseNamedArgs($args);
        $format = $this->getOptionValue($namedArgs, 'format', 'json');

        // Verifier la presence d'un flag (--verbose)
        $verbose = $this->hasFlag($args, 'verbose');

        return 0;
    }
}
```

## Injection de dependances

Vous pouvez definir une factory personnalisee pour l'injection de dependances :

```php
use Lunar\Cli\Application;

$container = new MyContainer();

$app = new Application();
$app->setCommandFactory(function (string $className) use ($container) {
    return $container->get($className);
});
```

## Licence

MIT
