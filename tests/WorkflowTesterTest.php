<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use PHPUnit\Framework\AssertionFailedError;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Testing\WorkflowTester;
use Sassnowski\Venture\WorkflowableJob;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\LegacyWorkflowJob;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestWorkflow;
use Stubs\WorkflowWithCallbacks;
use Stubs\WorkflowWithParameter;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__then.called'] = 0;
    $_SERVER['__catch.called'] = 0;
    $_SERVER['__workflow'] = null;
});

it('can run the then-callback of the workflow', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            then: fn () => ++$_SERVER['__then.called'],
        ),
    );

    $tester->runThenCallback();

    expect($_SERVER['__then.called'])->toBe(1);
});

it('accepts callback to configure the workflow before calling the then-callback', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            then: fn (Workflow $model) => $_SERVER['__workflow'] = $model,
        ),
    );

    $tester->runThenCallback(function (Workflow $workflow): void {
        $workflow->jobs_processed = 100;
    });

    expect($_SERVER['__workflow'])->jobs_processed->toBe(100);
});

it('can run the catch-callback of a workflow', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            catch: fn () => ++$_SERVER['__catch.called'],
        ),
    );

    $tester->runCatchCallback(new TestJob1(), new Exception());

    expect($_SERVER['__catch.called'])->toBe(1);
});

it('can configure the workflow before calling the catch-callback', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            catch: fn (Workflow $model) => $_SERVER['__workflow'] = $model,
        ),
    );

    $tester->runCatchCallback(
        new TestJob1(),
        new Exception(),
        function (Workflow $workflow): void {
            $workflow->jobs_processed = 200;
        },
    );

    expect($_SERVER['__workflow'])->jobs_processed->toBe(200);
});

test('assertJobExists passes if the workflow contains a job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExists(TestJob1::class);
});

test('assertJobExists fails if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExists(TestJob2::class);
})->throws(AssertionFailedError::class);

test('assertJobExists passes if the workflow contains a job with the provided id and the callback returns true', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2(), [TestJob1::class]);
    })->assertJobExists(TestJob2::class, function (WorkflowableJob $step) {
        return $step->getDependencies() == [TestJob1::class];
    });
});

test('assertJobExists fails if the workflow contains a job with the provided id but the callback returns false', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExists(TestJob1::class, fn () => false);
})->throws(AssertionFailedError::class);

test('assertJobExists unwraps WorkflowStepAdapter jobs before passing them to the callback', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new LegacyWorkflowJob());
    })->assertJobExists(LegacyWorkflowJob::class, fn (LegacyWorkflowJob $job) => true);
});

test('assertJobExistsWithDependencies passes if the workflow contains a job with the provided id and the correct dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addJob(new TestJob3(), [TestJob1::class, TestJob2::class]);
    })->assertJobExistsWithDependencies(TestJob3::class, [TestJob2::class, TestJob1::class]);
});

test('assertJobExistsWithDependency fails if the workflow contains a job with the provided id but different dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2());
    })->assertJobExistsWithDependencies(TestJob2::class, [TestJob1::class]);
})->throws(AssertionFailedError::class);

test('assertJobExistsWithDependency fails if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExistsWithDependencies(TestJob2::class, [TestJob1::class]);
})->throws(AssertionFailedError::class);

test('assertJobExistsOnConnection passes if the workflow contains a job with the provided id and the correct queue connection', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob((new TestJob1())->onConnection('::connection::'));
    })->assertJobExistsOnConnection(TestJob1::class, '::connection::');
});

test('assertJobExistsOnConnection fails if the workflow contains a job with the provided id but a different queue connection', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob((new TestJob1())->onConnection('::different-connection::'));
    })->assertJobExistsOnConnection(TestJob1::class, '::connection::');
})->throws(AssertionFailedError::class);

test('assertJobExistsOnConnection fails if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExistsOnConnection(TestJob2::class, '::connection::');
})->throws(AssertionFailedError::class);

test('assertJobMissing passes if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobMissing(TestJob2::class);
});

test('assertJobMissing passes if the workflow contains a job with the provided id and the callback returns false', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobMissing(TestJob1::class, fn () => false);
});

