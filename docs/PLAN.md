# Azowar — Umrah Operations SaaS: Domain & Architecture Plan

## Status (2026-07-25): core domain built, then audited

The sections below are the original architecture plan and remain the reasoning behind
the schema. This block records what's actually in the codebase now, cross-checked
against `uosProfileV2.pdf` — a product profile for an existing Umrah operations system
("UOS") — and a round of research into how Umrah operators actually run (Nusuk/Masar,
hotel bed-bank conventions, mahram/gender sharing rules). A full recheck pass then went
through configuration, schema, authorization, query efficiency and test coverage — see
"What the recheck found and fixed" below.

**Built and tested** (183 passing tests, clean PHPStan, clean Pint):

- **Tenancy** — `organizations` (operator/agent/hotel/transport_supplier/platform),
  `organization_user` membership with roles, a typed pivot model, and a
  `current_organization_id` context switch on the user, matching §1–2 below.
- **The operating order** — confirmed as the central document by the reference vendor
  profile, which calls it أمر التشغيل ("operating order") and describes almost the
  exact same shape: it ties the operator, the agent/client, the group, and (through
  order lines) hotels and transport together, and everything else hangs off it. See §3.
- **Groups & pilgrims**, including the mahram/family link used for room sharing (§4).
- **Movements** — arrival/internal/ziyarat/permit/departure legs, a vehicle
  assignment, and a manifest of named pilgrims (§5).
- **Hotels** — room types (bed capacity), contracts (allotment/free-sale/on-request)
  with a release deadline, seasonal per-bed-per-night rates, and **hotel stays that
  can exist with no operating order behind them** — the mechanism that represents an
  agent bringing its own hotel booking (§3, §4).
- **Vehicles & the "other services" catalogue** — operator-defined vehicle types,
  an owned-or-supplier fleet, and an extensible services table (§5, §6).
- **Money** — order lines carrying purchase/sale price in minor units, and a ledger
  that computes an organization's balance with a counterparty by summing entries,
  never by storing a running total (§7).
- **Policies** — `OperatingOrderPolicy` enforces the one rule that matters most: the
  operator sees every order it operates, an agent sees only what it sent in.
- A seeded, coherent demo scenario (one operator, two agents plus a sub-agent, a
  hotel per city, a fleet, a family group, one fully-worked operating order with full
  arrival/departure logistics) and twelve Livewire screens wired into the sidebar
  (dashboard, organizations, groups, operating orders, movements, hotels, vehicles,
  the services catalogue, and — added in the reconnaissance pass below — geography,
  movement types, and permit types).

## What the recheck found and fixed

A dedicated audit pass (config, schema, authorization, queries, tests) turned up six
real cross-tenant IDOR gaps and closed them, plus a handful of smaller issues:

- **Six unscoped foreign-key inputs let one operator link to another's data.** The
  worst: `operating-orders/create`'s `groupId` field validated only that a group
  *existed somewhere*, not that it belonged to the acting operator — a tampered
  request could attach another operator's group and leak its pilgrims' **names and
  passport numbers** onto the first operator's own order screen. A near-identical bug
  let a movement reference another operator's vehicle, leaking a driver's phone
  number. All six (operating order ↔ group/agent, movement ↔ vehicle, pilgrim ↔
  mahram link, hotel contract ↔ room type, vehicle ↔ vehicle type/supplier) are fixed
  with `Rule::exists(...)->where(...)` scoped to the current organization, and each
  has a regression test in `CrossTenantScopingTest.php` that attempts the exact
  cross-link and asserts it is rejected.
- **Every foreign key lacking an index got one** (23 gaps across the new tables) —
  found by walking `PRAGMA foreign_key_list` against `PRAGMA index_list` for every
  table. Harmless at seed-data scale; would have meant full table scans as the
  tables grew.
- **The services catalogue had no management screen.** The `services` table and
  model existed and the seeder populated one row, but there was no way to create or
  deactivate a service through the app — a real functional gap in the "other
  services" extensibility story from §6. Added `pages::services.index`.
- **`database/database.sqlite` was not gitignored** — the seeded database (containing
  real-shaped passport numbers, phone numbers, and admin context) would have been
  committed to version control the moment this project became a git repo.
- Query efficiency was checked directly (query-counting every list/show page against
  seeded data) rather than assumed from code review — all clean, no N+1s.
