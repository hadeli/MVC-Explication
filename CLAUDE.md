# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

Teaching project (in French) showing how to migrate from "classic" PHP pages to an MVC architecture.
The code is refactored step by step; each step must stay runnable and understandable on its own.
Comments and UI text are in French.

## Hard constraints

- **No Composer, ever.** No `composer.json`, no `vendor/`. Autoloading is done by a hand-written
  `autoload.php` using `spl_autoload_register` (PSR-4 style mapping `App\` -> `src/`), introduced at step 5.
- No external libraries or frameworks. Everything is plain PHP.

## Commands

Requires PHP >= 8 with `pdo_sqlite` (bundled by default).

```bash
php -S localhost:8000          # run from the project root, then open http://localhost:8000/index.php
php -l fichier.php             # syntax check a file
rm database.sqlite             # reset the database (recreated automatically on first request)
```

There is no test suite. Verify behaviour manually or with `curl` against the built-in server
(register, then login with `-c cookies`, then request `index.php` with `-b cookies`).

## Student material

`PLAN.md` is the Socratic guide handed to students: questions only, no solutions. Keep it that way. When a
step changes, keep `PLAN.md` and the roadmap below consistent.

## Current step: classic PHP pages (step 1)

Three standalone pages at the root, each mixing PHP logic and HTML in one file:

- `index.php`: home, shows the logged-in user from `$_SESSION['utilisateur']`, handles `?action=logout`.
- `register.php`: validation, uniqueness check, `password_hash`, insert into `users`.
- `login.php`: `password_verify`, stores `['id', 'email']` in the session, redirects to `index.php`.

Storage is SQLite via PDO in `database.sqlite` (gitignored). The `users` table is created with
`CREATE TABLE IF NOT EXISTS` at the top of `login.php` and `register.php`.

**The duplication is intentional.** The PDO connection block, `session_start()`, the `<nav>` and the
HTML skeleton are copy-pasted across pages to expose the problems MVC solves. Do not "clean up" this
step by extracting shared includes unless the user asks to move to the next step of the migration.

## Migration roadmap

| Step | Content | Concept introduced |
|---|---|---|
| 1 | Classic pages (done) | |
| 2 | `includes/db.php`, `header.php`, `footer.php` | Reuse |
| 3 | `views/` folder and a `render()` helper | View |
| 4 | `User` and `UserRepository` classes | Model |
| 5 | Hand-written `autoload.php`, then namespaces `App\...` mirrored in `src/` | Autoloading |
| 6 | `public/index.php` front controller and router | Front controller |
| 7 | `AuthController`, `HomeController` classes | Controller, constructor injection |
| 8 | `src/Core/`: `Router`, `Request`, `Response`, `View` | Reusable core |

Each step must leave the site fully working (register, login, home, logout). When a step is completed,
update the "Current step" section above to describe the new layout and how a request flows through it.
