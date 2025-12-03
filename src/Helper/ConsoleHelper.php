<?php
/**
 * Helper pour les operations console.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Helper;

/**
 * Class ConsoleHelper.
 *
 * Fournit un ensemble de methodes utilitaires pour ameliorer
 * l'experience utilisateur dans le terminal CLI.
 * Inclut des fonctions de coloration ANSI, d'interaction utilisateur,
 * d'affichage style (titres, tableaux, barres de progression).
 */
class ConsoleHelper
{
    /**
     * Definit l'encodage par defaut utilise pour les calculs de largeur dans les tableaux.
     * Peut etre modifie si le terminal ne supporte pas UTF-8.
     */
    protected const TERMINAL_ENCODING = 'UTF-8';

    /** @var string Encodage actif utilise dans le terminal */
    protected static string $terminalEncoding = self::TERMINAL_ENCODING;

    /**
     * Determine si le terminal semble compatible UTF-8 via la variable d'environnement LANG.
     */
    public static function isUtf8Compatible(): bool
    {
        $lang = getenv('LANG');

        return is_string($lang) && str_contains($lang, 'UTF-8');
    }

    /**
     * Affiche un tableau formate avec entetes et lignes, colore.
     * Gere correctement les largeurs de colonnes en UTF-8 (ou encodage force).
     *
     * @param array<int, string>                   $headers entetes de colonnes
     * @param array<int, array<int|string, mixed>> $rows    donnees a afficher (tableau de tableaux)
     */
    public static function table(array $headers, array $rows): void
    {
        $encoding = self::TERMINAL_ENCODING;
        $lengths = array_map(fn ($h) => mb_strwidth($h, $encoding), $headers);

        foreach ($rows as $row) {
            foreach ($row as $i => $col) {
                $colWidth = mb_strwidth((string) $col, $encoding);
                $lengths[$i] = max($lengths[$i] ?? 0, $colWidth);
            }
        }

        $line = '+' . implode('+', array_map(fn ($l) => str_repeat('-', $l + 2), $lengths)) . "+\n";
        echo self::color($line, '35');

        // En-tetes en magenta gras
        echo self::color('| ', '35');
        echo implode(self::color(' | ', '35'), array_map(
            fn ($h, $i) => self::color(str_pad($h, $lengths[$i] + mb_strlen($h) - mb_strwidth($h, $encoding)), '1;35'),
            $headers,
            array_keys($headers)
        ));
        echo self::color(" |\n", '35');

        echo self::color($line, '35');

        // Contenu des lignes (couleur claire)
        foreach ($rows as $row) {
            echo self::color('| ', '34');
            echo implode(self::color(' | ', '34'), array_map(
                fn ($col, $i) => self::color(str_pad(
                    (string) $col,
                    $lengths[$i] + mb_strlen((string) $col) - mb_strwidth((string) $col, $encoding)
                ), '0;37'),
                $row,
                array_keys($row)
            ));
            echo self::color(" |\n", '34');
        }

        echo self::color($line, '35');
    }

    /**
     * Permet de redefinir dynamiquement l'encodage utilise par la console.
     *
     * @param string $encoding ex: 'UTF-8', 'ISO-8859-1', etc
     */
    public static function setTerminalEncoding(string $encoding): void
    {
        self::$terminalEncoding = $encoding;
    }

    /**
     * Retourne l'encodage actuellement utilise par la console.
     */
    public static function getTerminalEncoding(): string
    {
        return self::$terminalEncoding;
    }

    /**
     * Applique une couleur ANSI a une chaine de texte.
     *
     * @param string $text      le texte a colorer
     * @param string $colorCode le code ANSI (ex: '32' pour vert)
     *
     * @return string le texte colore
     */
    public static function color(string $text, string $colorCode): string
    {
        return "\033[{$colorCode}m{$text}\033[0m";
    }

    /**
     * Affiche un message de succes (vert).
     */
    public static function success(string $message): void
    {
        echo self::color("  {$message}\n", '32');
    }

    /**
     * Affiche un message d'erreur (rouge).
     */
    public static function error(string $message): void
    {
        echo self::color("  {$message}\n", '31');
    }

    /**
     * Affiche un message d'information (cyan).
     */
    public static function info(string $message): void
    {
        echo self::color("  {$message}\n", '36');
    }

    /**
     * Affiche un message d'avertissement (jaune).
     */
    public static function warning(string $message): void
    {
        echo self::color("  {$message}\n", '33');
    }

