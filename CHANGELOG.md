# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), 
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
