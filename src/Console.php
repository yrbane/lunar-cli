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
 * Les commandes sont cumulatives : lunar-cli + packages + projet.
 */
class Console
{
    /**
     * Chemin par defaut du fichier de configuration.
     */
    private const CONFIG_PATH = 'config/cli.json';

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

        // Chargement de la configuration du projet
        $configPath = $projectRoot . '/' . self::CONFIG_PATH;
        $config = file_exists($configPath) ? self::loadConfig($configPath) : [];

        // Execution du bootstrap si defini
        if (isset($config['bootstrap'])) {
            self::executeBootstrap($config['bootstrap']);
        }

        // Creation de l'application
        $app = new Application(
            $config['name'] ?? 'Lunar CLI',
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

        // 1. Enregistrement des commandes internes de lunar-cli
        $lunarCliCommands = dirname(__DIR__) . '/src/Command';
        if (is_dir($lunarCliCommands)) {
            $app->registerCommandsFromDirectory($lunarCliCommands, 'Lunar\\Cli\\Command');
        }

        // 2. Enregistrement des commandes des packages Lunar (vendor/lunar/*)
        self::registerPackageCommands($app, $projectRoot);

        // 3. Enregistrement des commandes du projet
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
     * Enregistre les commandes des packages Lunar installes.
     */
    private static function registerPackageCommands(Application $app, string $projectRoot): void
    {
        $vendorDirs = [
            $projectRoot . '/vendor/lunar',
            $projectRoot . '/vendor/yrbane',
        ];

        foreach ($vendorDirs as $vendorDir) {
            if (!is_dir($vendorDir)) {
                continue;
            }

            foreach (scandir($vendorDir) as $package) {
                if ($package === '.' || $package === '..' || $package === 'lunar-cli') {
                    continue;
                }

                $packagePath = $vendorDir . '/' . $package;
                $commandsPath = $packagePath . '/src/Command';

                if (is_dir($commandsPath)) {
                    // Determine le namespace depuis composer.json du package
                    $namespace = self::getPackageNamespace($packagePath);
                    if ($namespace) {
                        $app->registerCommandsFromDirectory($commandsPath, $namespace . '\\Command');
                    }
                }
            }
        }
    }

    /**
     * Recupere le namespace PSR-4 d'un package depuis son composer.json.
     */
    private static function getPackageNamespace(string $packagePath): ?string
    {
        $composerFile = $packagePath . '/composer.json';

        if (!file_exists($composerFile)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerFile), true);

        if (!isset($composer['autoload']['psr-4'])) {
            return null;
        }

        // Prend le premier namespace PSR-4
        foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
            return rtrim($namespace, '\\');
        }

        return null;
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
     * @return array<string, mixed>
     */
    private static function loadConfig(string $path): array
    {
        $content = file_get_contents($path);

        if (false === $content) {
            return [];
        }

        $config = json_decode($content, true);

        if (!is_array($config)) {
            return [];
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
