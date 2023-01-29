<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Graph;

use Closure;

final class ConditionalDependency implements Dependency
{
    /**
     * @param Closure(DependencyGraph): bool $predicate
     */
    public function __construct(
        private Closure $predicate,
        private string $dependencyID,
        private ?string $fallback = null,
    ) {
    }

    public static function whenDefined(
        string $dependencyID,
        ?string $fallback = null,
    ): self {
        $predicate = function (DependencyGraph $graph) use ($dependencyID): bool {
            return $graph->has($dependencyID);
        };

        return new self($predicate, $dependencyID, $fallback);
    }

    public function getID(DependencyGraph $graph): ?string
    {
        if (!($this->predicate)($graph)) {
            return $this->fallback;
        }

        return $this->dependencyID;
    }
}
