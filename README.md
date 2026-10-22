# Identity Management Center (KYM)

**English** · [Türkçe](README.tr.md)

One application, two jobs: the **identity provider** for the other panels and
the **Quick Access** page everyone lands on.

Sibling projects: EYS (password vault) and NYS (network panel).

What is supported, and **to what extent**: [`SUPPORT.md`](SUPPORT.md) (Turkish).

## The center does not hand out permissions

The only question answered here is "who manages the center **itself**". What a
person may do in a given project is decided by that project's own permission
system; if roles leaked into the center, modularity would collapse at the first
special case.

A role is returned as information, **not as an order**: the project is free to
accept it or not, to map it to whichever local role, and to grant or withhold
administrator rights.

## Identity provider

Only **Authorization Code + PKCE** is enabled. The password grant is disabled
(it makes the client handle the user's password), and there is no implicit flow
(it puts the token in the address bar).

| Endpoint | Purpose |
| --- | --- |
| `/.well-known/openid-configuration` | Discovery document — projects in other languages only need this |
| `/oauth/authorize` · `/oauth/token` | Standard endpoints provided by Passport |
| `/oauth/userinfo` | Identity: `sub` (uuid), name, e-mail, verified or not, role |
| `/api/identity-status` | Back channel: connected projects ask "what is the state of these identities right now" |

Access tokens live **10 minutes**, refresh tokens 1 day. No `id_token` is
issued, and the discovery document says so explicitly.

### Connecting a new application

`/admin/clients` → "Yeni uygulama" (new application). The secret is shown
**once**. For Laravel projects a single file is generated (`app/IdentityCenter.php`):
routes, the login button, the PKCE flow and account matching come together, so
the work in the project comes down to three steps — drop in the file, register
the provider, add `@identityLogin`. For other languages the screen shows the raw
protocol and a working Node.js example.

Rotating the secret revokes every issued token: if we rotate because the secret
leaked, a token obtained through the leak would otherwise keep working until it
expired. Removing a connection does not delete the record, it revokes it — so
rows in the audit log are never left without an owner.

## Quick Access

Tiles and halls are records; the page is not static. Visibility has three
levels:

| Level | Who sees it |
| --- | --- |
| `herkes` (everyone) | No session needed (default) |
| `girisli` (signed in) | Everyone who is signed in |
| `yetkili` (authorized) | Those granted access to that tile/hall |

Filtering happens in the database: "fetch everything, then hide it on screen" is
not security — anyone reading the page source would still see the tile's name
and address. A hall left empty is not drawn at all.

A hall can be marked as **its own page** (`/group/salon-a`). Someone entering
a hall they cannot see gets 404, not 403: 403 would say "such a page exists".

### Gate endpoint

`/gate/{anahtar}` is for nginx's `auth_request` directive: 401 without a
session, 403 without permission, otherwise 200 plus identity headers. The rule
goes through the same place as the listing, and a test locks that the two never
diverge.

## Accounts

1. Registration is open to everyone — but it can be closed, and when it is, the
   endpoint itself closes too; hiding the link is not closing it.
2. A new account is born **pending** and cannot access anything.
3. An administrator approves it. When verification is not mandatory, the
   approver vouches for the owner of the address.
4. Suspension takes effect **immediately**: the status is checked on every web
   request.

Two-step verification (TOTP) is implemented by hand (RFC 6238) and verified
against the RFC's own test vectors. It can be made mandatory for administrators
only or for everyone; an administrator without their own second step set up
cannot turn the requirement on.

## Settings

Settings live in the database and are managed from the panel: changing `.env`
requires file access and leaves no trace of who did it. The registration gate,
the two-step requirement, the e-mail verification requirement and SMTP are here.
E-mail verification cannot be made mandatory until a test message has been sent.

## Operation

Installation is done with `docker-compose.yml` at the repository root (Compose
standard: MariaDB and redis as separate services, the center speaks HTTP only;
the same file on every platform). Details and variables: `docker/README.md`,
`.env.example`.

    cp .env.example .env && docker compose up -d --build   # install / update
    docker compose logs -f panel                           # logs
    docker compose exec panel supervisorctl status         # services

## Development setup

```bash
composer setup
php artisan admin:add --name="Ad Soyad" --email=ad@ornek.test
php artisan client:add --name="NYS" --address=https://ornek/login/center/callback
```

There is **no** "make myself administrator" path in the panel; the first
administrator is created with this command.

## Tests

```bash
composer test
```

The visual tests also check that **the console stays clean** on the rack drawings.

### Visual tests

The visual suite does NOT look at the PRODUCTION database: a separate server is
started with its own sqlite. Otherwise the tests would sway with real records and
the panel scenarios could modify real accounts.

```bash
S=storage/gorsel.sqlite
ORTAK="APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE=$PWD/$S \
  APP_CONFIG_CACHE=/tmp/dc-gorsel-config.php APP_ROUTES_CACHE=/tmp/dc-gorsel-routes.php"

touch $S
env $ORTAK php artisan migrate --force
env $ORTAK php artisan db:seed --class=VisualSeeder --force

# Local server: the live address speaks https, the browser speaks http locally.
env $ORTAK APP_URL=http://127.0.0.1:8402 SESSION_SECURE_COOKIE=false \
  php artisan serve --port=8402 &

PLAYWRIGHT_BASE_URL=http://127.0.0.1:8402 \
  E2E_EMAIL=gorsel@ornek.test E2E_PASSWORD=gorsel-test-1616 \
  npx playwright test      # desktop + phone
```

`VisualSeeder` builds a fixed, small world: one administrator, two halls, four
tiles. `APP_CONFIG_CACHE` must point somewhere else — if production has a
cached config it overrides `APP_URL` and assets are requested from the live
address.

## Rules

Coding standards are in `CLAUDE.md`. The design system comes from EYS; the panel
side uses the same component names as the sibling projects.
