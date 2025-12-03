<?php
/**
 * Rendu de tableaux ASCII en console.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Helper;

/**
 * Class TableRenderer.
 *
 * Gere l'affichage de tableaux en ASCII avec un alignement precis des colonnes.
 * La colorisation est deleguee a ConsoleHelper.
 */
class TableRenderer
{
    /**
     * Affiche un tableau ASCII simple avec alignement des colonnes.
     *
     * Les options disponibles :
     * - 'columns' (array)      : Tableau associatif definissant les colonnes (cle = index dans les donnees, valeur = etiquette).
     * - 'borderColor' (string) : Code ANSI pour la bordure (defaut '35').
     * - 'headerColor' (string) : Code ANSI pour l'en-tete (defaut '1;35').
     * - 'rowColor' (string)    : Code ANSI pour les lignes de donnees (defaut '0;37').
     * - 'showHeaders' (bool)   : Indique si l'en-tete doit etre affiche (defaut true).
     *
     * @param array<int, array<string, string>> $rows    liste des lignes de donnees
     * @param array<string, mixed>              $options options d'affichage
     */
    public static function renderSingleTable(array $rows, array $options = []): void
    {
        // Recuperation des options d'affichage
        $columns = $options['columns'] ?? [];
        $borderColor = (string) ($options['borderColor'] ?? '35');
        $headerColor = (string) ($options['headerColor'] ?? '1;35');
        $rowColor = (string) ($options['rowColor'] ?? '0;37');
        $showHeaders = (bool) ($options['showHeaders'] ?? true);

        // Si aucune colonne n'est definie, deduction a partir de la premiere ligne de donnees
        if (empty($columns) && !empty($rows)) {
            $keys = array_keys($rows[0]);
            $columns = array_combine($keys, $keys);
        }

        // Calcul de la largeur maximale de chaque colonne
        $encoding = ConsoleHelper::getTerminalEncoding();
        $maxWidths = [];
        foreach ($columns as $colKey => $label) {
            $maxWidths[$colKey] = mb_strwidth((string) $label, $encoding);
        }
        foreach ($rows as $row) {
            foreach ($columns as $colKey => $_label) {
                $cellValue = $row[$colKey] ?? '';
                $cellWidth = mb_strwidth((string) $cellValue, $encoding);
                if ($cellWidth > $maxWidths[$colKey]) {
                    $maxWidths[$colKey] = $cellWidth;
                }
            }
        }

        // Construction de la ligne de bordure horizontale
        $borderLine = '+';
        foreach ($columns as $colKey => $_label) {
            $borderLine .= str_repeat('-', $maxWidths[$colKey] + 2) . '+';
        }
        $borderLine .= "\n";
        $borderLine = ConsoleHelper::color($borderLine, $borderColor);

        // Affichage du tableau
        if ($showHeaders && !empty($columns)) {
            echo $borderLine;
            // Construction et affichage de l'en-tete
            $headerLabels = array_values($columns);
            echo self::formatRow(array_keys($columns), $headerLabels, $maxWidths, $headerColor);
            echo $borderLine;
        } else {
            echo $borderLine;
        }

        // Affichage des lignes de donnees
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($columns as $colKey => $_label) {
                $rowValues[] = $row[$colKey] ?? '';
            }
            echo self::formatRow(array_keys($columns), $rowValues, $maxWidths, $rowColor);
        }

