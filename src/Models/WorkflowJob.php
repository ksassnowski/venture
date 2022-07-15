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

namespace Sassnowski\Venture\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Sassnowski\Venture\State\WorkflowJobState;
use Sassnowski\Venture\Venture;
use Sassnowski\Venture\WorkflowStepInterface;
use Throwable;

/**
 * @property array<int, string> $edges
 * @property ?string            $exception
 * @property ?Carbon            $failed_at
 * @property ?Carbon            $finished_at
 * @property ?Carbon            $gated_at
 * @property string             $job
 * @property bool               $gated
 * @property string             $name
 * @property ?Carbon            $started_at
 * @property string             $uuid
 * @property Workflow           $workflow
 */
class WorkflowJob extends Model
{
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @var array<int, string>
     */
    protected $dates = [
        'failed_at',
        'finished_at',
        'started_at',
        'gated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'edges' => 'array',
        'manual' => 'bool',
    ];

    private WorkflowJobState $state;

    private ?WorkflowStepInterface $step = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('venture.jobs_table');

        parent::__construct($attributes);

        $this->state = app(Venture::$workflowJobState, ['job' => $this]);
    }

    /**
     * @return BelongsTo<Workflow, WorkflowJob>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Venture::$workflowModel, 'workflow_id');
    }

    public function step(): WorkflowStepInterface
    {
        if (null === $this->step) {
            /** @var WorkflowJobSerializer $serializer */
            $serializer = app(WorkflowJobSerializer::class);

            $step = $serializer->unserialize($this->job);

            if (null === $step) {
                throw new RuntimeException('Unable to unserialize job');
            }

            $this->step = $step;
        }

        return $this->step;
    }

    public function hasFinished(): bool
    {
        return $this->state->hasFinished();
    }

    public function markAsFinished(): void
    {
        $this->state->markAsFinished();
    }

    public function hasFailed(): bool
    {
        return $this->state->hasFailed();
    }

    public function markAsFailed(Throwable $exception): void
    {
        $this->state->markAsFailed($exception);
    }

    public function isProcessing(): bool
    {
        return $this->state->isProcessing();
    }

    public function markAsProcessing(): void
    {
        $this->state->markAsProcessing();
    }

    public function canRun(): bool
    {
        return $this->state->canRun();
    }

    public function isGated(): bool
    {
        return $this->state->isGated();
    }

    public function markAsGated(): void
    {
        $this->state->markAsGated();
    }

    public function transition(): void
    {
        $this->state->transition();
    }

    public function start(): void
    {
        \dispatch($this->step());
    }
}