- Two dead files were removed rather than left in place: an unreferenced alternate
  app layout, and a validation call whose exception didn't convert to an HTTP status
  the way `abort()` does under Livewire's test harness (found by a test that
  initially failed for the wrong reason).

## UI modernization, admin naming, and Arabic/RTL support

- **Visual refresh.** Introduced four shared components (`x-ui.card`, `x-ui.stat`,
  `x-ui.empty-state`, `x-ui.page-header`) and applied them across all nine screens plus
  the dashboard, so tables sit in consistent rounded cards, empty states carry an icon
  instead of bare grey text, and stat tiles get a coloured icon badge. The sidebar
  gained a border/background treatment and — a genuine functional addition, not just
  polish — an "acting as" organization pill under the logo, so a user with several
  memberships always sees which one is live without opening the Organizations page.
- **"Super Admin" → "Admin"** in the seeder's default display name and in the one test
  method name that referenced it. `config('app.admin_email')` / `User::isAdmin()` are
  unchanged — this was a display-name change only, not a role/permission change.
- **Arabic + RTL, built as real infrastructure, not a translation pass bolted onto
  English:**
  - `users.locale` (nullable — null means "use the app default"), a language settings
    screen (`pages::settings.language`) reachable from the settings nav, and a
    `SetLocale` middleware applied globally so every request (not just Livewire pages)
    honours the signed-in user's preference. Switching language forces a full page
    reload (`navigate: false`) because `<html lang/dir>` and every server-rendered
    string need to change together — a Livewire SPA hop wouldn't refresh either.
  - `lang/ar.json` covers **all 345 distinct strings** the app actually passes through
    `__()`, extracted programmatically (not by hand) and cross-checked to zero missing
    keys after every subsequent change. This included a real gap the extraction caught:
    every enum's `label()` method (`OperatingOrderStatus`, `MovementType`, `Gender`, …)
    was returning a raw English literal with no `__()` call at all, so every status
    badge across the app would have silently stayed in English regardless of locale —
    fixed in all twelve enums.
  - `dir="rtl"`/`dir="ltr"` is driven by `config('azowar.locales.{locale}.rtl')` on both
    layouts. An RTL audit found zero hardcoded physical-direction Tailwind classes
    (`ml-`, `pr-`, `text-left`, …) anywhere in the app — it already used logical
    properties throughout — but did find three `space-x-N` utilities missing their
    `rtl:space-x-reverse` companion (Tailwind's `space-x` is physical, not logical,
    unless paired), which are now fixed.
  - The browser tab `<title>` is a second, separate translation path: it comes from
    Livewire's `#[Title('Dashboard')]` PHP attribute, whose argument **must be a
    compile-time constant** — it cannot call `__()` directly. Translating it required
    wrapping the *rendered* `$title` variable in `__()` once, in
    `partials/head.blade.php`, rather than touching the attribute on every page.
  - Verified past the test suite: logged in as the same seeded admin with locale set to
    `ar` and separately to `en`, and fetched all 13 authenticated routes for each,
    confirming both the `dir` attribute and genuine Arabic page content (not just the
    attribute) on every single one.

**Deliberately not built yet** — real gaps, not oversights, listed here so they aren't
mistaken for finished work:

- **Nusuk/Masar integration.** Research turned up something the original plan
  under-weighted: as of the current Umrah season, **Saudi Arabia requires all Umrah
  hotel bookings to be made through the government Nusuk platform**, and only
  licensed operators (MISA + CR + Chamber of Commerce + ZATCA) may transact at all.
  This was treated as an optional future integration (§10); it should be treated as a
  compliance requirement instead. The reference vendor profile only claims Excel
  import of Nusuk's own group/pilgrim files, not a live API — so a real integration
  may not even be available to build against yet. Needs a decision, not more code.
- **Bed-level, gender-aware, concurrency-safe availability.** The schema is shaped
  for it (`room_types.capacity`, `contract_allotments`, `hotel_stays.gender`), but no
  code yet computes "how many beds are actually sellable tonight, accounting for who
  already occupies each room." Selling today just creates rows — nothing stops two
  staff from overselling the same contract simultaneously. This is the single most
  important piece of unbuilt work; see §4 and §7.
- **Holds/TTL, cancellation, amendment.** No quote-hold mechanism, no
  cancellation-with-penalty flow.
