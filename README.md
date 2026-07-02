# Simple RADIUS Manager

A lightweight PHP + MySQL web panel for managing PPPoE subscribers, speed
plans, and NAS (router) devices for a RADIUS-based PPPoE setup — the kind of
thing an ISP uses to authenticate customers via MikroTik/BRAS + FreeRADIUS.

## Features

- **PPPoE user management** — add/edit/delete subscribers, set username +
  password, assign a plan, suspend/reactivate with one click, track expiry
- **Plans** — define speed tiers (download/upload in kbps), price, validity;
  editing a plan automatically re-syncs every user on it
- **NAS management** — register the routers/BRAS allowed to send RADIUS
  requests, with their shared secret
- **RADIUS sync built in** — every change writes straight into the
  `radcheck` / `radreply` / `nas` tables that FreeRADIUS reads from, so
  there's no separate export/import step
- Single admin login, session-based auth

## Quick install (Ubuntu)

Installs Apache, MySQL, PHP, and FreeRADIUS, creates the database with a
random password, deploys the app, and wires FreeRADIUS to it — all in one
step:

```bash
unzip radius-manager.zip
cd radius-manager
sudo bash install.sh
```

At the end it prints the URL and the generated database credentials. Visit
the URL to create your admin account. This is meant for a fresh Ubuntu
server/VM — see "Security notes" below before using it for real customers.

For a manual, step-by-step install instead (useful if you want to understand
or customize each piece), see the sections below.

## Requirements

- PHP 8.0+
- MySQL / MariaDB
- A web server (Apache/Nginx) or just `php -S` for local testing
- FreeRADIUS, to actually handle the RADIUS protocol traffic (see
  `FREERADIUS_SETUP.md`)

## Setup

1. **Create the database and load the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE radius_manager CHARACTER SET utf8mb4;"
   mysql -u root -p radius_manager < schema.sql
   ```

2. **Edit `config.php`** with your database host/name/user/password.

3. **Serve the app.** For a quick local test:
   ```bash
   php -S localhost:8080
   ```
   Then open `http://localhost:8080` — for production, point Apache/Nginx's
   document root at this folder instead.

4. **First run:** since there's no admin account yet, you'll land on a setup
   page to create one. After that, you're taken to the login page.

5. **Add a NAS device** (your router's IP + shared secret), then **add a
   plan** (e.g. "10Mbps Home"), then **add PPPoE users** against that plan.

6. **Set up FreeRADIUS** to authenticate against the same database — see
   `FREERADIUS_SETUP.md` for the full walkthrough, including pointing your
   MikroTik/router at it.

## File structure

```
config.php              Database credentials
schema.sql               Full DB schema (FreeRADIUS-compatible + app tables)
install.php               First-run admin account creation
login.php / logout.php    Auth
index.php                 Dashboard
users.php                 PPPoE user management
plans.php                 Plan management
nas.php                    NAS/router management
includes/
  db.php                   PDO connection
  auth.php                 Session/login helpers
  radius_sync.php          Writes radcheck/radreply on every user change
  layout_header.php / layout_footer.php   Shared page shell
assets/style.css           Styling
```

## How the RADIUS sync works

- Adding an **active** user writes a `Cleartext-Password` row to `radcheck`
  and a `Mikrotik-Rate-Limit` reply to `radreply` based on their plan's
  speeds.
- **Suspending** a user replaces that with an `Auth-Type := Reject` row, so
  FreeRADIUS rejects their login immediately — no need to touch the router.
- **Deleting** a user removes their rows from both tables.
- **Editing a plan's speed** re-syncs every user currently on that plan.

This means FreeRADIUS can be a completely "dumb" SQL-backed server — all the
logic lives in this app.

## Security notes for production

- Change the default DB password in `config.php` and lock down MySQL to
  localhost-only access if the DB is on the same box as the app.
- Serve over HTTPS — login and NAS secrets pass over plain HTTP otherwise.
- Consider restricting access to the app itself (e.g. VPN, IP allowlist,
  `.htaccess`) since it manages live subscriber credentials.
