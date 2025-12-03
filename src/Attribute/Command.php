<?php
/**
 * Attribut pour definir une commande CLI.
 *
 * @since 1.0.0
 * @author seb@nethttp.net
 */
declare(strict_types=1);

namespace Lunar\Cli\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Command
{
    public function __construct(
        public string $name,
        public string $description = ''
    ) {
    }
}