- **Agent self-service portal, PDF/WhatsApp export, quick search, virtual program
  templates.** All named directly in the reference vendor profile as differentiators;
  none exist here yet.
- **Multi-currency conversion.** Money is minor-units-plus-currency-code (correct
  shape), but nothing converts between currencies — every seeded amount is SAR.

## Live competitor reconnaissance, and the nine gaps it found

Logged into a live licensed instance of the reference UOS product (the same one
`uosProfileV2.pdf` describes) and compared its actual screens against this build,
feature by feature. It found nine concrete gaps — all closed in this pass, backed by
137 passing tests (up from 106), clean PHPStan, clean Pint:

1. **Pilgrim counts by age band.** `operating_orders` gained nullable
   `adults_count`/`children_count`/`infants_count` — entered manually, since a real
   order is often opened before individual pilgrims exist. `App\Enums\AgeBand` (with
   `AgeBand::forAge()`) gives the computed equivalent once a pilgrim's date of birth is
   on file — both coexist rather than one replacing the other; see `Pilgrim::ageBand()`
   and `OperatingOrder::totalPilgrimCount()`.
2. **Structured arrival *and* departure logistics.** The old `travel_mode` free-text
   field covered arrival only. Replaced with a full block on each end: trip mode
   (air/land/sea — `App\Enums\TripMode`), city, port, carrier, flight/trip number,
   date, and time — twelve columns in
   `2026_07_25_110700_add_logistics_to_operating_orders_table.php`.
3. **A structured geography catalog**, replacing free-text location strings. One
   `cities` table plus one polymorphic `locations` table (`App\Enums\LocationType`:
   air/land/sea port, train station, ziyarat site, stopover point, tourist
   destination) — deliberately one table with a type column rather than six separate
   ones, per the "easy and coherent" brief. `LocationType::portsFor(TripMode)` filters
   the port choice once a trip mode is picked.
4. **Movement types became an operator-defined catalog** (`App\Models\MovementType`),
   matching the existing `VehicleType`/`Service` pattern, instead of a fixed PHP enum.
   The old `App\Enums\MovementType` is gone; `App\Enums\MovementCategory` is the fixed
   "kind" tag (arrival/internal/ziyarat/permit/departure) each catalog entry still
   carries, so reporting can keep asking "is this an arrival leg" without caring about
   the operator's own naming.
5. **Supplier reference numbers** — a BRN-equivalent field
   (`hotel_stays.supplier_reference_no`) and a trCoNo-equivalent field
   (`movements.supplier_reference_no`), both nullable, both surfaced on the operating
   order's Movements/Hotel Stays tables.
6. **Transport source** (`movements.source`, `App\Enums\MovementSource`:
   operator-provided vs agent-provided) mirrors the existing `HotelStay::$source`
   pattern exactly — the same operator-sold-vs-agent-brought distinction now applies
   to transport, not just hotels.
7. **Sub-agent hierarchy.** `organizations.parent_organization_id` is a nullable
   self-reference (`Organization::parent()`, `::subAgents()`, `::isSubAgentOf()`).
   An agent can be a sub-agent of another agent; the "Start a new organization" and
   operating-order agent pickers both surface the relationship.
8. **Bilingual names** (`name_ar`/`name_en` as real stored columns) on organizations
   and hotels, via a shared `App\Concerns\HasBilingualName` trait: a computed `name`
   accessor picks the current locale's version (falling back to whichever is filled),
   and a matching mutator writes both columns from a single `name` assignment — so
   every existing `->name` read and every `Model::factory()->create(['name' => 'X'])`
   call site kept working unchanged. New create forms (hotels, organizations,
   cities, locations, permit types, movement types) collect both languages directly.
9. **Hotel archive/restore and a confirmation workflow.** `Hotel` gained
   `SoftDeletes` (archive is a soft delete, not a hard one, and is restorable from a
   "Show archived" toggle on the Hotels screen) plus `confirmed_at`/
   `confirmed_by_user_id` (`Hotel::confirm()`/`unconfirm()`/`isConfirmed()`), gated to
   users who manage the organization. A bulk-add textarea (one hotel per pasted line:
   Arabic name, English name, city code, optional stars) covers the last piece —
   onboarding a whole contract's worth of hotels without one form submission each.

