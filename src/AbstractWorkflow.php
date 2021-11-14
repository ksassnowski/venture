<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Container\Container;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;

abstract class AbstractWorkflow
{
    public static function start(): Workflow
    {
        /** @psalm-suppress TooManyArguments, UnsafeInstantiation */
        return (new static(...func_get_args()))->run();
    }

    private function run(): Workflow
    {
        /** @var WorkflowManagerInterface $manager */
        $manager = Container::getInstance()->make('venture.manager');

        return $manager->startWorkflow($this);
    }

    abstract public function definition(): WorkflowDefinition;

    public function beforeCreate(Workflow $workflow): void
    {
    }

    public function beforeNesting(array $jobs): void
    {
    }
}
