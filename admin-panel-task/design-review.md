# Design Review — `admin-panel` (Revision 2)

**Verdict: CHANGES_REQUESTED**

---

## Prior Findings Status

| ID | Severity | Title | Status |
|----|----------|-------|--------|
| R1-F1 | HIGH | `session_start()` double-call | Closed |
| R1-F2 | HIGH | `require_admin()` relative redirect path | Closed |
| R1-F3 | HIGH | `MODIFY COLUMN` falsely claimed idempotent | Closed |
| R1-F4 | MEDIUM | Flash mechanism unspecified | Closed |
| R1-F5 | MEDIUM | Logout href wrong path | Closed |
| R1-F6 | MEDIUM | Edit-job JS unspecified | Closed |
| R1-F7 | MEDIUM | `h()` per-page redeclaration risk | Closed |
| R1-F8 | MEDIUM | Advance-status no-op missing flash | Closed |
| R1-F9 | NIT | Placeholder hash in seed SQL | Closed |
| R1-F10 | NIT | Nav hrefs resolving to wrong depth | Closed |
| R1-F11 | NIT | `match_pct` validation lacks range check | Closed |
| R2-F1 | HIGH | Already-logged-in admin redirected to student `home.php` | Closed |
| R2-F2 | HIGH | `h()` in `jobs.php` has no `function_exists` guard; design ambiguity | Closed |
| R2-F3 | MEDIUM | Flash CSS class conflicts with existing `.flash`/`.flash.ok` | Closed |
| R2-F4 | MEDIUM | `toggle_doc` POST handler: no flash on invalid/missing `doc_id` | Closed |
| R2-F5 | NIT | `form-err` description incorrectly states `border-left` | Closed |
| R2-F6 | NIT | `BASE_PATH` hardcoded value undocumented | Closed |

---

## New Findings

### Finding 1 — HIGH: `.flash` error state renders invisibly

**Location:** Design § Flash Message Mechanism — Rendering snippet; Design § Admin Layout — `admin.css`; `assets/css/style.css` lines 1159–1170

**Problem:** The `.flash` base class in `style.css` provides only layout properties: `display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 14px; font-size: 14px; font-weight: 500; margin-bottom: 14px`. It has **no `background` and no `color`** declaration. `.flash.ok` adds `background: #dcf5e3; color: var(--green)`, but there is no `.flash.err` or equivalent rule. When the design renders an error flash (`type=err`) using only the `.flash` class with no modifier, the div has transparent background and inherits text color — in practice the error message is invisible against the page background. Admin users who trigger a document validation failure, a database error, or any other error condition receive zero visual feedback.

**Fix (choose one of two):**

Option A — Add `.flash.err` to `admin.css`:
```css
.flash.err {
    background: #ffe8e6;
    color: #c4141f;
}
```
Then update the render snippet to emit `class="flash err"` for the error case:
```php
<div class="flash <?= $flash['type'] === 'ok' ? 'ok' : 'err' ?>">
```

Option B — Use the existing `.form-err` class for error flashes instead of `.flash`:
```php
<div class="<?= $flash['type'] === 'ok' ? 'flash ok' : 'form-err' ?>">
```
`.form-err` in `style.css` has `background: #ffe8e6; color: #c4141f; border-radius: 12px; padding: 10px 14px` — it is already visually styled for errors. The design must prescribe exactly one option; both are unambiguous fixes.

---

### Finding 2 — MEDIUM: Design contradicts FR-6.3 on `app.js` inclusion

**Location:** Design § Admin Layout — `admin_footer.php`; Requirements FR-6.3

**Problem:** FR-6.3 explicitly states: *"All admin pages use a dedicated `admin/includes/admin_footer.php` that closes the HTML structure and **includes `assets/js/app.js`**."* The design specifies `admin_footer.php` as: *"Closes `</main></div>`, includes `<script src="../../assets/js/admin.js"></script>`, and closes `</body></html>`."* `app.js` is absent. The implementor is left with a direct conflict between the requirements document and the design document. (Note: `app.js` is student-facing — job filters, tabs, mood check-in — and its DOM hooks will silently no-op on admin pages because the target elements are absent, so including it is harmless. But the conflict still needs resolution.)

