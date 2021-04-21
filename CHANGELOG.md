# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0] — 2021-04-21

### Added

- Added a `hasWorkflow` method to the `WorkflowDefinition` to check if a workflow contains 
  a nested workflow.

## [3.0.1] — 2021-04-20

### Changed

- Fixed possible race condition when multiple workers try to update the same workflow (#28). Credits, @connors511.

## [3.0.0] — 2021-03-30

### Added

- Added support for adding multiple instances of the same job to a workflow. Check the
  [documentation](https://laravel-venture.netlify.app/usage/duplicate-jobs.html) for more details.
  See #14 for the discussion on this feature. Special thanks to @conors511 for his help.

### Changed

- Change required minimum PHP version to 8.

### Removed

- Removed `addJobWithDelay` method from `WorkflowDefinition`. You should use `addJob`
  and provide the `delay` parameter instead. Since this version of Venture requires PHP 8,
  you can make use of named arguments to skip any default parameters you don't want to change.

## [2.1.1] — 2021-01-20

### Changed

- Specified minimum version for Laravel dependencies

## [2.1.0] — 2021-01-14

### Added

- Added `beforeNesting` hook to that gets called before a workflow gets added as a nested workflow. (#13)

### Changed

- Made `$dependencies` parameter optional in `addWorkflow` method of `WorkflowDefinition`. It now
  works the same as the `addJob` methods. (#20)

### Fixed

- Don't call `onStepFinished` method when a job was released back onto the queue (#21)

## [2.0.0] — 2021-01-12

### Added

- Added support for nested workflows

### Changed

- A job's dependencies have to be added to the workflow before the job itself is added.
  This also eliminates the problem of circular dependencies.
- All jobs in a workflow need to implement the `Illuminate\Contracts\Queue\ShouldQueue` interface.
  Otherwise, a `NonQueueableWorkflowStepException` gets thrown.
- Starting a workflow now returns the workflow instance (#10)

## [1.2.1] – 2020-12-14

### Changed

- The `WorkflowManagerFake` now calls the `beforeCreate` hook of the workflow definition, too.

## [1.2.0] – 2020-12-10

### Changed

- Added PHP 8 support

## [1.1.1] – 2020-11-20

### Changed

- Publish migrations
- Use file groups for publishable assets

## [1.1.0] – 2020-11-16

### Added

- Added a `beforeCreate` hook to the `WorkflowDefinition` class to
  manipulate a workflow before it gets saved to the database for the
  first time.

### Changed

- Added missing `date` casts to the `finished_at` and `failed_at`
  columns on the `Workflow` model.

## [1.0.0] – 2020-11-12

Please see the documentation's [upgrade guide](https://laravel-venture.netlify.app/upgrade-guide.html) to migrate from 0.x to 1.0.0.

### Added

- Added a `Workflow` facade to start defining a workflow.
- Added testing helpers to inspect workflow definitions
- Added testing helpers to check if a workflow was started

### Changed

- Workflows are now defined as standalone classes.
- Workflows no longer get started by chaining the `start` method on the builder.
  Instead, use the static `start` method on the workflow class itself.

### Removed

- Removed the static `new` method from the Workflow model. Use the `define`
  method on the `Workflow` facade instead.

## [0.9.0] – 2020-10-20

### Added

- Add option to define a delay for a job.

## [0.8.0] – 2020-10-12

### Changed

- Automatically register `WorkflowEventSubscriber` (shoutouts to [@phcostabh](https://twitter.com/phcostabh) for the suggestion)

## [0.7.0] – 2020-10-12

### Added

- Adds support for Laravel 7 (previously only 8)

## [0.6.1] – 2020-10-12

### Fixed

- Stops jobs of cancelled workflows from executing if they have already been scheduled but not yet picked up by a worker.

## [0.6.0] – 2020-10-10

### Added

- Added `catch` method to workflow. This method will be called everytime a job inside a workflow is marked as failed.
- Make it possible to cancel a workflow. A cancelled workflow will not execute any further jobs, but will finish any job
  that was already running before the workflow got cancelled.
