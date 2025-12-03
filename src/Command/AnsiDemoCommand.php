<?php
/**
 * Commande de demonstration des couleurs ANSI.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Command;

use Lunar\Cli\AbstractCommand;
use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper;

#[Command(name: 'style:ansi', description: 'Affiche toutes les couleurs ANSI supportees')]
class AnsiDemoCommand extends AbstractCommand
{
    public function execute(array $args): int
    {
        if ($this->wantsHelp($args)) {
            echo $this->getHelp();
            return 0;
        }

        ConsoleHelper::title('Palette ANSI');
        ConsoleHelper::subtitle('Couleurs de texte');

        foreach (range(30, 37) as $code) {
            echo ConsoleHelper::color("Code {$code} -> texte colore", (string) $code) . PHP_EOL;
        }

        ConsoleHelper::subtitle('Styles avances');

        echo ConsoleHelper::color('Texte en gras', '1') . PHP_EOL;
        echo ConsoleHelper::color('Texte souligne', '4') . PHP_EOL;
        echo ConsoleHelper::color('Texte inverse', '7') . PHP_EOL;

        ConsoleHelper::subtitle('Couleurs de fond');

        foreach (range(40, 47) as $code) {
            echo ConsoleHelper::color("Fond {$code}", (string) $code) . PHP_EOL;
        }

        ConsoleHelper::subtitle('Couleur combinee (texte + fond)');

        echo ConsoleHelper::color('Texte rouge sur fond jaune', '31;43') . PHP_EOL;
        echo ConsoleHelper::color('Texte vert sur fond bleu', '32;44') . PHP_EOL;

        ConsoleHelper::success('Demo terminee !');

        return 0;
    }

    public function getHelp(): string
    {
        return <<<'HELP'
Commande : style:ansi
Affiche toutes les couleurs ANSI supportees.

Utilisation :
  lunar style:ansi [--help]

Options :
    --help         Affiche cette aide

Description :
    Cette commande affiche toutes les couleurs ANSI supportees par le terminal.
    Elle est utile pour tester les styles de texte et de fond disponibles.

Exemples :
    lunar style:ansi
    lunar style:ansi --help

HELP;
    }
}
