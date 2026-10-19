# KaziLink — Job Seeking Platform

A fully functional MVP job board built with **HTML, CSS, JavaScript, PHP and MySQL**, covering the three user roles from the project brief: **Job Seekers**, **Employers**, and an **Admin**.

Every flow described below has been built and tested end-to-end against a real MySQL database during development (registration, login, CV upload, applying for jobs, employer review/shortlisting, admin approval, account suspension, and the reports/complaints feature).

---

## 1. What's included

```
kazilink/
├── index.php               Landing page (hero, search, latest jobs, categories, how-it-works)
├── jobs.php                 Public job search with filters (keyword, location, category, type, experience)
├── job-details.php          Single job page (apply, save, link to report a problem with the listing)
├── login.php / register.php / logout.php   (split layout: logo left, form right)
├── forgot-password.php      Request a password-reset link
├── reset-password.php       Choose a new password from the emailed link
├── config/database.php      Edit this file with your MySQL credentials
├── database/kazilink.sql    The ONE database file: creates the `kazilink` database, all tables and demo data — import this first
├── includes/                Shared PHP: DB connection, auth/session, helpers, header/footer, sidebars,
│                            auth-brand.php (the logo panel on the login / sign up pages)
├── assets/css/style.css     Full site styling (single stylesheet)
├── assets/js/main.js        Mobile nav, flash messages, skill picker, confirm dialogs, show/hide password
├── assets/images/           logo.png (login / sign up logo panel) and home-bg.jpg (homepage background)
├── assets/uploads/          Where CVs, profile photos and company logos are stored
├── seeker/                  Job seeker dashboard: profile, education, experience, skills,
│                            CV/resume, search & apply, application tracking, saved jobs, notifications,
│                            complaints (send a complaint, read the admin's reply)
├── employer/                Employer dashboard: company profile, post/edit/close jobs,
│                            view applicants & CVs, shortlist/reject, notifications
└── admin/                   Admin panel: dashboard/stats, manage seekers & employers (suspend),
                             manage jobs (approve/reject/delete), manage categories,
                             view all applications, reports & complaints
```

### Database schema
`database/kazilink.sql` creates all 14 tables from the original spec (`users`, `job_seekers`, `employers`, `job_categories`, `jobs`, `applications`, `resumes`, `skills`, `job_seeker_skills`, `education`, `work_experience`, `saved_jobs`, `notifications`, `admin_actions`) plus two small additions:

- **`reports`**: the complaints. Job seekers send them from **Complaints** in their dashboard (about a job listing, an employer, or KaziLink itself) and the admin answers them under **Reports & complaints**. It stores who sent it, what it is about, the subject, the details, a status (open / reviewed / dismissed) and the admin's reply. Two flags, `deleted_by_seeker` and `deleted_by_admin`, record who has deleted it from their list (the row is removed for good once both have). It wasn't in the original 14-table list, but the feature can't work without somewhere to store it. A database imported before this feature gets the few new columns added automatically the first time one of the complaints pages opens, so you don't need to re-import.
- **`password_resets`** — one-time "forgot password" links (see section 4b). Already on an older copy of the database? You don't need to re-import anything (that would wipe your data): the app creates this table by itself the first time someone uses "Forgot password".
- `jobs.experience_level` was added as a column so the "filter jobs by experience level" requirement has something to filter on.

---

## 2. Setup instructions (XAMPP / WAMP / MAMP / plain LAMP)

**You'll need:** PHP 8.0+, MySQL/MariaDB, and a web server (Apache is easiest via XAMPP/WAMP).

1. **Copy the project** into your server's web root, e.g. `htdocs/kazilink` (XAMPP) or `www/kazilink` (WAMP).

2. **Create the database.** Open phpMyAdmin (or the `mysql` CLI) and import the single file `database/kazilink.sql`:
   ```
   mysql -u root -p < database/kazilink.sql
   ```
   This creates a database called `kazilink`, all 16 tables, and the demo data (demo accounts, 10 job categories, 15 common skills, one sample employer and job). It is the only database file in the project. Careful: importing it again deletes and recreates `kazilink`, which wipes its data.

3. **Set your database credentials.** Open `config/database.php` and edit if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'kazilink');
   define('DB_USER', 'root');
   define('DB_PASS', '');   // XAMPP/WAMP default is usually an empty password
   ```

4. **Make the uploads folder writable** (usually already fine on XAMPP/WAMP):
   ```
   chmod -R 755 assets/uploads
   ```

5. **Visit the app** in your browser, e.g. `http://localhost/kazilink/`.

