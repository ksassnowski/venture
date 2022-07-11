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

use InvalidArgumentException;
use Sassnowski\Venture\WorkflowStepAdapter;
use Sassnowski\Venture\WorkflowStepInterface;

final class DefaultSerializer implements WorkflowJobSerializer
{
    public function serialize(WorkflowStepInterface $job): string
    {
        return \serialize($job);
    }

    public function unserialize(string $serializedJob): ?WorkflowStepInterface
    {
        $result = @\unserialize($serializedJob);

        if (!\is_object($result)) {
            throw new UnserializeException('Unable to unserialize job');
        }

        try {
            return WorkflowStepAdapter::make($result);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
