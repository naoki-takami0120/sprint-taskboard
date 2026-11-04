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

## Day 5 - 2026-10-23: Task CRUD API

**What I did**
- Wrote `TaskController` with index, store, show, update and destroy.
- Used shallow nested routes: list and create are under `/api/projects/{project}/tasks` while show, update, and destroy use `/api/tasks/{task}`.
- Wrote 19 feature tests for the task endpoints.

**Why**
- `status` is not accepted by the create and updated endpoints. Status changes will go through a dedicated endpoint that uses a transaction, writes a history log, and updates the ranking. Tests make sure the regular endpoints ignore `status`.
- On update, `title` uses `sometimes` and `required`: it can be left out, but it cannnot be sent empty.

**Problems and fixes**
- Several tests failed with 401 because I forgot `Sanctum::actingAs`. The failure message ("received 401") pointed to authentication, not validation.

**Learned**
- A test should check both what changes and what must not change.

## Day 6 - 2026-10-24: Form requests, API resources, pagination, and filters

**What I did**
- Moved validation from the controllers to form requests (store and update for projects and tasks, plus index requests for the list endpoints).
- Added API resources for projects and tasks, so the response shape is defined in one place.
- Added pagination and filters (`status`, `assignee_id`, `sprint_id`, `per_page`) to the task list, and pagination to the project list.
- Added tests for pagination, filters, and the order of the 404 and 422 checks.

**Why**
- Form requests run before the controllers, so the permission check lives in `authorize()`. A non-member gets 404 before any validation runs, so the API does not reveal what the validation rules are.
- API resources list the returned fields explicitly. A column added later is not exposed by accident.
- The list orders by `created_at` and then by `id`. Seeded rows share the same `created_at`, and an unstable order can duplicate or skip rows across pages.
- `per_page` is capped at 100 so a client cannot ask for the whole table in one request.


**Learned**
- Starting a query from a many-to-many relationship joins the pivot table, so column names need a table prefix.

## Day 7 - 2026-10-25: Policies and member management

**What I did**
- Wrote `ProjectPolicy` with `update`, `delete`, and `manageMembers`, and replaced the owner checks in the controller and form request with it.
- Added member endpoints: list, add by email, and remove.
- Added tests for the policy, including admins, and 11 tests for the member endpoints.
- Moved the shared `projectFor()` test helper into a trait (`tests/Concerns/CreatesProjects.php`).

**Why**
- Admins are handled in the policy's `before()` method. It returns `true` for admins and `null` for everyone else, so the normal checks still run. An admin who is not a member of a project still gets 404, because I did not want admins to read every project by default.
- Removing the project owner returns 422, not 403. The caller has permission, but the request itself breaks a rule, because the owner must always be a member.
- Removing a member unassigns their tasks inside one transaction, so a task never keeps an assignee who is no longer in the project. The tasks themselves are kept.
- I did not write a task policy yet. The only rule for tasks is "members can do everything", and the query scope already enforces it.

**Learned**
- A 405 means the URL exists but the method is not allowed, so I should check the HTTP method first.

## Day 8 - 2026-10-26: Task status endpoint with row locking

**What I did**
- Added a `TaskStatus` enum with the allowed transitions (`todo` to `in_progress`, `in_progress` to `todo` or `done`, `done` back to `in_progress`).
- Added `PATCH /api/tasks/{task}/status`, which updates the status, sets or clears `completed_at`, and writes a row to `task_status_logs`, all inside one transaction.
- Added tests for transitions, `completed_at`, history rows, validation, and access control.
- Ran 10 concurrent requests against one task and checked that only one history row was written.

**Why**
- Create and update endpoints ignore `status`. Status changes go through one endpoint, so history, `completed_at`, and the ranking cannot be skipped.
- The transaction rule and the "already in this state" check run inside the transaction, after locking the row with `lockForUpdate()`. A check made before the lock could use an outdated status when two requests arrive at the same time.
- Sending the current status again returns 200 and changes nothing, so retries and duplicates are safe.
- A rule violation throws a `ValidateException`, so the client gets a normal 422 with an `errors.status` message, and the transaction rolls back.
- `completed_at` is set only when the new status is `done` and cleared when the task leaves `done`, in the same update as the status.

**Learned**
- A transition rule that depends on the current state must be checked after locking the row, not before.

## Day 9 - 2026-10-27: Vue app setup

**What I did**
- Created the Vue 3 app in `frontend/` with Vite, Vue Router, Pinia, ESLint, and Prettier.
- Fixed the dev server port and added a proxy for `/api` to the Laravel app.
- Wrote an API client (`request`) and an auth store (login, logout, fetch user).

**Why**
- Proxying `/api` through the Vite dev server makes the browser see one origin, so I do not need CORS settings during development.
- The dev server port is fixed with `strictPort`, because Sail already uses port 5173 and Vite would otherwise pick another port silently.
- All API calls go through one `request` function, so headers, JSON handling, and error handling live in one place.
- The token is stored in `localStorage`. This is simple, but it can be read by scripts if the app has an XSS bug. A cookie-based setup would avoid that but needs CSRF protection. I chose the simple option for now.

