# wizarrInvite – Organizr Plugin v2.1

<p align="center">
<img src="wizarr.png" width="140">
</p>

<p align="center">
<img src="preview.png" width="900">
</p>

wizarrInvite is an Organizr plugin that lets you **generate and manage Wizarr invitation links directly from Organizr**.

Version 2.1 builds on the Automatic Slots system introduced in v2.0, adding **next-expiry display**, **interval auto-check**, and a live **last-check status indicator** that updates in real time without any user interaction.

---

## Recommended Installation (Marketplace)

The easiest way to install this plugin is through the **custom Organizr marketplace repository**.

Repository:

```
https://github.com/Katsugami/Organizr-Plugins
```

### Add the repository to Organizr

Go to:

```
Settings → Plugins → Settings → Marketplace
```

In **External Marketplace Repo**, add:

```
https://github.com/Katsugami/Organizr-Plugins
```

Save, then go to **Plugins → Marketplace**. The wizarrInvite plugin will appear and can be installed directly.

---

## Manual Installation

Clone or download this repository into the Organizr plugins folder:

```
Organizr/api/plugins/wizarrInvite/
```

Required structure:

```
wizarrInvite/
├── plugin.php
├── api.php
├── routes.php
├── config.php
├── settings.js
├── page.php
├── main.js
├── wizarr.png
├── preview.png
├── display/
│   └── default/
│       ├── display-en.php
│       ├── display-fr.php
│       └── display-es.php
└── includes/
    ├── request.php
    ├── helpers.php
    ├── payload.php
    ├── invitations.php
    ├── cache.php
    ├── auto.php
    ├── slots.php
    ├── users.php
    ├── display.php
    ├── debug.php
    └── js/
        ├── slots.js
        ├── settings-cache.js
        ├── settings-users.js
        └── settings-plex-home.js
```

Restart Organizr if necessary. The plugin will appear under **Settings → Plugins**.

---

## What's New in v2.1

### Next Expiry Display

Each slot now has a **Show Next Expiry** toggle. When enabled, the public display page shows the next member expiration date below the user counter:

- **Limit not reached** → shows the next scheduled departure and number of days remaining
- **Limit reached** → shows when the next slot will become available (next expiry date)
- **No future expiry** → shows *"No upcoming expiration"* (all members have permanent access)

Expiry is read from the Wizarr user data (the `expires`, `expiry`, `auth_expiry`, or similar fields depending on your Wizarr version). In deferred mode, the users cache is used so no extra API call is needed.

### Interval Auto-Check — Reworked Scheduler

The auto-check scheduler has been completely rewritten. It now uses a **comparison-based approach**:

- At each tick, the scheduler computes `elapsed = now − lastCheckAt` and fires a check only when `elapsed ≥ interval`
- Uses `setTimeout` (single-shot + reschedule) instead of `setInterval`, so the countdown starts from the **actual completion time** of the previous check — not from when the timer was created
- On page load, if the last check is already overdue, a new check fires within 5 seconds automatically
- If Organizr is reopened mid-interval, the scheduler resumes from where it left off and waits only the remaining time

### Live "Last Check" Status — Always Visible

The **Last Check** indicator in the *Live User Check* section now:

- Appears automatically on page load without pressing *Show Cache*
- Ticks every second, showing how long ago the last check ran and which trigger caused it (manual button, display page, auto refresh, API)
- Shows `⏳ Auto check running…` while a check is in progress
- Updates immediately when the check completes

### Per-Server Bars Always Shown

When **Common Servers** is off (independent servers), each server's bar is now always rendered — even when no `Max Users` is set. Bars are drawn **proportionally** to each other (the server with the most users fills 100 %, others scale accordingly), giving a visual comparison without needing an absolute limit.

### `N / ∞` Always Displayed

The counter always shows the `/ ∞` separator when no user limit is set, both in the global counter and in each per-server row.

---

## What's New in v2.0

### Automatic Slots

The core new feature. A **slot** is a permanent invitation channel with its own ID, settings, and public URL. You can create as many slots as you need — for example, one slot per access tier, language, or server.

Each slot has:
- Its own **display URL** (`/display/1`, `/display/2`, etc.)
- Independent server and library selection
- Its own user limit (`Max Users`)
- Its own invitation expiration and access duration
- Its own bundle (optional)
- A **Check / Recreate** button to force-refresh the invite code

The slot configuration is saved as a single JSON field in Organizr, with no extra database required.

### Debug Log Viewer

A new **Debug** section in settings lets you view and clear the plugin activity log directly from the UI. The log file is stored in the Organizr data cache directory:

```
/config/www/organizr/data/cache/wizarrinvite_debug.log
```

Logged events include: invite creation, cache hits, errors, and slot status checks.

### API Docs Links

The **API Tester** section now links directly to Wizarr's built-in Swagger / OpenAPI documentation page:

- Internal: `{Wizarr internal URL}/api/docs/`
- External: `{Wizarr public URL}/api/docs/` (shown only if a public URL is configured)

### Improved Performance

- Added **connection timeout** (`CURLOPT_CONNECTTIMEOUT`) on all HTTP requests. If a Plex server or Wizarr is unreachable, the request fails in at most 5 seconds instead of blocking for the full transfer timeout.
- Reduced `/users` timeout from 15 s to 8 s.
- Reduced `/servers` timeout from 10 s to 5 s.

### User Stats — Per-Server Breakdown

The **Check Users** button now shows a collapsible list of users per server. Click a server name to expand and see each user's username and expiry date.

### Bundle Support (Manual & Slots)

