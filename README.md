# Hector Habit

A private habit grid sold as a small annual subscription. **₹199 / year**, 3-day
trial, no public signup.

> “Obsession is a word the lazy use to describe the dedicated.”

| Surface | What it is | Where it lives |
|---|---|---|
| Marketing | Hero, FOMO line, price, trial CTA | WordPress (`wordpress/landing.html`) or `GET /` |
| App | Login, dashboard (Today / Week / Month), Habits, Profile, Logs, Plan, Look | custom PHP in `public/` |

Stack: **PHP 8 + MySQL 8/MariaDB + HTML + CSS + vanilla JS**. No framework, no
build step, no Node at runtime. Runs on ordinary shared hosting (cPanel/Apache).

---

## 1. Install (5 minutes)

```bash
# 1. Files — put the app somewhere outside the web root if you can
unzip hector-habit.zip -d ~/hector-habit
cd ~/hector-habit

# 2. Database
mysql -u YOURUSER -p -e "CREATE DATABASE hector_habit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u YOURUSER -p hector_habit < sql/schema.sql

# Existing v1.2 database only (run once; do NOT run on a fresh schema):
# mysql -u YOURUSER -p hector_habit < sql/migrations/001_sheet_parity.sql
# Existing database created before "forgot password" (run once; not after a fresh schema.sql):
# mysql -u YOURUSER -p hector_habit < sql/migrations/002_password_recovery.sql

# 3. Configuration — either real env vars, or copy the example
cp src/config.local.php.example src/config.local.php   # then edit it
export HECTOR_DB_HOST=127.0.0.1 HECTOR_DB_NAME=hector_habit
export HECTOR_DB_USER=... HECTOR_DB_PASS=...

# 4. First user + habits + imported logs. Refuses to run without a password.
HECTOR_ADMIN_PASSWORD='choose-a-strong-one' php sql/seed.php

# 5. Web root points at /public
#    Apache vhost:  DocumentRoot /home/you/hector-habit/public
#    cPanel:        point the (sub)domain's document root at .../hector-habit/public
```

Then open `https://your-domain/login` and sign in as **afzal** with the password
you passed to `seed.php`. Change it immediately: **Look → Password**. Then create
a recovery key in **Look → Recovery key** and store it somewhere safe.

### Forgot password (no email)

The app has no email, so a forgotten password is reset with a **recovery key**:

1. While signed in: **Look → Recovery key** → enter the current password →
   the key (`XXXX-XXXX-XXXX-XXXX`, 80 bits) is shown **once**. Only its bcrypt
   hash is stored (`users.recovery_hash`).
2. Locked out: **/login → Forgot password?** (`/forgot`) → username + recovery
   key + new password. On success the used key stops working and a fresh one is
   shown once.
