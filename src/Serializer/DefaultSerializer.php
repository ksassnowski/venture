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

namespace Sassnowski\Venture\Serializer;

final class DefaultSerializer implements WorkflowJobSerializer
{
    public function serialize(object $job): string
    {
        return \serialize($job);
    }

    public function unserialize(string $serializedJob): object
    {
        $result = @\unserialize($serializedJob);

        if (false === $result) {
            throw new UnserializeException('Unable to unserialize job');
        }

        return $result;
    }
}