        // Affichage de la ligne finale
        echo $borderLine;
    }

    /**
     * Affiche un tableau groupe en fonction d'un regroupement defini par cle.
     *
     * @param array<int, array<string, string>>|array<int|string, array<int, array<string, string>>> $groupedData donnees groupees
     * @param array<string, mixed>                                                                   $options     options d'affichage
     */
    public static function renderGrouped(array $groupedData, array $options = []): void
    {
        // Determination du format (groupe ou simple)
        $isGrouped = self::isAssociative($groupedData);
        if (!$isGrouped) {
            $groupedData = ['' => $groupedData];
        }

        // Agregation de toutes les lignes pour determiner l'ensemble des colonnes utilisees
        $allRows = [];
        foreach ($groupedData as $rows) {
            foreach ($rows as $row) {
                $allRows[] = $row;
            }
        }

        // Deduction des colonnes existantes
        $columns = [];
        foreach ($allRows as $row) {
            foreach (array_keys($row) as $colName) {
                if (!in_array($colName, $columns, true)) {
                    $columns[] = $colName;
                }
            }
        }

        // Calcul des largeurs maximales par colonne
        $encoding = ConsoleHelper::getTerminalEncoding();
        $maxWidths = array_fill_keys($columns, 0);
        foreach ($allRows as $row) {
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                $colWidth = mb_strwidth((string) $value, $encoding);
                if ($colWidth > $maxWidths[$col]) {
                    $maxWidths[$col] = $colWidth;
                }
            }
        }

        // Recuperation des options d'affichage pour les groupes
        $borderColor = (string) ($options['borderColor'] ?? '35');
        $headerColor = (string) ($options['headerColor'] ?? '1;35');
        $rowColor = (string) ($options['rowColor'] ?? '0;37');
        $groupLabelColor = (string) ($options['groupLabelColor'] ?? '1;34');
        $showHeaders = (bool) ($options['showHeaders'] ?? true);

        // Construction de la ligne horizontale de separation
        $horizontalLine = '+';
        foreach ($columns as $col) {
            $horizontalLine .= str_repeat('-', $maxWidths[$col] + 2) . '+';
        }
        $horizontalLine .= "\n";
        $horizontalLine = ConsoleHelper::color($horizontalLine, $borderColor);

        // Affichage des groupes
        foreach ($groupedData as $group => $rows) {
            if ('' !== $group) {
                $groupDisplay = ConsoleHelper::color("[{$group}]", $groupLabelColor);
                echo "\n{$groupDisplay}\n";
            }
            if ($showHeaders && !empty($rows)) {
                echo $horizontalLine;
                // Affichage de l'en-tete
                echo self::formatRow($columns, $columns, $maxWidths, $headerColor);
                echo $horizontalLine;
            }
            foreach ($rows as $row) {
                $renderValues = [];
                foreach ($columns as $col) {
                    $renderValues[] = $row[$col] ?? '';
                }
                echo self::formatRow($columns, $renderValues, $maxWidths, $rowColor);
            }
            if (!empty($rows)) {
                echo $horizontalLine;
            }
        }
    }

    /**
     * Determine si un tableau est associatif.
     *
     * @param array<mixed> $array le tableau a tester
     *
     * @return bool retourne true si le tableau est associatif, false sinon
     */
    protected static function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Formate une ligne du tableau en alignant chaque cellule selon la largeur maximale.
     *
     * @param string[]           $orderedColumns tableau ordonne des noms de colonnes
     * @param string[]           $rowValues      valeurs a afficher dans la ligne
     * @param array<string, int> $maxWidths      tableau associatif indiquant la largeur maximale de chaque colonne
     * @param string             $colorCode      code ANSI pour la coloration de la ligne
     *
     * @return string la ligne formatee suivie d'un saut de ligne
     */
    private static function formatRow(
        array $orderedColumns,
        array $rowValues,
        array $maxWidths,
        string $colorCode
    ): string {
        $encoding = ConsoleHelper::getTerminalEncoding();
        $line = ConsoleHelper::color('| ', $colorCode);
        $columnsCount = count($orderedColumns);

        // Construction de la ligne cellule par cellule
        foreach ($orderedColumns as $index => $colName) {
            $value = (string) ($rowValues[$index] ?? '');
            $padding = $maxWidths[$colName] - mb_strwidth($value, $encoding);
            $valuePadded = $value . str_repeat(' ', $padding);
            $line .= ConsoleHelper::color($valuePadded, $colorCode);
            $separator = $index < $columnsCount - 1 ? ' | ' : ' |';
            $line .= ConsoleHelper::color($separator, $colorCode);
        }

        return $line . "\n";
    }
}