**Problems and fixes**
- `create-vue` warned that my Node version (23.7.0) is not in its supported range. The warning did not block the setup.

## Day 10 - 2026-10-28: Login screen, route guards, and logout

**What I did**
- Built the login screen with field-level validation errors and a message for failed sign-ins.
- Added route guards: protected routes redirect to the login screen and remember the original path, and the login screen redirects signed-in users to the project list.
- Fetched the user on the first navigation after a reload, and cleared the session when the token was rejected.
- Added a sign-out button to the header.

**Why**
- The token stays in `localStorage`, so a reload keeps the user signed in, but the user object lives only in memory. The guard fetches it again after a reload, which also checks that the token is still valid.
- The session is cleared only on a 401. A network error or a 500 should not sign the user out.
- The `redirect` query value is used only when it starts with `/` and not `//`. Otherwise an attacker could send a login link that sends the user to another site after sign-in.
- The submit button is disabled while the request runs, to prevent double submits.
- `clearSession` is one function used by both sign-out and the guard, so the cleanup logic is not duplicated.

**Learned**
- A guard that returns nothing lets the navigation through, so the redirect has to be returned explicitly.

## Day 11 - 2026-10-29: Project list and create form

**What I did**
- Built the project list with pagination, using the `page` query in the URL.
- Showed one of four states at a time: loading, error with a retry button, empty list, or the list itself.
- Built the create form as its own component. It emits a `created` event, and the list reloads.
- Moved API calls into `src/api/projects.js`.

**Why**
- The page number lives in the URL, so a reload, a bookmark, and the browser's back button all keep working.
- Each load gets a request id, and a response is used only if its id is still the latest. If the user changes pages quickly, a slow response for an old page cannot overwrite the current one.
- The form does not know what the parent does after a create. It only emits an event, so the same form could be reused on another screen.
- I left `required` off the title input on purpose, so I could see the server's 422 message appear under the field. The server validation is the real guard.
- After a create, the list goes to page 1, because new projects appear first.

**Problems and fixes**
- Validation errors did not show up under the title field. I had written `error.value = error.body.errors` instead of `errors.value`. The code ran without any error message, and I found it only by testing the empty-title case.

**Learned**
- Responses can arrive out of order, so a screen that loads data on every change needs a way to ignore old responses.

## Day 12 - 2026-10-30: Project board

**What I did**
- Built the project board at `/projects/:id` with three columns: to do, in progress, and done.
- Linked each project in the list to its board.
- Split the board into small components: the board view, a column, and a task card.
- Showed a "not found" message, a loading state, and an error state with a retry button.

**Why**
- Each column is loaded with its own request, filtered by status (`per_page=100`). With one paginated request, a column could be empty just because other statuses filled the page. The status filter from Day 6 made this easy.
- The column header uses the total from the response metadata, so it stays correct even when the column shows only the first 100 tasks.
- Tasks carry only an assignee id, so I turn the member list into an id-to-name map once after loading. Each card then looks up the name directly.
- The five requests run in parallel with `Promise.all`, and any failure shows one error state for the whole board.
- The board reloads when the `id` in the URL changes, and a response is used only if it belongs to the latest request, as on the project list.
- The "not found" message is the same for a project that does not exist and a project the user is not a member of, matching the API, which returns 404 for both.

**Learned**
- A page that loads several independent resources can fetch them in parallel with `Promise.all`, but one failure rejects the whole group.

## Day 13 - 2026-11-01: Task create, edit, delete, and status change

**What I did**
- Added a task form used for both creating and editing, with field-level validation errors.
- Added buttons on each card to move it to another status, edit it, or delete it.
- Showed only the status buttons that the current status allows.
- Reloaded the whole board after every action, including after a failed one.

**Why**
- After an action, the board is reloaded from the server instead of being patched in the browser. The screen always matches the server. The cost is a few extra requests, which I accept for now.
- The board is reloaded even when the action fails. If another tab changed the task first, the screen is out of date, and a reload fixes it.
- The reload after an action is silent (no "Loading..." state), so the board does not flicker or disappear.
- The status transition rules exist in two places: the server decides, and the browser only decides which buttons to show. If the browser is wrong, the server still returns 422.
- The form gets a `key` based on the task, so Vue creates a new form when I switch from editing one task to editing another. Without it, the old values stay in the form.
- All buttons are disabled while an action runs, to prevent double submits.
- Events from a card travel up through the column to the board view, because Vue events do not bubble to a grandparent on their own.

**Learned**
- Vue events go up one level at a time, so a component in the middle has to re-emit the events from its children.

## Day 14 - 2026-11-02: Shared 401 handling and assignee filter

**What I did**
- Handled 401 responses in one place in the API client: the session is cleared and the user is sent to the login screen, and the page they were on is remembered.
- Showed a "session expired" message on the login screen.
- Added an assignee filter to the board, kept in the URL query.
- Made network failures and non-JSON error responses show a readable message.

