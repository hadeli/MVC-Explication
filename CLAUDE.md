# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Purpose

Teaching project (in French) showing how to migrate from "classic" PHP pages to an MVC architecture.
The code is refactored step by step; each step must stay runnable and understandable on its own.
Audience: 2nd-year BUT informatique students who must then build an MVC web site for their SAÉ; the repo
is a support for structuring their code and thinking. Steps 1-11 are the expected course; steps 12-14 are
presented to them as an optional bonus that prepares later teaching units (architecture, frameworks). Keep
that framing in `README.md`, `PLAN.md` and the READMEs of steps 11-14.
Comments and UI text are in French.

## Hard constraints

- **No Composer, ever.** No `composer.json`, no `vendor/`. Autoloading is done by a hand-written
  `autoload.php` using `spl_autoload_register` (PSR-4 style mapping `App\` -> `src/`), introduced at step 6.
- No external libraries or frameworks. Everything is plain PHP: the `.env` loader (step 3) and the view
  components (step 10) are hand-written too. There is no template engine and no template syntax.
- `.env` is gitignored; `.env.example` is versioned. Never put connection values back into PHP code once step 3 is done.

## Two repositories

- **Public repo `hadeli/MVC-Explication`** (remote `origin`, local branch `master`) is what students get. Only
  `etapes/01-pages-classiques/` (and the root copy) contains code. `etapes/02-*` to `etapes/14-*` hold **only**
  `README.md`: students write each step themselves inside that folder, then run `./verifier.sh NN`. Never add
  code to those folders. Student-facing docs must not say where the correction lives.
- **Private repo `hadeli/MVC-Explication-correction`** (remote `prive`, its `master` is the local branch
  `correction`) holds the thirteen complete, runnable step folders. All code work happens here:
  `git switch correction`, change the step(s), run `verifier.sh`, commit, `git push prive correction:master`.
  Then bring only the doc changes (`README.md`, `PLAN.md`, `MVC.md`, `CLAUDE.md`, `verifier.sh`,
  `etapes/*/README.md`) to `master`, e.g. `git switch master && git checkout correction -- PLAN.md etapes/12-interfaces/README.md`.
  A change to the root pages or step 1 goes on both. Doc-only changes may go the other way: edit on `master`,
  then `git switch correction && git checkout master -- README.md PLAN.md ...`, commit, push to `prive`.
  Whatever the direction, the two repos must end up with identical docs (`git diff master correction -- PLAN.md`
  empty). The public `master` history was squashed to a root commit that never contained step code; keep it
  that way (no merge from `correction`, never push `correction` to `origin`).

## Layout

The root `index.php`, `login.php`, `register.php` are the students' starting point: an exact copy of
`etapes/01-pages-classiques/`. Keep them identical to that folder (`diff` must be empty); never refactor them.

In the private repo, every step lives in its own complete, runnable folder under `etapes/NN-nom/` (01 to 14).
Each folder has a French `README.md` (present in both repos) describing the problem, the mechanism, the
pitfalls and the limits of the step, with code excerpts. Steps are built cumulatively: a change to an early
step usually has to be propagated to all later steps.

- Steps 01-06: pages at the folder root, run with `php -S localhost:8000` from the folder, URLs `/login.php` etc.
- Steps 07-14: `public/` is the docroot, run with `php -S localhost:8000 -t public`, URLs `/login` etc.
- Steps 03+: need `.env` (copy from `.env.example`). `verifier.sh` does this automatically.
- Step 14 writes `cache/controleurs.php` (`cache/` is gitignored). No other step writes to disk besides SQLite.
- Steps 11-14: views are component trees. `src/View/Component.php` is abstract (`render(): string`, `__toString()`,
  `protected e(string)` = the only `htmlspecialchars()` in the project, `protected renderAll(array)` where a `string`
  child is escaped text and a `Component` child is HTML inserted as is). Concrete components in `src/View/`:
  `Layout(string $title, ?array $user, array $children)` (whole document, nav from `$user`, `<h1>`; replaces
  `views/layout.php`), `Alert(array $messages)`, `Form(string $action, array $children, string $method = 'post')`,
  `Input(string $name, string $label, string $type = 'text', string $value = '')`, `Button(string $label)`,
  `Paragraph(array $children)`, `Link(string $href, string $text)`, `Strong(string $text)`. Each `views/*.php`
  does `echo new Layout(...)`; `View::render()` only captures the view file. `src/View/` and `views/` are
  identical in steps 11 to 14.
- Step 12 has no `config/routes.php` and no `AuthController`: one controller class per verb/path
  (`Home`, `LoginForm`, `Login`, `RegisterForm`, `Register`, `Logout` + `Controller`), each implementing
  `App\Core\ControllerInterface` (`public static function support(Request): bool`, `handle(Request): Response`).
  All six extend `App\Core\AbstractController`, which implements `support()` from abstract static `verb()`
  and `path()` (`static::` late static binding). The four `/login` and `/register` controllers share `App\Controller\FormTrait`
  (`lireEmail()`, `rendreFormulaire()`, relies on the host's `$view`). `public/index.php` hands the Router
  `[class => factory]`; the Router calls `$class::support()` and only instantiates the matching one.
- Step 13: same controllers, but `public/index.php` names none of them. `App\Core\ControllerFinder::trouver(dir, ns)`
  globs `src/Controller/*.php` and keeps instantiable classes implementing the interface (alphabetical order);
  `App\Core\Container` (`creer(class)`) builds objects by reflecting constructor parameter types, with
  `View` and `UserRepository` registered as shared services. Router takes `(class list, Container, View)`.
- Step 14: `App\Core\ControllerCache::charger()` returns `[class => constructor type list]`, read from
  `cache/controleurs.php` when fresh (cache mtime strictly > dir mtime and > every `*.php` mtime; equality counts as
  stale because `filemtime()` has 1 s resolution), otherwise rebuilt via
  `ControllerFinder::trouver()` + `Container::analyser()` (now public static) and written with `var_export`.
  `Container` accepts the plans as second constructor argument; `index.php` no longer calls the finder.

Root-level docs: `README.md` (French, humans), `PLAN.md`, `MVC.md`, this file, and `verifier.sh`.

## Commands

```bash
git switch correction                                   # code lives here (private repo, remote `prive`)
./verifier.sh 05                                        # smoke-test one step (starts php -S, runs curl checks)
for n in 01 02 03 04 05 06 07 08 09 10 11 12 13 14; do ./verifier.sh $n; done
php -l etapes/10-noyau/src/Core/Router.php              # syntax check a file
diff -r etapes/04-vues etapes/05-modele                 # see exactly what a step changed
```

There is no PHPUnit. `verifier.sh` is the test suite: in the private repo it must print `=> OK` for every step
after any change. In the public repo it passes for step 01 only and prints an explanatory message for a folder
without PHP files. Run it against every step from the one you touched onward. The exact French strings it looks
for are listed in `README.md` (« Vérifier une étape »); every step must keep them verbatim.

RTK proxy hook caveats: `verifier.sh` output is sometimes truncated (redirect to a file and `cat` it), and a
`for ... do ...; done` loop whose body runs `cat` or `git show` may be rewritten into invalid shell. Chain
commands with `;` or run them one by one instead.

## Student material

- `PLAN.md`: directive guide handed to students. Per step: objective, imperative "À faire" list naming the
  target files and signatures (matching `correction`), hints (functions to look up), two or three questions,
  and the `verifier.sh` check. No code solutions: those stay in the step `README.md` files.
- `MVC.md`: reference diagrams (Mermaid + ASCII) of the target step-10 architecture and a full `POST /login`
  trace with code excerpts. It contains solutions, so it is end-of-course / teacher material.

When a step changes, keep `PLAN.md`, `MVC.md` and the roadmap below consistent. Code in `MVC.md` is the
reference for naming (`App\Core\{Router,Request,Response,View,Database}`, `config/routes.php`, `views/layout.php`).

## Current state

All 14 steps are implemented in the private repo and pass `verifier.sh` there. Reference naming for steps 10-11 (namespaces mirror
`src/`): `App\Core\{Env,Database,Request,Response,View,Router}`, `App\Controller\{AuthController,HomeController}`,
`App\Model\{User,UserRepository}`, `App\View\{Component,Layout,Form,Input,Button,Alert,Paragraph,Link,Strong}` (step 11+),
routes in `config/routes.php` as `[method, path, [class, action]]`, `views/*.php` at both steps
(`views/layout.php` at step 10 only, replaced by the `Layout` component at step 11).

Behaviour that every step must preserve (checked by `verifier.sh`): register with validation (email format,
8-char minimum, confirmation, uniqueness), login with `password_verify`, session holding `['id','email']`,
home page showing `Bonjour <strong>email</strong>`, logout, HTML escaping of user input. Steps 7+ also: 404
for unknown paths, no access to files outside `public/`, and `/login/` served like `/login`. `php -S` chdirs to the
docroot, so a relative SQLite DSN resolved from cwd lands in `public/` from step 7 on: the script reports that
explicitly before the URL check.

## Migration roadmap

| Step | Content | Concept introduced |
|---|---|---|
| 1 | Classic pages | Starting point |
| 2 | `includes/db.php`, `header.php`, `footer.php` | Reuse |
| 3 | `.env` + `.env.example`, hand-written loader in `includes/env.php` | Config vs code, secrets out of VCS |
| 4 | `views/` folder and a `render()` helper | View |
| 5 | `User` and `UserRepository` classes | Model |
| 6 | Hand-written `autoload.php`, then namespaces `App\...` mirrored in `src/` | Autoloading |
| 7 | `public/index.php` front controller and router | Front controller |
| 8 | `AuthController`, `HomeController` classes | Controller, constructor injection |
| 9 | `src/Core/`: `Request`, `Response`; controllers take a `Request` and return a `Response`, `render()` captures the HTML with `ob_start` and returns a `Response`; `includes/` and partials unchanged | Request/Response objects, testable controllers |
| 10 | `src/Core/`: `Router`, `View` + `views/layout.php`, `Env`, `Database` (reads `.env`); `includes/` and `views/partials/` removed | Reusable core |
| 11 | `src/View/`: abstract `Component` (`render()`, `__toString()`, `e()`, `renderAll()`), `Layout`, `Form`, `Input`, `Button`, `Alert`, `Paragraph`, `Link`, `Strong`; views become component trees, `views/layout.php` removed | View components: inheritance, composition, escaping by type |
| 12 | `src/Core/ControllerInterface.php` (`static support(Request): bool`, `handle(Request): Response`); `AbstractController` (`verb()`, `path()`); `FormTrait`; one controller per verb/path; Router asks classes, instantiates only the match; `config/routes.php` removed | Interface, abstract class, trait, dynamic router |
| 13 | `src/Core/ControllerFinder.php` (glob + `is_a`), `src/Core/Container.php` (constructor autowiring via Reflection); `index.php` names no controller | Discovery, reflection-based injection |
| 14 | `src/Core/ControllerCache.php`: discovery + reflection results written to `cache/controleurs.php`, `filemtime`-invalidated (dir + files) | Caching a deterministic computation |

Each step must leave the site fully working (register, login, home, logout).
