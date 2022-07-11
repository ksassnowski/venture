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
use Sassnowski\Venture\Venture;

/**
 * @property array<int, string> $edges
 * @property string   $exception
 * @property ?Carbon  $failed_at
 * @property ?Carbon  $finished_at
 * @property string   $name
 * @property string   $uuid
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
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'edges' => 'array',
    ];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = config('venture.jobs_table');

        parent::__construct($attributes);
    }

    /**
     * @return BelongsTo<Workflow, WorkflowJob>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Venture::$workflowModel, 'workflow_id');
    }
}
