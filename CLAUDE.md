# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

Teaching project (in French) showing how to migrate from "classic" PHP pages to an MVC architecture.
The code is refactored step by step; each step must stay runnable and understandable on its own.
Comments and UI text are in French.

## Hard constraints

- **No Composer, ever.** No `composer.json`, no `vendor/`. Autoloading is done by a hand-written
  `autoload.php` using `spl_autoload_register` (PSR-4 style mapping `App\` -> `src/`), introduced at step 6.
- No external libraries or frameworks. Everything is plain PHP: the `.env` loader (step 3) and the template
  engine (step 10) are hand-written too.
- `.env` is gitignored; `.env.example` is versioned. Never put connection values back into PHP code once step 3 is done.

## Two branches

- **`master`** is what students get. Only `etapes/01-pages-classiques/` (and the root copy) contains code.
  `etapes/02-*` to `etapes/13-*` hold **only** `README.md`: students write each step themselves inside that
  folder, then run `./verifier.sh NN`. Never add code to those folders on `master`.
- **`correction`** holds the thirteen complete, runnable step folders. All code work happens here:
  `git switch correction`, change the step(s), run `verifier.sh`, commit. Then bring only the doc changes
  (`README.md`, `PLAN.md`, `MVC.md`, `CLAUDE.md`, `verifier.sh`, `etapes/*/README.md`) to `master`,
  e.g. `git switch master && git checkout correction -- PLAN.md etapes/11-interfaces/README.md`.
  A change to the root pages or step 1 goes on both branches.

## Layout

The root `index.php`, `login.php`, `register.php` are the students' starting point: an exact copy of
`etapes/01-pages-classiques/`. Keep them identical to that folder (`diff` must be empty); never refactor them.

On `correction`, every step lives in its own complete, runnable folder under `etapes/NN-nom/` (01 to 13).
Each folder has a French `README.md` (present on both branches) describing the problem, the mechanism, the
pitfalls and the limits of the step, with code excerpts. Steps are built cumulatively: a change to an early
step usually has to be propagated to all later steps.

- Steps 01-06: pages at the folder root, run with `php -S localhost:8000` from the folder, URLs `/login.php` etc.
- Steps 07-13: `public/` is the docroot, run with `php -S localhost:8000 -t public`, URLs `/login` etc.
- Steps 03+: need `.env` (copy from `.env.example`). `verifier.sh` does this automatically.
- Steps 10-13 compile templates into `cache/` (gitignored); step 13 also writes `cache/controleurs.php`.
- Step 11 has no `config/routes.php` and no `AuthController`: one controller class per verb/path
  (`Home`, `LoginForm`, `Login`, `RegisterForm`, `Register`, `Logout` + `Controller`), each implementing
  `App\Core\ControllerInterface` (`public static function support(Request): bool`, `handle(Request): Response`).
  All six extend `App\Core\AbstractController`, which implements `support()` from abstract static `verb()`
  and `path()` (`static::` late static binding). the four `/login` and `/register` controllers share `App\Controller\FormTrait`
  (`lireEmail()`, `rendreFormulaire()`, relies on the host's `$view`). `public/index.php` hands the Router
  `[class => factory]`; the Router calls `$class::support()` and only instantiates the matching one.
- Step 12: same controllers, but `public/index.php` names none of them. `App\Core\ControllerFinder::trouver(dir, ns)`
  globs `src/Controller/*.php` and keeps instantiable classes implementing the interface (alphabetical order);
  `App\Core\Container` (`creer(class)`) builds objects by reflecting constructor parameter types, with
  `View` and `UserRepository` registered as shared services. Router takes `(class list, Container, View)`.
- Step 13: `App\Core\ControllerCache::charger()` returns `[class => constructor type list]`, read from
  `cache/controleurs.php` when fresh (cache mtime >= dir mtime and >= every `*.php` mtime), otherwise rebuilt via
  `ControllerFinder::trouver()` + `Container::analyser()` (now public static) and written with `var_export`.
  `Container` accepts the plans as second constructor argument; `index.php` no longer calls the finder.

Root-level docs: `README.md` (French, humans), `PLAN.md`, `MVC.md`, this file, and `verifier.sh`.

## Commands

```bash
git switch correction                                   # code lives here
./verifier.sh 05                                        # smoke-test one step (starts php -S, runs curl checks)
for n in 01 02 03 04 05 06 07 08 09 10 11 12 13; do ./verifier.sh $n; done
php -l etapes/09-noyau/src/Core/Router.php              # syntax check a file
diff -r etapes/04-vues etapes/05-modele                 # see exactly what a step changed
```

There is no PHPUnit. `verifier.sh` is the test suite: on `correction` it must print `=> OK` for every step
after any change. On `master` it passes for step 01 only and prints an explanatory message for a folder
without PHP files. Run it against every step from the one you touched onward. Its output is sometimes truncated by the RTK
proxy hook; redirect to a file and `cat` it if lines are missing.

## Student material

- `PLAN.md`: Socratic guide handed to students, questions only, no solutions. Keep it that way.
- `MVC.md`: reference diagrams (Mermaid + ASCII) of the target step-9 architecture and a full `POST /login`
  trace with code excerpts. It contains solutions, so it is end-of-course / teacher material.

When a step changes, keep `PLAN.md`, `MVC.md` and the roadmap below consistent. Code in `MVC.md` is the
reference for naming (`App\Core\{Router,Request,Response,View,Database}`, `config/routes.php`, `views/layout.php`).

## Current state

All 13 steps are implemented on `correction` and pass `verifier.sh` there. Reference naming for steps 9-10 (namespaces mirror
`src/`): `App\Core\{Env,Database,Request,Response,View,Router,Template}`, `App\Controller\{AuthController,HomeController}`,
`App\Model\{User,UserRepository}`, routes in `config/routes.php` as `[method, path, [class, action]]`,
templates in `views/*.html` for step 10 (`views/*.php` + `views/layout.php` for step 9).

Behaviour that every step must preserve (checked by `verifier.sh`): register with validation (email format,
8-char minimum, confirmation, uniqueness), login with `password_verify`, session holding `['id','email']`,
home page showing `Bonjour <strong>email</strong>`, logout, HTML escaping of user input. Steps 7+ also: 404
for unknown paths and no access to files outside `public/`.

## Migration roadmap

| Step | Content | Concept introduced |
|---|---|---|
| 1 | Classic pages (done) | |
| 2 | `includes/db.php`, `header.php`, `footer.php` | Reuse |
| 3 | `.env` + `.env.example`, hand-written loader in `includes/env.php` | Config vs code, secrets out of VCS |
| 4 | `views/` folder and a `render()` helper | View |
| 5 | `User` and `UserRepository` classes | Model |
| 6 | Hand-written `autoload.php`, then namespaces `App\...` mirrored in `src/` | Autoloading |
| 7 | `public/index.php` front controller and router | Front controller |
| 8 | `AuthController`, `HomeController` classes | Controller, constructor injection |
| 9 | `src/Core/`: `Router`, `Request`, `Response`, `View`, `Database` (reads `.env`) | Reusable core |
| 10 | `src/Core/Template.php`: `{{ }}` escaped output, loops, conditions, layout blocks, compiled to `cache/` | Templating |
| 11 | `src/Core/ControllerInterface.php` (`static support(Request): bool`, `handle(Request): Response`); `AbstractController` (`verb()`, `path()`); `FormTrait`; one controller per verb/path; Router asks classes, instantiates only the match; `config/routes.php` removed | Interface, abstract class, trait, dynamic router |
| 12 | `src/Core/ControllerFinder.php` (glob + `is_a`), `src/Core/Container.php` (constructor autowiring via Reflection); `index.php` names no controller | Discovery, reflection-based injection |
| 13 | `src/Core/ControllerCache.php`: discovery + reflection results written to `cache/controleurs.php`, `filemtime`-invalidated (dir + files) | Caching a deterministic computation |

Each step must leave the site fully working (register, login, home, logout).
