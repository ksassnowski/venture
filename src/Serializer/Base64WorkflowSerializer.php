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

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sassnowski\Venture\WorkflowStepAdapter;
use Sassnowski\Venture\WorkflowStepInterface;

final class Base64WorkflowSerializer implements WorkflowJobSerializer
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    public function serialize(WorkflowStepInterface $job): string
    {
        if ($this->isPostgresConnection()) {
            return \base64_encode(\serialize($job));
        }

        return \serialize($job);
    }

    public function unserialize(string $serializedJob): ?WorkflowStepInterface
    {
        if ($this->isPostgresConnection() && !Str::contains($serializedJob, [':', ';'])) {
            $serializedJob = \base64_decode($serializedJob, true);

            if (false === $serializedJob) {
                throw new UnserializeException('Unable to base64 decode job');
            }
        }

        $result = @\unserialize($serializedJob);

        if (!\is_object($result)) {
            throw new UnserializeException('Unable to unserialize job');
        }

        try {
            return WorkflowStepAdapter::fromJob($result);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function isPostgresConnection(): bool
    {
        return $this->connection instanceof PostgresConnection;
    }
}
