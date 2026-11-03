#!/usr/bin/env bash
# ============================================================================
# Hector Habit — smoke test. Runs against a live install and proves the rules.
#
#   BASE=http://localhost:8099 HH_USER=afzal PASS='...' bash tests/smoke.sh
#
# Needs: curl, grep, mysql client (for the DB assertions).
# ============================================================================
set -uo pipefail

BASE="${BASE:-http://localhost:8099}"
USERNAME="${HH_USER:-afzal}"   # NB: $USER is taken by the shell
PASS="${PASS:?set PASS to the seeded password}"
JAR="$(mktemp)"
PASSES=0
FAILS=0

MYSQL=(mysql -h "${DB_HOST:-127.0.0.1}" -u "${DB_USER:-root}" -p"${DB_PASS:-}" "${DB_NAME:-hector_habit}" -N -B)
# (set DB_USER / DB_PASS / DB_NAME if your MySQL login differs)

ok()   { PASSES=$((PASSES+1)); printf '  \033[32m✓\033[0m %s\n' "$1"; }
bad()  { FAILS=$((FAILS+1));  printf '  \033[31m✗\033[0m %s\n' "$1"; }
check(){ if [ "$2" = "$3" ]; then ok "$1 ($2)"; else bad "$1 — expected [$3] got [$2]"; fi; }
sqlq() { "${MYSQL[@]}" -e "$1" 2>/dev/null | tr -d '\r'; }
csrf() { grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }

echo "Hector Habit smoke test → $BASE"
echo

# --------------------------------------------------------------- marketing --
code=$(curl -s -o /tmp/hh_home.html -w '%{http_code}' "$BASE/")
check "GET / returns 200" "$code" "200"
grep -q "do not get back" /tmp/hh_home.html && ok "hero headline present" || bad "hero headline missing"
grep -q "₹199" /tmp/hh_home.html && ok "₹199 price on landing" || bad "price missing"
grep -qi "signup" /tmp/hh_home.html && bad "landing mentions signup" || ok "no signup anywhere on landing"

# -------------------------------------------------------------------- login --
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/signup")
check "GET /signup is 404 (there is no signup)" "$code" "404"

login_page=$(curl -s -c "$JAR" "$BASE/login")
token=$(printf '%s' "$login_page" | csrf)
[ -n "$token" ] && ok "login page issued a CSRF token" || bad "no CSRF token"

stale=$(curl -s -b "$JAR" -c "$JAR" -d "csrf=stale-token&username=$USERNAME&password=unused" "$BASE/login")
printf '%s' "$stale" | grep -q "Your sign-in page expired" && ok "stale login session returns a readable retry page" || bad "stale login session returned an unreadable response"
token=$(printf '%s' "$stale" | csrf)
[ -n "$token" ] && ok "expired login form issued a fresh CSRF token" || bad "expired login form did not refresh its token"

wrong=$(curl -s -b "$JAR" -c "$JAR" -d "csrf=$token&username=$USERNAME&password=definitely-wrong" "$BASE/login")
printf '%s' "$wrong" | grep -qE "credentials don(&#039;|')t match" && ok "wrong password → exact error copy" || bad "wrong-password copy changed"

code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" -d "csrf=$token&username=$USERNAME&password=$PASS" "$BASE/login")
check "correct password redirects (302)" "$code" "302"

# --------------------------------------------------------------- dashboard --
app=$(curl -s -b "$JAR" -c "$JAR" "$BASE/app")
printf '%s' "$app" | grep -q "Deep Work" && ok "/app renders the habit list state" || bad "/app missing state"
printf '%s' "$app" | grep -q "$PASS" && bad "the password leaked into the HTML" || ok "no password in the HTML or JS"
curl -s "$BASE/assets/app.js" | grep -q "$PASS" && bad "the password leaked into app.js" || ok "app.js holds no credentials"

