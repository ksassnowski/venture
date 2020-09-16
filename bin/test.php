<?php declare(strict_types=1);

use Sassnowski\LaravelWorkflow\Workflow;

require 'vendor/autoload.php';

$workflow = Workflow::withInitialJobs([
    'job1',
    'job2',
    'job3'
])
    ->addJob('job4', ['job2'])
    ->addJob('job5', ['job1', 'job3', 'job4'])
    ->addJob('job6', ['job3'])
    ->addJob('job7', ['job5', 'job6'])
    ->addJob('job8', ['job5', 'job7'])
    ->build();

$workflow->start();
