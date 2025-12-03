<?php
/**
 * Point d'entree principal de la console.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

/**
 * Class Console.
 *
 * Charge la configuration depuis config/console.json et lance l'application.
 */
class Console
{
    /**
     * Chemin par defaut du fichier de configuration.
     */
    private const CONFIG_PATH = 'config/console.json';

    /**
     * Lance la console en lisant la configuration.
     *
     * @param string|null $projectRoot Racine du projet (auto-detectee si null)
     *
     * @return int Code de sortie
     */
    public static function run(?string $projectRoot = null): int
    {
        // Auto-detection de la racine du projet
        if (null === $projectRoot) {
            $projectRoot = self::detectProjectRoot();
        }

        // Definition de la constante PROJECT_ROOT si pas deja definie
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', $projectRoot);
        }

        // Chargement de la configuration
        $configPath = $projectRoot . '/' . self::CONFIG_PATH;

        if (!file_exists($configPath)) {
            fwrite(STDERR, "Erreur: Fichier de configuration introuvable: {$configPath}\n");
            return 1;
        }

        $config = self::loadConfig($configPath);

        if (null === $config) {
            fwrite(STDERR, "Erreur: Impossible de parser le fichier de configuration JSON.\n");
            return 1;
        }

        // Execution du bootstrap si defini
        if (isset($config['bootstrap'])) {
            self::executeBootstrap($config['bootstrap']);
        }

        // Creation de l'application
        $app = new Application(
            $config['name'] ?? 'Console',
            $config['version'] ?? '1.0.0'
        );

        // Configuration de la factory si definie
        if (isset($config['factory'])) {
            $factoryClass = $config['factory'];
            if (class_exists($factoryClass)) {
                $factory = new $factoryClass();
                $app->setCommandFactory(fn(string $class) => $factory->make($class));
            }
        }

        // Enregistrement des commandes
        if (isset($config['commands']) && is_array($config['commands'])) {
            foreach ($config['commands'] as $namespace => $directory) {
                $fullPath = $projectRoot . '/' . ltrim($directory, '/');
                if (is_dir($fullPath)) {
                    $app->registerCommandsFromDirectory($fullPath, $namespace);
                }
            }
        }

        // Execution
        return $app->run();
    }

    /**
     * Detecte la racine du projet en cherchant composer.json.
     */
    private static function detectProjectRoot(): string
    {
        // Cherche depuis le repertoire courant
        $dir = getcwd();

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        // Fallback: repertoire courant
        return getcwd() ?: '.';
    }

    /**
     * Charge et parse le fichier de configuration JSON.
     *
     * @return array<string, mixed>|null
     */
    private static function loadConfig(string $path): ?array
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return null;
        }

        $config = json_decode($content, true);

        if (!is_array($config)) {
            return null;
        }

        return $config;
    }

    /**
     * Execute la classe de bootstrap.
     */
    private static function executeBootstrap(string $bootstrapClass): void
    {
        if (!class_exists($bootstrapClass)) {
            fwrite(STDERR, "Warning: Classe de bootstrap introuvable: {$bootstrapClass}\n");
            return;
        }

        $bootstrap = new $bootstrapClass();

        if ($bootstrap instanceof BootstrapInterface) {
            $bootstrap->boot();
        } elseif (method_exists($bootstrap, 'boot')) {
            $bootstrap->boot();
        }
    }
}
