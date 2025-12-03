<?php
/**
 * Commande d'affichage d'arborescence de fichiers.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Command;

use Lunar\Cli\AbstractCommand;
use Lunar\Cli\Attribute\Command;
use Lunar\Cli\Helper\ConsoleHelper as C;

/**
 * Commande "fs:tree" qui affiche un arbre de fichiers/dossiers (ou formats plats/JSON).
 */
#[Command(name: 'fs:tree', description: 'Affiche un arbre recursif de fichiers/dossiers.')]
class TreeCommand extends AbstractCommand
{
    /**
     * Execution principale de la commande.
     *
     * @param string[] $args arguments passes a la commande
     *
     * @return int code de sortie (0 = succes)
     */
    public function execute(array $args): int
    {
        // 1) Gestion de l'aide
        if ($this->wantsHelp($args)) {
            echo $this->getHelp();

            return 0;
        }

        // 2) Parsing des arguments
        $namedArgs = $this->parseNamedArgs($args);

        // Le chemin peut venir du 1er argument ou d'un --path
        $path = $this->getFirstPositionalArgument($args)
            ?? $this->getOptionValue($namedArgs, 'path', '.');

        $depthValue = $this->getOptionValue($namedArgs, 'depth');
        // Si depth n'est pas specifie, on le laisse a null (pas de limite).
        // S'il est specifie, on le cast en int.
        $maxDepth = null === $depthValue ? null : (int) $depthValue;

        $filesOnly = $this->hasFlag($args, 'files-only');
        $dirsOnly = $this->hasFlag($args, 'dirs-only');
        $nice = $this->hasFlag($args, 'nice');
        $compact = $this->hasFlag($args, 'compact');
        $flat = $this->hasFlag($args, 'flat');
        $json = $this->hasFlag($args, 'json');

        // 3) Verification du repertoire
        if (!is_dir($path)) {
            C::error("Le chemin '{$path}' n'est pas un dossier valide.");

            return 1;
        }

        // 4) Traitement selon les options
        if ($flat) {
            $this->displayFlat($path);

            return 0;
        }

        if ($json) {
            $structure = $this->buildTree($path, $maxDepth);
            echo json_encode($structure, JSON_PRETTY_PRINT) . "\n";

            return 0;
        }

        // 5) Affichage standard
        C::title("Explorateur de : {$path}");
        $this->displayTree(
            $path,
            prefix: '',
            isLast: true,
            depth: 0,
            maxDepth: $maxDepth,
            filesOnly: $filesOnly,
            dirsOnly: $dirsOnly,
            nice: $nice,
            compact: $compact
        );

        return 0;
    }

    /**
     * Affiche l'aide de la commande.
     */
    public function getHelp(): string
    {
        return <<<'HELP'
Commande : fs:tree

Description :
    Cette commande affiche l'arborescence d'un repertoire donne, avec des options
    pour personnaliser l'affichage (profondeur, fichiers/dossiers uniquement, etc.).

Utilisation :
  lunar fs:tree [chemin] [--depth=N] [--files-only] [--dirs-only] [--nice] [--compact] [--flat] [--json] [--help]

Options :
    --depth=N      Limite la profondeur de l'arborescence (N = entier positif).
    --files-only   N'affiche que les fichiers.
    --dirs-only    N'affiche que les dossiers.
    --nice         Affiche avec des icones et couleurs.
    --compact      Masque les dossiers vides.
    --flat         Affiche en liste plate (sans arborescence).
    --json         Affiche au format JSON.
    --help         Affiche cette aide.

Exemples :
    lunar fs:tree
    lunar fs:tree /chemin/vers/dossier --depth=2 --files-only
    lunar fs:tree /chemin/vers/dossier --json
    lunar fs:tree /chemin/vers/dossier --flat
    lunar fs:tree /chemin/vers/dossier --nice
    lunar fs:tree /chemin/vers/dossier --compact
    lunar fs:tree /chemin/vers/dossier --dirs-only

Remarque :
    - Le chemin par defaut est le repertoire courant ('.').
    - La profondeur par defaut est illimitee (tous les niveaux).
    - Les options --files-only et --dirs-only sont mutuellement exclusives.
    - L'option --nice necessite une prise en charge des couleurs ANSI par le terminal.

HELP;
    }

