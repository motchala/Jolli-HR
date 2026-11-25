# Implementation Tasks

## Task List

- [x] 1. Extend `database.sql` with new tables and seed data
  - Add `CREATE TABLE IF NOT EXISTS` DDL for `user_profiles`, `user_documents`, `training_modules`, `user_training`, `class_slots`, `work_shifts`, `mood_entries`
  - Add `INSERT IGNORE` seed rows for the 5 training modules
  - Add `INSERT IGNORE` seed rows for the demo account (`shamea@jollihr.test`) covering `user_profiles`, `user_documents`, `user_training`, and `work_shifts`
  - Verify the auto-bootstrap in `db.php` picks up the new tables without manual import
  - _Requirements: 8.1–8.9_

- [x] 2. Rewrite `profile.php` — read real data
- [x] 3. Add profile preference update form (`profile.php` POST)
- [x] 4. Add mood check-in persistence (`profile.php` POST)
- [x] 5. Rewrite `training.php` — read real module data
- [x] 6. Add mark-complete action (`training.php` POST)
- [x] 7. Rewrite `schedule.php` — read real schedule data
- [x] 8. Add class slot form (`schedule.php` POST)
- [x] 9. Add select-shift and swap actions (`schedule.php` POST)
- [x] 10. Smoke-test the full flow with the demo account
