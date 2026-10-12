# Stockly

A small stock-control app for a single shop. Everything is recorded as a
movement — stock is never typed in by hand, it is what the ledger says it is.

- **Buy** and **Sell** screens that record purchase and sale lines.
- **Stock** showing on-hand quantity, cost and value per item.
- **Items** and **Units** catalogues.
- **Reports** for week, month, quarter and year.

Laravel 12, PHP 8.2, SQLite. No Node build step: Blade, vendored Alpine.js and
one stylesheet.

## Getting started

```bash
composer install
cp .env.example .env          # if you do not already have one
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

`--seed` loads the 93-unit catalogue, a demo ledger, and a `test@example.com`
/ `password` account for looking around.

Create a real account with:

```bash
php artisan stockly:user --name="Ada" --email=ada@example.com
```

Omit `--password` and it will prompt for it twice. There is no signup page on
purpose: the ledger is shared, so anyone who can sign in can edit every item.

## Not destroying your database

If `.env` points at MySQL, the commands that drop every table refuse to run:

```
$ php artisan migrate:fresh
RuntimeException
migrate:fresh was not run: it would drop every table in the 'mysql' database.
```

That is deliberate. A reset is exactly right against a throwaway SQLite file and
exactly wrong against the database you are building on, so Stockly asks you to say
which one you meant rather than assuming. To wipe MySQL on purpose:

```bash
STOCKLY_ALLOW_DB_RESET=1 php artisan migrate:fresh --seed
```

To get a clean database to look at **without** touching MySQL at all:

```bash
composer demo
```

That builds a scratch SQLite file under `storage/`, seeds it, and serves it on
`http://127.0.0.1:8000`. Every connection variable is exported explicitly, so it
overrides `.env` entirely — you can freely record purchases, break things, or reset
again, because none of it can reach your development data. Press Ctrl-C when done;
the file is disposable and gitignored.

Prefer plain `php artisan serve`? That uses your `.env`, i.e. the real database.

## Tests

```bash
composer test
```

`phpunit.xml` pins the suite to `DB_CONNECTION=sqlite` with `:memory:`, so the
tests cannot connect to MySQL whatever `.env` says. Each test builds its own data
on a throwaway in-memory database and never touches `database/database.sqlite`.

Nine focused files, kept to one rule each: ledger maths, the buy/sell flow end to
end (including the transaction rollback), what a pack makes a fraction, reports, the
catalogue rules, the database reset guard, login, that every page renders, and that
the views and the Alpine component agree on names. Where several tests were only
reaching for the same rule from different angles, they are now one test that walks
the whole rule.

That last one exists because of its own bug. The Measurement dropdown rendered empty
on both record tabs with a green suite: the view iterated a name the component never
declared, so `x-for` iterated undefined. Checking the payload proves the server sent
a key; checking the view renders proves the blade compiled. Neither can see that a
template asks for something the browser cannot resolve — which is why the pair of
tests now checks both ends.

Two ideas carry the whole app.

**Stock is derived, never stored.** A purchase line adds, a sale line subtracts.
On-hand quantity is the sum over the ledger. If a correction leaves an item
negative, it shows as `OUT` and is valued at nothing rather than as a negative
number.

**Quantities are normalised to a base unit, then reported in the unit you trade
in.** A bag of rice is 50 kg underneath, but a trader thinks in bags, so the
stock page shows `4 bags` even though the ledger is counting kilograms.

## What a carton is made of

Some things are counted, some are weighed, and some arrive part-used. A carton of
sachets is three kinds of quantity at once: the carton you buy by, the sachet you
break open, and the loose one you sell by the piece. So a purchase line may say
what is inside one:

> 1 carton = 12 rolls &nbsp;&nbsp; 1 carton = 24 sachets

That single number is what makes a fraction honest. Half a carton is accepted
because it is exactly 6 rolls; two fifths of a carton is refused, because it is
4.8 rolls and there is no such thing as 0.8 of a sachet. The sale form then offers
rolls and sachets directly, and the stock page reads `29.5 cartons (354 rolls, 708
sachets)`.

Three rules keep this from turning into guesswork:

- **The declaration is made on the receipt, not the item.** Stock only ever becomes
  what it becomes by being received, so that is where the count belongs. The most
  recent receipt declaring an item sets the units on offer; earlier ones are kept
  as history, not silently rewritten.
- **An inner unit has to belong to the same family as the item.** A carton cannot
  hold kilograms, and a carton cannot hold cartons.
- **Stock stays in base units.** Declaring packs never reinterprets what is already
  recorded, so no valuation, average or past report moves. It only lets the
  arithmetic be honest about a half.

## Prices

Both tabs state a **price per unit**. Line totals and transaction totals are worked
out by the server and are never trusted from the browser. That is what lets the
form add up correctly as you build it, and what keeps a mistyped total from
becoming a permanent record.

## Choosing a measurement, then a unit

With 90-odd units in the catalogue, one long dropdown is a poor way to find `kg`.
Every form that asks for a unit therefore asks in two steps: pick the measurement
(count, weight, volume, length, area), then the unit inside it.

