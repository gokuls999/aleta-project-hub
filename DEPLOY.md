# Deploying to GoDaddy (cPanel shared hosting)

Runs as its own app in its own folder + its own database. Your existing site is untouched.
Recommended layout: a **subdomain** whose document root is this app's `public/` folder.

> Upload ONLY the `aleta-worktracker` folder. Do NOT upload the sibling MCP folders
> (`zoho-inventory-mcp`, `zoho-creator-mcp`) or `.mcp.json` — those are local tools.

---

## 1. Create the subdomain
cPanel → **Domains → Subdomains** (or **Domains**):
- Subdomain: `work` → `work.YOURDOMAIN.com`
- **Document Root:** set to `worktracker/public`  ← important (points at `public/`, keeps `config/` + `src/` above web root)

## 2. Upload the files
cPanel → **File Manager** → go to your home dir (e.g. `/home/USER/`):
1. Create a folder `worktracker`.
2. Zip the local `aleta-worktracker` folder, upload the zip into `worktracker`, then **Extract** so you have:
   ```
   /home/USER/worktracker/public/     ← subdomain doc root (from step 1)
   /home/USER/worktracker/src/
   /home/USER/worktracker/config/
   /home/USER/worktracker/sql/
   ```
   (If the zip extracts an extra nested folder, move the inner contents up so `public` sits directly in `worktracker`.)
3. (FTP works too — FileZilla to the same paths.)

## 3. Create the database
cPanel → **MySQL Databases**:
1. **Create New Database** → e.g. `worktracker` (real name becomes `USER_worktracker`).
2. **Add New User** → e.g. `wtuser` + a strong password.
3. **Add User To Database** → grant **ALL PRIVILEGES**.
4. Note the final DB name, user, and password.

## 4. Import the data
cPanel → **phpMyAdmin** → select your new DB → **Import** → upload **`sql/deploy_full.sql`** → **Go**.
This loads all 12 tables with every project, task, user, journal, and your theme in one shot.

## 5. Configure
In File Manager, open `config/`:
1. Rename `config.production.php` → `config.php` (overwrite the existing one).
2. Edit it: set the **db** name/user/pass from step 3, and **base_url** to `https://work.YOURDOMAIN.com`.
   (Leave the whole **zoho** block unchanged.)

## 6. PHP version
cPanel → **Select PHP Version** → choose **8.0 or 8.1**. Ensure extensions **pdo_mysql** and **curl** are ticked (usually default).

## 7. SSL + go live
- cPanel → **SSL/TLS Status** → run **AutoSSL** for the subdomain (free HTTPS).
- Visit **https://work.YOURDOMAIN.com** → you'll get the login.
- Sign in as admin: `dr.amalmr@aletasoftwarelabs.in` / `Aleta@2026` (change this password after first login).

---

## Notes & gotchas
- **`public/index.php`** redirects to login/dashboard, so the subdomain root just works.
- **`health.php`** (`https://work.YOURDOMAIN.com/health.php`) shows a diagnostics page — handy if something's off (it reports DB connection + tables).
- **Timeouts:** never run `tools/sync_in.php` via browser on shared hosting (it's a 2-min job). The DB import in step 4 already brings all the data. If you later want a fresh Zoho pull, do it locally and re-import, or set it up as a cron job.
- **Security:** because the doc root is `public/`, the `config/` file (with DB + Zoho secrets) is NOT web-accessible. Keep it that way — don't move config into `public/`.
- **Attaching under the existing site instead of a subdomain?** Put the folder inside the site and point people at `.../worktracker/public/`. A subdomain is cleaner; ask me if you must use a subfolder and I'll give the `.htaccess` variant.