    /**
     * Affiche l'arborescence en liste plate (recursive).
     *
     * @param string $dir    chemin du repertoire
     * @param string $prefix prefixe pour calculer le chemin relatif
     */
    protected function displayFlat(string $dir, string $prefix = ''): void
    {
        foreach (scandir($dir) as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $relative = ltrim($prefix . DIRECTORY_SEPARATOR . $item, DIRECTORY_SEPARATOR);

            echo $relative . (is_dir($path) ? '/' : '') . "\n";

            if (is_dir($path)) {
                $this->displayFlat($path, $relative);
            }
        }
    }

    /**
     * Affiche l'arborescence dans un style arborescente.
     *
     * @param string   $dir       chemin du repertoire en cours d'exploration
     * @param string   $prefix    prefixe textuel a afficher avant chaque element
     * @param bool     $isLast    indique si c'est le dernier element du niveau courant
     * @param int      $depth     profondeur actuelle
     * @param null|int $maxDepth  profondeur max souhaitee (ou null si illimitee)
     * @param bool     $filesOnly N'afficher que les fichiers
     * @param bool     $dirsOnly  N'afficher que les dossiers
     * @param bool     $nice      activer le mode "graphique" (icones, couleurs)
     * @param bool     $compact   masquer les dossiers vides
     */
    protected function displayTree(
        string $dir,
        string $prefix = '',
        bool $isLast = true,
        int $depth = 0,
        ?int $maxDepth = null,
        bool $filesOnly = false,
        bool $dirsOnly = false,
        bool $nice = false,
        bool $compact = false
    ): void {
        if (null !== $maxDepth && $depth > $maxDepth) {
            return;
        }

        // Filtre des elements
        $items = array_filter(
            array_diff(scandir($dir), ['.', '..']),
            function ($item) use ($dir, $filesOnly, $dirsOnly, $compact) {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if ($filesOnly && is_dir($path)) {
                    return false;
                }
                if ($dirsOnly && !is_dir($path)) {
                    return false;
                }
                if ($compact && is_dir($path)) {
                    $sub = array_diff(scandir($path), ['.', '..']);

                    return count($sub) > 0;
                }

                return true;
            }
        );

        $items = array_values($items);
        $total = count($items);

        // Parcours des elements
        foreach ($items as $index => $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($path);

            $isLastItem = ($index === $total - 1);
            $symbol = $isLastItem ? '└──' : '├──';

            // Couleur du prefixe vertical
            $nextPrefix = $prefix . C::color($isLastItem ? '    ' : '│   ', '36');

            // Affiche la ligne
            echo $prefix . C::color($symbol . ' ', '36');

            if ($nice) {
                // Icone/texte colore
                echo C::color(C::fileIcon($path) . ' ' . $item . ($isDir ? '/' : ''), C::fileColor($path)) . "\n";
            } else {
                // Mode basique
                echo ($isDir ? $item . '/' : $item) . "\n";
            }

            // Si c'est un dossier, on continue la descente
            if ($isDir) {
                $this->displayTree(
                    $path,
                    $nextPrefix,
                    $isLastItem,
                    $depth + 1,
                    $maxDepth,
                    $filesOnly,
                    $dirsOnly,
                    $nice,
                    $compact
                );
            }
        }
    }

    /**
     * Construit la structure de l'arborescence sous forme de tableau
     * (utilisee pour l'option --json).
     *
     * @param string   $dir      chemin du repertoire
     * @param null|int $maxDepth profondeur max (null = illimitee)
     * @param int      $depth    profondeur courante
     *
     * @return array<string, mixed>
     */
    protected function buildTree(string $dir, ?int $maxDepth = null, int $depth = 0): array
    {
        if (null !== $maxDepth && $depth > $maxDepth) {
            return [];
        }

        $structure = [];
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $structure[$item] = $this->buildTree($path, $maxDepth, $depth + 1);
            } else {
                $structure[] = $item;
            }
        }

        return $structure;
    }
}