profile_page=$(curl -s -b "$JAR" "$BASE/app?v=profile")
grep -q "Lifetime by habit" <<< "$profile_page" && ok "Profile shows lifetime habit totals" || bad "Profile is missing lifetime stats"
grep -qi "Current rank" <<< "$profile_page" && ok "Profile shows level/rank progression" || bad "Profile is missing rank progression"
logs_page=$(curl -s -b "$JAR" "$BASE/app?v=logs&m=all")
grep -q "Completion log" <<< "$logs_page" && ok "Logs page renders the database history" || bad "Logs page missing"
grep -q "Habit ID" <<< "$logs_page" && ok "Logs page includes the Sheet columns" || bad "Logs table columns missing"
habits_page=$(curl -s -b "$JAR" "$BASE/app?v=habits")
grep -q "habit-form" <<< "$habits_page" && ok "Habits page renders the editor" || bad "Habit editor missing"
grep -q 'id="year-select"' <<< "$habits_page" && grep -q 'id="month-select"' <<< "$habits_page" && ok "separate year and month selectors are present" || bad "time-travel selectors missing"

state=$(curl -s -b "$JAR" "$BASE/state?m=2026-08")
printf '%s' "$state" | grep -q '"month_label":"August 2026"' && ok "/state answers for August 2026" || bad "/state month label wrong"
month_page=$(curl -s -b "$JAR" "$BASE/app?v=month&m=2026-08")
grep -q 'grid-achievement' <<< "$month_page" && ok "month calendar renders the Sheet achievement badges" || bad "month achievement badges missing"
printf '%s' "$state" | grep -q '"today_cell":' && ok "/state keeps today's action independent from the selected month" || bad "missing current-day cell"
printf '%s' "$state" | grep -q '"week_cells":\[' && ok "/state returns per-habit seven-day cells" || bad "missing week cells"
printf '%s' "$state" | grep -q '2026-07-27' && ok "historical first-week dates cross the month boundary" || bad "week starts inside the selected month only"

# August 2026: exactly the 3rd and the 10th have Deep Work checked
aug=$(sqlq "SELECT COUNT(*) FROM logs l JOIN habits h ON h.id=l.habit_id WHERE l.log_date LIKE '2026-08%' AND l.user_id=1")
check "August 2026 unique log rows in DB" "$aug" "9"
aug_days=$(sqlq "SELECT GROUP_CONCAT(DISTINCT DAY(log_date) ORDER BY DAY(log_date)) FROM logs WHERE log_date LIKE '2026-08%' AND user_id=1")
check "checked days in August 2026" "$aug_days" "3,10"

# ------------------------------------------------------------------- toggle --
app=$(curl -s -b "$JAR" -c "$JAR" "$BASE/app?v=month&m=2026-08")
grep -q '/app?v=week&amp;m=2026-08' <<< "$app" && ok "view tabs preserve the selected month" || bad "month selection lost on tab navigation"
token=$(printf '%s' "$app" | grep -o '"csrf":"[a-f0-9]*"' | head -1 | sed 's/.*:"//;s/"//')
[ -n "$token" ] && ok "app shell exposes CSRF for the API" || bad "no CSRF in app shell"

