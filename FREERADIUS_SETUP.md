# FreeRADIUS Setup Guide

Simple RADIUS Manager is a web front-end for managing PPPoE users, plans, and
NAS devices. It does **not** itself speak the RADIUS protocol — that job
belongs to **FreeRADIUS**, which reads its data straight out of the
`radcheck`, `radreply`, and `nas` tables this app manages. This guide gets
FreeRADIUS running and pointed at that database.

Tested on Ubuntu/Debian. Assumes you already have MySQL/MariaDB installed.

## 1. Install FreeRADIUS + MySQL support

```bash
sudo apt update
sudo apt install freeradius freeradius-mysql freeradius-utils mariadb-server -y
sudo systemctl stop freeradius
```

## 2. Create the database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE radius_manager CHARACTER SET utf8mb4;
CREATE USER 'radius_manager'@'localhost' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON radius_manager.* TO 'radius_manager'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Then load the schema shipped with this app:

```bash
mysql -u radius_manager -p radius_manager < schema.sql
```

Update `config.php` in the app with the same DB name/user/password.

## 3. Point FreeRADIUS's SQL module at the same database

Edit `/etc/freeradius/3.0/mods-available/sql`:

```
sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"

    server = "localhost"
    port = 3306
    login = "radius_manager"
    password = "change_me"
    radius_db = "radius_manager"

    read_clients = yes
    client_table = "nas"
}
```

Enable the module and remove the default SQLite/flat-file schema conflicts:

```bash
sudo ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
```

In `/etc/freeradius/3.0/sites-enabled/default`, make sure `sql` is
uncommented in the `authorize {}`, `accounting {}`, and `post-auth {}`
sections (it usually already is by default — just confirm it's not commented
out).

## 4. Load clients (NAS devices) from the database

Because `read_clients = yes` is set above, FreeRADIUS will read allowed NAS
devices and their shared secrets directly from the `nas` table — anything you
add on the **NAS / Routers** page in the app becomes a valid RADIUS client
automatically, no `clients.conf` editing required.

## 5. Start and test

```bash
sudo systemctl start freeradius
sudo systemctl enable freeradius

# Debug mode - very useful for troubleshooting, run this instead of
# starting the service while you're testing:
sudo freeradius -X
```

Test authentication for a user you created in the app (replace values):

```bash
radtest myuser mypassword localhost 0 testing123
```

`testing123` is FreeRADIUS's default local testing secret (in
`clients.conf`, `client localhost`) — not related to your NAS secrets.

You should see `Access-Accept` if the user is active, or `Access-Reject` if
suspended/expired/wrong password.

## 6. Point your router (e.g. MikroTik) at FreeRADIUS

On your MikroTik (RouterOS), under **PPP > PPPoE Server** or **Radius**:

- Add a RADIUS server: the IP of the box running FreeRADIUS, port 1812
  (auth) / 1813 (accounting)
- Secret: must match the `secret` you set for that router on the **NAS /
  Routers** page in the app
- Enable RADIUS for PPP: `/ppp aaa set use-radius=yes`

Once that's set, any user you add in **PPPoE Users** in the app can log in
through that router immediately — no router-side user config needed.

## Notes

- Passwords are stored in cleartext in `radcheck` (as `Cleartext-Password`).
  This is standard practice for PPPoE/CHAP authentication, since CHAP
  requires the plaintext password to compute the challenge response. Keep
  your database access locked down accordingly.
- Speed limits are pushed as `Mikrotik-Rate-Limit` reply attributes. If your
  NAS isn't a MikroTik, you'll want to swap that attribute in
  `includes/radius_sync.php` for whatever your vendor uses (e.g.
  `Cisco-Avpair` for Cisco).
- `radacct` is included in the schema for accounting data (session start/stop,
  bytes in/out) if you enable RADIUS accounting on your router — the app
  doesn't currently have a page to view it, but the data will be there if you
  want to add one later.
