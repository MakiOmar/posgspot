# Access logs around Accounts wipes — 21 Nov 2026

**Windows:** Cairo **03:24** / **03:35** = UTC **01:24** / **01:35**  
**Host TZ:** UTC (`Etc/UTC`)  
**Evidence on VPS:** `/opt/spot-staging/data/forensics/access_logs_2026-11-21*.txt`

---

## Verdict

| Source | Result at wipe time |
|---|---|
| **SSH** | **No successful login** in the wipe window. Next root SSH that day starts ~**22:04 UTC** (00:04 Cairo 22 Nov) from `41.236.81.74`. |
| **MySQL general log** | **OFF** — no connection user/host audit trail. |
| **MySQL port** | **Not published** to the host (`3306/tcp` only inside Docker). No Adminer/phpMyAdmin container. |
| **Accounts activity** | **No** `order.deleted` for the wiped IDs. App actions by **Mody (32)** resume at **03:40 Cairo** (purchase order) — *after* the orders wipe. |
| **POS activity_log** | Normal shop activity earlier; **no login event** tied to the wipe seconds. POS user **32** login at **03:55 Cairo** (after wipe). |

**Implication:** The deletes were almost certainly issued **from inside the Docker network** (Accounts/POS app DB user `spot_stg`, or `root` from a container/`docker exec`), **not** via a logged SSH session or public MySQL. We **cannot name the OS user** from MySQL logs because `general_log` was off and ROW binlog does not store `User@Host`.

---

## Binlog thread IDs (hard evidence of the SQL)

| UTC | Cairo | MySQL `thread_id` | Action |
|---|---|---|---|
| 01:24:23 | 03:24:23 | **1601699** | `DELETE` `next_load_orders` (323, 329, 336, 337, 338, …) |
| 01:35:03 | 03:35:03 | **1602620** | `DELETE` `orders` (105024, 105067, 105072, 105084) |
| 01:35:15 | 03:35:15 | **1602628** | Mass `UPDATE card_categories` + `sessions` (looks like normal Accounts app traffic right after) |

Each wipe thread appears for that delete only (one-shot connection/request).

---

## SSH / host

- `last`: no pts/tty session covering 01:24–01:35 UTC on 21 Nov.
- Failed brute-force noise exists in `btmp` (random usernames) on other days — not tied to this window.
- MySQL users that can connect if reachable: `root@%`, `spot_stg@%` (plus localhost system users).

---

## App-side context (Cairo `created_at`)

**Accounts `system_activity_logs` (actor 32 / Mody) same night:** active before and after the wipe (POs, Next Load rejects, logged undos of *other* orders). Gap with **no** logged action for the four wiped orders / NL rows.

Around the wipe itself:

| Cairo | Event |
|---|---|
| 03:24:23 | NL wipe (binlog only) |
| 03:30:04 | system `next_load.reconcile_mismatch` (references wiped NL refs) |
| 03:35:03 | orders wipe (binlog only) |
| 03:40:04 | Mody creates purchase order #365 |
| 03:55:08 | POS login user 32 (`tarek osama` — different person from Accounts Mody id 32) |

Note: POS user id **32** ≠ Accounts user id **32** (Mody). Do not conflate them.

---

## Recommendations — status (2026-11-22)

| Item | Status |
|---|---|
| Traefik **accessLog** (stdout → `docker logs spot-staging-traefik-1`) | **Done** going forward. Historical window 01:20–01:40 UTC on 21 Nov was empty (not retained). |
| DELETE audit capturing MySQL `USER()` / `CURRENT_USER()` | **Done** — triggers on `orders` + `next_load_orders`; tables `order_delete_audit` / `next_load_delete_audit`. |
| Rotate `MYSQL_ROOT_PASSWORD` / app DB password | **Done** on VPS + local `deploy/staging/.vps-secrets.env`. |
| Restrict grants (`root@%` / `spot_stg@%`) | **Done** — now `root@localhost` + `spot_stg@172.%` only. |
| Hostinger **browser console / KVM** history | **Manual** — check panel if anyone used non-SSH root around the wipe. |
| Cloudflare request logs for Accounts admin | **Manual** — pull from Cloudflare dashboard for 03:24–03:35 Cairo if needed. |
