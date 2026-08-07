# Aleta Work Tracker

A simple, staff-friendly front-end for **Zoho Projects**. Staff log in with their Zoho email
+ a password we set, work with Tasks / Task lists / Time logs, and the app syncs everything to
Zoho Projects (manual button + every 12h) so **Zoho Analytics keeps working unchanged**.

- **Stack:** plain PHP + MySQL (PDO) — runs on XAMPP locally and GoDaddy cPanel in production.
- **System of record:** this app's DB. Zoho Projects stays in sync as the reporting engine.
- **Auth:** local users table (Zoho email as username, password we set, hashed).

## Project layout
```
aleta-worktracker/
  config/    config.php (local secrets), config.sample.php
  sql/       schema.sql
  src/       bootstrap.php (config + DB + helpers), [more added per part]
  public/    web root — index.php (health), [login/dashboard/tasks... per part]
  cron/      12h sync entry (added in Part 6)
```

## Local setup (XAMPP — already installed at C:\xampp)

1. **Start MySQL** (and Apache) from the XAMPP Control Panel.
2. **Create the database + tables.** Easiest via phpMyAdmin:
   - Open http://localhost/phpmyadmin → **New** → database name `aleta_worktracker` → Create.
   - Select it → **Import** → choose `sql/schema.sql` → Go.
   - *(Or command line:)*
     ```
     C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS aleta_worktracker CHARACTER SET utf8mb4"
     C:\xampp\mysql\bin\mysql.exe -u root aleta_worktracker < sql/schema.sql
     ```
3. **Run the app** with PHP's built-in server (keeps it inside this folder, no htdocs copy needed):
   ```
   C:\xampp\php\php.exe -S localhost:8000 -t public
   ```
4. Open **http://localhost:8000** — you should see the health page, all green.

## Config
`config/config.php` holds DB + Zoho settings. Local defaults match XAMPP (root / no password).
The Zoho **Projects** refresh token is added in Part 3.

## Build progress
- [x] Part 1 — skeleton, schema, DB, health page
- [x] Part 2 — staff login (Zoho email + password), CSRF, route guard, admin seed + `tools/create_user.php`
- [x] Part 3 — Zoho Projects API v3 client (`src/zoho.php`), cached token, live read verified (54 projects) via `tools/zoho_test.php`
- [x] Part 4 — sync-in (`src/sync_in.php`, `tools/sync_in.php`): projects + task lists + tasks into local DB, assignee mapping; screens `projects.php`, `project.php`, `tasks.php`. Included: name/project/list/assignee/status/priority/due/%/description. Omitted: milestones, recurrence, work-hours, followers, tags, dependencies, docs.
- [x] Part 5 — roles & permissions (`src/perms.php`, `public/task_action.php`): staff add+complete tasks; delete/reopen admin-only (server-enforced). Add-task form + role-aware action buttons on `project.php`; open-only default view. 6/6 tests pass (`tools/verify_perms.php`).
- [x] Part 6 — templates: `project_templates` + `template_task_lists`, seeded "PhD Research Project" (9 lists) via `tools/seed_templates.php`. Admin creates project from template at `new_project.php` (＋ New Project on projects list).
- [x] Part 7 — Journals module (anomaly fix): `journals` + `journal_checklist` (+ defaults). `journals.php` (list/add), `journal.php` (edit + pre/post checklist), `journal_action.php`. Each journal = one card (name/indexing/IF/deadline/fee/url/notes + stage + result + checklist). Staff add/edit/tick; admin-only delete. 8/8 tests pass (`tools/verify_journals.php`).
- [x] UI rebuild + **theming system** (`assets/style.css` driven by data-look/data-palette/data-font on <body>; helpers in `src/bootstrap.php`: theme(), theme_body_attrs(), theme_font_links()). 3 LOOKS (clay/glass/liquid) × 10 COLOUR templates × 8 FONTS (small→big) = 240 combos. Admin-only `appearance.php` with live preview + global save to settings (theme_look/theme_palette/theme_font). All 3 looks screenshot-verified readable. `index.php` redirects to app; health at `health.php`.
- [ ] Part 8 — deploy to GoDaddy (cPanel: create DB, import schema+migration+seed, upload files, point subdomain docroot to /public, fill config)
- [ ] Add the 9 real staff users

## Direction (updated): app runs INDEPENDENT of Zoho
Zoho was the source of the template content (already pulled). App is now its own system.
- Template = the 9 research task lists (Client Interaction … Journal Submission).
- The "journal anomaly": Zoho's Journal Submission list = ~420 identical Pre/Submit/Post tasks with no journal names → being replaced by a dedicated **Journals module** (each journal = one named card: details + stage + pre/post checklist).
- Next: Part 6 templates (create project from template), Part 7 Journals module, Part 8 GoDaddy deploy.
- [ ] Part 5 — Time logging
- [ ] Part 6 — Sync engine (manual + 12h)
- [ ] Part 7 — Deploy to GoDaddy
