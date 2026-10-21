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
- project_user table is a pivot table with a composite primary key, so a user cannot join the same project twice.
- task_status_logs has only created_at because logs are never updated.

**Learned**
- A pivot table updated through `attach()` does not fill `created_at` and `updated_at` by default. I need to add `withTimestamps()` to both sides of the many-to-many relationship.