3. Wrong username and wrong key give the same message (no account probing);
   failures are slowed like wrong logins. No key? The owner resets it
   (`seed.php` refreshes Afzal's password).

### Local test server (instead of Apache)

```bash
cd hector-habit
php -S 0.0.0.0:8099 -t public public/index.php
# → http://localhost:8099
```

The app detects its own base path, so a subfolder install
(`https://site.com/habits/login`) works with no extra configuration. Set
`APP_BASE_URL` only if you are behind a proxy.

For a Windows/XAMPP + VS Code walkthrough in Hinglish, see
[`GETTING_STARTED_HINGLISH.md`](GETTING_STARTED_HINGLISH.md).

---

## 2. Environment variables

| Variable | Purpose |
|---|---|
| `HECTOR_DB_HOST` / `_PORT` / `_NAME` / `_USER` / `_PASS` | MySQL connection |
| `HECTOR_ADMIN_PASSWORD` | seed.php only — the first user's password |
| `APP_BASE_URL` | optional fixed base URL (proxies, CLI, Razorpay webhook) |
| `RAZORPAY_MODE` | `test` by default; set `live` only after live activation. Key ID prefix is checked against the mode. |
| `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` | payments. Use `rzp_test_...` keys in test mode. **Missing/mismatched → Pay stays disabled; nothing succeeds** |
| `RAZORPAY_WEBHOOK_SECRET` | lets `/billing/webhook` confirm payments server-to-server |
| `HECTOR_TIMEZONE` | default `Asia/Kolkata` |
| `HECTOR_COOKIE_SECURE` | `auto` (default) marks the session cookie Secure on https. Set `0` only if a proxy fights you |
| `HECTOR_PRICE_PAISE` | default `19900` (₹199) |

`src/config.local.php` (git-ignored) can hold the same keys as an array when the
host will not give you real environment variables.

---

## 3. Razorpay setup

1. Start in Razorpay **Test Mode** → Settings → API keys → generate a test pair. Put the `rzp_test_...` key id + secret in the server config (`src/config.local.php` or environment) and set `RAZORPAY_MODE=test`.
2. The Plan page displays the active mode. Test mode uses Razorpay's sandbox; it cannot charge real money. The app rejects a live key while in test mode.
3. For webhook testing after you have a public HTTPS endpoint, add `https://your-domain/billing/webhook`,
   subscribe to `payment.captured` and `order.paid`, and copy the webhook secret
   into `RAZORPAY_WEBHOOK_SECRET`. For local-only test checkout, webhook setup can wait.
4. Flow: `/billing/order` creates the order server-side (amount 19900 paise, or
   the coupon price) → Razorpay Checkout opens with the **key id only** →
   `/billing/verify` checks the HMAC signature → `users.paid_until` moves one year
   forward (never backwards, never doubled) → the coupon's `uses_left` drops by one.
5. Missing or mode-mismatched keys keep Pay disabled. The secret never reaches the browser; the app never fakes a payment.

---

## 4. WordPress (marketing page only)

1. Create a page, add a **Custom HTML** block, paste `wordpress/landing.html`.
2. Replace every placeholder `https://APP-DOMAIN/login` with your app URL.
3. Menu items “Start trial” and “Sign in” both point at `{APP}/login`.
4. Hard rule: WordPress never stores habit data and never creates accounts.

---

## 5. Coupons (affiliate programme)

```sql
INSERT INTO coupons (code, percent_off, uses_left, affiliate_name)
VALUES ('ALI10', 10, 100, 'ali');
```

Seeded coupon: `HECTOR20` — 20% off, 50 uses, affiliate `founder`
(₹199.00 → ₹159.20, i.e. 19900 → 15920 paise).

---

## 6. Pre-flight checklist (all verified by `tests/`)

```bash
php tests/rules.php                                  # due rules, billing maths, idempotent fulfilment
BASE=http://localhost:8099 PASS='...' bash tests/smoke.sh   # end-to-end over HTTP
```

- [x] Seed refuses to run without `HECTOR_ADMIN_PASSWORD`; wrong password cannot log in
- [x] No password in any HTML, JS, template or comment
- [x] Day toggles writes exactly one log row; clicking again deletes it; duplicates impossible (unique key)
- [x] A day the habit is not due is refused, server-side
- [x] August 2026 shows the imported checks on the 3rd and the 10th, and only those
- [x] Read & Upskill starts with 0 weekday flags exactly as in the Sheet; weekday rules can now be edited in Habits
- [x] Gym is Mon–Sat, Clean Fuel Mon–Fri, Plan Tomorrow every 2 days from 2026-01-01
- [x] Theme lives in the database and survives reload; Paper is genuinely light
- [x] Sheet Achievement badges use exact daily tiers (100, 80, 60, 40, 20, 1, 0%)
- [x] Profile shows lifetime completions, total XP, level, XP to next level, and Novice→Legend rank
- [x] Logs view lists unique completion rows with pagination; archive preserves history
- [x] Habits view adds/edits/archives/restores up to 20 habits with due rules and per-completion XP
- [x] Year and month can be selected separately; history remains in MySQL
- [x] Razorpay test mode is the default and rejects a `rzp_live_` key until mode is switched
- [x] Razorpay absent → no fake success; `HECTOR20` → 15920 paise
- [x] Expired trial: every route except `/billing` and `/logout` redirects to `/billing`
- [x] Usable at 400px, no page-level horizontal scroll
- [x] Password change requires the current password, then re-hashes (tested end to end)

```bash
php tests/rules.php            # 91 rule checks (due dates, achievements, XP/ranks, habits, billing)
BASE=http://localhost:8099 PASS='...' bash tests/smoke.sh   # 53 checks over HTTP
```

`docs/preview.html` retains the 18 original browser captures. The added Profile, Logs, and Habits screens are available in the live app and are covered by the HTTP smoke suite.

Developer-only convenience in the workspace: `tools/devserver.sh` creates and
**drops/recreates** a throwaway demo database, then serves on :8099. It is
intentionally excluded from the release zip. Never run it against production or
any database containing real user data. The released site is installed using
steps 1–5 above.

---

## 7. Layout of the repo

```
sql/schema.sql             tables + coupon; sql/migrations/ upgrades older DBs (001 sheet parity, 002 recovery keys)
sql/seed.php               CLI only. first user, 10 habits, supplied unique logs
public/index.php           front controller + owner-scoped POST/API routes
public/.htaccess           Apache front controller + hardening
public/assets/app.css      responsive themes, grid, profile/logbook/editor UI
public/assets/app.js       vanilla JS, toggles, forms, themes, test checkout
src/config.php db.php auth.php http.php habits.php billing.php
  habits.php               due rules, streaks, stats, ranks, achievements, habit CRUD
  billing.php              Razorpay test/live guard, orders, signatures, webhook
 templates/                login · forgot · today/week/month · habits · profile · logs · billing · look
wordpress/landing.html     paste-ready Custom HTML marketing page
GETTING_STARTED_HINGLISH.md VS Code/XAMPP setup and test-key instructions
tests/                     rules.php (unit) · smoke.sh (HTTP)
```

Routes: `GET /` · `GET|POST /login` · `GET|POST /forgot` · `GET /logout` · `GET /app?v=today|week|month|habits|profile|logs|look` ·
`GET /billing` · `POST /toggle` · `GET /state` · `POST /theme` · `POST /password` · `POST /recovery` ·
`POST /habit/save|archive|restore` · `POST /billing/order` · `POST /billing/verify` · `POST /billing/webhook`.

There is no `/signup`. There will not be one.

---

## 8. Day-to-day

- **Owner (you):** change the password after the first login, keep the Razorpay
  keys in the environment, watch `payments` for anything stuck in `created`.
- **Adding a habit:** use **Habits** in the app. Add/edit the name, XP reward,
  start date, daily/weekday/every-X-days schedule; archive rather than delete so
  past logs and lifetime XP remain intact. Limit: 20 active habits, like the Sheet.
- **Backups:** `mysqldump hector_habit > hector-$(date +%F).sql`. The whole
  product state is in those five tables.

## 9. Not included, on purpose

No signup, no email, no password-reset-by-mail (forgotten passwords use a
recovery key instead, see §1), no native app, no social
features, no analytics, and no live Google Sheet/API sync. Hector Habit is the
source of truth after import. WordPress is the public marketing page only.
