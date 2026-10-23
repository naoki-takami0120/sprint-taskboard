# Development log

## Day 1 - 2026-10-19: Project setup

**What I did**
- Created the Laravel project with Composer, without a starter kit.
- Installed Laravel Sail with MySQL and Redis.
- Confirmed the app runs at http://localhost.

**Why**
- No starter kit: I want to build the REST API and the Vue frontend separately, and write the authentication myself.
- Sail: so the development environment is reproducible with Docker.

**Problems and fixes**
- The Laravel installer did not offer a "no starter kit" option, so I used Composer's create-project instead.

**Learned**
- Laravel Sail is a command-line tool that makes it easy to run Laravel's Docker environment (PHP, MySQL, and Redis) without writing Docker settings myself.
- With Sail, I run commands inside the container, such as `sail artisan` and `sail composer`.

## Day 2 - 2026-10-20: CI, migrations, and models

**What I did**
- Added a GitHub Actions workflow that runs PHPUnit against a MySQL service.
- Wrote migrations for projects, sprints, tasks, project_user, task_status_logs, and a role column on users.
- Wrote Eloquent models with relationships, and checked them in tinker.

**Why**
- MySQL in CI: task status changes will use database transactions and row locks, which behave differently on SQLite.
- Cascade on delete for projects, sprints, and tasks: they have no meaning without their parents. For assignees and sprint links, I used null on delete so tasks are kept.
- `project_user` table is a pivot table with a composite primary key, so a user cannot join the same project twice.
- `task_status_logs` has only created_at because logs are never updated.
- `status` is a string column, not text, so I can add a composite index on `(project_id, status)` for the board queries.

**Problems and fixes**
- CI failed because `composer.lock` required PHP 8.4.1 or higher, but the workflow used PHP 8.3. Composer chooses package versions based on the PHP version it runs on, so I updated CI to use the same PHP version as my local environment.
- Fixed several mistakes in the migrations by running `migrate:fresh` and reading the errors. For example, I added `timestamps()` twice, and `nullable()` was in the wrong place.

**Learned**
- A pivot table updated through `attach()` does not fill `created_at` and `updated_at` by default. I need to add `withTimestamps()` to both sides of the many-to-many relationship.

## Day 3 - 2026-10-21: Factories, seeders, and Sanctum authentication

**What I did**
- Wrote factories for projects, sprints, and tasks, with states such as `done()` and `inProgress()`.
- Wrote a seeder that creates an admin, members, a project, a sprint, and tasks in different statuses.
- Installed Laravel Sanctum with `install:api` and wrote `register`, `login`, `logout`, and `user` endpoints.
- Wrote 11 feature tests for authentication.

**Why**
- Task states set `status` and `completed_at` together, so I never get a done task without a completion date. This will matter for the ranking and burndown later.
- Token authentication first: I can test the API without a browser. I will decide about the cookie-based SPA mode when I build the Vue app.
- `login` returns the same error for an unknown email and a wrong password, so the API does not reveal which emails are registered.
- `role` is not in the validation rules or `$fillable`, so a user cannot make themselves an admin when registering. I added a test for this.

**Problems and fixes**
- The first `install:api` run did not create the `personal_access_tokens` migration, and `vendor:publish` did not list Sanctum. Running `install:api` a second time created the files.

**Learned**
- A factory state overrides only some attributes, so I can build data in different situations with a short, readable call.

## Day 4 - 2026-10-22: Project CRUD API

**What I did**
- Wrote `ProjectController` with index, store, show, update, and destroy.
- Wrote 14 feature tests for the project endpoints.

**Why**
- `owner_id` is set from the logged-in user, not from the request. Tests check that a value sent by the client is ignored.
- Creating a project and adding the owner as a member run in one database transaction, so I never get a project without a member.
- Validation runs after the permission check, so users without permission do not see validation messages.

**Problems and fixes**
- The routes were outside the `auth:sanctum` group, so `$request->user()` was null and the request failed. Moving the routes into the group fixed it.
- Several typos (`users()` instead of `user()`, a missing `$id`) appeared as errors and were fixed by reading the messages.

**Learned**
- Starting a query from the user's relationship is a simple way to scope data to the current user.