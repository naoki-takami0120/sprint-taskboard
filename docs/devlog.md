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
- The Laravel installer didn't offer "no starter kit" option, so I used Composer's create-project instead.

**Learned**
- Laravel Sail is a command-line tool that makes it easy to run Laravel's Docker environment (PHP, MySQL and Redis) without writing Docker settings myself.
- With Sail, I run commands inside the container, such as `sail artisan` and `sail composer`.