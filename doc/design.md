# Architecture / design choices


## Submodules

There is a core module for basic functionality and to provide the API client.

There are submodules for specific functionality that a website can opt into selectively.

E.g. the API endpoint for workspaces is in the main module, but the functionality to create workspaces for Drupal groups is in the submodules. This way, other modules can use the API client without using the functionality of the submodule.


## Class names

Some class names contain redundant-ish parts, to distinguish them from similar classes that already exist in Drupal.


## Interfaces, or lack thereof

Most of the classes don't use interfaces. In the future we might add them, but in this phase of development, having interfaces would make renaming and refactoring a lot more difficult.


## Services, autowire

Services use autowire, and most of them use class names as service names.

Drupal core is going in a similar direction, although for now they keep the original string service ids, and only add the interface name as alias. See [#3021900: Bring new features from Symfony 3/4/5/6 into our container
](https://www.drupal.org/project/drupal/issues/3021900).


## Entity hooks, fallback behavior

One design goal is to avoid the website to crash if the module is not fully configured.

This is for the following scenarios:
- An administrator should be able to safely login and navigate to a place where they can configure the API.
- Developers might want to work on a local instance of a website with this module enabled, without having to install Nextcloud.
- A production website should be able to surive while Nextcloud is not available for some reason.

Technical considerations:
- We want to implement Nextcloud-related components with the assumption that Nextcloud is available, and not have to clutter every component with try/catch blocks or conditional statements.
- We don't want to add try/catch blocks into every Drupal hook that interacts with the Nextcloud components.
- We want only 1 loggable failure event per request. E.g. if in one request there are 3 events that would trigger something in Nextcloud, only one of them should trigger a loggable failure.

The way we do this is by having one central gateway service, where the service factory does the try/catch and provides a fallback service in case Nextcloud is not configured. Currently this role is filled by the entity hook dispatcher.


## Exceptions

Currently, exceptions from Guzzle are wrapped and re-cast as NextcloudApiException. This keeps the `@throws` signature smaller. But perhaps we should rethink this.

There are a few `catch` blocks, but most exceptions just go uncaught and cause a WSOD. The idea for now is to use the project in a local development instance, with errors reported directly to the screen.

In the future, all exceptions should be caught and logged, whenever we can provide a safe fallback behavior. A production website should be allowed to continue to work, even if the Nextcloud instance is no longer available.

A good place to catch exceptions could be in the entity hook dispatcher.

We do put `@throws` docs for all exceptions, and also make them part of the contract, because we actually plan to catch and handle them in strategic places.


## Logging and reporting

In earlier versions, and in some places still now, there are/were log statements all over the place. We have to discuss how we want to do this in future versions.

In general:
- An administrator might want to know when changes are made in Nextcloud, e.g. new groups, new users, users joining or leaving groups, etc.
- We definitely want to know about unexpected behavior, like errors or failures, as this means we need to fix something.
- Ideally we want to know what triggered a change in Nextcloud, or an error. So we would want to log the id of Drupal content.
