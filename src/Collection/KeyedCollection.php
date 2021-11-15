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

namespace Sassnowski\Venture\Collection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @psalm-template T extends Identifiable
 */
class KeyedCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<string, T>
     */
    protected array $items;

    /**
     * @param T[] $items
     */
    public function __construct(array $items = [])
    {
        $this->items = \array_reduce($items, function (array $acc, mixed $item) {
            $acc[$item->getId()] = $item;

            return $acc;
        }, []);
    }

    /**
     * @return null|T
     */
    public function find(string $id)
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @param T $item
     */
    public function add(mixed $item): void
    {
        $this->items[$item->getId()] = $item;
    }

    /**
     * @return ArrayIterator<string, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
