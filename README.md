# CarHub

**A Web-Based Vehicle Rental Marketplace for Connecting Vehicle Owners and Renters.**

CarHub is a peer-to-peer vehicle rental platform. Owners list vehicles that would otherwise sit idle; renters find and book them through a process that verifies both sides, validates the payment before a reservation is held, and records every trip against a digital contract.

Built as an undergraduate thesis presented to the Department of Computer Science, College of Computing Studies, in partial fulfilment of the requirements for the degree of Bachelor of Science in Computer Science.

---

## Project status

This repository currently contains the **public-facing site** and the **authentication scaffolding**. The rental domain itself has not been modelled yet.

**Implemented**

- Complete guest-facing marketing site — landing page, vehicle browse with working filters, vehicle detail with a live booking calculator, plus How it works, About, Contact, FAQ, and Terms pages.
- Authentication via Laravel Fortify — registration, login, password reset, and email verification, on a branded split-screen shell that matches the marketing design.
- User settings — profile, password/security, and appearance.
- Design system — brand tokens, a reusable marketing component library, and a light-only public shell.
- Branded error pages for 401, 402, 403, 404, 419, 429, 500, and 503.

**Not yet implemented**

- Domain models. `app/Models/` contains only `User` — there is no `Vehicle`, `Booking`, `Owner`, or `Payment` model, and no corresponding migrations.
- The ML features from the panel review — content-based recommendations, LSTM demand forecasting, GPS map search, and the chatbot. **The marketing site deliberately does not advertise these**, since nothing backs them yet; a test in `tests/Feature/Marketing/PublicPagesTest.php` asserts no public page claims them. The chat bubble is presentational and its input is disabled.
- Payment processing, ID verification uploads, and contract generation.
- The authenticated dashboard is still the starter-kit placeholder.