Three new catalog screens (Geography, Movement Types, Permit Types) joined the
sidebar under a new "Catalog" group; every new foreign-key input introduced above
(movement type, city, port location, sub-agent parent) is scoped to the acting
organization with `Rule::exists(...)->where(...)`, the same pattern the original audit
established, each with its own `CrossTenantScopingTest` case. `lang/ar.json` grew from
345 to 419 keys — the 73 new ones covering every string this phase's screens and enum
labels introduced, verified by the same programmatic extraction used originally.

## Platform administration, catalog-editing safety rails, and Excel pilgrim import

- **Umrah companies are now admin-provisioned, not self-service.** `OrganizationType::Operator`
  was pulled out of the public "start a new organization" flow (self-service still works for
  Agent/Hotel/TransportSupplier); only the platform admin (`User::isAdmin()`, previously
  unused anywhere in the app) can create one, from a new `/admin/umrah-companies` panel. The
  "approval" *is* the admin entering the founding owner's onboarding code during creation —
  no separate request/approve workflow. The admin is deliberately never a member of any
  company (removed from the demo seeder's membership grant); a custom Fortify `LoginResponse`
  binding (covering password, two-factor, *and* the OAuth callback, which previously hardcoded
  its own `/dashboard` redirect and would have silently stranded an admin) sends them to their
  own panel instead of an empty "you don't belong to an organization" dashboard.
- **Every new company gets a starter catalog**, not an empty set of screens:
  `App\Support\OperatorDefaults::seed()` creates default vehicle types, a Makkah/Madinah/Jeddah
  geography catalog, an Arrival/Internal/Ziyarat/Departure movement-type set, two permit types,
  three services, and two starter hotels (one room type each) — run automatically on company
  creation, and separately exercised end-to-end by `TestUmrahSeeder` ("Test Umrah"), which
  builds a full order using only what the defaults provided, proving they're actually enough
  to operate on day one.
- **Subscriptions**: `organizations.subscription_expires_at`, admin-settable per company from
  the same panel, paired with the existing `is_active` toggle. Deliberately informational, not
  enforced — an expired subscription is a banner/flag the admin sees, not an automatic lockout.
- **Every catalog is now editable, and deletable with a real safety gate.** Services, Vehicle
  Types, Vehicles, Movement Types, Permit Types, Cities, and Locations all gained an edit
  action, plus a shared `App\Livewire\Concerns\GuardsDestructiveDeletes` trait: deleting a row
  nothing else references is a plain confirm; deleting one other records still point at (a
  vehicle type with vehicles on it, a city named on a live operating order, …) additionally
  requires solving a small arithmetic check before the delete proceeds, and a DB-level
  rejection (e.g. `movements.movement_type_id` is RESTRICT, not SET NULL) is caught and shown
  as a message instead of a crash. A real bug this reasoning caught: calling the delete action
  without ever having opened the confirm step threw an uncaught `TypeError` — fixed with a
  null-guard, now covered by a regression test.
- **Excel pilgrim import**, reverse-engineered against a real agent export
  (`mutamers_list_export_*.xlsx` — 13 columns: name, age, passport, nationality, main/sub
  external-agent code and name, visa/biometric status, visa number, MOFA number, booking
  type). `phpoffice/phpspreadsheet` parses `.xlsx/.xls/.csv`; a four-step wizard
  (`pages::groups.import`) uploads → maps each source column to a target field (best-guessed
  from the header text, e.g. "Sub EA Code" → Sub-Agent Code) → reviews every row with inline
  fixes for anything invalid (most commonly gender, since real exports often omit it
  entirely — deliberately *not* made nullable in the schema given room-sharing depends on it
  being correct, so a missing value blocks that one row until picked manually, rather than
  relaxing the constraint) → commits. `PilgrimImportMapping` remembers the mapping per operator
  keyed by a hash of the sorted column headers, so a second upload from the same source system
  skips straight to review. An unparseable file (corrupt, blank, headers with no data under
  them) surfaces as a modal with a specific message, not a stack trace. The agent/sub-agent
  columns find-or-create the corresponding `Organization` via a new `external_code` column,
  scoped to "an agent this operator has already dealt with" rather than a global unique
  constraint, since the same numeric code from two different operators' own systems could mean
  two different agencies.
- **An agent can finally search its own information.** Previously an agent-type organization
  had no screen at all for its own pilgrims (`Groups & Pilgrims` was operator-only); a new
  `My Pilgrims` screen (agent-only, mirrored in the sidebar the same way the admin panel
  replaces the normal nav) searches by name/passport/nationality across every operator the
  agent works with, scoped to `pilgrims.agent_id`.
- **New Pilgrim fields** a real export carries that had no home before: `visa_status`,
  `visa_number`, `biometric_status`, `mofa_number`, `booking_type` — all nullable, since none
  of them are required for a usable pilgrim record.
- **Migrations consolidated**: the 43 files accumulated since the project's start (many of
  them small incremental `add_X_to_Y` alterations from this same session) were reduced to 7,
  grouped by domain (framework/auth, tenancy, catalogs, groups & pilgrims, supply, operations,
  financial). Done by dumping the actual live schema's `CREATE TABLE`/`CREATE INDEX`
  statements straight from SQLite's `sqlite_master` (no `sqlite3` CLI binary needed, unlike
  `artisan schema:dump`) and regrouping them — verified byte-identical by running the full
  test suite, plus a rollback/re-migrate round-trip, against the consolidated set before
  deleting the originals. **Later split again into one file per table** (35 files) at explicit
  request — same dump-and-regroup technique, one dependency-ordered file per table instead of
  per domain, re-verified byte-identical (111 CREATE statements, same set) plus another
  rollback/re-migrate round-trip. Both approaches are legitimate; this repo currently uses
  one-file-per-table.