manage_page=$(curl -s -b "$JAR" "$BASE/app?v=habits")
manage_token=$(printf '%s' "$manage_page" | grep -o '"csrf":"[a-f0-9]*"' | head -1 | sed 's/.*:"//;s/"//')
created=$(curl -s -b "$JAR" -H "X-CSRF-Token: $manage_token" -d "csrf=$manage_token&name=HH_Smoke_Temporary&xp=7&freq=every_day&start_date=$(date +%F)" "$BASE/habit/save")
printf '%s' "$created" | grep -q '"ok":true' && ok "habit manager creates a database-backed habit" || bad "habit manager failed to create a habit"
test_habit_id=$(printf '%s' "$created" | sed -n 's/.*"id":\([0-9][0-9]*\).*/\1/p')
if [ -n "$test_habit_id" ]; then
  archived=$(curl -s -b "$JAR" -H "X-CSRF-Token: $manage_token" -d "csrf=$manage_token&habit_id=$test_habit_id" "$BASE/habit/archive")
  printf '%s' "$archived" | grep -q '"ok":true' && ok "habit can be archived without deleting its row" || bad "habit archive failed"
  restored=$(curl -s -b "$JAR" -H "X-CSRF-Token: $manage_token" -d "csrf=$manage_token&habit_id=$test_habit_id" "$BASE/habit/restore")
  printf '%s' "$restored" | grep -q '"ok":true' && ok "archived habit can be restored" || bad "habit restore failed"
  "${MYSQL[@]}" -e "DELETE FROM logs WHERE habit_id=$test_habit_id; DELETE FROM habits WHERE id=$test_habit_id" 2>/dev/null
else
  bad "habit manager response did not return an id"
fi

habit_id=$(sqlq "SELECT id FROM habits WHERE name LIKE '%Hydration%' AND user_id=1")
before=$(sqlq "SELECT COUNT(*) FROM logs WHERE habit_id=$habit_id AND log_date='2026-08-11'")

r1=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&habit_id=$habit_id&date=2026-08-11&checked=1" "$BASE/toggle")
after1=$(sqlq "SELECT COUNT(*) FROM logs WHERE habit_id=$habit_id AND log_date='2026-08-11'")
check "toggle ON inserts exactly one log" "$after1" "1"

r2=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&habit_id=$habit_id&date=2026-08-11&checked=1" "$BASE/toggle")
after2=$(sqlq "SELECT COUNT(*) FROM logs WHERE habit_id=$habit_id AND log_date='2026-08-11'")
check "clicking ON twice cannot duplicate (unique key)" "$after2" "1"

r3=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&habit_id=$habit_id&date=2026-08-11&checked=0" "$BASE/toggle")
after3=$(sqlq "SELECT COUNT(*) FROM logs WHERE habit_id=$habit_id AND log_date='2026-08-11'")
check "toggle OFF deletes the log" "$after3" "0"

# not-due day: Sunday 2026-08-16 for Gym (Mon–Sat) must be refused
gym_id=$(sqlq "SELECT id FROM habits WHERE name LIKE '%Gym%' AND user_id=1")
nd=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&habit_id=$gym_id&date=2026-08-16&checked=1" "$BASE/toggle")
printf '%s' "$nd" | grep -q '"ok":false' && ok "Gym on a Sunday is refused" || bad "Gym accepted a Sunday"
nd_rows=$(sqlq "SELECT COUNT(*) FROM logs WHERE habit_id=$gym_id AND log_date='2026-08-16'")
check "no log written for the non-due Sunday" "$nd_rows" "0"

# no CSRF → 419
nc=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -d "csrf=wrong&habit_id=$gym_id&date=2026-08-10&checked=1" "$BASE/toggle")
check "POST without a valid CSRF token is rejected" "$nc" "419"

# ------------------------------------------------------------------- theme --
t=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&theme=paper" "$BASE/theme")
dbtheme=$(sqlq "SELECT theme FROM users WHERE id=1")
check "theme persists in the database" "$dbtheme" "paper"
page=$(curl -s -b "$JAR" "$BASE/app")
printf '%s' "$page" | grep -q 'class="theme-paper"' && ok "paper class rendered on <html>" || bad "theme class missing"
curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&theme=ember" "$BASE/theme" >/dev/null

# ----------------------------------------------------------------- billing --
bill=$(curl -s -b "$JAR" "$BASE/billing?coupon=HECTOR20")
printf '%s' "$bill" | grep -q "₹159.20" && ok "HECTOR20 → ₹159.20 (15920 paise)" || bad "coupon price wrong"
printf '%s' "$bill" | grep -q "Razorpay TEST MODE" && ok "Razorpay sandbox mode is clearly labelled" || bad "test mode label missing"
printf '%s' "$bill" | grep -q "Payments are not configured" && ok "no Razorpay keys → pay does not pretend" || bad "pay button faked a payment"