Vehicle listings on the public site are read from a static config file, **not** the database. See [Demo data](#demo-data).

---

## Tech stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.4.1+ (Symfony 8 components in the lock require it) |
| Framework | Laravel 13 |
| Frontend | Livewire 4 + Alpine.js (bundled) |
| UI components | Flux UI 2 (free tier) |
| Styling | Tailwind CSS 4 — CSS-first config, no `tailwind.config.js` |
| Auth | Laravel Fortify 1 |
| Build | Vite 8 |
| Database | MySQL 8 |
| Testing | Pest 5 |
| Formatting | Laravel Pint |
| Static analysis | Larastan / PHPStan |

---

## Requirements

- PHP **8.4.1+** with the `pdo_mysql`, `mbstring`, `openssl`, `xml`, `curl`, and `fileinfo` extensions

  > Laravel 13 itself allows PHP 8.3, but the Symfony 8 components it pulls in require `>= 8.4.1`. `composer.json` declares `^8.4.1` so an unsupported runtime is reported against this project rather than as 40-odd confusing conflicts deep in the dependency tree.

- Composer 2
- Node.js **20.19+** or **22.12+**, and npm (Vite 8 requirement)
- MySQL 8 (or MariaDB 10.6+)

---

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url> carhub
cd carhub
composer install
npm install
```

### 2. Create the environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Create the database

CarHub uses MySQL. Create an empty database first — the migration step will not create it for you:

```bash
mysql -u root -p -e "CREATE DATABASE carhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then set the connection details in `.env` (the `DB_*` lines below `DB_CONNECTION` are commented out by default — uncomment and fill them in):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=carhub
DB_USERNAME=root
DB_PASSWORD=your_password
```

> Sessions, the cache, and the queue all default to the `database` driver, so the migrations below are required even for a bare front-end run.

### 4. Run migrations and build assets

```bash
php artisan migrate
npm run build
```

### 5. Start the application

```bash
composer run dev
```

This starts the PHP server, the Vite dev server, the queue listener, and log tailing together. Open **http://localhost:8000**.

<details>
<summary>Prefer to run the pieces separately?</summary>

```bash
php artisan serve      # http://localhost:8000
npm run dev            # Vite with hot reload
php artisan queue:listen
php artisan pail       # live logs
```

</details>

### Shortcut

Once the database exists and `.env` is configured, steps 1–4 collapse into:

```bash
composer run setup
```

---

## Common commands

| Command | What it does |
|---|---|
| `composer run dev` | Run every dev process together (server, Vite, queue, logs) |
| `composer run test` | Clear config, check formatting, run PHPStan, run the suite |
| `php artisan test --compact` | Run the test suite only |
| `composer run lint` | Fix code style with Pint |
| `composer run types:check` | Run static analysis |
| `npm run build` | Build production assets |
| `php artisan route:list --except-vendor` | List application routes |

> **If a change doesn't show up in the browser**, the assets are stale — run `npm run build`, or keep `npm run dev` running.

---

## Public routes

| Method | URI | Name | View |
|---|---|---|---|
| GET | `/` | `home` | `pages/marketing/home.blade.php` |
| GET | `/vehicles` | `vehicles.index` | `pages/marketing/⚡browse.blade.php` (Livewire) |
| GET | `/vehicles/{slug}` | `vehicles.show` | `pages/marketing/vehicle.blade.php` |
| GET | `/how-it-works` | `how-it-works` | `pages/marketing/how-it-works.blade.php` |
| GET | `/about` | `about` | `pages/marketing/about.blade.php` |
| GET | `/contact` | `contact` | `pages/marketing/⚡contact.blade.php` (Livewire) |
| GET | `/faq` | `faq` | `pages/marketing/faq.blade.php` |
| GET | `/terms` | `terms` | `pages/marketing/terms.blade.php` |

Authentication routes (`/login`, `/register`, `/forgot-password`, `/reset-password/{token}`) are registered by Fortify. Enabled features are registration, password reset, and email verification — two-factor authentication is **not** enabled, and the `users` table has no 2FA columns.

Authenticated routes: `/dashboard`, `/settings/profile`, `/settings/security`, `/settings/appearance`.

---

## Project structure

```
app/
  Actions/Fortify/          Registration and password-reset actions
  Http/Controllers/
    VehicleController.php   Resolves a vehicle by slug for the detail page
  Livewire/Actions/         Logout action
  Models/User.php           The only model so far

config/
  demo.php                  Static vehicles, testimonials, and FAQs (see below)

resources/
  css/app.css               Tailwind entry + brand tokens + custom utilities
  views/
    layouts/
      marketing.blade.php   Public shell — light-only, no dark class
      error.blade.php       Error shell — standalone, no Livewire/Flux
      app/                  Authenticated shell (sidebar) — the only dark shell
      auth.blade.php        Thin wrapper forwarding to the split shell
      auth/split.blade.php  Guest auth shell — form column + brand panel
    components/marketing/   Marketing component library (12 components)
    errors/                 Branded 401/402/403/404/419/429/500/503 pages
    pages/marketing/        The eight public pages
    pages/auth/             Fortify's login, register, reset views
    pages/settings/         Livewire settings components
    partials/
      head.blade.php        Head for app/auth pages (includes @fluxAppearance)
      marketing-head.blade.php  Head for public pages (SEO meta, no appearance script)

routes/
  web.php                   Public + authenticated routes
  settings.php              Settings routes

tests/Feature/
  Marketing/                Public site test coverage
  ErrorPagesTest.php        Error page coverage
```

### Error pages

`resources/views/errors/` overrides Laravel's defaults for 401, 402, 403, 404, 419, 429, 500, and 503, all sharing `layouts/error.blade.php`.

The error shell is deliberately **standalone** — no Livewire, no Flux, no marketing nav or footer — because an error page has to render when the application is unhealthy. Two specific safeguards:

- **`@vite` is guarded.** If `public/build/manifest.json` is missing, the layout falls back to a small inline stylesheet instead of throwing a `ViteException` *from the error page itself*. A fresh clone still gets a legible 404 before the first `npm run build`.
- **500 and 503 use `url()`, never `route()`.** A routing or config fault is a plausible cause of a 500, so those two pages avoid anything that could fail for the same reason. A test enforces this.

To preview them locally:

```bash
php artisan down --render=errors::503   # then php artisan up
```

### Blade conventions

Livewire 4 registers two view namespaces, and this project uses both:

- `layouts::name` → `resources/views/layouts/` — e.g. `<x-layouts::marketing>`
- `pages::name` → `resources/views/pages/` — e.g. `view('pages::marketing.home')`

Livewire single-file components are marked with a **`⚡` filename prefix** (`⚡browse.blade.php`) and are routed with `Route::livewire()`. Files in `pages/` without the prefix are ordinary Blade views.

---

## Demo data

The public site reads its vehicle catalogue from **`config/demo.php`**, not the database. It holds roughly a dozen sample vehicles plus testimonials and FAQ content, shared by the landing page, the browse page, and the vehicle detail page.

This is deliberate — the rental domain has not been designed yet, so hard-coding the catalogue avoids locking in a schema prematurely. Once `Vehicle` and `Booking` models exist, the views should read from Eloquent and this file can be deleted.

### Vehicle images

No photography ships with the repository. `<x-marketing.vehicle-image>` renders a branded SVG illustration by default and automatically switches to a real photo when one is available:

1. Drop the image into `public/images/vehicles/`.
2. Set the `image` key on that vehicle in `config/demo.php`, e.g. `'image' => 'images/vehicles/toyota-vios.jpg'`.

The component checks the file actually exists before using it, so a missing or misspelled path falls back to the illustration rather than rendering a broken image.

---

## Design system

### Brand tokens

Tailwind 4 is configured CSS-first — there is **no `tailwind.config.js`**. Theme values live in the `@theme` block in `resources/css/app.css`:

- `--color-brand-50` … `--color-brand-950` — electric blue primary (`brand-600` = `#2442f5`)
- `--color-spark-300` … `--color-spark-600` — cyan secondary, used only in gradients and glows

Two custom utilities are defined in `@layer components`:

- `.text-gradient` — brand-to-cyan gradient text, used on the emphasised phrase in marketing headings
- `.glass-card` — frosted panel with a hairline ring

The existing `--color-accent` tokens are left untouched — they drive Flux's focus rings across the authenticated and auth screens.

### Light vs dark

Everything a guest sees — the marketing pages, the auth screens, and the error pages — is **light-only**. `layouts/marketing.blade.php`, `layouts/auth/split.blade.php`, and `layouts/error.blade.php` each omit both the `dark` class and the `@fluxAppearance` script. That second omission matters: `@fluxAppearance` restores a dark preference persisted inside the application, and would otherwise re-darken a guest page at runtime. Auth and marketing pages therefore use `partials/marketing-head.blade.php`, not `partials/head.blade.php`.

Only the **authenticated application** (`layouts/app/*`) hardcodes `class="dark"` and honours the appearance toggle at `/settings/appearance`.

Because of that split, marketing navigation uses plain links rather than `wire:navigate` — an SPA body swap would not update the `<html>` class when crossing into the app shell.

### Auth screens

The six Fortify screens share one shell, `layouts/auth/split.blade.php`: the form column on the left, `<x-auth.brand-panel>` on the right (hidden below `lg`). Each page passes its own `:panel-heading` and `:panel-description` so the pitch matches the task — "Welcome back" on login, "Start renting, or start earning" on register.

`layouts/auth.blade.php` is a thin wrapper that forwards to the split shell, so swapping the whole auth look is a one-file change.

### Marketing components

`resources/views/components/marketing/` holds twelve reusable anonymous Blade components: `nav`, `footer`, `chat-bubble`, `glow`, `section-heading`, `vehicle-card`, `vehicle-image`, `feature-card`, `stat`, `step`, `faq-item`, and `prose`.

Flux is used where it fits — inputs, selects, buttons, and toasts. Marketing surfaces (heroes, cards, CTA bands) are hand-rolled Tailwind, since Flux's free tier is styled as application chrome rather than marketing chrome.

---

## Testing

```bash
php artisan test --compact                      # everything
php artisan test --compact tests/Feature/Marketing   # public site only
php artisan test --compact --filter=browse      # a single group
```

The suite runs against an **in-memory SQLite database** configured in `phpunit.xml`, so no MySQL setup is needed to run tests.

Public site coverage lives in `tests/Feature/Marketing/`:

- **`PublicPagesTest.php`** — every public route responds as a guest, the vehicle detail page renders its listing, unknown slugs 404, and the marketing shell never renders dark.
- **`BrowseTest.php`** — each filter narrows results correctly, sorting orders them, filters clear individually and in bulk, over-filtering shows the empty state, and query parameters from the home page search bar are applied on mount.
- **`ContactFormTest.php`** — validation rules on the enquiry form.

`tests/Feature/ErrorPagesTest.php` covers the error views — every code renders with its heading, the 403 page surfaces an authorization message when one is supplied, and the 500/503 views are checked to contain no `route()` calls.

### Gotcha: clear the config cache before running tests

If `bootstrap/cache/config.php` exists, the cached `APP_ENV=local` overrides the `testing` value in `phpunit.xml`. Laravel then stops treating the run as a unit test and **re-enables CSRF verification**, so every `POST` in the auth suite fails with a `419`.

This is why `composer run test` clears config first. If you run `php artisan test` directly and see unexplained 419s:

```bash
php artisan optimize:clear
```

Expected result on a clean run:

```
Tests:    72 passed (183 assertions)
```

Everything passes — nothing skipped, no todos, no risky tests. Two-factor authentication is not implemented in this application (no `two_factor_*` columns on the `users` table, feature disabled in `config/fortify.php`, no interface), so the starter kit's 2FA test scaffolding has been removed rather than left permanently skipping. Reintroduce it alongside the feature if 2FA is ever built.

---

## Continuous integration

`.github/workflows/tests.yml` runs `composer setup` then `composer ci:check` (Pint, PHPStan, Pest) on every push to `main` and every pull request.

Two things the workflow has to keep in step with the project:

- **PHP 8.4.** It must satisfy the `^8.4.1` constraint in `composer.json`. Pinning CI to 8.3 makes `composer install` fail with dozens of Symfony conflicts.
- **A MySQL service.** `composer setup` runs `php artisan migrate`, so CI provisions MySQL 8 and passes `DB_*` environment variables. The test suite itself does not use it — Pest runs against in-memory SQLite — but it smoke-tests the migrations against the real engine.

To reproduce the CI run locally:

```bash
composer ci:check
```

---

## Code style

PHP is formatted with Pint (config in `pint.json`) and analysed with Larastan (`phpstan.neon`). Run both before committing:

```bash
composer run lint
composer run types:check
```

Project conventions worth knowing:

- Curly braces on all control structures, explicit return types, and typed parameters.
- PHPDoc blocks over inline comments; array shapes documented in PHPDoc.
- Constructor property promotion.
- Named routes and the `route()` helper for links.
- Descriptive method names (`isRegisteredForDiscounts`, not `discount()`).

Additional guidance for AI coding agents is in `CLAUDE.md`.

---

## Roadmap

Derived from the thesis panel's review comments.

The ML-backed items (2, 3, 4) are **not advertised anywhere on the site** — the marketing pages were deliberately stripped of AI claims so nothing is promised that isn't built. Where a capability has a plain, honest description (demand-based pricing, GPS tracking) it is worded without naming a model.

| # | Feature | Where it's surfaced today | Built? |
|---|---|---|---|
| 1 | Chatbot | Floating assistant on every public page | UI only, input disabled |
| 2 | Personalised recommendations & price optimisation | Owner earnings panel (suggested rate + forecast chart) | No |
| 3 | Content-based filtering | Browse filters — attribute matching only, no learning | Filters work |
| 4 | LSTM demand forecasting | Owner earnings panel chart is illustrative | No |
| 5 | Renter security | Landing trust & safety section | Described |
| 6 | GPS-based vehicle search with map | Disabled "Map view" toggle on browse | No |
| 7 | Vehicle tracking | Vehicle features; Terms §8 | Described |
| 8 | Two valid ID validation | Vehicle detail "What you will need"; trust section; Terms §2 |
| 9 | Payment methods | How it works → Payments & security |
| 10, 12 | Scheduling & booking validation | Booking panel date rules; How it works → Scheduling |
| 11 | Payment validation | How it works → Payments & security; Terms §4 |
| 13 | Terms & conditions | `/terms` |
| 14 | Rental contract | Vehicle detail; Terms §5 |

The natural next step is scaffolding the domain — `Vehicle`, `Booking`, `Payment`, and owner/renter roles — then pointing the browse and detail pages at Eloquent instead of `config/demo.php`.

---

## Research team

**Researchers** — Jayrald, Christian Nemaria, Hannah Claire
