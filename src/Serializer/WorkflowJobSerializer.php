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

namespace Sassnowski\Venture\Serializer;

use Sassnowski\Venture\WorkflowableJob;

interface WorkflowJobSerializer
{
    public function serialize(WorkflowableJob $job): string;

    /**
     * @throws UnserializeException thrown if the string could not be unserialized for any reason
     */
    public function unserialize(string $serializedJob): ?WorkflowableJob;
}
