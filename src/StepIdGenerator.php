<?php declare(strict_types=1);

namespace Sassnowski\Venture;

interface StepIdGenerator
{
    public function generateId(object $job): string;
}
