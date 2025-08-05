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

final class GroupDependency implements Dependency
{
    public function __construct(private readonly string $groupID)
    {
    }

    public static function forGroup(string $groupID): self
    {
        return new self($groupID);
    }

    public function getID(DependencyGraph $graph): string
    {
        // @todo In the next major version, getID should allow returning an array as well
        return $this->groupID;
    }
}