**Used the earlier AjiraLink version?** The database is now called `kazilink` instead of `ajiralink`. Importing `database/kazilink.sql` makes a new, separate database with the demo data, so your old `ajiralink` database is left untouched and nothing is lost (the test data you entered in it is not copied across). Once you're happy, you can delete the old `ajiralink` database in phpMyAdmin. The app doesn't care what its folder is called, so the old folder name works too.

### Demo logins (seeded by kazilink.sql)

| Role        | Email                        | Password       |
|-------------|-------------------------------|----------------|
| Admin       | admin@kazilink.com           | Admin@123      |
| Employer    | hr@abacustech.co.ke           | Employer@123   |
| Job seeker  | brian.kimani@example.com      | Seeker@123     |

(These are also shown on the login page itself.)

---

## 3. Feature checklist (against the brief)

**Everyone:** show/hide password on every password field, "Forgot password?" reset by one-time link.

**Job seeker:** register/login/logout, edit profile (photo, summary, location), add education, add work experience, add skills, upload multiple CVs and set a default, search & filter jobs (location / category / job type / experience level), view job details, apply with a chosen CV + cover letter, save/bookmark jobs, view application history, track status (Applied → Under Review → Shortlisted → Interview → Accepted/Rejected), receive notifications, send complaints to the admin and read the replies, delete a complaint once it has been reviewed or dismissed (**Complaints**).

**Employer:** register/login/logout, company profile with logo, post a job (goes to "pending" for admin review), edit a job, close/reopen/delete a job, view posted jobs, view applicants, view full applicant profile & CV, change application status (shortlist/reject/etc.), notifications.

**Admin:** login, dashboard with system stats, manage job seekers (suspend/reactivate), manage employers (suspend/reactivate), manage jobs (approve/reject/delete), manage job categories, view all applications, read and reply to complaints, set their status (**Reports & complaints**, with a count of open ones in the menu), delete complaints once they are reviewed or dismissed, basic audit log of admin actions (`admin_actions` table).

---

## 4. Notes & things worth knowing

- **New jobs start as "pending"** and only appear publicly once an admin approves them from Admin → Jobs. This matches the "approve/reject job postings" requirement in the brief.
- **Passwords** are hashed with PHP's `password_hash()` (bcrypt) — never stored in plain text.
- **CSRF protection** is on every form (a hidden token checked on submit).
- **All database queries use PDO prepared statements** — no raw string concatenation into SQL.
- File uploads (CVs, photos, logos) are restricted by file type and size, and renamed on save so a user can't overwrite another user's file.
- This is a learning-project MVP: notifications are in-app only (the only email the app tries to send is the password-reset link, and only if your server can send mail), there's no payment/subscription logic, and no automated tests — if you extend it for real-world use, add email verification at sign-up, rate limiting on login and password-reset requests, and HTTPS.

---

## 4a. Logo panel (login, sign up) and homepage background

**Login and sign up** use a split-screen layout: the KaziLink logo fills the **left half** and the form sits on the **right half**. These two pages no longer have a background image. On a phone the logo becomes a banner across the top, with the form underneath.

| What | Where |
|---|---|
| The logo image | `assets/images/logo.png` |
| The panel markup (shared by both pages) | `includes/auth-brand.php` |
| The panel's colour and sizing | `assets/css/style.css`, search for `.auth-brand` |

- **About the current logo:** `logo.png` is your KaziLink logo (the yellow "K" with the figure and arrow, "KaziLink", and "Find Jobs • Grow Your Career"). The original upload had a lot of empty space around the artwork, so it was cropped to the logo itself, and its background was evened out to one exact colour so it blends into the panel.
- **To change the logo:** replace `assets/images/logo.png` with your own file of the same name (or change the `src` in `includes/auth-brand.php`). If your new image has different pixel dimensions, update the `width` / `height` attributes there too. The logo is shown as large as fits without cropping.
- **To change the panel colour:** the left panel is navy `#02173e`, which is the exact background colour of the current logo, so the image blends into the panel with no visible edge. If you use a logo with a different background colour, change that one colour under `.auth-brand` to match. A transparent PNG works with any colour.
- The forgot-password and reset-password pages keep the original centred-card style.

**Homepage background.** `index.php` sets the image on the opening tag of the hero, `<section class="hero" style="background-image: url('assets/images/home-bg.jpg');">`, with a comment above it you can find by searching for `BACKGROUND IMAGE`. To swap it, replace the URL inside `url('...')` with either:
- a link to an image hosted elsewhere (e.g. `https://yoursite.com/photo.jpg`), or
- a local file: drop your image in `assets/images/` and point to it, e.g. `url('assets/images/my-photo.jpg')`.

