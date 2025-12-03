<?php
/**
 * Classe abstraite pour les commandes CLI.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

/**
 * Classe abstraite pour les commandes de la console.
 * Gere la logique de base de parsing des arguments.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * Verifie si l'utilisateur a demande l'aide via --help.
     *
     * @param string[] $args liste des arguments de la ligne de commande
     */
    protected function wantsHelp(array $args): bool
    {
        return in_array('--help', $args, true);
    }

    /**
     * Renvoie le premier argument "nu" (celui qui n'est pas un --key=value)
     * ou null si aucun argument nu n'est trouve.
     *
     * @param string[] $args
     */
    protected function getFirstPositionalArgument(array $args): ?string
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--')) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * Parse les arguments nommes de style --key=value
     * et renvoie un tableau associatif [key => value].
     *
     * @param string[] $args
     *
     * @return array<string,string>
     */
    protected function parseNamedArgs(array $args): array
    {
        $parsed = [];
        foreach ($args as $arg) {
            if (preg_match('/^--([^=]+)=(.*)$/', $arg, $matches)) {
                $parsed[$matches[1]] = $matches[2];
            }
        }

        return $parsed;
    }

    /**
     * Verifie la presence d'une option nommee (ex: --some-flag).
     * Ici, on ne teste que la cle (pas la valeur).
     *
     * @param string[] $args
     * @param string   $option nom de l'option sans le "--"
     */
    protected function hasFlag(array $args, string $option): bool
    {
        foreach ($args as $arg) {
            if ($arg === '--' . $option) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recupere la valeur d'une option nommee dans le tableau issu de parseNamedArgs().
     *
     * @param array<string,string> $namedArgs
     */
    protected function getOptionValue(array $namedArgs, string $key, mixed $default = null): mixed
    {
        return $namedArgs[$key] ?? $default;
    }
}
