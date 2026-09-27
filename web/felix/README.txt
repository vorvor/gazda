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

Prototype login
---------------
The browser displays an HTTP Basic username/password prompt. Demo users
are Elek and Kecsu, both with the presentation password 12345. Usernames
are case-sensitive. The roster greets the authenticated user at the top.
The page and all three PHP data endpoints require authentication.
Use "Log out" beside the greeting (also available in log views) to sign out.
Pending saves finish before logout. You are then shown a username/password
form, which can be used to sign in as either demo user. A server-side session
marker prevents cached browser Basic credentials from silently signing you
back in. Logout and form login require a CSRF token; use HTTPS remotely.

These accounts are for mockup presentations only, not production use.
Use HTTPS for remote presentations. Apache's .htaccess blocks direct
CSV/JSON/tmp downloads; PHP's built-in local server ignores .htaccess,
so use only mock data with that local development server.

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

Per-project audit logs
----------------------
Every saved action appends to logs/<project-filename-without-.csv>-log.csv.
For example: logs/2026-09-24-project1-log.csv. Each file is created on
that project's first saved action; historical actions are not backfilled.
The web-server user needs permission to create/write the logs/ directory.

Columns: event_id, timestamp, user, project, action, person_id,
person_name, field, old_value, new_value.

The user is taken from server authentication, never from a submitted name.
Timestamps use Europe/Budapest time, including the UTC offset and microseconds.
Actions cover arrived, late, cancelled, checkout, diet, flag, notes, session,
undo, and generic saves. Each changed field has its own CSV row; rows from
one action share an event_id. Repeated saves without changes use no_change.
Boolean values are 0/1. UNDO appends its own before/after entries; it never
removes earlier log entries. Searches, selections, and other unsaved UI
interactions are not audit events.

CSV quoting preserves commas, quotes, and multiline notes. A leading
apostrophe protects values beginning with spreadsheet formula characters.
Logs contain personal data: Apache blocks direct CSV downloads through
.htaccess; the PHP development server does not. Use mock data locally.
Saves are serialized and report a failure if the audit cannot be written.
These CSV files are prototype audit records, not a tamper-proof database.

General audit log (all dates)
-----------------------------
New saved actions are also appended to logs/general-log.csv, alongside
the per-project log. Both contain identical event IDs, timestamps, users,
project names and field changes, including undo and no-change events.
The general log starts with the first save after this feature is enabled;
older entries remain in their project logs and are not copied automatically.
If either log cannot be written, the save reports a failure and reported
write failures roll back both logs rather than leaving a one-sided event.

Use "All logs" for the general view, with a Project / date column and
newest records first. "View log" still shows only the selected project.
"Back to roster" returns without resetting the current roster or undo history.
Both views require authentication and do not modify log files.