The dark layer that keeps the white text readable is in `assets/css/style.css` under `.hero::before` (darkest on the left where the headline sits, lighter on the right; on phones it's a flat even tint). The "Why job seekers use KaziLink" box next to the headline is frosted glass (`.hero-side`) so busy parts of a photo blur out behind it. If you swap in a calmer photo, lower the numbers in `.hero::before` (e.g. `0.96` → `0.7`) to let more of it show.

> **About the current poster:** the "Job Seekers" image has its own text built into it (headline, steps, checklist). Behind live page text that would clash, which is why it's dimmed fairly heavily. A photo *without* writing on it (just a person at a desk, say) will look much better at a lighter overlay.

Images are cached by the browser, so after swapping one, press **Ctrl + F5** to see the change.

---

## 4b. Passwords: show/hide and "Forgot password?"

**Show / hide.** Every password box (log in, sign up, reset) has a small **Show / Hide** button inside it. It's added automatically by `assets/js/main.js`, so any new password field you add to a page gets one for free.

**Forgot password — how it works.**
1. On the log-in page, **Forgot password?** → enter your email (`forgot-password.php`).
2. The app creates a one-time link that expires in **1 hour** and replaces any earlier link for that account. Only a hash of the link's secret is stored in the database (`password_resets` table), never the secret itself.
3. The link opens `reset-password.php`, where you choose a new password (6+ characters). After it's saved, the link is deleted and can't be reused, and you're sent to the log-in page.
4. The page says the same thing whether or not the email has an account, so it can't be used to find out who's registered. Suspended accounts don't get a link.

**On your own computer vs. a live website — read this before deploying.** XAMPP/WAMP usually can't send email, so when you're running locally the reset link is **shown on the page** right after you request it (click *Reset my password*). That is only safe on a private machine. On a real website, anyone who knew an email address could use an on-screen link to take over that account, so the app only shows it when the page is opened from the same computer as the server (`localhost`), and **emails** it otherwise (using PHP's `mail()`, which needs your host's mail to be set up).

Two settings in `includes/bootstrap.php` control this:

| Setting | Default | Meaning |
|---|---|---|
| `SHOW_RESET_LINK_ON_SCREEN` | `'auto'` | `'auto'` = on-screen link on localhost only; `true` = always (never on a live site); `false` = never, email only |
| `APP_BASE_URL` | `''` | Leave empty locally. On a live site set it to your address, e.g. `'https://www.yoursite.com/kazilink'`, so emailed links can't be pointed at another domain by a forged request |

Not covered (add these before real-world use): rate limiting on reset requests, and logging out other devices after a password change.

---

## 5. Changelog

**This update — deleting complaints:**
- **Delete once it is dealt with:** a complaint that is **reviewed** or **dismissed** now has a **Delete** button, on the job seeker's **Complaints** page and on the admin's **Reports & complaints** page, with an "are you sure?" box first. Open complaints can't be deleted (the server checks this too, not just the button), and nobody can delete someone else's.
- **Each person deletes their own copy:** when the job seeker deletes a complaint it leaves their list but the admin still has the record, and when the admin deletes it the job seeker keeps theirs and the reply. Once both have deleted it, the row is removed from the database for good. If the admin sets a complaint back to **open**, it shows up on the job seeker's page again. Admin deletes are written to the audit log.
- **Database:** `reports` has two new yes/no columns, `deleted_by_seeker` and `deleted_by_admin`, in `database/kazilink.sql`. A database you already imported gets them added automatically the first time a complaints page opens, so no re-import is needed.

**Earlier update — complaints and a code tidy-up:**
- **Complaints for job seekers:** a new **Complaints** page in the job seeker dashboard (`seeker/complaints.php`). Pick what it is about (a job listing, an employer, or KaziLink itself), write a subject and the details, and send it. The page also lists everything you sent, with its status and the admin's reply. "Report a problem with this listing" on a job page now opens this page with that job already chosen. To keep the admin from being flooded, one person can send 5 complaints a day.
- **Admin side:** **Reports & complaints** now shows the subject, who sent it and what it is about, lets the admin write a reply and set the status (open / reviewed / dismissed), and filter by status. Saving sends the job seeker a notification. The menu shows how many complaints are still open.
- **Code tidy-up:** every PHP page, include, the stylesheet, the script and the database file now has short, plain comments (each page starts with one line saying what it is for), and the long docblocks and banner lines are gone. Only comments changed. The code itself was checked line by line against the version before the tidy-up and is identical.

**Earlier update — renamed to KaziLink, one database file:**
- The system is now called **KaziLink** everywhere: page titles, the top bar and footer, the homepage text, welcome messages, the password-reset email, the demo admin login (`admin@kazilink.com`) and this README. The logo on the login and sign up pages is now your new KaziLink logo (the yellow "K" design).
- **One database file:** `database/kazilink.sql` is now the only SQL file. It creates a database called `kazilink` (previously `ajiralink`), all 16 tables and the demo data, and `config/database.php` points at it. The optional rename script from the previous update is gone because it's no longer needed — see section 2 if you used the earlier version.

**Earlier update (login and sign up layout):**
- **Login and sign up redesigned:** your KaziLink logo is now on the left half of the screen and the form on the right (a split-screen layout, applied from the layout idea only, nothing else was copied from the reference). The background images on these two pages are removed. The logo lives in `assets/images/logo.png` and the shared panel in `includes/auth-brand.php`. See section 4a.

**Earlier update (homepage poster, passwords):**
- **Homepage background:** the "Job Seekers — Dream, Apply, Get Hired" poster is now the homepage background (`assets/images/home-bg.jpg`, compressed from 1.9 MB to about 230 KB so the page stays fast). A dark overlay and a frosted-glass side box keep the headline and text readable over the poster's own writing — see section 4a.
- **Show / hide password** on every password field (log in, sign up, reset password).
- **Forgot password:** "Forgot password?" link on the log-in page, `forgot-password.php` and `reset-password.php`, one-time links that expire after 1 hour — see section 4b for how it behaves on localhost vs. a live site. Adds a `password_resets` table (auto-created if your database is older).

**Earlier update:**
- Added background images to the homepage, login and signup pages (the login and signup ones were replaced by the logo layout in the latest update).

**Previous update — fixing corrupted "Hi, `<br`" / "Undefined array key" text:**

If you saw literal text like `<br /><b>Warning</b>: Undefined array key ...` showing up as an employer's name or company name (e.g. "Hi, `<br`" in the top-right corner, or "About `<br`..." on a job page), here's what happened and how it's fixed:

- **Root cause:** An earlier version of `register.php` had a bug (now fixed — see below) that could let a raw PHP warning get printed directly into a form field's value. If that field was submitted as-is, the warning's own text got saved into the database as if it were the person's real name or company name. The value was then stuck there, showing up everywhere that name or company name is displayed.
- **If your data is already affected:** log in as that employer and go to **Company profile** (there's now a "Your name" field at the top, alongside company name and about-the-company) and re-type the correct name — saving overwrites the bad value. This is the fastest fix and needs no database access.
- **Underlying fix (prevents this from ever happening again):** `display_errors` is now turned **off** app-wide. PHP warnings are logged to `php-error.log` in the project root instead of being printed into the page — so even if some future edge case triggers a warning, it can no longer leak into a form field and get saved as data. (There's an `APP_DEBUG` constant at the top of `includes/bootstrap.php` you can flip to `true` if you're actively developing and want to see errors inline again.)
- **Also fixed:** the employer "Company profile" page and the job seeker "Personal details" page both had a subtler version of the original bug — if you fixed a validation error (e.g. left a required field blank) and resubmitted, some of your *other* edits could silently revert to their old saved values instead of keeping what you'd just typed. Both pages now correctly preserve everything you typed when redisplaying a form after an error.
- Employers previously had no way to edit their own contact name at all (only job seekers could). That's now added to Company profile.

**Earlier updates:**
- Post-job form now includes **Company name** and **About your company** fields, pre-filled from your profile, and saved back to it.
- CVs are served through a permission-checked `view-resume.php` endpoint with a friendly "CV not found" page for missing files or unauthorized access, instead of a raw broken link.
- Verified the app already supports many people logged in at once (each session is fully isolated via the standard PHP session cookie) — tested with three concurrent accounts and confirmed no cross-contamination.
- Fixed the original "Undefined array key" warning on the registration page's Full name/Company name fields.
- Added a "redirect back after login" flow so a guest who registers or logs in from a job page lands back on that exact job afterward.
- Fixed a similar stale-value bug in "Edit job" (category/job type/experience level reverting on a validation error).

**Verified already working (no changes needed):**
- Jobs posted by an employer start as `pending` and appear on the homepage/job search automatically once an admin approves them (Admin → Jobs → Approve).
- Guests cannot apply for jobs without an account.
- Employers can open any applicant's full profile (CV, work experience, education, skills) and change an application's status from Employer → Applicants.
- Job seekers see live status updates on both their dashboard and "My applications" page.

## 6. Suggested next steps if you extend this

- Add pagination to the admin tables (applications.php already has it — the pattern is there to reuse).
- Add a "forgot password" flow (the `functions.php` helpers make this straightforward to add).
- Add employer analytics (views per job, applicant funnel chart).
- Move file uploads to cloud storage if deploying beyond a single server.
