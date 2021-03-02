<?php declare(strict_types=1);

namespace Sassnowski\Venture\Exceptions;

use Exception;
use Facade\IgnitionContracts\Solution;
use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\ProvidesSolution;

class DuplicateJobException extends Exception implements ProvidesSolution
{
    public function getSolution(): Solution
    {
        return BaseSolution::create('Duplicate jobs')
            ->setSolutionDescription(
                'If you tried to add multiple instances of the same job to a workflow, ' .
                'make sure to provide unique ids for each of them'
            );
    }
}
