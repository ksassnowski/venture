<?php declare(strict_types=1);

use Sassnowski\Venture\VentureServiceProvider;
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
            VentureServiceProvider::class,
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

        config()->set('venture.workflow_table', 'workflows');
        config()->set('venture.jobs_table', 'workflow_jobs');
    }

    protected function setUpDatabase()
    {
        include_once __DIR__ . '/../database/migrations/2020_08_16_000000_create_workflow_table.php';
        include_once __DIR__ . '/../database/migrations/2020_08_17_000000_add_additional_columns_to_workflow.php';

        (new CreateWorkflowTable())->up();
        (new AddAdditionalColumnsToWorkflow())->up();
    }
}
