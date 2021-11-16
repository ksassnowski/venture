<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\DTO;

use DateTimeImmutable;
use Sassnowski\Venture\Collection\Identifiable;

/**
 * @psalm-immutable
 */
final class WorkflowJob implements Identifiable
{
    /**
     * @param string                 $id
     * @param string[]               $dependencies
     * @param null|DateTimeImmutable $failedAt
     */
    public function __construct(
        public string $id,
        public array $dependencies,
        public ?DateTimeImmutable $failedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }
}