    /**
     * Pose une question simple a l'utilisateur.
     *
     * @param string      $question le message a afficher
     * @param null|string $default  valeur par defaut si l'utilisateur ne saisit rien
     *
     * @return string la reponse de l'utilisateur
     */
    public static function ask(string $question, ?string $default = null): string
    {
        $prompt = $default ? "{$question} [{$default}] " : "{$question} ";
        echo self::color($prompt, '33');
        $answer = trim((string) fgets(STDIN));

        return '' !== $answer ? $answer : ($default ?? '');
    }

    /**
     * Pose une question dont la reponse doit rester masquee (mot de passe).
     *
     * @return string reponse saisie sans affichage
     */
    public static function askHidden(string $question): string
    {
        echo self::color($question . ' ', '33');
        if (preg_match('/^win/i', PHP_OS)) {
            $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
            file_put_contents($vbscript, 'wscript.echo(InputBox("' . $question . '","",""))');
            $command = 'cscript //nologo ' . escapeshellarg($vbscript);
            $password = rtrim((string) shell_exec($command));
            unlink($vbscript);
        } else {
            system('stty -echo');
            $password = trim((string) fgets(STDIN));
            system('stty echo');
        }
        echo "\n";

        return $password;
    }

    /**
     * Demande une confirmation Oui/Non a l'utilisateur.
     *
     * @param bool $default valeur par defaut (true = oui)
     *
     * @return bool resultat de la confirmation
     */
    public static function confirm(string $question, bool $default = true): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $response = strtolower(trim(self::ask("{$question} [{$defaultText}]", $default ? 'y' : 'n')));

        return 'y' === $response;
    }

    /**
     * Affiche un titre stylise encadre en ASCII et colore.
     *
     * @param string $text le texte du titre
     */
    public static function title(string $text): void
    {
        $border = str_repeat('=', strlen($text) + 8);
        echo "\n" . self::color('+' . $border . '+', '35') . "\n";
        echo self::color('|    ' . $text . '    |', '1;35') . "\n";
        echo self::color('+' . $border . '+', '35') . "\n\n";
    }

    /**
     * Affiche un sous-titre stylise.
     */
    public static function subtitle(string $text): void
    {
        echo self::color("> {$text}\n", '1;34');
    }

    /**
     * Affiche une barre de progression sur une ligne.
     *
     * @param int $current valeur actuelle
     * @param int $total   valeur finale
     * @param int $width   largeur de la barre
     */
    public static function progressBar(int $current, int $total, int $width = 50): void
    {
        $percent = ($current / $total);
        $filled = (int) round($percent * $width);
        $bar = str_repeat('#', $filled) . str_repeat('-', $width - $filled);
        $percentDisplay = str_pad((string) round($percent * 100) . '%', 4, ' ', STR_PAD_LEFT);
        echo "\r" . self::color("Progress: [{$bar}] {$percentDisplay}", '36');
        if ($current === $total) {
            echo "\n";
        }
    }

    /**
     * Affiche une ligne vide.
     */
    public static function newLine(int $count = 1): void
    {
        echo str_repeat("\n", $count);
    }

    /**
     * Affiche une ligne de separation.
     */
    public static function separator(int $width = 60, string $char = '-'): void
    {
        echo self::color(str_repeat($char, $width) . "\n", '90');
    }

    /**
     * Retourne une icone selon le type de fichier.
     *
     * @param string $path chemin du fichier ou dossier
     *
     * @return string icone unicode
     */
    public static function fileIcon(string $path): string
    {
        if (is_dir($path)) {
            return '📁';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => '🐘',
            'js', 'ts' => '📜',
            'json' => '📋',
            'md', 'txt' => '📝',
            'yml', 'yaml' => '⚙️',
            'css', 'scss', 'sass' => '🎨',
            'html', 'htm' => '🌐',
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp' => '🖼️',
            'pdf' => '📕',
            'zip', 'tar', 'gz', 'rar' => '📦',
            'sh', 'bash' => '🔧',
            'sql' => '🗃️',
            'lock' => '🔒',
            default => '📄',
        };
    }

    /**
     * Retourne un code couleur ANSI selon le type de fichier.
     *
     * @param string $path chemin du fichier ou dossier
     *
     * @return string code couleur ANSI
     */
    public static function fileColor(string $path): string
    {
        if (is_dir($path)) {
            return '1;34'; // Bleu gras
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => '35',    // Magenta
            'js', 'ts' => '33', // Jaune
            'json' => '36',   // Cyan
            'md', 'txt' => '37', // Blanc
            'yml', 'yaml' => '32', // Vert
            'css', 'scss', 'sass' => '95', // Magenta clair
            'html', 'htm' => '91', // Rouge clair
            'lock' => '90',   // Gris
            default => '0',   // Defaut
        };
    }
}
