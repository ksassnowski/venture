<?php declare(strict_types=1);

use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\UnserializeJobExtractor;

return [
    /*
     * The name of the table Venture uses to store workflows.
     */
    'workflow_table' => 'workflows',

    /*
     * The name of the table Venture uses to store workflow jobs.
     */
    'jobs_table' => 'workflow_jobs',

    /*
     * The class that should be used to generate an id for a workflow
     * job if no explicit id was provided. In most cases, you won't have
     * to change this.
     *
     * Needs to implement `Sassnowski\Venture\StepIdGenerator`.
     */
    'workflow_step_id_generator_class' => ClassNameStepIdGenerator::class,

    /*
     * The class that should be used to extract the workflow job instance
     * from a Laravel queue job. In most cases, you won't have to change this.
     *
     * Needs to implement `Sassnowski\Venture\JobExtractor`.
     */
    'workflow_job_extractor_class' => UnserializeJobExtractor::class,
];
