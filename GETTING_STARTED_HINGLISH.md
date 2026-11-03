# Hector Habit — VS Code setup (Hinglish)

## Pehle: kya ready hai?

- **Frontend:** PHP templates + CSS + vanilla JavaScript. Screens: Today, Week, Month, Habits, Profile, Logs, Plan, Look.
- **Backend:** `public/index.php` routes; `src/auth.php` sessions/passwords/CSRF; `src/habits.php` schedule rules, streaks, XP, achievements, stats and habit management; `src/billing.php` Razorpay order/signature/webhook flow.
- **Database:** MySQL/MariaDB (`sql/schema.sql`) mein users, habits, logs, coupons, payments. Seed adds Afzal, the supplied 10 habits, `HECTOR20`, and 18 unique log rows from the supplied Sheet snapshot (duplicate log rows collapse to one). No live Sheet sync: app is the source of truth.
- **WordPress:** `wordpress/landing.html` is the complete public marketing page. It does not store tracker data or create accounts.
- **Payments:** `RAZORPAY_MODE` defaults to `test`. Add your own `rzp_test_...` credentials privately on the server; no key means disabled Pay button, no fake payment.
- **Ashna API key:** If you add a server-side Ashna integration later, store the rotated key only in `src/config.local.php` as `ASHNA_API_KEY` or as a real environment variable. Never put it in `public/assets/app.js`, templates, HTML, Git, or chat.

## Local run: Windows + VS Code + XAMPP

1. Install **VS Code**, **XAMPP** (PHP + MySQL), and optionally **Git for Windows** (for the Git Bash test terminal).
2. Download and unzip `hector-habit-v*.zip`. In VS Code choose **File → Open Folder** and open the extracted `hector-habit` folder.
3. Open XAMPP Control Panel and start **MySQL**. Apache is optional for local development; the PHP built-in server below is enough.
4. Open `http://localhost/phpmyadmin`, create database **`hector_habit`** with collation `utf8mb4_unicode_ci`, select it, choose **Import**, and import `sql/schema.sql`.
   - If you are upgrading an existing v1.2 database instead, import `sql/migrations/001_sheet_parity.sql` once. Do not run that migration after importing the fresh schema.
   - **Forgot password update:** agar aapka `hector_habit` database is update se pehle bana tha, to `sql/migrations/002_password_recovery.sql` bhi **ek baar** import karo (phpMyAdmin → database select → Import). Fresh `schema.sql` import ke baad ise mat chalao — usme ye columns pehle se hain. Import nahi kiya to Look page aur Forgot page khud bata denge "Database update needed".
5. In VS Code Explorer, copy `src/config.local.php.example` to `src/config.local.php`. This local file is ignored by Git. For default XAMPP, edit its DB entries to:

   ```php
   'HECTOR_DB_HOST' => '127.0.0.1',
   'HECTOR_DB_PORT' => '3306',
   'HECTOR_DB_NAME' => 'hector_habit',
   'HECTOR_DB_USER' => 'root',
   'HECTOR_DB_PASS' => '',
   ```

   If you set a MySQL root password, put that password in `HECTOR_DB_PASS`.
6. In VS Code choose **Terminal → New Terminal**. Check PHP is available with `php -v`. If Windows says `php` is not found, run it with XAMPP's full path, e.g. `& 'C:\xampp\php\php.exe' -v`.
7. Seed the first account once. In PowerShell:

   ```powershell
   $env:HECTOR_ADMIN_PASSWORD = 'choose-your-own-strong-password'
   php sql/seed.php
   ```

   The username is `afzal`; the password is the one you chose. Seed refuses to run without that environment variable. If you rerun seed, it refreshes Afzal's password hash to the value currently in the variable.
