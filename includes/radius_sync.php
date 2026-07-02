<?php
require_once __DIR__ . '/db.php';

/**
 * Push a PPPoE user's credentials + speed limits into radcheck/radreply
 * so FreeRADIUS (reading straight from these tables) authenticates and
 * shapes the session correctly. Called on create/edit/status-change.
 */
function radius_sync_user(string $username, string $password, ?array $plan, string $status): void
{
    $pdo = db();

    // Wipe any existing rows for this username, then rebuild them.
    $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$username]);
    $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$username]);

    if ($status !== 'active') {
        // Suspended/expired: reject the login outright, keep the row so it's
        // obvious the account exists but is deliberately locked.
        $pdo->prepare(
            "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')"
        )->execute([$username]);
        return;
    }

    // Active: cleartext password check (standard for PPPoE/CHAP via MikroTik/BRAS)
    $pdo->prepare(
        "INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)"
    )->execute([$username, $password]);

    if ($plan) {
        // MikroTik-Rate-Limit format: "upload/download" e.g. "1M/5M"
        $rate = format_rate($plan['upload_kbps']) . '/' . format_rate($plan['download_kbps']);
        $pdo->prepare(
            "INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', '=', ?)"
        )->execute([$username, $rate]);
    }
}

/** Remove all RADIUS rows for a username (used when deleting a user). */
function radius_delete_user(string $username): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM radcheck WHERE username = ?')->execute([$username]);
    $pdo->prepare('DELETE FROM radreply WHERE username = ?')->execute([$username]);
}

/** Re-sync every user currently on a given plan (call after editing a plan's speeds). */
function radius_resync_plan_users(int $planId): void
{
    $pdo = db();
    $plan = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
    $plan->execute([$planId]);
    $plan = $plan->fetch();
    if (!$plan) return;

    $users = $pdo->prepare('SELECT * FROM pppoe_users WHERE plan_id = ?');
    $users->execute([$planId]);
    foreach ($users->fetchAll() as $u) {
        radius_sync_user($u['username'], $u['password'], $plan, $u['status']);
    }
}

/** Turn kbps into RouterOS-style shorthand, e.g. 5000 -> "5M", 512 -> "512k". */
function format_rate(int $kbps): string
{
    if ($kbps >= 1000 && $kbps % 1000 === 0) {
        return ($kbps / 1000) . 'M';
    }
    if ($kbps >= 1000) {
        return round($kbps / 1000, 1) . 'M';
    }
    return $kbps . 'k';
}