**Fix:** Add one sentence to the `admin_footer.php` section:
> `admin_footer.php` includes **both** `admin.js` and `app.js` per FR-6.3. Because `app.js` only hooks onto DOM elements absent from admin pages (`[data-tabs]`, `[data-chips]`, `.job`, etc.), it has no effect there, but its inclusion satisfies the requirement.

Then update the snippet:
```html
<script src="../../assets/js/app.js"></script>
<script src="../../assets/js/admin.js"></script>
</body>
</html>
```

---

### Finding 3 — NIT: `tags` silent truncation can silently corrupt data

**Location:** Design § admin/jobs.php — Input validation table, `tags` row

**Problem:** The validation table says tags `> 255 chars` is handled by "Silently truncate". The `jobs.tags` column is `VARCHAR(255)`. Silently discarding user input without feedback creates invisible data loss. For a demo it is low risk, but the prescribed behavior ("silently") means no error is shown and no flash is written — the user does not know their tags were cut.

**Fix:** Either accept the silent truncate and add a note justifying it ("acceptable for demo scale; tags are display-only"), or change the on-fail column to "Re-show form + error" and add `strlen($v) <= 255` as the check with an error message.

---

## Verified Assumptions

The following design claims were checked against the actual source files and confirmed correct.

- **`session_start()` is unguarded in `auth.php`** (line 2: bare `session_start()`). The design correctly identifies this and prescribes the `session_status()` guard. ✓
- **`login.php` early-exit guard sends all users to `home.php` unconditionally** (lines 5–6). The design prescribes extending it to branch on `is_admin`. ✓
- **`login.php` SELECT omits `is_admin`** (line 13: `SELECT id, first_name, last_name, password_hash`). The design prescribes adding it. ✓
- **`jobs.php` defines `h()` at file scope without a guard** (line 26: `function h($s)`). The design correctly prescribes removing it entirely and explaining why adding a guard there fails at runtime. ✓
- **`database.sql` does not yet contain `is_admin`, `Rejected` ENUM value, or the admin seed row.** The design's ALTER TABLE and INSERT IGNORE statements add these. ✓
- **`database.sql` applications ENUM is `('Applied','Docs Verified','Training','Hired')`** — no `Rejected`. The MODIFY COLUMN statement in the design adds it correctly. ✓
- **`.flash` class in `style.css` has no background/color in its base rule** (lines 1160–1170). The `.flash.ok` modifier adds green styling; there is no corresponding `.flash.err`. The design's flash error path uses `.flash` alone, which is unstyled. ✗ (Finding 1 above.)
- **`.form-err` in `style.css` has `background: #ffe8e6; color: #c4141f`** — no `border-left`. The revised design correctly makes no claim about `border-left`. ✓
- **`BASE_PATH` constant is not defined anywhere in `includes/db.php`**. The design prescribes adding it with the noted comment. ✓
- **`db_bootstrap()` reads and executes `database.sql` statements**, so the ALTER TABLE and INSERT IGNORE rows in the design will run automatically on a cold boot. ✓
- **`match_pct` column is `TINYINT UNSIGNED` (0–255)**. The design's `FILTER_VALIDATE_INT` with `min_range: 0, max_range: 100` correctly enforces the business rule. ✓
- **`app.js` contains only student-facing DOM hooks** (job filter chips, tabs, mood check-in, signup validation). None of these elements will be present on admin pages. ✓ (Harmless to include, but requirement conflict must be resolved — Finding 2.)

## Unverified / Wrong Assumptions

- **Admin bcrypt hash** (`$2y$10$cZS4ryhi8HBg3.5hfVjWUePbmJUH812ExaMedtbAJIZQZLTUmZJYS`): claimed to be a real bcrypt hash of `admin123`. Not verifiable at design-review time without running `password_verify`. The design correctly instructs QA to verify it. Treat as unverified until tested.
- **`.flash` base class styled for error use** — wrong. The base class has no background or color; error flashes are invisible. (Finding 1.)
