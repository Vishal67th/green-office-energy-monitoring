# Setup Guide — Online Green Office Energy Monitoring System

Plain PHP + MySQL. No build step, no package manager, no framework.

## What the project does

The office electricity meter is read once a month and the reading is entered into the app.
From those readings it derives:

- energy used since the previous reading (kWh)
- the CO₂ that energy represents (kg)
- whether usage rose or fell compared with the month before

## Files

| File | Purpose |
|---|---|
| `index.php` | the page — summary boxes, table, chart |
| `conn/conn.php` | database credentials |
| `endpoint/add-reading.php` | saves a new meter reading |
| `endpoint/delete-reading.php` | deletes a meter reading |
| `message.php` | the styled page used for errors that stop a request |
| `green_office_db.sql` | creates the database and table |
| `check.php` | self-test for the calculations (optional) |

---

## Step 1 — Install XAMPP

https://www.apachefriends.org

XAMPP bundles the four pieces this project needs: Apache, PHP, MySQL, and phpMyAdmin.

Web root:

- **Windows:** `C:\xampp\htdocs`
- **macOS:** `/Applications/XAMPP/xamppfiles/htdocs`

> **macOS:** this machine has a Homebrew MySQL on the same port XAMPP's MySQL wants.
> Stop it first:
> ```bash
> brew services stop mysql
> ```

## Step 2 — Copy the project into the web root

```
htdocs/green-office-energy-monitoring/
├── index.php
├── check.php
├── message.php
├── green_office_db.sql
├── conn/conn.php
└── endpoint/
    ├── add-reading.php
    └── delete-reading.php
```

macOS:

```bash
cp -R ~/Desktop/green-office-energy-monitoring /Applications/XAMPP/xamppfiles/htdocs/
```

The folder can be renamed — nothing in the code depends on its name.

## Step 3 — Start Apache and MySQL

Start both from the XAMPP Control Panel (Windows) or Manager (macOS).

http://localhost should now show the XAMPP page.

## Step 4 — Create the database

http://localhost/phpmyadmin → **Import** → **Choose File** → `green_office_db.sql` → **Import**

The one import creates the `green_office_db` database and the `tbl_reading` table, and
loads six months of sample readings.

Terminal equivalent:

```bash
mysql -u root < green_office_db.sql
```

## Step 5 — Confirm the credentials

`conn/conn.php`, currently set to the XAMPP defaults:

```php
$servername = "localhost";
$username   = "root";
$password   = "";          // empty
$db         = "green_office_db";
```

Change `$password` if your MySQL `root` user has one.

## Step 6 — Open the app

http://localhost/green-office-energy-monitoring/

Three summary boxes across the top, table on the left, bar chart on the right.

Smoke test:

1. **+ Add Meter Reading**
2. Date `2026-10-01`, reading `17050`, **Save**
3. New row appears with "Used" = `670`, and a new bar on the chart
4. Red **X** on that row removes it

## Step 7 — Self-test (optional)

http://localhost/green-office-energy-monitoring/check.php

Prints `OK - all checks passed`. It uses fixed sample numbers and does not touch the database.

---

## Operating notes

Enter **the cumulative total shown on the meter**, not the amount used that month — the app
does the subtraction. Entering monthly amounts produces incorrect usage figures.

The emission factor is one line near the top of `index.php`:

```php
$CO2_PER_KWH = 0.71;   // kg CO2 per kWh — India grid average
```

The monthly target sits on the next line:

```php
$MONTHLY_TARGET_KWH = 900;   // the office aims to stay under this each month
```

Months above it are shown in red in the Used column, a dashed red line marks it on the chart,
and the fourth summary box says how far the latest month came in over or under.

Readings are matched by date, so they can be entered out of order.

A meter only counts upwards, so a reading that would break that sequence is refused when
saving — either lower than the reading before its date, or higher than the reading after it.
Readings that are already stored and go backwards (a replaced meter, or rows loaded straight
into the database) are marked **check** in the Used column, left out of the totals and the
chart, and counted in a warning above the table.

---

## Troubleshooting

| Message / symptom | Cause |
|---|---|
| `Could not connect to the database: ... [1049] Unknown database` | Step 4 not completed |
| `Could not connect ... [1045] Access denied for user 'root'` | wrong password in `conn/conn.php` |
| `Could not connect ... [2002] Connection refused` | MySQL not running (macOS: Homebrew MySQL holding the port) |
| `Base table 'tbl_reading' doesn't exist` | import failed part-way; re-run it |
| PHP source shown as text | opened as a file instead of through `http://localhost/...` |
| Blank page | error display off — see `xamppfiles/logs/php_error_log` (macOS) or `xampp\php\logs\php_error_log` (Windows) |
| Table renders, chart missing | Chart.js, Bootstrap and the font load from CDNs — requires an internet connection |
| Port 80 in use | change Apache's port to 8080 in XAMPP, then use `http://localhost:8080/...` |
| `A meter cannot count down...` when saving | the reading is lower than an earlier one — check the number, or delete the earlier row if it was the mistake |
| `The reading after this date is...` when saving | the date is being back-filled and the number is too high for that slot |

## Without XAMPP (macOS, Homebrew)

```bash
brew install php
brew services start mysql
mysql -u root < ~/Desktop/green-office-energy-monitoring/green_office_db.sql
php -S localhost:8000 -t ~/Desktop/green-office-energy-monitoring
```

http://localhost:8000

---

## Not implemented

Scoped out to keep the codebase small:

- authentication / user accounts
- multiple meters, per-room or per-department breakdown
- monthly targets or threshold alerts
- editing a reading (delete and re-add instead)
- duplicate dates — two readings on the same day are allowed
- resuming automatically after a meter replacement; the affected row stays marked as `check`