bad_coupon=$(curl -s -b "$JAR" "$BASE/billing?coupon=NOPE123")
printf '%s' "$bad_coupon" | grep -q "not valid" && ok "unknown coupon is rejected" || bad "unknown coupon accepted"

order=$(curl -s -b "$JAR" -c "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&coupon=HECTOR20" "$BASE/billing/order")
printf '%s' "$order" | grep -q "Payments are not configured" && ok "/billing/order refuses without keys" || bad "/billing/order did not refuse"

# --------------------------------------------------------------- trial gate --
"${MYSQL[@]}" -e "UPDATE users SET trial_ends_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=1" 2>/dev/null
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/app")
loc=$(curl -s -o /dev/null -D - -b "$JAR" "$BASE/app" | grep -i '^location:' | tr -d '\r')
printf '%s' "$loc" | grep -q "/billing" && ok "expired trial: /app redirects to /billing" || bad "expired trial did not redirect ($loc)"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/billing")
check "expired trial: /billing still reachable" "$code" "200"

# paying restores access (simulate the fulfilment path in SQL, same maths)
"${MYSQL[@]}" -e "UPDATE users SET paid_until = DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE id=1" 2>/dev/null
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/app")
check "after payment: /app is 200 again" "$code" "200"
"${MYSQL[@]}" -e "UPDATE users SET paid_until = NULL, trial_ends_at = DATE_ADD(CURDATE(), INTERVAL 3 DAY) WHERE id=1" 2>/dev/null

# ---------------------------------------------------------- change password --
app=$(curl -s -b "$JAR" "$BASE/app")
token=$(printf '%s' "$app" | grep -o '"csrf":"[a-f0-9]*"' | head -1 | sed 's/.*:"//;s/"//')
wrongcur=$(curl -s -b "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&current=not-it&new=something-long" "$BASE/password")
printf '%s' "$wrongcur" | grep -q "Current password is wrong" && ok "change password: wrong current password refused" || bad "change password accepted a wrong current password"
short=$(curl -s -b "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&current=$PASS&new=short" "$BASE/password")
printf '%s' "$short" | grep -q "at least 8 characters" && ok "change password: short password refused" || bad "short password accepted"
chg=$(curl -s -b "$JAR" -H "X-CSRF-Token: $token" -d "csrf=$token&current=$PASS&new=temporary-pass-123" "$BASE/password")
printf '%s' "$chg" | grep -q '"ok":true' && ok "change password: valid change stored" || bad "valid password change failed"
# prove the new hash works, then put the original back
J2="$(mktemp)"
lp=$(curl -s -c "$J2" "$BASE/login")
t2=$(printf '%s' "$lp" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$J2" -c "$J2" -d "csrf=$t2&username=$USERNAME&password=temporary-pass-123" "$BASE/login")
check "change password: new password logs in (302)" "$code" "302"
t3=$(curl -s -b "$J2" "$BASE/app" | grep -o '"csrf":"[a-f0-9]*"' | head -1 | sed 's/.*:"//;s/"//')
curl -s -b "$J2" -H "X-CSRF-Token: $t3" -d "csrf=$t3&current=temporary-pass-123&new=$PASS" "$BASE/password" >/dev/null
rm -f "$J2"

# ------------------------------------------------------------------ logout --
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/logout")
check "logout redirects" "$code" "302"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/app")
loc=$(curl -s -o /dev/null -D - -b "$JAR" "$BASE/app" | grep -i '^location:' | tr -d '\r')
printf '%s' "$loc" | grep -q "/login" && ok "signed out: /app bounces to /login" || bad "session survived logout"

rm -f "$JAR"
echo
printf 'passed %s, failed %s\n' "$PASSES" "$FAILS"
[ "$FAILS" -eq 0 ]