8. Start the site from the project folder:

   ```powershell
   php -S 127.0.0.1:8099 -t public public/index.php
   ```

   If `php` is not on PATH, use:

   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8099 -t public public/index.php
   ```

9. Open **http://127.0.0.1:8099** for the FOMO landing page or **http://127.0.0.1:8099/login** to sign in. Change the chosen seed password at **Look → Password**. Keep the terminal open while using the site; `Ctrl+C` stops the local server.
10. **Forgot password setup:** sign in karke **Look → Recovery key** kholo, current password daalo, aur **Create recovery key** dabao. Key (`XXXX-XXXX-XXXX-XXXX`) sirf ek baar dikhegi — Copy karke password manager ya kahin safe likh lo. Password bhool gaye to **/login → Forgot password?** pe username + recovery key + naya password daalo. Reset hote hi purani key band ho jaati hai aur nayi key screen pe dikhti hai (use bhi save karo). App mein email nahi hai, isliye recovery key hi proof hai ki account aapka hai.

## Ashna key (secure local use)

The key shared in chat should be revoked/rotated because it is exposed. This project currently has no Ashna API endpoint, so adding a key alone does not create an AI feature. After rotating it, add the new key in the ignored local config:

```php
'ASHNA_API_KEY' => 'your-new-rotated-key',
```

The app can read it server-side with `hh_cfg('ashna_api_key')`. Do not print it, return it from JSON, or include it in browser JavaScript. For a terminal session in VS Code PowerShell, use:

```powershell
$env:ASHNA_API_KEY = 'your-new-rotated-key'
```

That environment variable lasts only for the current terminal. Restart the PHP server after setting it.

## Razorpay TEST keys (your part)

1. Create a **Test Mode** key pair in the Razorpay Dashboard. Test Key ID begins `rzp_test_`.
2. Open your private `src/config.local.php` and add/uncomment the following values. Never paste the Secret into browser JavaScript, HTML, Git, or this chat:

   ```php
   'RAZORPAY_MODE' => 'test',
   'RAZORPAY_KEY_ID' => 'rzp_test_your_key_id',
   'RAZORPAY_KEY_SECRET' => 'your_test_key_secret',
   // Optional, after configuring a webhook:
   // 'RAZORPAY_WEBHOOK_SECRET' => 'your_test_webhook_secret',
   ```

3. Stop and restart the PHP server. **Plan** should say **Razorpay TEST MODE**; use Razorpay's test payment details. No real money is charged in test mode. The app rejects a live (`rzp_live_...`) key while mode is `test`.
4. Leave `RAZORPAY_MODE` as `test` until you intentionally switch to production and have live credentials. Webhook/domain setup can wait.

## WordPress marketing page

1. In WordPress create a page such as **Hector Habit** and add a **Custom HTML** block.
2. Paste the entire contents of `wordpress/landing.html` into that block.
3. Replace every `https://APP-DOMAIN/login` placeholder with your app's eventual login URL.
4. Add “Start trial” and “Sign in” menu links to that same app `/login` URL. The landing page is public; habits and payments stay in the PHP/MySQL app.

## Tests (optional, developer terminal)

- Rules: `php tests/rules.php`
- Full HTTP smoke suite: use Git Bash and set `BASE`, `PASS`, `DB_USER`, `DB_PASS`, `DB_NAME` before `bash tests/smoke.sh`. Run tests only against a development/test database; the smoke suite creates and removes a test habit and changes/restores the demo account password.

## File map — kaunsi file kya karti hai?

| File | Kaam |
|---|---|
| `public/index.php` | Every page/API route; auth gate and CSRF-protected POST actions (incl. `/forgot` and `/recovery`) |
| `templates/` | PHP-rendered landing, login, forgot-password, tracker, billing, profile, logs and habit-manager pages |
| `public/assets/app.css` | Responsive UI and four saved themes (`ember`, `ink`, `moss`, `paper`) |
| `public/assets/app.js` | Day toggles, grid render, premium year/month picker, theme/password/recovery-key/coupon/habit forms, Razorpay Checkout |
| `src/habits.php` | Due dates, logs, daily achievement tiers, streaks, XP levels/ranks, profile totals, habit CRUD |
| `src/billing.php` | ₹199 annual pricing, coupons, Razorpay test/live mode checks, order/signature/webhook verification |
| `sql/schema.sql` | MySQL tables and `HECTOR20` coupon |
| `sql/migrations/002_password_recovery.sql` | Purane database mein recovery-key columns add karta hai (ek baar) |
| `sql/seed.php` | First user hash, 10 habits and supplied unique log-history seed |
| `src/config.local.php` | Local DB and optional Razorpay secrets; private and never package/commit |
| `wordpress/landing.html` | Paste-ready WordPress marketing page |
| `tests/` | Rule tests and end-to-end HTTP regression tests |