---

## Onboarding-code-only org creation, admin add-to-existing-company, and the `env:sync` command

- **Self-service organization creation is gone entirely — this supersedes the "self-service
  still works for Agent/Hotel/TransportSupplier" line above.** There is no longer any path
  where a signed-in user can spontaneously create their own Agent/Hotel/TransportSupplier
  organization. The only way a non-Umrah-company organization comes into existence now: an
  existing organization's manager (Owner/Admin) enters *someone else's* onboarding code on
  `pages::organizations.index` (`bringInOrganization()`), picks the type, and that person —
  not the manager — becomes its owner. Sub-agent nesting (`parent_organization_id`) still
  works exactly as before, just gated the same way. Umrah companies (`Operator`) are still
  only ever created by the platform admin.
- **The platform admin can now add a user to an *existing* company**, not just create new
  ones — `pages::admin.umrah-companies.show` gained an `addMemberByCode()` method mirroring
  the one on the organizations page, scoped to `OrganizationRole::availableFor(Operator)`.
  Removing or re-assigning an existing member's role is still the company's own job.
- **A user with no organization** now sees a minimal sidebar (Dashboard only — Operating
  Orders, Daily Movements, Groups & Pilgrims, and now Organizations too are hidden until they
  belong to something) and the dashboard's empty state shows their own onboarding code
  (copy-to-clipboard) with instructions, replacing a vaguer "create one or ask to be added"
  message that never actually showed the code.
- **`php artisan env:sync`** (`App\Console\Commands\SyncEnvExample`): copies any `KEY=value`
  line present in `.env.example` but absent from `.env`, appended under a dated comment
  header — existing values in `.env` are never touched, and a commented-out key in `.env`
  is treated as absent (so it gets a fresh active line from the example). If `.env` doesn't
  exist at all, it's created from `.env.example` wholesale. `--dry-run` previews without
  writing; `--example=` / `--target=` override the default paths for testing or unusual
  layouts. See `README.md` for the day-to-day usage.

---

## Agent-side accounting: ticketing and other sales

Researched before building, per the request — three sources, each contributing a distinct
piece of the design:

1. **IATA BSP** (the industry-standard airline-ticket settlement model): an agent's ticket
   sale has *two independent cash flows* — what the customer owes the agent, and what the
   agent owes the airline/supplier (remitted net of commission, on its own cycle). This is
   why `AgentSalePayment` has a `direction` (from customer / to supplier) rather than a
   single running balance — either side can lag the other.
2. **Ezus** (a modern travel-agency management SaaS): per-sale margin tracking, invoicing
   with deposits, and supplier purchase tracking — confirmed the "purchase price vs sale
   price on one row gives a margin" shape already used by `OrderLine` is the right one to
   reuse here, rather than inventing a different model for agents.