- **On the record forms** the measurement is pinned and greyed out as soon as an
  item is chosen, because an item's family is fixed and no other measurement would
  be accepted. It stays live while you are naming something new, and the unit you
  choose is what gives that new item its family.
- **The inner-unit boxes stay single-step.** They are already limited to the item's
  measurement — an item cannot be counted in another one — so a second box there
  would offer a choice that cannot change anything.

**An item's family is not stored from the form.** It is read off the unit the item
is traded in, which closes a gap where `family = weight` and `unit = carton` could
be submitted together and quietly disagree. The measurement box on the item form is
a filter, not a source of truth: it carries no `name`, and the family always comes
from the posted unit. It also means the unit box still works with JavaScript off,
since the server-rendered optgroups are already grouped.

## Deleting asks first

Deleting a ledger line is a one-click button next to a figure the trader just
entered, and it moves stock. It confirms through a single `<dialog>` included once
by the layout, rather than the browser's own `confirm()` box.

The dialog is native on purpose: focus trapping, Esc to cancel, an inert page behind
it and focus returned to the button all come from the platform, so there is no
dependency and nothing to keep in sync. The submit is *held* rather than cancelled,
and replayed on accept — which deliberately bypasses validation and the submit
event, and is also what stops it looping back into the handler. Cancel, Esc and the
backdrop all abandon it. With JavaScript unavailable the inline `onsubmit` still
fails open, exactly as it did before.

The wording reaches the handler as a `data-confirm-text` attribute rather than an
argument. Splicing a name into that inline call would mean an item called
`O'Reilly` closed the JavaScript string early and killed the handler; as an
attribute value, the apostrophe is just a character.

## Frozen fields

Once an item or a unit has been used in a recorded movement, the fields that
give it meaning are frozen:

- an item keeps its **trading unit**, and with it the family derived from it;
- a unit keeps its **code**, **family** and **multiplier**.

Editing those would silently restate the meaning of history. The forms disable
the inputs, and the server refuses them if they are sent anyway — so a stale tab
or a hand-rolled request cannot corrupt the past. Names, labels, notes and the
active flag stay editable, and deactivating hides an item from sale without
touching what it already sold.

## Layout

```
app/Services/Stock/    deriving stock, availability, activity
app/Services/Record/   buying and selling, line normalisation
app/Services/Report/   period maths, trend buckets, valuations
app/Http/Requests/     the validation rules, including the frozen fields
database/data/units/   the unit catalogue, one JSON file per family
public/js/             stockly.js helpers (toast, theme, confirm dialog,
                       unit picker), packs.js pack arithmetic,
                       record-form.js the line builder
```

Routes are thin: controller → service → model. The interesting behaviour lives
in the services, which is where the tests point.

### Script load order is load-bearing

`alpine.min.js` normally calls `start()` from a `queueMicrotask` queued the
instant it executes -- not on `DOMContentLoaded`. So anything an `x-data`
expression needs **must already be defined** by the time Alpine scans the DOM.

In `layouts/app.blade.php`, the local Alpine bundle is patched to honor
`window.deferLoadingAlpine`. The layout captures that callback, waits for
`packs.js`, `record-form.js` and `record-display.js` to mark themselves ready,
then starts Alpine exactly once. Starting earlier makes `recordForm()` or its
mixins unavailable while the form is parsed, and Alpine marks the nodes as
initialised with an empty scope; the visible symptom is `ReferenceError` for
bindings such as `lines`, `draft` or `totalProfitLabel`.

There is no JS test runner in this project (no Node), so the startup contract is
asserted by a PHP feature test and should still be re-checked in a browser if you
touch the layout's script tags.

## Troubleshooting

### No Units Displaying (Only Family Dropdown Has Data)

**Symptoms**: Measurement/Family dropdown works, but Unit dropdown is empty.

**Root Cause**: UnitSeeder not run on the environment, so `config.units` is empty.

**Fix**:
```bash
php artisan db:seed --class=UnitSeeder
```

**Verification**:
```bash
# Check units exist
php artisan tinker --execute="echo 'Units: ' . \App\Models\Unit::count()"

# Check active units per family
php artisan tinker --execute="\App\Models\Unit::where('is_active', true)->get()->groupBy('family')->map->count()"
```

**Prevention**: Include `UnitSeeder` in your deployment pipeline after migrations.

## Deployment Setup

Run these commands after deploying to a new environment:

```bash
# 1. Run migrations
php artisan migrate --force

# 2. Seed units catalogue (required for forms to work)
php artisan db:seed --class=UnitSeeder --force

# 3. (Optional) Seed demo data
php artisan db:seed --class=DemoLedgerSeeder --force

# 4. Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

> **Note**: The UnitSeeder is essential - without it, the Unit dropdown will be empty on sell/buy forms.

## Notes

- `docs/inventory-original.html` is the original design this was ported from,
  kept for reference.
- Stock valuation is an average cost, not FIFO: total purchase cost divided by
  total base quantity bought. It is an estimate for pricing decisions, not an
  accounting ledger.
