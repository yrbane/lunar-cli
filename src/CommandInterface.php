<?php
/**
 * Interface pour les commandes CLI.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

/**
 * Interface CommandInterface.
 *
 * Represente la forme minimale d'une commande CLI.
 */
interface CommandInterface
{
    /**
     * Execute la commande avec les arguments passes en CLI.
     *
     * @param string[] $args
     *
     * @return int Code de sortie (0 = succes, >0 = code d'erreur)
     */
    public function execute(array $args): int;

    /**
     * Retourne une aide detaillee pour la commande (utilisee par --help).
     */
    public function getHelp(): string;
}