3. **General travel-agency accounting guides** (chart-of-accounts breakdowns): flagged that
   customer deposits are properly a *liability* (held in trust) until a booking is finalized,
   not immediate revenue. Deliberately **not** implemented that way here — a sale is booked
   as revenue the moment it's recorded, same simplification level `OrderLine` already uses
   for operators. Noted as a scope decision, not an oversight, in `AgentSale`'s docblock.

**What was built**: `agent_sales` (one row per ticket or other sale — reference_no, type,
customer_name, status, purchase/sale price, supplier_reference) and `agent_sale_payments`
(direction + amount + date, summed rather than stored — same discipline as `ledger_entries`).
A new `Sales` screen (agent-only, alongside `My Pilgrims` in the sidebar) lists every sale
with running totals (sale/cost/margin/outstanding, excluding voided sales), and a detail page
per sale for recording payments from either side and changing status (issued → refunded/
voided). Any agent member can record a sale and receive customer payments; only Owner/Admin
can record a supplier payment or change a sale's status — the one place this introduces a
permission split narrower than "any member." Entirely independent of the operator
relationship: no `operating_order_id`, nothing scoped to any operator — this is the agent's
own business, the same way `LedgerEntry` is scoped to whichever organization's book it's in.

---

## 1. What this product actually is

Azowar is **SaaS sold to Umrah operators** (licensed Umrah companies in KSA). Each
operator is a tenant. Inside their tenancy they:

- hold or arrange **accommodation** in Jeddah, Makkah and Madinah,
- run **transport** between cities, airports and ziyarat sites,
- handle **visa** services,
- define **other services** as they see fit,

and then **sell those services to external agents** — travel agencies abroad who send
pilgrims.

The single most useful reframing: **this is not a consumer booking site. It is a B2B
supply-and-distribution system** — closer to a bed-bank / DMC platform than to
Booking.com. The operator is the *supplier*; the agent is the *channel*. Everything
downstream (contracted rates per agent, allotments, holds, vouchers, statements) follows
from that, and none of it makes sense in a consumer model.

**Tenant = the operator.** Agents are external parties who transact *with* a tenant. An
agent may work with several operators, so an agent account cannot live inside one
operator's silo — see §9.

---

## 2. Actors

| Party | Where | Role in the flow |
| --- | --- | --- |
| **Operator** (Umrah company) | KSA | Defines and sells services; delivers on the ground; legally accountable |
| **Agent** | Abroad | Sends pilgrims; buys services; may bring their *own* hotel booking |
| **Pilgrim** | Travels | Subject of the work, **not a user** |
| **Hotel** | Makkah / Madinah / Jeddah | Supplies rooms to the operator (contract, allotment or free-sale) |
| **Transport supplier** | KSA | Supplies vehicles; may be the operator's own fleet |
| **Platform** (Computerize) | — | Super admin, support |

People are **users**; the parties above are **organisations**; a user's powers come from
their *membership* in an organisation, never from a `users.type` column. (A person can
work for two agencies; permission is contextual, not intrinsic. This was the core
finding of the earlier draft and it still holds.)

---

## 3. The three intake paths — and why they force a key separation

You described three ways a booking arrives:

1. **Pre-booking** — the operator holds inventory in advance and sells it.
2. **Booking on arrival** — the pilgrim lands, the operator places them then and there.
3. **Agent-provided booking** — the agent already has their own hotel; the operator just
   services the rest.

Path 3 is the one that dictates the architecture. In that case the operator **sells no
room at all**, yet still must know *which hotel the pilgrim sleeps in and when they
land*, because transport pickup, visa paperwork and manifests all depend on it.

**Therefore: separate what was SOLD from what is being SERVICED.**

```
Sales side      : order lines  — what the agent bought from this operator (may be empty)
Operations side : stay records, movements — where each pilgrim actually is, whoever booked it
```

If you conflate these (the usual instinct: "accommodation = a booking line"), path 3
becomes impossible to represent and gets hacked in later as a fake zero-price booking.
Model the pilgrim's **itinerary** as first-class fact, independent of who sold it.

An **arrival intimation** from the agent (flight, airport, date/time, pax list, and
their own hotel if any) is the trigger that populates the operations side.

---

## 4. Hotel engine — the hard part is the *bed*, not the room