Both manual invitations and slots support a **Bundle ID** field:
- Leave empty → Wizarr uses the default bundle
- `1` → first bundle, `2` → second bundle, etc.

---

## Features

- **Manual invitation creation** from the settings panel
- **Automatic Slots** — multiple independent invitation channels, each with a permanent URL
- Automatic invite validation and recreation when settings change
- Per-slot and global **user limit** enforcement
- **Per-server user count** with independent or shared deduplication
- **Next Expiry Display** — shows the next member expiration date on the public page (optional per slot)
- **Deferred Count Cache** — slot user counts cached at a configurable interval to avoid live API calls on every page visit
- **Interval Auto-Check** — automatically refreshes user data and slot caches on a configurable schedule
- **Live Last Check indicator** — always-visible, ticks every second, shows trigger type
- Server and library selection (per slot and manual)
- Permission controls: Downloads, Live TV, Mobile Uploads, Plex Home
- **Public display pages** with multi-language support (English, French, Spanish)
- **Debug log** viewer and clear button
- **Wizarr API Docs** quick-access links
- Compatible with Organizr auto-translation

---

## Automatic Slots — How It Works

1. Create a slot in **Settings → Automatic Slots → + Add Slot**
2. Configure its label, servers, libraries, user limit, and permissions
3. Save
4. The slot's public URL is:

```
https://yourdomain.com/api/v2/plugins/wizarrinvite/display/[ID]
```

| Slot | URL |
|------|-----|
| Slot #1 | `/api/v2/plugins/wizarrinvite/display/1` |
| Slot #2 | `/api/v2/plugins/wizarrinvite/display/2` |
| Slot #3 | `/api/v2/plugins/wizarrinvite/display/3` |

When a user visits the URL:
- If a valid invite code exists in cache → served immediately (no Wizarr call)
- If the cache is missing or the config changed → a new invite is created automatically
- If the user limit is reached → the invitation is not served

Use this URL as the **homepage URL** in an Organizr tab.

---

## Display Language

The page shown to users is controlled by the **Display Used** field under **Display Language**.

Built-in options:

| Path | Language |
|------|----------|
| `display/default/display-en` | English |
| `display/default/display-fr` | French |
| `display/default/display-es` | Spanish |

To use a custom display, place a `.php` file anywhere inside the plugin folder and enter its relative path (without `.php`). The file must stay within the plugin directory.

This setting applies to all slots and the manual display at once.

---

## Cache Files

All generated files are stored in the Organizr data cache directory:

```
/config/www/organizr/data/cache/
```

| File | Purpose |
|------|---------|
| `wizarrinvite_slot_1.json` | Cached invite code for Slot #1 |
| `wizarrinvite_slot_2.json` | Cached invite code for Slot #2 |
| `wizarrinvite_users_cache.json` | Cached Wizarr user list (used by deferred slots and next-expiry) |
| `wizarrinvite_count_cache.json` | Cached user counts per slot (deferred mode) |
| `wizarrinvite_debug.log` | Plugin activity log (auto-rotated at 100 KB) |

To force a new invite code for a slot, delete its `.json` file or click **Check / Recreate** on the slot card.

---

## Configuration Reference

### Wizarr Connection

| Field | Description |
|-------|-------------|
| Internal Wizarr URL | URL used by the server to reach Wizarr (e.g. `http://127.0.0.1:5690`) |
| Wizarr API Key | API key from Wizarr settings |
| Public URL | Optional — URL used for public invite links (e.g. `https://invite.yourdomain.com`) |

### Manual Invitation

| Field | Description |
|-------|-------------|
| Minimum Organizr Group | Minimum group required to create a manual invite |
| Wizarr Expiration | How long the invite link itself is valid |
| Access Duration (days) | How many days access is granted after the invite is used |
| Bundle | Bundle ID (empty = default, `1` = first, `2` = second…) |
| Allow Downloads | Grant download permission |
| Allow Live TV | Grant Live TV permission |
| Allow Mobile Uploads | Grant mobile upload permission |
| Invite to Plex Home | Add user to Plex Home |
| Servers and Libraries | Select which servers and libraries to grant access to |

### Automatic Slots

Each slot has the same permission fields as the manual invitation, plus:

| Field | Description |
|-------|-------------|
| Label | Display name for the slot card |
| Min Organizr Group | Minimum group required to access this slot's display page |
| Max Users | Maximum number of users before the invite is blocked (empty = ∞) |
| Show User Count | Display the user counter on the public page and enforce the limit |
| Show Next Expiry | Show the next member expiration date below the counter (empty = *"No upcoming expiration"*) |
| Deferred Count Check | Use a cached user count instead of a live API call on each page visit |
| Common Servers | Treat all selected servers as sharing the same Plex account (deduplicated). If off, each server is counted independently and the highest count is used |

### User Check & Count Cache

| Field | Description |
|-------|-------------|
| Cache Interval Auto-Check | Enable/disable automatic user refresh at the configured interval |
| Cache Hours / Minutes | How often the auto-check should run |
| Smart Check | Force a live count when the cached count is within a threshold of the max users limit |
| Smart Threshold | Number of users below the limit at which a smart live check is triggered |

---

## Compatibility

| Software | Tested version |
|----------|----------------|
| Organizr | 2.1.4010 |
| Wizarr | v2026.4.0 |

---

## Authors

Katsugami

AI development assistance: ChatGPT (v1.0) — Claude by Anthropic (v2.0 — v2.1)

The project structure, integration, testing, and assembly were performed by Katsugami.

---

## License

Personal project.
