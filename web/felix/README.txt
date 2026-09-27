Check-in / Check-out Roster — PHP + multi-file CSV version
=============================================================

Files:
  index.php          the app
  list_files.php      scans data/ and returns the dated CSV files as tabs
  get_roster.php      reads one data/<file>.csv + its saved session
  save_roster.php     writes one data/<file>.csv + its session, on every change
  data/               one CSV per project, plus meta.json for session state

  data/2026-09-24-project1.csv
  data/2026-09-25-project2.csv
  data/2026-09-26-project3.csv
  data/meta.json      { "filename.csv": {"session": "checkin"} , ... }

Requires a PHP-capable web server — double-clicking index.php in a
browser will not work.

Quickest way to run it:
  1. Open a terminal in this folder (the one containing index.php).
  2. Run:  php -S localhost:8000
  3. Open http://localhost:8000/ in your browser.

Any PHP host works (XAMPP, MAMP, a real server, etc.) — just keep the
folder structure intact, and make sure data/ and its files are
writable by the web server (needed to save changes).

Multiple files / tabs
----------------------
Every CSV in data/ named like YYYY-MM-DD-projectname.csv shows up as a
tab at the top, sorted by date, labeled e.g. "Sep 24 · Project1". Click
a tab to switch to that file — the whole table, search, and
check-in/check-out session state switch with it.

Each file remembers its own state independently:
  - every row's status (arrived/late/cancelled/allergy/time/reason/notes)
  - which session it's in (check-in or check-out) — shown as a small
    dot on its tab (grey = check-in, blue = check-out)

Adding a new project: just drop another CSV into data/ named
YYYY-MM-DD-something.csv with the same 11-column header used by the
others:

  id,name,allergy,cls,time,arrived,late,cancelled,reason,notes,checkedOut

It'll appear as a new tab next time the page loads (no restart needed).

Persistence
-----------
Any change — Arrived, Late, Cancelled, Diet, Save note, Check out, or
switching check-in/check-out — is saved immediately to that file's CSV
(and meta.json for the session). Reloading the page, switching tabs,
or restarting the server all pick up right where you left off.
