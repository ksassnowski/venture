<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowJob extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function __construct($attributes = [])
    {
        $this->table = config('venture.jobs_table');
        parent::__construct($attributes);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
