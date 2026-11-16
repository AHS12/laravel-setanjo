# HTTP API

Everything the dashboard does is available as a JSON API, so you can build your own admin
screens, wire settings into an existing back office or dev dashboard, or change flags from
scripts and other services. The dashboard itself uses exactly these endpoints.

- [Turning it on](#turning-it-on)
- [Authentication and access](#authentication-and-access)
- [Conventions](#conventions)
- [Endpoints](#endpoints)
- [Examples](#examples)

**OpenAPI.** A machine-readable description of every endpoint, request and response is in
[`openapi.yaml`](openapi.yaml) (OpenAPI 3.1). Load it into Swagger UI, Scalar, Postman or
Insomnia, or generate a typed client from it (for example with `openapi-typescript`). Point the
server variables at your app and prefix. The package's tests check it against the routes and
real responses, so it stays in step with the code.

## Turning it on

The API is off by default. Enable it in `.env`:

```dotenv
SETANJO_API_ENABLED=true
```

and pick its middleware in `config/setanjo.php`:

```php
use Ahs12\Setanjo\Http\Middleware\Authorize;

'api' => [
    'enabled' => env('SETANJO_API_ENABLED', false),
    'prefix' => env('SETANJO_API_PREFIX', 'api/setanjo'),   // https://app.test/api/setanjo/...
    'domain' => env('SETANJO_API_DOMAIN'),
    'middleware' => ['api', 'auth:sanctum', Authorize::class],
],
```

It does not need the dashboard: `dashboard.enabled` can be `false` while the API is on.

## Authentication and access

The API runs `setanjo.api.middleware`, then the same guard as the dashboard. Put the auth your
clients use in the list (Sanctum tokens, Passport, `auth.basic`, your own middleware):

| Middleware list | Who gets in |
| --- | --- |
| `['api', 'auth:sanctum', Authorize::class]` | A valid token, and the `viewSetanjo` gate (or `Setanjo::auth()`) allows its user |
| `['api', 'auth:sanctum', 'can:manage-settings']` | A valid token whose user passes your gate; `Authorize` is not needed |
| `['api', Authorize::class]` (default) | Only the `local` environment, or `Setanjo::auth()` / `viewSetanjo` |

Always enforced, whatever the list:

- **Never public by accident.** Without `Authorize` in the list, outside `local` a user must be
  signed in (`dashboard.require_auth`).
- **Writes** (`POST`, `PUT`, `DELETE`) are refused in `dashboard.readonly` mode and need
  `Setanjo::manage()` or the `manageSetanjo` gate when you define them. A token that can read
  is not automatically allowed to write.

Sanctum example:

```php
// AppServiceProvider::boot()
Gate::define('viewSetanjo', fn (User $user) => $user->hasRole('admin'));
Gate::define('manageSetanjo', fn (User $user) => $user->hasRole('super-admin'));

// issue a token for a client
$token = $user->createToken('settings-dashboard')->plainTextToken;
```

## Conventions

- **JSON only.** Every response is JSON, errors included, whatever `Accept` header the client
  sends. Requests with a body send JSON (`Content-Type: application/json`), except the import
  upload.
- **No CSRF and no session** are needed; that depends only on your middleware list.
- **Keys** go in the path URL-encoded: `/settings/billing.tax_rate`. They may contain dots,
  dashes and colons, but not `/`.
- **Scopes** (a record that overrides values) are named by the scope type alias and the
  record's id: `scope_type=user&scope_id=42`. The aliases come from `GET /scopes`. Leave both
  out for the global value.
- **Sensitive values** (meta `sensitive => true`) are never returned: `value` is `null` and
  `masked` is `true`. They can still be written.
- **Meta** (`meta`, `definition_meta`, `stored_meta`, a group's `meta`) is always a JSON object,
  `{}` when empty, or `null` where nothing is stored.
- **Errors**:

  | Status | When | Body |
  | --- | --- | --- |
  | 401 | Your auth middleware rejected the request | `{"message": "Unauthenticated."}` |
  | 403 | Not allowed to view; read-only; locked setting | `{"message": "..."}` |
  | 404 | Unknown key, scope type or record | `{"message": "..."}` |
  | 422 | Invalid input or value (validation rules of the definition) | `{"message": "...", "errors": {"value": ["..."]}}` |

- **Paging**: list endpoints that page take `page` and `per_page` (max 200) and answer with
  `meta: {page, per_page, total, last_page}`. History answers with
  `meta: {current_page, last_page, total}`.
- **Records.** Wherever a record appears (`scope`, `source_scope`, `causer`, a search result,
  the `records` of a bulk change), it has the same shape:

  ```json
  {"type": "user", "class": "App\\Models\\User", "id": 42, "label": "Jane", "description": "jane@example.com", "state": "found", "trashed": false}
  ```

  `type` is the scope type alias (`null` for a model the API does not manage), `label` and
  `description` come from `setanjo.scopes`, `state` is `found`, `hidden` (it exists, but a
  global scope filters it out) or `missing` (deleted), `trashed` marks soft-deleted records.
  The examples below shorten records to `Record`.
- **Stability**: the API follows the package's semantic versioning. Fields may be added in
  minor releases; nothing is removed or renamed before the next major version.

## Endpoints

All paths are relative to the prefix (`/api/setanjo` by default).

| Method | Path | What it does |
| --- | --- | --- |
| GET | `/overview` | Counts: settings, flags (on / off / scheduled / rolling out / archived), overrides per scope type, most overridden keys |
| GET | `/settings` | Every defined or stored setting |
| GET | `/settings/{key}` | One setting; with `scope_type` + `scope_id`, as that record sees it |
| PUT | `/settings/{key}` | Set the value (global, or for a record) |
| DELETE | `/settings/{key}` | Remove the stored value (global falls back to the default, a record inherits) |
| PUT | `/settings/{key}/meta` | Replace (or merge) the stored meta |
| GET | `/settings/{key}/overrides` | Records that override the setting |
| POST | `/settings/order` | Put settings into a group, in order |
| GET | `/features` | Every feature flag (`?archived=1`: only the archived ones) |
| GET | `/features/{key}` | One flag; with `scope_type` + `scope_id`, as that record sees it |
| PUT | `/features/{key}` | Turn on / off / back to default (global, or for a record) |
| PUT | `/features/{key}/meta` | Replace (or merge) the stored meta |
| PUT | `/features/{key}/rules` | Change the schedule, rollout or required flags |
| POST | `/features/{key}/archive` | Archive the flag (it reads as off everywhere) |
| POST | `/features/{key}/restore` | Restore an archived flag |
| GET | `/features/{key}/overrides` | Records that override the flag |
| POST | `/features/{key}/overrides` | Turn the flag on / off / inherit for many records at once |
| POST | `/features/order` | Put flags into a group, in order |
| GET | `/groups` | Groups in order |
| POST | `/groups` | Create a group |
| PUT | `/groups/{id}` | Update a group |
| DELETE | `/groups/{id}` | Delete a group (its settings become ungrouped) |
| POST | `/groups/order` | Reorder groups |
| GET | `/scopes` | Scope types the API can manage |
| GET | `/scopes/{type}` | Records with overrides (paged), or `?search=` to find records |
| GET | `/scopes/{type}/{id}` | Every setting and flag as one record sees it |
| DELETE | `/scopes/{type}/{id}` | Remove every override of one record |
| GET | `/drift` | Definitions in code vs the database |
| POST | `/sync` | Run `setanjo:sync` (with `dry_run` to preview) |
| GET | `/history` | Recorded changes (when `history.enabled`) |
| POST | `/history/{id}/revert` | Undo one recorded change |
| GET | `/export` | Download a `setanjo:export` file |
| POST | `/import` | Import a `setanjo:export` file or payload |

### Settings

`GET /settings` → `{"data": [Setting, ...]}`. A setting:

```json
{
    "key": "retention_days",
    "label": "Retention Days",
    "description": "How long audit entries are kept",
    "type": "integer",
    "defined": true,
    "stored": true,
    "default": 180,
    "value": 90,
    "masked": false,
    "source": "global",
    "sensitive": false,
    "is_locked": false,
    "readonly": false,
    "group_id": 2,
    "sort_order": 0,
    "meta": {"unit": "days", "min": 1, "max": 3650},
    "definition_meta": {"unit": "days", "min": 1, "max": 3650},
    "stored_meta": null,
    "override_count": 3,
    "scopes": []
}
```

`source` is `global` (stored) or `default` (not stored, the definition default applies).
`scopes` lists the scope types allowed to override it (empty: any).

`GET /settings/{key}?scope_type=user&scope_id=42` → the setting as user 42 sees it. The row
has `key`, `label`, `description`, `type`, `default`, `sensitive`, `is_locked`, `readonly`,
`group_id`, `sort_order` and `meta` like above (no global `value`), plus:

```json
{
    "data": {
        "key": "frequency",
        "allowed": true,
        "overridden": false,
        "override": null,
        "effective": "daily",
        "source": "scope",
        "source_scope": "Record (company 7)",
        "chain": [
            {"level": "scope", "scope": "Record (user 42)", "set": false, "value": null},
            {"level": "scope", "scope": "Record (company 7)", "set": true, "value": "daily"},
            {"level": "global", "scope": null, "set": true, "value": "weekly"},
            {"level": "default", "scope": null, "set": true, "value": "daily"}
        ]
    }
}
```

`allowed` is false when the definition's `scopes()` exclude this record's type. `overridden`
/ `override`: the value stored for this record itself. `effective` and `source` (`scope`,
`global` or `default`): what it gets and from where.

`chain` is every level the value is looked up in, most specific first; the first one with
`"set": true` wins (`Settings::resolveScopesUsing()` decides the levels).

`PUT /settings/{key}` body: `{"value": 90}`, plus `scope_type` and `scope_id` for a record.
The value is validated and cast by the definition (422 with `errors.value` when it fails).
Answers with the updated setting (global) or the record's row (scoped).

`DELETE /settings/{key}`: optional `scope_type` + `scope_id` in the body or query.

`PUT /settings/{key}/meta` body: `{"meta": {"unit": "days"}}` replaces the stored meta
(`null` clears it); add `"merge": true` to merge into it.

`GET /settings/{key}/overrides?scope_type=user&page=1&per_page=50`:

```json
{
    "data": [
        {"scope": "Record (user 42)", "value": "daily", "updated_at": "2026-11-16T09:12:44+00:00"}
    ],
    "meta": {"page": 1, "per_page": 50, "total": 1, "last_page": 1}
}
```

Newest first. Records that were deleted still appear (`scope.state: "missing"`) until their
overrides are cleared.

`POST /settings/order` body: `{"group_id": 2, "keys": ["company_name", "phone"]}`
(`group_id: null` for ungrouped). Answers with all settings.

### Feature flags

`GET /features` → `{"data": [Flag, ...]}`:

```json
{
    "key": "new_checkout",
    "label": "New checkout",
    "description": null,
    "type": "boolean",
    "defined": true,
    "stored": true,
    "default": false,
    "global": true,
    "enabled": true,
    "source": "global",
    "group_id": null,
    "sort_order": 0,
    "meta": {},
    "definition_meta": {},
    "stored_meta": null,
    "override_count": 12,
    "scopes": [],
    "rules": {
        "enabled_from": "2026-12-01T09:00:00+00:00",
        "enabled_until": null,
        "rollout": 25,
        "requires": ["new_cart"],
        "archived_at": null
    },
    "window": "pending",
    "archived": false
}
```

`global` is the stored global state (`null`: not set, the default applies); `enabled` is that
state or the default: the switch the rules apply to. `window` is `pending`, `open` or `ended`
relative to the schedule (`null` without one). Archived flags are left out of `GET /features`
unless `?archived=1`, which lists only them.

`PUT /features/{key}/rules` changes only the fields sent; `null` clears one:

```json
{"enabled_from": "2026-12-01T09:00:00Z", "enabled_until": null, "rollout": 25, "requires": ["new_cart"]}
```

Dates are ISO 8601 (a time without an offset is read in the app timezone). `enabled_until` must
be after `enabled_from`, `rollout` is 0-100, and `requires` (up to 20 keys) refuses unknown flags,
the flag itself and cycles with a 422. `POST /features/{key}/archive` and `/restore` take no body.
All three answer with the flag. How the rules combine is in the README (Feature Flags → Rules).

`PUT /features/{key}` body: `{"state": true}` (`false`, or `null` to go back to the default
/ inherit), plus `scope_type` + `scope_id` to set it for one record.

`GET /features/{key}?scope_type=user&scope_id=42` answers like the setting version:
`allowed`, `override` (`true` / `false` / `null` when it inherits), `effective`, `source`,
`source_scope` and `chain`, plus `rules` and `detail`. When a rule decided the state, `source`
is `archived`, `schedule`, `dependency` (`detail`: the required flag that is off) or `rollout`
(`detail`: the record's bucket, 0-99; it gets the flag when the bucket is below the percentage).

`POST /features/{key}/overrides` — the same change for many records of one type:

```json
{"scope_type": "user", "ids": ["12", "15", "42"], "state": true, "dry_run": true}
```

Up to 1000 ids; blank entries are skipped. With `dry_run` nothing is written. The answer:

```json
{
    "data": {
        "dry_run": true,
        "matched": 3,
        "changed": 2,
        "unchanged": 1,
        "missing": ["999"],
        "not_allowed": [],
        "records": ["Record (user 12)", "Record (user 15)"],
        "feature": "Flag (as in GET /features/{key})"
    }
}
```

`missing`: ids with no visible record; `not_allowed`: records the flag's `scopes()` exclude;
`changed` / `unchanged`: matched records whose state does / already does match; `records`:
the first 20 that change; `feature`: the flag after the change (its new `override_count`).

### Groups

A group: `{"id": 2, "name": "Billing", "slug": "billing", "description": null, "icon": "cash", "sort_order": 0, "meta": null}`.

`POST /groups` body: `name` (required), `slug`, `description`, `icon`, `meta` → 201 with the
group. `PUT /groups/{id}` takes the same fields. `DELETE /groups/{id}` → 204.
`POST /groups/order` body: `{"ids": [3, 1, 2]}` → the groups in their new order.

### Scopes (records)

`GET /scopes` → `{"data": [{"alias": "user", "class": "App\\Models\\User", "name": "User"}]}`.

`GET /scopes/user` → records that have overrides, in id order, paged:
`{"data": [Record + {"settings": 2, "features": 1}], "overridden": true, "meta": {...}}`
(`settings` / `features`: how many overrides it stores).
`GET /scopes/user?search=jane` → matching records (by the configured search columns, or the
id), `"overridden": false`.

`GET /scopes/user/42` → `{"data": {"scope": Record, "settings": [...], "features": [...]}}`:
every setting and flag as the record sees it (same rows as `GET /settings/{key}?scope_type=…`).

`DELETE /scopes/user/42` removes all its overrides; works for deleted records too.

### Drift and sync

`GET /drift` → `{"data": {"definitions": 12, "missing": [...], "missing_features": [...], "type_mismatch": [...], "stale": [...], "stale_features": [...], "flags_in_settings": [...], "missing_records": [...], "unknown_scope_types": [...]}}`.

`POST /sync` body: `dry_run`, `prune`, `force_types`, `reset_meta`, `reset_groups`
(booleans) → `{"exit_code": 0, "output": "...", "data": DriftReport}`.

### History

`GET /history?key=&action=&scope_type=&scope_id=&page=&per_page=` (`action`: `set`,
`forget`, `meta`, `toggle`, `rules`). When history is off: `{"enabled": false, "message": "...", "data": []}`.

```json
{
    "enabled": true,
    "data": [
        {
            "id": 118,
            "key": "new_checkout",
            "action": "toggle",
            "scope": "Record (user 42), null for global",
            "causer": "Record (who made the change), null for the console",
            "old_value": "inherit",
            "new_value": "true",
            "old_meta": null,
            "new_meta": null,
            "kind": "feature",
            "revertable": true,
            "created_at": "2026-11-16T09:12:44+00:00"
        }
    ],
    "meta": {"current_page": 1, "last_page": 3, "total": 118}
}
```

Values are stored as strings (JSON for non-strings); flag states as `"true"`, `"false"`,
`"inherit"` (`null` for "inherit" in `new_value`). `rules` entries (3.2) are global and hold a
flag's rules before and after the change in `old_meta` / `new_meta`
(`enabled_from`, `enabled_until`, `rollout`, `requires`, `archived_at`).

`POST /history/{id}/revert` writes back what the change replaced, for the same scope, and is
recorded as a new change; for a `rules` entry it puts the flag's previous rules back. 422 when
it cannot (`revertable: false`: sensitive values, flag changes recorded before 3.1).

### Import / export

`GET /export` (`?global=1` for global settings only) downloads the `setanjo:export` JSON.

`POST /import`: a multipart `file`, or a JSON body `{"payload": {...export...}}`, plus
`dry_run` → `{"exit_code": 0, "output": "..."}` (422 when the import fails).

## Examples

```bash
TOKEN=...   # e.g. a Sanctum token

# What does user 42 get?
curl -H "Authorization: Bearer $TOKEN" "https://app.test/api/setanjo/features/new_checkout?scope_type=user&scope_id=42"

# Turn the flag on for three users
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"scope_type":"user","ids":["12","15","42"],"state":true}' \
     https://app.test/api/setanjo/features/new_checkout/overrides

# Change a setting
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"value":90}' https://app.test/api/setanjo/settings/retention_days
```

```js
const setanjo = (path, options = {}) =>
    fetch(`/api/setanjo${path}`, {
        ...options,
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', ...options.headers },
    }).then(async (response) => {
        const body = await response.json();
        if (!response.ok) throw Object.assign(new Error(body.message), { status: response.status, errors: body.errors });
        return body;
    });

const { data: flags } = await setanjo('/features');
await setanjo('/features/new_checkout', { method: 'PUT', body: JSON.stringify({ state: false }) });
```
