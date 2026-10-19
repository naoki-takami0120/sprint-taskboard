# Sprint Taskboard

A sprint-based task board built with Laravel and Vue.js. Work in progress.

## Tech stack

- **Backend:** Laravel (REST API), MySQL, Redis
- **Frontend:** Vue.js (planned)
- **Environment:** Docker (Laravel Sail)

## Planned features

- [ ] Authentication with Laravel Sanctum
- [ ] Projects and tasks (CRUD)
- [ ] Role-based authorization (admin / member) with Laravel policies
- [ ] Safe task status changes using database transactions
- [ ] Vue.js single-page app with a task board
- [ ] Weekly ranking with Redis sorted sets
- [ ] Burndown data with caching
- [ ] Deadline notifications with queues
- [ ] Automated tests and CI with Github Actions

## Getting started

### Requirements

- Docker Desktop
- PHP and Composer

### Setup

```bash
git clone <repository-url>
cd sprint-taskboard
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

Open http://localhost in your browser.

### Run tests

```bash
./vendor/bin/sail artisan test
```

## About this project

I am building this project to refresh my hands-on skills with Laravel and Vue.js. My development notes are in [docs/devlog.md](docs/devlog.md).