<?php
/**
 * Point d'entree principal de la console.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

use Lunar\Config\Config;

/**
 * Class Console.
 *
 * Charge la configuration depuis config/cli.json et lance l'application.
 * Les commandes sont cumulatives : lunar-cli + packages + projet.
 */
class Console
{
    private const CONFIG_FILE = 'cli';

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
            $projectRoot = Config::getProjectRoot();
        }

        // Definition de la constante PROJECT_ROOT si pas deja definie
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', $projectRoot);
        }

        // Execution du bootstrap si defini
        $bootstrap = Config::get(self::CONFIG_FILE, 'bootstrap');
        if ($bootstrap) {
            self::executeBootstrap($bootstrap);
        }

        // Creation de l'application
        $app = new Application(
            Config::get(self::CONFIG_FILE, 'name', 'Lunar CLI'),
            Config::get(self::CONFIG_FILE, 'version', '1.0.0')
        );

        // Configuration de la factory si definie
        $factoryClass = Config::get(self::CONFIG_FILE, 'factory');
        if ($factoryClass && class_exists($factoryClass)) {
            $factory = new $factoryClass();
            $app->setCommandFactory(fn(string $class) => $factory->make($class));
        }

        // 1. Enregistrement des commandes internes de lunar-cli
        $lunarCliCommands = dirname(__DIR__) . '/src/Command';
        if (is_dir($lunarCliCommands)) {
            $app->registerCommandsFromDirectory($lunarCliCommands, 'Lunar\\Cli\\Command');
        }

        // 2. Enregistrement des commandes des packages Lunar (vendor/lunar/*)
        self::registerPackageCommands($app, $projectRoot);

        // 3. Enregistrement des commandes du projet
        $commands = Config::get(self::CONFIG_FILE, 'commands', []);
        if (is_array($commands)) {
            foreach ($commands as $namespace => $directory) {
                $fullPath = Config::resolvePath($directory);
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
