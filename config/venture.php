<?php declare(strict_types=1);

use Sassnowski\Venture\ClassNameStepIdGenerator;

return [
    'workflow_table' => 'workflows',

    'jobs_table' => 'workflow_jobs',

    /*
     * The class that should be used to generate an id for a
     * workflow job if no explicit id was provided. In most cases,
     * you won't have to change this.
     *
     * This class needs to implement the `Sassnowski\Venture\StepIdGenerator` interface.
     */
    'workflow_step_id_generator_class' => ClassNameStepIdGenerator::class,
];
