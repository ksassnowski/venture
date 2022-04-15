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
use Laravel\SerializableClosure\Serializers\UnserializeException;

final class Base64WorkflowSerializer implements WorkflowJobSerializer
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    public function serialize(object $job): string
    {
        if ($this->isPostgresConnection()) {
            return \base64_encode(\serialize($job));
        }

        return \serialize($job);
    }

    public function unserialize(string $serializedJob): object
    {
        if ($this->isPostgresConnection() && !Str::contains($serializedJob, [':', ';'])) {
            $serializedJob = \base64_decode($serializedJob, true);
        }

        $result = @\unserialize($serializedJob);

        if (false === $result) {
            throw new UnserializeException('Unable to unserialize job');
        }

        return $result;
    }

    private function isPostgresConnection(): bool
    {
        return $this->connection instanceof PostgresConnection;
    }
}
