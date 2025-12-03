<?php
/**
 * Interface pour le bootstrap de la console.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

/**
 * Interface BootstrapInterface.
 *
 * Permet de definir une classe de bootstrap qui sera executee
 * avant le lancement de la console.
 */
interface BootstrapInterface
{
    /**
     * Methode appelee au demarrage de la console.
     * Utilisee pour charger la configuration, definir des constantes, etc.
     */
    public function boot(): void;
}
