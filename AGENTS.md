# Drupal Project Context

This is a Drupal 10 project.

Project root:

/workspace

You are already running inside the development container.

## Environment

Use directly:

- composer
- php
- vendor/bin/drush
- git
- curl

NEVER use:

- lando
- docker
- docker-compose
- sudo
- hermes skin

Lando runs on the Mac host and is not available inside your container.

## Drupal

When the user says "theme", "module", "Gin", "Claro",
"Olivero", "Pathauto", "Webform", etc., interpret these
as Drupal concepts unless explicitly stated otherwise.

Install contributed Drupal packages with Composer.

Example:

composer require drupal/gin

Enable modules/themes with Drush.

Example:

vendor/bin/drush theme:enable gin -y

After Drupal changes, run when appropriate:

vendor/bin/drush cr

## Drupal development

Custom modules:

web/modules/custom

Custom themes:

web/themes/custom

Never modify:

- web/core
- vendor
- contributed modules directly

Follow Drupal coding standards.

Prefer dependency injection over static \Drupal calls.

Use Drupal APIs instead of custom low-level implementations.

## Infrastructure

Never delete, move or rename:

- .lando/
- .lando.yml
- AGENTS.md
- .git/
- .gitignore

## Workflow

Before changing code:

1. Inspect the current implementation.
2. Run git status.
3. Understand the existing architecture.

After changing code:

1. Validate the implementation.
2. Rebuild cache when needed.
3. Check relevant Drupal errors.
4. Run git diff.
5. Report what changed.

Do not commit or push unless explicitly requested.