test('assertJobMissing fails if the workflow contains a job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobMissing(TestJob1::class);
})->throws(AssertionFailedError::class);

test('assertJobMissing fails if the workflow contains a job with the provided id and the callback returns true', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobMissing(TestJob1::class, fn () => true);
})->throws(AssertionFailedError::class);

test('assertJobMissing unwraps WorkflowStepAdapter jobs before passing them to the callback', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new LegacyWorkflowJob());
    })->assertJobMissing(LegacyWorkflowJob::class, fn (LegacyWorkflowJob $job) => false);
});

test('assertGatedJobExists passes if the workflow contains a gated job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addGatedJob(new TestJob1());
    })->assertGatedJobExists(TestJob1::class);
});

test('assertGatedJobExists  if the workflow contains a job with the provided id and the correct dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addGatedJob(new TestJob2(), [TestJob1::class]);
    })->assertGatedJobExists(TestJob2::class, [TestJob1::class]);
});

test('assertGatedJobExists fails if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertGatedJobExists(TestJob2::class);
})->throws(AssertionFailedError::class);

test('assertGatedJobExists fails if the workflow contains a non-gated job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertGatedJobExists(TestJob1::class);
})->throws(AssertionFailedError::class);

test('assertGatedJobExists fails if the workflow contains a gated job with the provided id but different dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addGatedJob(new TestJob3(), [TestJob2::class]);
    })->assertGatedJobExists(TestJob3::class, [TestJob1::class]);
})->throws(AssertionFailedError::class);

test('assertJobExistsOnQueue passes if the workflow contains a job with the provided id and on the correct queue', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob((new TestJob1())->onQueue('::queue::'));
    })->assertJobExistsOnQueue(TestJob1::class, '::queue::');
});

test('assertJobExistsOnQueue fails if the workflow contains a job with the provided id but on a different queue', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob((new TestJob1())->onConnection('::different-queue::'));
    })->assertJobExistsOnQueue(TestJob1::class, '::queue::');
})->throws(AssertionFailedError::class);

test('assertJobExistsOnQueue fails if the workflow contains no job with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertJobExistsOnConnection(TestJob2::class, '::queue::');
})->throws(AssertionFailedError::class);

test('assertWorkflowExists passes if the workflow contains a nested workflow with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addWorkflow(new TestWorkflow());
    })->assertWorkflowExists(TestWorkflow::class);
});

test('assertWorkflowExists fails if the workflow does not contain a nested workflow with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addWorkflow(new TestWorkflow());
    })->assertWorkflowExists(WorkflowWithParameter::class);
})->throws(AssertionFailedError::class);

test('assertWorkflowExists passes if the workflow contains a nested workflow with the provided id and correct dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addWorkflow(new TestWorkflow(), [TestJob1::class, TestJob2::class]);
    })->assertWorkflowExists(TestWorkflow::class, [TestJob1::class, TestJob2::class]);
});

test('assertWorkflowExists fails if the workflow contains a nested workflow with the provided id but different dependencies', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addWorkflow(new TestWorkflow(), [TestJob1::class]);
    })->assertWorkflowExists(TestWorkflow::class, [TestJob1::class, TestJob2::class]);
})->throws(AssertionFailedError::class);

test('assertWorkflowMissing passes if the workflow does not contain a nested workflow with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addJob(new TestJob1());
    })->assertWorkflowMissing(TestWorkflow::class);
});

test('assertWorkflowMissing fails if the workflow contains a nested workflow with the provided id', function (): void {
    testWorkflow(function (WorkflowDefinition $definition): void {
        $definition->addWorkflow(new TestWorkflow());
    })->assertWorkflowMissing(TestWorkflow::class);
})->throws(AssertionFailedError::class);

/**
 * @param Closure(WorkflowDefinition): void $callback
 */
function testWorkflow(Closure $callback): WorkflowTester
{
    $workflow = new class($callback) extends AbstractWorkflow {
        /**
         * @param Closure(WorkflowDefinition): void $callback
         */
        public function __construct(private Closure $callback)
        {
        }

        public function definition(): WorkflowDefinition
        {
            return tap($this->define('Test Workflow'), $this->callback);
        }
    };

    return new WorkflowTester($workflow);
}