**Shared-room selling means the unit of inventory is a BED, not a room.** A quad room is
four beds that may be sold to four unrelated pilgrims — potentially from different
agents. Everything follows from this.

```
hotels → room_types (double / triple / quad / quint) → rooms (physical) → beds
```

The operator sells *"a bed in quad sharing, Makkah, 12–17 Ramadan"*, and the price per
person falls as the sharing size rises (a bed in a double costs more than a bed in a
quad). Price is therefore **per person, per night, per sharing type**.

### The subtlety that will bite if it is missed

**You cannot compute availability by simply counting free beds.** A shared room is
**gender-locked once occupied**: a quad with three men in it has one free bed that can be
sold *only to another man*. Free beds are not fungible.

So availability for a given night is not one number. It is:

```
empty rooms            → beds sellable to either gender
male-occupied rooms    → free beds sellable to men only
female-occupied rooms  → free beds sellable to women only
```

A naive `SUM(free_beds)` will happily oversell, and the failure only surfaces at the
front desk in Makkah at 2am. **Availability must be computed per room, per night, with
the room's gender state as part of the answer** — and family/mahram rooms are an explicit
exception (a family may share a room across genders).

### Two-stage allocation

Do not assign a named pilgrim to a specific physical bed at sale time. Selling and
rooming are different jobs, done at different times, by different people:

1. **Sell** — decrement *capacity* for (hotel, room_type, night). Fast, concurrent, no names.
2. **Room** — nearer arrival, assign named pilgrims to physical rooms/beds, honouring
   gender, family/mahram grouping, and keeping each agent's group together where possible.

Conflating them means every sale needs a full rooming decision, and every rooming change
looks like a re-sale.

### Where the beds come from

The operator's own inventory is itself contracted from hotels, in three familiar shapes,
and the model must express all of them:

- **Allotment** — a block of rooms held for a period, with a **release deadline** (unsold
  rooms go back to the hotel). This is the common case and the deadline is a real,
  money-losing date.
- **Free-sale** — sell freely up to a ceiling.
- **On-request** — must confirm with the hotel per booking.

**Seasonality is not optional here.** Ramadan and school-holiday rates are multiples of
low-season rates. Rates and allotments are **date-ranged**, always.

---

## 5. Transport engine

Vehicle **types are operator-defined** (49-seat bus, Hiace, GMC, sedan, …) — a config
table, not an enum in code.

Transport sells in **two fundamentally different modes**, and both are needed:

- **Per-seat** — shared transfer/shuttle; inventory is seats on a scheduled movement.
- **Per-vehicle (charter)** — the agent's group takes the whole bus; inventory is the vehicle.

The sellable/operational unit is a **movement** (one vehicle, one route leg, one
departure time):

```
Jeddah airport → Makkah hotel      (arrival transfer)
Makkah → Madinah                   (inter-city)
Madinah → Madinah airport          (departure transfer)
Makkah/Madinah ziyarat circuits    (tours)
```

A movement carries: vehicle (or vehicle type until assigned), driver, capacity, seats
sold, pickup/drop points, and its **manifest**. Note the operator must be able to put
pilgrims on a movement **even when it sold them no room** (intake path 3) — which is
exactly why §3 separates sales from operations.

Capacity is a hard constraint (a 49-seat bus is 49 seats) and, like beds, is subject to
concurrent sale — see §7.

---

## 6. "Other services" — make the catalogue extensible from day one

You explicitly want more service types later (visa, ziyarat, catering, laundry, SIM,
guide, insurance). If each new one needs a migration and a new booking table, the product
calcifies.

**Recommendation:** one **service catalogue**, owned by the operator:

```
services            (operator_id, type, name, active, …)   ← what the operator sells
service_variants    (sharing type / vehicle type / visa class …) + pricing rules
order_lines         (polymorphic → the thing bought)
```

Hotel accommodation and transport are then simply the **two service types that carry a
scarce, date-bounded inventory** and therefore need real availability engines. Visa,
catering and the rest are mostly *unconstrained* services — sold per head with a
workflow, no capacity maths.

That distinction — **capacity-constrained vs unconstrained** — is the right axis on which
to split the code. Build one inventory abstraction (`Allocatable`: has capacity per date,
supports hold/confirm/release) and let hotels and transport implement it. New
unconstrained services then cost almost nothing to add.

---

## 7. The engine concerns that decide whether this works