**Why**
- The router guard runs only when the URL changes. It cannot notice a token that becomes invalid while the user stays on a page, for example after signing out in another tab. A shared 401 handler covers that case.
- The API client does not import the auth store or the router, because the store already imports the client and that would be a circular import. The client only calls a handler that `main.js` registers.
- The handler runs only for requests that carry a token, so a wrong password on the login form is not treated as an expired session.
- The board loads five requests at once, so up to five 401 responses can arrive together. The handler returns early when the user is already signed out, so the redirect happens once.
- The sign-out request and the guard's user fetch skip the shared handler, because each one already handles its own 401. Otherwise a user who signs out on purpose would see "session expired".
- The assignee filter lives in the URL, so a reload, a bookmark, and the back button keep working. Changing the filter reloads silently, so the board does not flash.

**Learned**
- To avoid a circular import, a low-level module can expose a hook that a higher-level module registers.

**Known limits**
- The guard's 401 and the shared handler redirect differently: only the shared handler adds the "session expired" message.
- A task with no assignee cannot be filtered, because the API has no "unassigned" option.
- A task created while a filter is active may not appear, if it does not match the filter.
- A 502 shows as "HTTP 502". A friendlier message per status code would help.
- The front end has no automated tests yet.

## Day 16 - 2026-11-03: Weekly points ranking with Redis

**What I did**
- Built a weekly ranking of completed points per project with a Redis sorted set, one key per project and week.
- Updated the ranking when a task enters or leaves `done`, after the database transaction commits.
- Added `GET /api/projects/{project}/ranking` and an Artisan command that rebuilds the ranking from the tasks table.
- Added tests for the ranking service, the status endpoint integration, and the ranking endpoint, and added Redis to CI.

**Why**
- A sorted set keeps members ordered by score, so adding points and reading the top N are single, fast commands. This is the same idea I used for rankings in a game with hundreds of thousands of users.
- The database is the source of truth, and Redis is a copy that can be rebuilt. If Redis is flushed, `ranking:rebuild` restores it from the tasks table.
- The ranking is updated in `DB::afterCommit`. If it were updated inside the transaction, a rolled-back status change would still leave points in Redis, because Redis does not take part in the database transaction.
- Points are counted in the week the task was completed, and moving a task out of `done` removes points from that original week. For this, the controller keeps a copy of the task as it was before the update, so the old `completed_at` is still available.
- Keys use the ISO week format (`o-\WW`), so weeks that span a new year are handled correctly. Keys expire after eight weeks, so old weeks do not pile up.
- Redis stores only user ids and scores. Names are loaded from the database when the ranking is shown, in one query, because names can change.
- Users with zero points are left out of the result when the top list is read, so a user whose points were all removed does not appear.

**Problems and fixes**
- `remove` received the updated task, whose `completed_at` was already null, so it removed nothing. I passed the copy made before the update instead.

**Learned**
- Redis does not take part in database transactions, so an update to Redis has to run after the commit.

**Known limits**
- If Redis is down when a status changes, the ranking misses that change until it is rebuilt.
- If the process stops between the commit and the Redis update, the database and Redis disagree until a rebuild.
- `ranking:rebuild` deletes keys with `KEYS`, which is fine for a small data set but can block a large Redis instance. `SCAN` would be safer.
- The ranking is not shown in the Vue app yet.

**Next**
- Day 17: cache the burndown data in Redis, invalidate it when tasks change, and show the ranking in the board.

## Day 17 - 2026-11-04: Sprint burndown cache and board insights

**What I did**
- Added a read-only sprint list endpoint and a burndown endpoint (`remaining` points per day, `null` for days in the future).
- Cached the burndown in Redis and invalidated it from a task observer, after the database commit.
- Showed the weekly ranking and the burndown of a selected sprint on the board, and reloaded both after every task action.
- Added tests for the calculation, the cache, the invalidation, and access control.

**Why**
- The burndown is the same for every viewer until a task changes, so it is a good candidate for a cache. The key contains today's date, so the cache rolls over by itself at midnight, and the "future days are null" part stays correct.
- The cache entry is removed in `DB::afterCommit`. If it were removed inside the transaction, another request could rebuild it from the old data before the commit, and the stale value would stay.
- An observer on the task model clears the cache in one place, so a create, update, delete, or status change cannot forget it. A one-hour expiry is only a safety net.
- When a task moves to another sprint, the old sprint's numbers change too. The observer reads the old sprint id in `updating`, before the save, because the original value is not reliable after the commit.
- The ranking and burndown load separately from the board, so a failure in the side panels shows a message but does not break the board.
- Each load has a request id, and only the latest response is used, as on the other screens.

**Problems and fixes**
- (実際に起きたことを書く。なければ、この項目は、省く)

**Learned**
- Putting invalidation in a model observer is safer than remembering it in every controller, but it does not catch bulk query updates.

**Known limits**
- Bulk updates through the query builder do not fire model events, so they do not clear the cache. The only one today is clearing assignees, which does not change points.
- Days are computed in the application's timezone (UTC), so a task completed late in the evening in Vancouver can fall on the next day.
- Changing a sprint's dates does not clear the cache. There is no sprint update endpoint yet.
- A fast sprint switch can show an older response. The burndown request has no request id.
- The UI cannot create sprints or assign a task to a sprint, so the burndown only shows data that was created in the seeder or by the API.