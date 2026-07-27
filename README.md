# Azowar — Umrah Operations Manager

A Laravel + Livewire application for running an Umrah operator's business end to end:
operating orders, groups & pilgrims, hotels, transport, catalogs, and the money that ties
it together. See [`docs/PLAN.md`](docs/PLAN.md) for the full architecture writeup and the
reasoning behind each design decision — this file is the practical "get it running and
find your way around" guide.

## Setup

First time on a machine:

```bash
composer install
cp .env.example .env      # or: composer run setup, which does this + key:generate + migrate + npm build
php artisan key:generate
php artisan migrate --seed
npm install
npm run build              # or `npm run dev` for a watching dev build
php artisan serve
```

Pulling code that added new `.env.example` keys (a new OAuth provider, a new feature flag,
etc.) later on:

```bash
php artisan env:sync
```

This copies any `KEY=value` line that's in `.env.example` but missing from your own `.env`,
**without ever touching a value you've already set** — so you never have to hand-diff the
two files to see what's new. A few things worth knowing:

- **Existing keys are left alone**, even if `.env.example`'s value differs (e.g. your real
  `DB_CONNECTION=mysql` next to the example's `sqlite` — yours wins, untouched).
- **A commented-out key in `.env`** (`#GOOGLE_CLIENT_ID=...`) is treated as *not set* — you'll
  get a fresh active line from the example. If you deliberately disabled something by
  commenting it out, re-comment it after running the command.
- **New keys are appended** at the bottom of `.env`, under a dated comment
  (`# Added by \`php artisan env:sync\` from .env.example — 2026-07-28 ...`) so it's obvious
  what changed and when — diff or search for that marker if you want to review or undo it.
- **If `.env` doesn't exist at all**, it's created from `.env.example` wholesale (same as the
  first-time setup step above).

Preview what would change without writing anything:

```bash
php artisan env:sync --dry-run
```

Options, if you ever need non-default paths (mainly useful for testing):

```bash
php artisan env:sync --example=.env.staging.example --target=.env.staging
```

## Test users

Seeded by `database/seeders/UmrahDemoSeeder.php` and `TestUmrahSeeder.php` — every account
below uses the password `password`, except the platform admin.

| Email | Password | Belongs to |
| --- | --- | --- |
| `info@computerize.biz` | `.env`'s `ADMIN_PASSWORD` | Platform admin — no organization |
| `ops.admin@alrahma.test` | `password` | Al-Rahma Umrah Services — Admin |
| `ops.supervisor@alrahma.test` | `password` | Al-Rahma — Supervisor |
| `ops.entry@alrahma.test` | `password` | Al-Rahma — Data Entry |
| `ops.accountant@alrahma.test` | `password` | Al-Rahma — Accountant |
| `agent@sudan-travel.test` | `password` | Sudan Travel Agency — Agent |
| `owner@test-umrah.test` | `password` | Test Umrah — Owner |
| `agent@test-umrah.test` | `password` | Test Agent — Agent |

## System structure

### Identity vs. organization

A `User` is just identity — name, email, password/OAuth, locale, and an auto-generated
**onboarding code**. A user has **no permissions of their own**; every ability comes from
membership in an `Organization`, via `organization_user` (user + org + role).

### The platform admin — deliberately separate from everything else

A single fixed email (`config('app.admin_email')`, checked via `User::isAdmin()`). Not an
organization, not a membership, not a role — just an email comparison. Its only powers:

- Create a new Umrah company (`Organization` of type `Operator`), handing it to a founding
  owner by their onboarding code.
- Add an already-registered user to *any existing* company by onboarding code, picking their
  role.
- Set or clear a company's subscription renewal date, and toggle it active/deactivated.
- View every company's member roster, read-only.

Everything operational inside a company — pilgrims, orders, hotels, movements, the ledger —
is entirely that company's own business, never the admin's.

### Organizations — the real actors

| Type | What it is |
| --- | --- |
| `Operator` | An Umrah company — the tenant everything else revolves around |
| `Agent` | Sends pilgrims to one or more operators; can nest under a parent agent (sub-agents) |
| `Hotel` | Supplies room-nights to an operator |
| `TransportSupplier` | Supplies vehicle capacity to an operator |
| `Platform` | Defined in the enum, never actually instantiated anywhere in the app |

**There is no self-service organization creation.** The only two ways an organization comes
into existence:

1. The platform admin creates an `Operator`.
2. An existing organization's manager (Owner/Admin) enters *someone else's* onboarding
   code, picks the type, and that person — not the manager — becomes its owner
   (`pages::organizations.index`, `bringInOrganization()`).

A user with no organization at all sees a minimal sidebar (just Dashboard) and their own
onboarding code front and center, ready to hand to whoever should pull them in.

### Roles within an organization

`OrganizationRole`: `Owner`, `Admin`, `DataEntry`, `Supervisor`, `Accountant`, `AgentUser` —
few, fixed, checked directly rather than through a permissions package. Which roles make
sense depends on the organization's type (`OrganizationRole::availableFor()`) — e.g.
`AgentUser` only means something inside an `Agent` organization.

### The operating order

The central document (أمر التشغيل) tying an operator, an agent, a group, and — through
order lines — hotels and transport together. Hotel stays and movements can each exist with
no operating order behind them at all, which is how an agent's own hotel booking (something
the operator sold nothing for) still gets tracked.

### Money

Order lines carry purchase/sale price in minor currency units; a ledger computes an
organization's balance with a counterparty by summing entries — never a stored running
total, so it can't drift out of sync.

For the reasoning behind every one of these decisions, and the parts still open
(agent ↔ operator multi-tenancy, gender-mixed room sharing, Nusuk/Masar integration, …),
see [`docs/PLAN.md`](docs/PLAN.md).

## Running the test suite

```bash
php artisan test
vendor/bin/pint --test        # code style
vendor/bin/phpstan analyse    # static analysis
```
