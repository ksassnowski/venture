<?php declare(strict_types=1);

namespace Sassnowski\Venture;

/**
 * @psalm-immutable
 */
final class JobDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public object $job
    ) {
    }
}
