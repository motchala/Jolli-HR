# Requirements — `admin-panel`

## Summary

Add an HR/admin side to JolliHR so that HR managers can manage job listings, review applicants, verify student documents, and move applicants through the hiring pipeline. The student-facing side of the app remains unchanged.

---

## Assumptions

1. **Admin login reuses the existing `login.php`**, extended with a role check. A separate `admin/login.php` would duplicate form markup and validation logic with no real gain; a redirect from `admin/` pages to the shared `login.php` is simpler and consistent with the existing session pattern.

2. **Rejection adds a `Rejected` status** rather than hard-deleting the row. Hard deletion loses audit history and breaks the student's status stepper (the row disappears silently). A soft `Rejected` value can be added to the `applications.status` ENUM. This is a one-way terminal state: a rejected application cannot be advanced.

3. **`match_pct` is set manually** when creating or editing a job, consistent with the existing schema where it is a stored integer. Automatic calculation is out of scope.

4. **Document upload is out of scope.** The `user_documents` table already holds document records; the admin can only toggle the `verified` flag per record. File upload is a separate feature.

5. **Admin accounts are created by directly seeding the database** (or via a future admin-creation tool). No self-registration for admins.

---

## Functional Requirements

### FR-1 Admin Role
- FR-1.1 The `users` table gains an `is_admin TINYINT(1) NOT NULL DEFAULT 0` column.
- FR-1.2 A demo admin account (`admin@jollihr.test` / `admin123`) is seeded in `database.sql` with `is_admin = 1`.
- FR-1.3 On successful login via the shared `login.php`, if `is_admin = 1` the session stores `is_admin: true` alongside the existing user fields, and the redirect target becomes `admin/index.php`.
- FR-1.4 A `require_admin()` function (added to `includes/auth.php`) checks that the active session has `is_admin = true`. On failure it redirects to `login.php` and exits.
- FR-1.5 All pages under `admin/` call `require_admin()` before any output.

### FR-2 Admin Dashboard (`admin/index.php`)
- FR-2.1 Display four summary cards: total applicants, applicants with at least one unverified document (pending docs), applicants in Training status, applicants in Hired status.
- FR-2.2 Display a "Recent Applications" list showing the 10 most recent application rows, with applicant full name, job title, current status, and date applied.
- FR-2.3 Each row in the recent list links to the applicant detail page.

### FR-3 Applicant List (`admin/applicants.php`)
- FR-3.1 Display all applications in a table with columns: applicant name, email, job title, current status, date applied.
- FR-3.2 Provide a status filter (All, Applied, Docs Verified, Training, Hired, Rejected) that reloads the page with a `?status=` query parameter.
- FR-3.3 Each row links to the applicant detail page (`admin/applicant.php?id={application_id}`).

### FR-4 Applicant Detail (`admin/applicant.php?id={application_id}`)
- FR-4.1 Display the applicant's full name, email, and university profile fields (university, programme, year level, available days, preferred shift, max hours/week).
- FR-4.2 Display all `user_documents` rows for the applicant, each showing document name and verified status, with a toggle button to flip `verified` (0↔1). Each toggle is a POST form that follows PRG.
- FR-4.3 Display the current application status and a stepper control that allows advancing to the next stage (Applied → Docs Verified → Training → Hired) or rejecting the application. Each action is a POST form that follows PRG.
- FR-4.4 Rejection is a one-way terminal action: once an application is set to `Rejected`, the advance/reject form is hidden and replaced with a "Rejected" label.
- FR-4.5 After a status change or document toggle, a flash message confirms the action.

### FR-5 Jobs Management (`admin/jobs.php`)
- FR-5.1 Display all jobs in a list with title, branch, hours, distance, match_pct, and Edit/Delete actions.
- FR-5.2 Provide an "Add New Job" form (title, branch, tags, hours, distance, match_pct). On submit (POST) it inserts the row and redirects (PRG).
- FR-5.3 Each job has an Edit action that pre-fills the same form fields for that job. On submit it updates the row and redirects (PRG).
- FR-5.4 Each job has a Delete action (POST form with confirmation via `onclick` prompt in JS). On submit it deletes the row and redirects (PRG).
- FR-5.5 Deleting a job that has active applications is allowed; the `ON DELETE CASCADE` constraint in the schema removes the linked applications automatically.

### FR-6 Admin Layout
- FR-6.1 All admin pages use a dedicated `admin/includes/admin_header.php` that renders a top navigation bar (not the student bottom nav).
- FR-6.2 The admin top nav shows links: Dashboard, Applicants, Jobs, and a Logout link.
- FR-6.3 All admin pages use a dedicated `admin/includes/admin_footer.php` that closes the HTML structure and includes `assets/js/app.js`.
- FR-6.4 Admin pages link stylesheets using a relative path to `../assets/css/style.css` and a new `../assets/css/admin.css` for admin-specific overrides.

---

## Non-Functional Requirements

- **NFR-1 Security:** All output in admin pages is escaped with `htmlspecialchars`. All writes use parameterized PDO queries via the `db()` helper. Admin actions are protected by `require_admin()` on every page.
- **NFR-2 Consistency:** Admin pages follow the same POST-redirect-GET pattern already in `jobs.php` and `home.php`.
- **NFR-3 Schema safety:** The `is_admin` column is added via `ALTER TABLE … ADD COLUMN IF NOT EXISTS` in `database.sql` so re-importing is idempotent, consistent with the existing `IF NOT EXISTS` pattern.

---

## Acceptance Criteria

1. Visiting any `admin/` page while not logged in (or logged in without `is_admin`) redirects to `login.php`.
2. Logging in with the demo admin credentials (`admin@jollihr.test` / `admin123`) sets `$_SESSION['user']['is_admin'] = true` and lands on `admin/index.php`.
3. The dashboard shows four cards with non-negative integer counts drawn from the live database.
4. The dashboard Recent Applications list shows at most 10 rows, ordered by `applied_at DESC`.
5. The applicant list shows all applications; selecting a status filter from the dropdown reloads the page and shows only applications matching that status.
6. The applicant detail page shows all `user_documents` rows for the user; clicking the verify toggle changes `verified` in the database and reloads with a flash confirmation.
7. Advancing an application status from the detail page moves it to the next ENUM value and displays a flash confirmation.
8. Rejecting an application sets `status = 'Rejected'`; the advance/reject form is no longer shown on subsequent loads of that page.
9. Adding a job via the admin jobs page inserts a row in `jobs` and shows the new job in the list on redirect.
10. Editing a job updates the existing row; deleting a job removes it and its applications (cascade).
11. All admin pages use the top-nav admin layout; the student bottom nav is absent on admin pages.
12. All form inputs are validated server-side; missing required fields re-display the form with an inline error message.

---

## Out of Scope

- Document file upload or storage.
- Admin user creation UI (seeded via SQL only).
- Password reset or email flows.
- Pagination on the applicant list (deferred; list is acceptable for demo scale).
- Automated match percentage calculation.
- Role levels beyond "student" and "admin" (e.g., branch manager).
- Audit log of admin actions.
