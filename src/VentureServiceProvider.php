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

namespace Sassnowski\Venture;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Sassnowski\Venture\Actions\HandleFailedJobs;
use Sassnowski\Venture\Actions\HandleFinishedJobs;
use Sassnowski\Venture\Actions\HandlesFailedJobs;
use Sassnowski\Venture\Actions\HandlesFinishedJobs;
use Sassnowski\Venture\Dispatcher\JobDispatcher;
use Sassnowski\Venture\Dispatcher\QueueDispatcher;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Sassnowski\Venture\State\FakeWorkflowState;
use Sassnowski\Venture\State\WorkflowJobState;
use Sassnowski\Venture\State\WorkflowState;
use Sassnowski\Venture\State\WorkflowStateStore;
use function config;

class VentureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/venture.php' => config_path('venture.php'),
        ], ['config', 'venture-config']);

        if (!\class_exists('CreateWorkflowTables')) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_media_table.php.stub' => database_path('migrations/' . \date('Y_m_d_His', \time()) . '_create_workflow_tables.php'),
            ], ['migrations']);
        }

        /** @phpstan-ignore-next-line */
        $this->app['events']->subscribe(WorkflowEventSubscriber::class);

        Venture::bootPlugins();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture',
        );

        $this->registerManager();
        $this->registerJobExtractor();
        $this->registerStepIdGenerator();
        $this->registerJobSerializer();
        $this->registerActions();
        $this->registerDispatcher();

        if (app()->runningUnitTests()) {
            $this->registerFakeWorkflowState();
        }
    }

    private function registerManager(): void
    {
        $this->app->bind('venture.manager', WorkflowManager::class);
    }

    private function registerJobExtractor(): void
    {
        $this->app->bind(
            JobExtractor::class,
            config(
                'venture.workflow_job_extractor_class',
                UnserializeJobExtractor::class,
            ),
        );
    }

    private function registerStepIdGenerator(): void
    {
        $this->app->bind(
            StepIdGenerator::class,
            config(
                'venture.workflow_step_id_generator_class',
                ClassNameStepIdGenerator::class,
            ),
        );
    }

    private function registerJobSerializer(): void
    {
        $this->app->bind(
            WorkflowJobSerializer::class,
            config(
                'venture.workflow_job_serializer_class',
                Base64WorkflowSerializer::class,
            ),
        );
    }

    private function registerActions(): void
    {
        $this->app->bind(
            HandlesFinishedJobs::class,
            HandleFinishedJobs::class,
        );

        $this->app->bind(
            HandlesFailedJobs::class,
            HandleFailedJobs::class,
        );
    }

    private function registerDispatcher(): void
    {
        $this->app->bind(
            JobDispatcher::class,
            QueueDispatcher::class,
        );
    }

    private function registerFakeWorkflowState(): void
    {
        $this->app->bind(
            FakeWorkflowJobState::class,
            function (Application $app, array $args): WorkflowJobState {
                /** @var array{job: WorkflowJob} $args */
                return WorkflowStateStore::forJob($args['job']->step()->getJobId());
            },
        );

        $this->app->bind(
            FakeWorkflowState::class,
            function (Application $app, array $args): WorkflowState {
                /** @var array{workflow: Workflow} $args */
                return WorkflowStateStore::forWorkflow($args['workflow']);
            },
        );
    }
}
