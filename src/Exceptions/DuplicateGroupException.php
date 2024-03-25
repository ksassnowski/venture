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

namespace Sassnowski\Venture\Exceptions;

final class DuplicateGroupException extends \Exception
{
    public static function forGroup(string $groupID): self
    {
        return new self("A group with the name {$groupID} already exists in this graph");
    }
}
