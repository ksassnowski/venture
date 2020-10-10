<?php declare(strict_types=1);

use Sassnowski\Venture\WorkflowServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getServiceProviders($app)
    {
        return [
            WorkflowServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('workflow.workflow_table', 'workflows');
        config()->set('workflow.jobs_table', 'workflow_jobs');
    }

    protected function setUpDatabase()
    {
        include_once __DIR__ . '/../database/migrations/2020_08_16_000000_create_workflow_table.php';

        (new CreateWorkflowTable())->up();
    }
}
