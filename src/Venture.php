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

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Plugin\Core;
use Sassnowski\Venture\Plugin\PluginContext;
use Sassnowski\Venture\Plugin\PluginInterface;
use Sassnowski\Venture\State\DefaultWorkflowJobState;
use Sassnowski\Venture\State\DefaultWorkflowState;
use Sassnowski\Venture\State\WorkflowJobStateInterface;
use Sassnowski\Venture\State\WorkflowStateInterface;

final class Venture
{
    /**
     * @var class-string<Workflow>
     */
    public static string $workflowModel = Workflow::class;

    /**
     * @var class-string<WorkflowJob>
     */
    public static string $workflowJobModel = WorkflowJob::class;

    /**
     * @var class-string<WorkflowStateInterface>
     */
    public static string $workflowState = DefaultWorkflowState::class;

    /**
     * @var class-string<WorkflowJobStateInterface>
     */
    public static string $workflowJobState = DefaultWorkflowJobState::class;

    /**
     * @var array<int, class-string<PluginInterface>>
     */
    private static array $plugins = [];

    /**
     * @param class-string<Workflow> $workflowModel
     */
    public static function useWorkflowModel(string $workflowModel): void
    {
        self::$workflowModel = $workflowModel;
    }

    /**
     * @param class-string<WorkflowJob> $workflowJobModel
     */
    public static function useWorkflowJobModel(string $workflowJobModel): void
    {
        self::$workflowJobModel = $workflowJobModel;
    }

    /**
     * @param class-string<WorkflowStateInterface> $state
     */
    public static function useWorkflowState(string $state): void
    {
        self::$workflowState = $state;
    }

    /**
     * @param class-string<WorkflowJobStateInterface> $state
     */
    public static function useWorkflowJobState(string $state): void
    {
        self::$workflowJobState = $state;
    }

    /**
     * @param class-string<PluginInterface> ...$plugin
     */
    public static function registerPlugin(string ...$plugin): void
    {
        foreach ($plugin as $plug) {
            if (\in_array($plug, self::$plugins, true)) {
                continue;
            }

            self::$plugins[] = $plug;
        }
    }

    public static function bootPlugins(): void
    {
        // We want to ensure that the Core plugin always runs last, so we're
        // adding it to the end of the list here.
        $plugins = [...self::$plugins, Core::class];

        $context = new PluginContext(app('events'));

        foreach ($plugins as $plugin) {
            /** @var PluginInterface $plug */
            $plug = app($plugin);

            $plug->install($context);
        }
    }
}
