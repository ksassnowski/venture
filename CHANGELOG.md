# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.4.0](https://github.com/ksassnowski/venture/compare/v5.3.0...v5.4.0) (2025-03-21)


### Features

* add php 8.4 support ([95b88e3](https://github.com/ksassnowski/venture/commit/95b88e3f7e0acaf20f98506eafe02817dfe235d7))
* drop php 8.1 support ([870056c](https://github.com/ksassnowski/venture/commit/870056c7ad57ba778bbcbb6127206b203f4fc59a))

## [5.3.0](https://github.com/ksassnowski/venture/compare/v5.2.0...v5.3.0) (2024-03-25)


### Features

* drop Laravel 9 support ([3ac30a9](https://github.com/ksassnowski/venture/commit/3ac30a9bc0b418f08ab4820e79fb2b5338f1d69e))
* support Laravel 11 ([410c0e1](https://github.com/ksassnowski/venture/commit/410c0e1b5a3489750989e5fbf75b9188cce31384))

## [5.2.0](https://github.com/ksassnowski/venture/compare/5.1.0...v5.2.0) (2023-03-10)


### Features

* add release-please workflow ([6acd9c3](https://github.com/ksassnowski/venture/commit/6acd9c3bd8aa83681edc7e95dec1d5907b426d31))
* pass job to ClosureWorkflowStep callback ([74a804b](https://github.com/ksassnowski/venture/commit/74a804bed46021c3c82374501578c8e1c002c6b5))
* plugin to provide compatibility with lorisleiva/laravel-actions ([628043c](https://github.com/ksassnowski/venture/commit/628043c532aff6785a538c7c1a04ca5beb926e1d))


### Miscellaneous Chores

* update README ([baccba8](https://github.com/ksassnowski/venture/commit/baccba8b28d103bdd5d8eb18bb066c973cffa59c))

## [5.1.0] — 2023-02-21

### Added

- Add ability to add jobs or workflows as a group (#74)

## [5.0.0] — 2023-01-29

### Added

- Added support for Laravel 10
- Added support for PHP 8.2

### Removed

- Dropped support for Laravel 8
- Dropped support for PHP 8.0

## [4.0.1] — 2022-09-22

### Fixed

- Fix manually failed jobs reporting as processed (#59)

## [4.0.0] — 2022-08-30

🎉 Check the upgrade guide here: https://laravel-venture.com/upgrade-guide.html#migrating-to-4-0-from-3-x

## [3.7.0] — 2022-07-09

### Added

- Added `when` and `unless` methods to `WorkflowDefinition` to conditionally add jobs to a workflow (#53)

## [3.6.5] — 2022-05-17

### Changed

- Replaced `opis/closure` with `laravel/serializable-closure` (#52). Keep `opis/closure` around
  as a dependency to preserve backwards compatibility with existing jobs that still used 
  `Opis\Closure\SerializableClosure` for their callbacks.

## [3.6.4] — 2022-04-16

### Fixed

- Moved `UnserializeException` to correct namespace

## [3.6.3] — 2022-04-16

### Fixed

- Fixed serialization bug that could occur when using Postgres (#51)

## [3.6.2] — 2022-04-11

### Changed

- Add `markAsFinished()` method for overridability and make `markJobAsFinished` protected (#47). Credits, @stevebauman.

## [3.6.1] — 2022-04-10

### Fixed

- Fixes non-static method calls introduced in #44 (#48)

## [3.6.0] — 2022-04-05

### Added

- Added option to define custom `Workflow` and `WorkflowJob` models (#44). Credits, @stevebauman.

## [3.5.0] — 2022-02-01

### Added

- Add support for Laravel 9 (#41)

## [3.4.0] — 2021-11-19

### Added

- Add `hasRan()` method to workflow to check if all jobs have at lease been attempted once (#37). Credits, @stevebauman.
- Add `JobExtractor` interface to extract a workflow job instance from a Laravel queue job. This gets used by
  the `WorkflowEventSubscriber` class.

## [3.3.2] — 2021-11-18

### Fixed

- Fix BC break if existing config was missing `workflow_step_id_generator_class` key. Credits, @stevebauman. (#40)

## [3.3.1] — 2021-11-18

### Changed

- Clone job instance before serializing it when saving the workflow to the database. This could
  lead to hard to track down bugs since `serialize` mutates the object in place.

## [3.3.0] — 2021-11-18

### Changed

- Added `StepIdGenerator` interface to abstract id generation for workflow steps (#39).

## [3.2.0] — 2021-11-16

### Changed

- Dropped support for Laravel 7 (#38)
- Added support for PHP 8.1 (#38)

## [3.1.2] — 2021-11-13

### Changed

- Added missing `int` cast to `jobs_failed` property of `Workflow` model (#36). Credits, @stevebauman.
- Added `vimeo/psalm` dependency for static type checking during development.
- Added various missing type hints to get `psalm` to pass at level 2.

### Fixed

- Fixed bug where `WorkflowDefinition::hasWorkflow()` wasn't working properly when checking
  for the workflow's `$dependencies`, too.

## [3.1.1] — 2021-05-13

### Changed

- Store step id instead of serialized instance for dependent jobs. This could cause
  an error in rare cases if the job payload was too big (#30). Credits, @connors511.

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

[5.1.0]: https://github.com/ksassnowski/venture/compare/5.0.0...5.1.0
[5.0.0]: https://github.com/ksassnowski/venture/compare/4.0.1...5.0.0
[4.0.1]: https://github.com/ksassnowski/venture/compare/3.7.0...4.0.0
[4.0.0]: https://github.com/ksassnowski/venture/compare/3.7.0...4.0.0
[3.7.0]: https://github.com/ksassnowski/venture/compare/3.6.5...3.7.0
[3.6.5]: https://github.com/ksassnowski/venture/compare/3.6.4...3.6.5
[3.6.4]: https://github.com/ksassnowski/venture/compare/3.6.3...3.6.4
[3.6.3]: https://github.com/ksassnowski/venture/compare/3.6.2...3.6.3
[3.6.2]: https://github.com/ksassnowski/venture/compare/3.6.1...3.6.2
[3.6.1]: https://github.com/ksassnowski/venture/compare/3.6.0...3.6.1
[3.6.0]: https://github.com/ksassnowski/venture/compare/3.5.0...3.6.0
[3.5.0]: https://github.com/ksassnowski/venture/compare/3.4.0...3.5.0
[3.4.0]: https://github.com/ksassnowski/venture/compare/3.3.2...3.4.0
[3.3.2]: https://github.com/ksassnowski/venture/compare/3.3.1...3.3.2
[3.3.1]: https://github.com/ksassnowski/venture/compare/3.3.0...3.3.1
[3.3.0]: https://github.com/ksassnowski/venture/compare/3.2.0...3.3.0
[3.2.0]: https://github.com/ksassnowski/venture/compare/3.1.2...3.2.0
[3.1.2]: https://github.com/ksassnowski/venture/compare/3.1.1...3.1.2
[3.1.1]: https://github.com/ksassnowski/venture/compare/3.1.0...3.1.1
[3.1.0]: https://github.com/ksassnowski/venture/compare/3.0.1...3.1.0
[3.0.1]: https://github.com/ksassnowski/venture/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/ksassnowski/venture/compare/2.1.1...3.0.0
[2.1.1]: https://github.com/ksassnowski/venture/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/ksassnowski/venture/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/ksassnowski/venture/compare/1.2.1...2.0.0
[1.2.1]: https://github.com/ksassnowski/venture/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/ksassnowski/venture/compare/1.1.1...1.2.0
[1.1.1]: https://github.com/ksassnowski/venture/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/ksassnowski/venture/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/ksassnowski/venture/compare/0.9.0...1.0.0
[0.9.0]: https://github.com/ksassnowski/venture/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/ksassnowski/venture/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/ksassnowski/venture/compare/0.6.1...0.7.0
[0.6.1]: https://github.com/ksassnowski/venture/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/ksassnowski/venture/compare/0.5.2...0.6.0
