<?php
/**
 * Application CLI principale.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli;

use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper;
use Lunar\Cli\Helper\TableRenderer;

/**
 * Classe Application.
 *
 * Gere l'enregistrement et l'execution des commandes CLI.
 */
class Application
{
    private string $name;
    private string $version;

    /**
     * Liste des commandes enregistrees.
     *
     * @var array<string, array{instance: CommandInterface, description: string}>
     */
    private array $commands = [];

    /**
     * Factory pour creer les instances de commandes.
     *
     * @var callable|null
     */
    private $commandFactory = null;

    public function __construct(string $name = 'Lunar CLI', string $version = '1.0.0')
    {
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * Definit une factory personnalisee pour creer les instances de commandes.
     *
     * @param callable $factory Fonction qui recoit le nom de classe et retourne une instance
     */
    public function setCommandFactory(callable $factory): self
    {
        $this->commandFactory = $factory;
        return $this;
    }

    /**
     * Enregistre une commande manuellement.
     */
    public function addCommand(string $name, CommandInterface $command, string $description = ''): self
    {
        $this->commands[$name] = [
            'instance' => $command,
            'description' => $description,
        ];
        return $this;
    }

    /**
     * Enregistre des commandes en scannant un repertoire.
     *
     * @param string $directory Repertoire contenant les fichiers *Command.php
     * @param string $namespace Namespace de base des commandes
     */
    public function registerCommandsFromDirectory(string $directory, string $namespace): self
    {
        $pattern = rtrim($directory, '/') . '/*Command.php';

        foreach (glob($pattern) ?: [] as $file) {
            $className = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (!class_exists($className)) {
                require_once $file;
            }

            $this->registerCommandClass($className);
        }

        return $this;
    }

    /**
     * Enregistre une classe de commande via son attribut.
     *
     * @param class-string $className
     */
    public function registerCommandClass(string $className): self
    {
        if (!class_exists($className)) {
            return $this;
        }

        $ref = new \ReflectionClass($className);

        if (!$ref->isInstantiable()) {
            return $this;
        }

        $attrs = $ref->getAttributes(Command::class);

        if (empty($attrs)) {
            return $this;
        }

        /** @var Command $meta */
        $meta = $attrs[0]->newInstance();

        $instance = $this->createCommandInstance($className);

        if ($instance instanceof CommandInterface) {
            $this->commands[$meta->name] = [
                'instance' => $instance,
                'description' => $meta->description,
            ];
        }

        return $this;
    }

    /**
     * Execute l'application avec les arguments CLI.
     *
     * @param string[] $argv Arguments de la ligne de commande
     */
    public function run(array $argv = []): int
    {
        if (empty($argv)) {
            $argv = $_SERVER['argv'] ?? [];
        }

        // Verifie la compatibilite du terminal avec l'UTF-8
        if (!ConsoleHelper::isUtf8Compatible()) {
            ConsoleHelper::setTerminalEncoding('ISO-8859-1');
        }

        $commandName = $argv[1] ?? null;

        // Pas de commande => lister tout
        if (!$commandName) {
            $this->displayTitle();
            $this->displayCommandList();
            return 0;
        }

        // Commande exacte trouvee => execution ou aide
        if (isset($this->commands[$commandName])) {
            $cmd = $this->commands[$commandName]['instance'];
            $args = array_slice($argv, 2);

            if (in_array('--help', $args, true)) {
                ConsoleHelper::info($cmd->getHelp());
                return 0;
            }

            return $cmd->execute($args);
        }

        // Filtrage par prefixe
        $possibleMatches = [];
        foreach ($this->commands as $name => $info) {
            if (str_starts_with($name, $commandName . ':')) {
                $possibleMatches[$name] = $info;
            }
        }

        if (!empty($possibleMatches)) {
            $this->displayTitle();
            $this->displayCommandList($possibleMatches);
            return 0;
        }

        // Commande inconnue
        $this->displayTitle();
        ConsoleHelper::error("Commande inconnue : \"{$commandName}\"");
        ConsoleHelper::newLine();
        $this->displayCommandList();
        return 1;
    }

    /**
     * Affiche le titre de l'application.
     */
    private function displayTitle(): void
    {
        ConsoleHelper::title("{$this->name} v{$this->version}");
    }

    /**
     * Affiche la liste des commandes.
     *
     * @param array<string, array{instance: CommandInterface, description: string}>|null $commands
     */
    private function displayCommandList(?array $commands = null): void
    {
        $commands = $commands ?? $this->commands;

        if (empty($commands)) {
            ConsoleHelper::warning("Aucune commande enregistree.");
            return;
        }

        ConsoleHelper::subtitle("Commandes disponibles :");
        ConsoleHelper::newLine();

        $rows = $this->buildCommandRows($commands);

        TableRenderer::renderSingleTable($rows, [
            'borderColor' => '35',
            'headerColor' => '1;35',
            'rowColor' => '0;37',
            'showHeaders' => true,
            'columns' => [
                'Groupe' => 'Groupe',
                'Commande' => 'Commande',
                'Description' => 'Description',
            ],
        ]);

        ConsoleHelper::newLine();
    }

    /**
     * Construit les lignes pour le tableau des commandes.
     *
     * @param array<string, array{instance: CommandInterface, description: string}> $commands
     * @return array<array<string, string>>
     */
    private function buildCommandRows(array $commands): array
    {
        $rows = [];

        foreach ($commands as $name => $data) {
            $prefix = explode(':', $name)[0] ?? 'misc';
            $rows[] = [
                'Groupe' => strtoupper($prefix),
                'Commande' => $name,
                'Description' => $data['description'],
            ];
        }

        // Tri par groupe puis par commande
        usort($rows, function (array $a, array $b) {
            $groupComp = strcmp($a['Groupe'], $b['Groupe']);
            if ($groupComp !== 0) {
                return $groupComp;
            }
            return strcmp($a['Commande'], $b['Commande']);
        });

        return $rows;
    }

    /**
     * Cree une instance de commande.
     *
     * @param class-string $className
     */
    private function createCommandInstance(string $className): object
    {
        if ($this->commandFactory !== null) {
            return ($this->commandFactory)($className);
        }

        return new $className();
    }

    /**
     * Retourne le nom de l'application.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retourne la version de l'application.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Retourne les commandes enregistrees.
     *
     * @return array<string, array{instance: CommandInterface, description: string}>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
}
