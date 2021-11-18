<?php declare(strict_types=1);

namespace Sassnowski\Venture;

final class ClassNameStepIdGenerator implements StepIdGenerator
{
    public function generateId(object $job): string
    {
        return get_class($job);
    }
}
