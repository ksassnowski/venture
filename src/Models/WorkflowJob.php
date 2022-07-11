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
 * @property string[] $edges
 * @property string   $exception
 * @property ?Carbon  $failed_at
 * @property ?Carbon  $finished_at
 * @property string   $name
 * @property string   $uuid
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class WorkflowJob extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $dates = [
        'failed_at',
        'finished_at',
    ];

    protected $casts = [
        'edges' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('venture.jobs_table');

        parent::__construct($attributes);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Venture::$workflowModel, 'workflow_id');
    }
}
