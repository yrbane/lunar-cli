<?php
/**
 * Commande de demonstration interactive.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Command;

use Lunar\Cli\AbstractCommand;
use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper as C;

#[Command(name: 'hello', description: 'Demande ton nom et te salue')]
class HelloCommand extends AbstractCommand
{
    public function execute(array $args): int
    {
        if ($this->wantsHelp($args)) {
            echo $this->getHelp();
            return 0;
        }

        C::title('Bienvenue dans Lunar CLI');
        C::subtitle('Nous allons faire connaissance...');

        $name = C::ask('Quel est ton prenom ?', 'Utilisateur');
        C::success("Enchante, {$name} !");

        return 0;
    }

    public function getHelp(): string
    {
        return <<<'HELP'
Commande : hello
Demande ton nom et te salue.

Utilisation :
  lunar hello [--help]

Options :
    --help         Affiche cette aide

Description :
    Cette commande te demande ton prenom et t'accueille chaleureusement.
    Elle sert d'exemple pour les interactions utilisateur dans la console.

Exemples :
    lunar hello
    lunar hello --help

Remarque :
    Tu peux appuyer sur Entree pour accepter la valeur par defaut.

HELP;
    }
}