These are the things that separate a real booking engine from a CRUD app. They must be
designed in, not bolted on:

- **Overselling.** Two agents buying the last bed simultaneously must not both succeed.
  Inventory decrements need atomic, DB-level guarantees (row locks / conditional updates
  with a capacity check), not read-then-write in PHP. This is *the* classic failure of
  home-grown booking systems.
- **Holds / TTL.** An agent assembling a quote must be able to hold beds and seats for N
  minutes without paying. Holds expire and return to inventory automatically.
- **Cancellation & amendment.** Rooms release back to allotment; deadlines and penalties
  apply. Amendments (date shift, pax change, sharing change) are the *normal* case in
  Umrah, not the exception.
- **Date-ranged everything.** Rates, allotments, release deadlines. Never a single price
  column on a hotel.
- **Money.** Agent buys in their currency, operator accounts in SAR. Store **minor units +
  currency code**; record the FX rate on the transaction. Never a float.
- **Audit.** Who changed a rate, who moved a pilgrim, who saw a passport.

---

## 8. Data model sketch

```
Tenancy      operators, organizations (agent/hotel/transport), organization_user, users
Catalogue    services, service_variants, rate_plans (agent-specific), price_periods
Supply       hotels, room_types, rooms, beds
             hotel_contracts (allotment | free_sale | on_request), allotment_blocks, release_deadlines
             vehicle_types, vehicles, drivers
Distribution agent_contracts (operator ↔ agent), allotments_to_agent, credit_terms
Sales        quotes, bookings, order_lines (polymorphic), holds, vouchers
People       pilgrims (passport, nationality, gender, dob, mahram_of), groups
Operations   arrival_intimations, stays (pilgrim × hotel × nights — regardless of who booked)
             room_allocations (pilgrim → bed), movements, movement_pax, manifests
Money        invoices, payments, agent_statements, supplier_payables
Trust        audit_log
```

Two columns worth arguing about now:

- `bookings.agent_org_id` **and** `bookings.operator_id` — the pair that drives nearly
  every visibility rule.
- `stays` exists **even with no order line**, which is what makes intake path 3 work.

---

## 9. SaaS shape: can an agent work with two operators?

Almost certainly yes — an agency in Pakistan or Indonesia will deal with several Saudi
operators. That means:

- an **agent is a first-class organisation**, not a row inside one operator's tenancy;
- one agent login sees **all operators they are contracted with**, switching context;
- an **operator↔agent contract** carries the rates, allotments and credit terms *for that
  pair*.

If instead each operator keeps a private list of "their" agents, the same agency ends up
with N logins and N passwords, and cross-operator work becomes impossible. **This is the
decision that is cheapest to make now and most expensive to reverse**, so it needs a
firm answer before phase 1 ships.

---

## 10. Build order

1. **Tenancy & access** — operators, organisations, memberships, roles, policies, audit.
2. **Supply setup** — hotels, room types, rooms/beds; vehicle types and vehicles;
   contracts, allotments, seasonal rates.
3. **Catalogue & agent contracts** — services, variants, agent-specific rate plans.
4. **Inventory core** — the `Allocatable` abstraction: availability, holds, atomic
   confirm, release. *This is the heart of the product; get it right before any UI.*
5. **Hotel booking engine** — search → hold → book, bed-level with the gender rule.
6. **Transport booking engine** — per-seat and charter movements.
7. **Agent portal** — arrival intimations, booking, own-hotel path, vouchers.
8. **Operations** — rooming lists, manifests, check-in; narrow mobile screens for ground staff.
9. **Money** — invoices, statements, payables.
10. **Visa & other services** — riding on the unconstrained-service pattern.

---

## 11. Open questions

1. **Agent ↔ operator: many-to-many?** (§9) — blocks the schema.
2. **Do agents ever see each other?** Shared rooms mean an agent's pilgrim may share a
   quad with another agent's pilgrim. Is that visible, or must it be opaque?
3. **Gender-mixed sharing:** confirm the rule — same-gender only, except family/mahram?
4. **Does the operator resell to sub-agents** (agent → sub-agent → pilgrim)?
5. **Own fleet vs contracted transport** — both, or one?
6. **Nusuk / Masar:** integrate, or run parallel for now?
7. **Payment:** does Azowar handle money movement, or only record invoices/statements?
