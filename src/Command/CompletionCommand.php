<?php

declare(strict_types=1);

namespace Lunar\Cli\Command;

use Lunar\Cli\AbstractCommand;
use Lunar\Cli\Application;
use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper as C;

#[Command(name: 'cli:completion', description: 'Génère le script de complétion shell')]
final class CompletionCommand extends AbstractCommand
{
    private ?Application $app = null;

    public function setApplication(Application $app): void
    {
        $this->app = $app;
    }

    public function execute(array $args): int
    {
        if ($this->wantsHelp($args)) {
            echo $this->getHelp();

            return 0;
        }

        $namedArgs = $this->parseNamedArgs($args);
        $shell = $this->getOptionValue($namedArgs, 'shell', $this->detectShell());
        $install = $this->hasFlag($namedArgs, 'install');

        $script = match ($shell) {
            'bash' => $this->generateBashCompletion(),
            'zsh' => $this->generateZshCompletion(),
            'fish' => $this->generateFishCompletion(),
            default => null,
        };

        if ($script === null) {
            C::error("Shell non supporté: {$shell}");
            C::warning('Shells supportés: bash, zsh, fish');

            return 1;
        }

        if ($install) {
            return $this->installCompletion($shell, $script);
        }

        echo $script;

        return 0;
    }

    private function detectShell(): string
    {
        $shell = getenv('SHELL') ?: '/bin/bash';

        return basename($shell);
    }

    private function generateBashCompletion(): string
    {
        $commands = $this->getCommandList();
        $commandsStr = implode(' ', $commands);

        return <<<BASH
# Bash completion for Lunar CLI
# Add to ~/.bashrc: eval "\$(bin/console cli:completion --shell=bash)"

_lunar_cli_completion() {
    local cur="\${COMP_WORDS[COMP_CWORD]}"
    local commands="{$commandsStr}"

    if [[ \${COMP_CWORD} -eq 1 ]]; then
        COMPREPLY=(\$(compgen -W "\${commands}" -- "\${cur}"))
    fi
}

complete -F _lunar_cli_completion bin/console
complete -F _lunar_cli_completion console
complete -F _lunar_cli_completion lunar

BASH;
    }

    private function generateZshCompletion(): string
    {
        $commands = $this->getCommandList();
        $descriptions = $this->getCommandDescriptions();

        $completions = [];
        foreach ($commands as $cmd) {
            $desc = $descriptions[$cmd] ?? '';
            $desc = str_replace("'", "'\\''", $desc);
            $completions[] = "'{$cmd}:{$desc}'";
        }
        $completionsStr = implode("\n        ", $completions);

        return <<<ZSH
# Zsh completion for Lunar CLI
# Add to ~/.zshrc: eval "\$(bin/console cli:completion --shell=zsh)"

_lunar_cli_completion() {
    local -a commands
    commands=(
        {$completionsStr}
    )

    _describe 'command' commands
}

compdef _lunar_cli_completion bin/console
compdef _lunar_cli_completion console
compdef _lunar_cli_completion lunar

ZSH;
    }

    private function generateFishCompletion(): string
    {
        $commands = $this->getCommandList();
        $descriptions = $this->getCommandDescriptions();

        $completions = [];
        foreach ($commands as $cmd) {
            $desc = $descriptions[$cmd] ?? '';
            $desc = str_replace("'", "\\'", $desc);
            $completions[] = "complete -c console -n '__fish_use_subcommand' -a '{$cmd}' -d '{$desc}'";
        }
        $completionsStr = implode("\n", $completions);

        return <<<FISH
# Fish completion for Lunar CLI
# Save to ~/.config/fish/completions/console.fish

complete -c console -e
complete -c bin/console -e
{$completionsStr}

FISH;
    }

    /**
     * @return array<string>
     */
    private function getCommandList(): array
    {
        if ($this->app === null) {
            return [];
        }

        return array_keys($this->app->getCommands());
    }

    /**
     * @return array<string, string>
     */
    private function getCommandDescriptions(): array
    {
        if ($this->app === null) {
            return [];
        }

        $descriptions = [];
        foreach ($this->app->getCommands() as $name => $command) {
            $descriptions[$name] = $command['description'] ?? '';
        }

        return $descriptions;
    }

    private function installCompletion(string $shell, string $script): int
    {
        $home = getenv('HOME');

        $configFile = match ($shell) {
            'bash' => $home . '/.bashrc',
            'zsh' => $home . '/.zshrc',
            'fish' => $home . '/.config/fish/completions/console.fish',
            default => null,
        };

        if ($configFile === null) {
            C::error("Installation automatique non supportée pour {$shell}");

            return 1;
        }

        if ($shell === 'fish') {
            $dir = dirname($configFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($configFile, $script);
            C::success("Complétion installée dans {$configFile}");
        } else {
            $marker = "# Lunar CLI completion";
            $content = file_exists($configFile) ? file_get_contents($configFile) : '';

            if (str_contains($content, $marker)) {
                C::warning("Complétion déjà installée dans {$configFile}");

                return 0;
            }

            $evalCmd = match ($shell) {
                'bash' => 'eval "$(bin/console cli:completion --shell=bash)"',
                'zsh' => 'eval "$(bin/console cli:completion --shell=zsh)"',
                default => '',
            };

            $addition = "\n{$marker}\n{$evalCmd}\n";
            file_put_contents($configFile, $content . $addition);
            C::success("Complétion ajoutée à {$configFile}");
            C::warning("Redémarrez votre shell ou exécutez: source {$configFile}");
        }

        return 0;
    }

    public function getHelp(): string
    {
        return <<<'HELP'
            Command: cli:completion
            Génère le script de complétion pour votre shell.

            Usage:
              bin/console cli:completion [options]

            Options:
              --shell=<shell>    Shell cible (bash, zsh, fish). Auto-détecté si omis.
              --install          Installe la complétion dans votre config shell
              --help             Affiche cette aide

            Exemples:
              bin/console cli:completion                    # Affiche le script
              bin/console cli:completion --shell=zsh       # Script pour zsh
              bin/console cli:completion --install         # Installation automatique
              eval "$(bin/console cli:completion)"         # Activation manuelle

            HELP;
    }
}
