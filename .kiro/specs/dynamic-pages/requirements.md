# Requirements Document

## Introduction

Three pages in JolliHR are currently hardcoded with static demo data: **Profile** (`profile.php`), **Training** (`training.php`), and **Schedule** (`schedule.php`). This feature replaces every hardcoded value with database-backed data stored per user, adds the missing DB tables, and wires up the existing PHP/MySQL stack (PDO, `db()`, XAMPP) to read and write that data. No frameworks are introduced; the existing conventions from `jobs.php` and `home.php` are followed throughout.

---

## Glossary

- **System**: The JolliHR PHP web application running on XAMPP.
- **User**: An authenticated Jollibee working-student whose session is established by `auth.php`.
- **Profile**: The personal-information, documents, and well-being page (`profile.php`).
- **Training_Module**: A named unit of pre-employment training (e.g., "Orientation", "Food Safety").
- **User_Progress**: The per-user completion record for a single Training_Module.
- **Class_Slot**: A weekly recurring time block when the User attends university classes.
- **Work_Shift**: A candidate or confirmed work-shift time block at a Jollibee branch.
- **Conflict**: A time overlap between a Class_Slot and a Work_Shift on the same day of the week.
- **Availability**: The User's stored set of preferred working days and maximum weekly hours.
- **Document**: A required onboarding file (e.g., COR, Valid ID, NBI Clearance, Medical Certificate) tracked per user.
- **Mood_Entry**: A single weekly well-being check-in record storing the User's mood selection.
- **DB**: The MySQL database accessed via the `db()` PDO helper in `includes/db.php`.

---

## Requirements

### Requirement 1: Persistent User Profile Data

**User Story:** As a User, I want my profile page to show my real name, university details, availability, and document statuses from the database, so that the information stays correct across sessions.

#### Acceptance Criteria

1. WHEN a User loads `profile.php`, THE System SHALL query the DB and display the User's `first_name` and `last_name` from the `users` table.
2. WHEN a User loads `profile.php`, THE System SHALL query the DB and display the User's university name, programme, and year level from a `user_profiles` table.
3. WHEN a User loads `profile.php`, THE System SHALL query the DB and display the User's available days, preferred shift, and maximum weekly hours from the `user_profiles` table.
4. WHEN a User loads `profile.php`, THE System SHALL query the DB and display each Document's name and verification status for that User from a `user_documents` table.
5. IF the `user_profiles` row for the authenticated User does not exist, THEN THE System SHALL display sensible placeholder text instead of a blank or PHP error.

### Requirement 2: Profile Preference Updates

**User Story:** As a User, I want to edit and save my availability preferences from the profile page, so that my schedule suggestions reflect what I actually told the system.

#### Acceptance Criteria

1. WHEN a User submits a valid preference update (available days, preferred shift, max weekly hours) via the form on `profile.php`, THE System SHALL write the new values to the `user_profiles` row for that User in the DB.
2. WHEN the preference update succeeds, THE System SHALL redirect the User back to `profile.php` and display a success notice.
3. IF the submitted `max_hours_per_week` value is not an integer between 1 and 40, THEN THE System SHALL reject the update and display a validation error without modifying the DB.
4. IF the submitted `available_days` list is empty, THEN THE System SHALL reject the update and display a validation error without modifying the DB.
5. THE System SHALL sanitize all profile form inputs with `htmlspecialchars` before rendering them and use parameterized PDO queries for all DB writes.

### Requirement 3: Training Module Progress Tracking

**User Story:** As a User, I want the training page to show my real completion status for each module, so that I can see which modules I still need to finish.

#### Acceptance Criteria

1. WHEN a User loads `training.php`, THE System SHALL query the DB and display each Training_Module's name, description, and that User's completion status from a `training_modules` table joined with a `user_training` table.
2. WHEN a User loads `training.php`, THE System SHALL calculate and display the number of completed modules and the completion percentage for that User based on the DB records.
3. WHEN a User marks a Training_Module as complete via a form action on `training.php`, THE System SHALL write a `completed_at` timestamp to the `user_training` row for that User and module in the DB.
4. WHEN the module completion write succeeds, THE System SHALL redirect the User back to `training.php` without leaving a resubmittable POST in the browser history.
5. IF a Training_Module has already been marked complete for that User, THEN THE System SHALL display its status as "Completed" and not render the complete action for it.
6. WHEN a User loads `training.php`, THE System SHALL display only the certificates for Training_Modules that the User has completed.

### Requirement 4: Class Schedule Storage and Display

**User Story:** As a User, I want my class schedule to be stored in the database and shown on the schedule page, so that the weekly calendar reflects my actual university timetable.

#### Acceptance Criteria

1. WHEN a User loads `schedule.php`, THE System SHALL query the DB and render one calendar cell per day of the week (Mon–Sun), colouring each cell based on whether that day contains a Class_Slot, Work_Shift, or Conflict from the `class_slots` and `work_shifts` tables for that User.
2. WHEN a User submits a new Class_Slot (day of week, start time, end time, subject label) via the form on `schedule.php`, THE System SHALL insert a row into the `class_slots` table for that User in the DB.
3. WHEN the Class_Slot insert succeeds, THE System SHALL redirect the User back to `schedule.php`.
4. IF the submitted Class_Slot has a `start_time` that is not earlier than its `end_time`, THEN THE System SHALL reject the insert and display a validation error.
5. THE System SHALL sanitize all schedule form inputs with `htmlspecialchars` before rendering and use parameterized PDO queries for all DB writes.

### Requirement 5: Work Shift Storage and Display

**User Story:** As a User, I want available and selected work shifts to be stored in the database, so that the schedule page shows only real, persisted shift data.

#### Acceptance Criteria

1. WHEN a User loads `schedule.php`, THE System SHALL query the DB and display each Work_Shift from the `work_shifts` table that belongs to that User, showing the shift name, time range, branch, and status.
2. WHEN a User selects a Work_Shift via the select action on `schedule.php`, THE System SHALL update that Work_Shift's status to `selected` in the DB for that User.
3. WHEN the Work_Shift status update succeeds, THE System SHALL redirect the User back to `schedule.php`.
4. IF a Work_Shift does not belong to the authenticated User, THEN THE System SHALL reject any update to it and return a 403 response.

### Requirement 6: Conflict Detection

**User Story:** As a User, I want the schedule page to automatically detect and highlight time overlaps between my classes and work shifts, so that I know when I need to request a swap.

#### Acceptance Criteria

1. WHEN a User loads `schedule.php`, THE System SHALL compare each Work_Shift's day and time range against each Class_Slot's day and time range for that User and identify every pair where the time ranges overlap.
2. WHEN at least one Conflict exists for the User, THE System SHALL display an alert card naming the conflicting class day, class time, shift time, and offering a "Request Shift Swap" action.
3. WHEN no Conflict exists for the User, THE System SHALL not display the conflict alert card.
4. THE System SHALL define a Conflict as any case where a Work_Shift and a Class_Slot share the same `day_of_week` and their time ranges satisfy: `shift_start < class_end AND shift_end > class_start`.

### Requirement 7: Well-being Mood Check-in Persistence

**User Story:** As a User, I want my weekly mood selection on the profile page to be saved to the database, so that my response persists after I leave and return to the page.

#### Acceptance Criteria

1. WHEN a User selects a mood option ("Great", "Okay", or "Tired") on `profile.php`, THE System SHALL insert or update a Mood_Entry row in a `mood_entries` table for that User, recording the mood value and the ISO week number and year of the current date.
2. WHEN a User loads `profile.php` during a week in which a Mood_Entry already exists for that User, THE System SHALL render the previously selected mood option as the active selection.
3. IF a Mood_Entry already exists for the current User and ISO week, THEN THE System SHALL update the existing row rather than insert a duplicate.
4. THE System SHALL accept only the values "Great", "Okay", or "Tired" as valid mood inputs; any other value SHALL be rejected without modifying the DB.

### Requirement 8: Database Schema Extension

**User Story:** As a developer, I want all new tables defined in `database.sql` using `CREATE TABLE IF NOT EXISTS` with `INSERT IGNORE` for seed rows, so that the existing auto-bootstrap logic in `db.php` works without manual migration steps.

#### Acceptance Criteria

1. THE System SHALL define the `user_profiles` table in `database.sql` with columns: `user_id` (FK → `users.id`), `university`, `programme`, `year_level`, `available_days` (comma-separated), `preferred_shift`, `max_hours_per_week`.
2. THE System SHALL define the `user_documents` table in `database.sql` with columns: `id`, `user_id` (FK → `users.id`), `document_name`, `verified` (TINYINT).
3. THE System SHALL define the `training_modules` table in `database.sql` with columns: `id`, `name`, `description`, `display_order`.
4. THE System SHALL define the `user_training` table in `database.sql` with columns: `id`, `user_id` (FK → `users.id`), `module_id` (FK → `training_modules.id`), `status` (ENUM: 'not_started', 'in_progress', 'completed'), `completed_at` (TIMESTAMP, nullable).
5. THE System SHALL define the `class_slots` table in `database.sql` with columns: `id`, `user_id` (FK → `users.id`), `day_of_week` (TINYINT 0–6, 0 = Monday), `start_time` (TIME), `end_time` (TIME), `label`.
6. THE System SHALL define the `work_shifts` table in `database.sql` with columns: `id`, `user_id` (FK → `users.id`), `shift_name`, `day_of_week` (TINYINT 0–6), `start_time` (TIME), `end_time` (TIME), `branch`, `status` (ENUM: 'available', 'selected').
7. THE System SHALL define the `mood_entries` table in `database.sql` with columns: `id`, `user_id` (FK → `users.id`), `mood` (ENUM: 'Great', 'Okay', 'Tired'), `iso_year` (SMALLINT), `iso_week` (TINYINT).
8. THE System SHALL include `INSERT IGNORE` seed rows in `database.sql` for the five standard Training_Modules (Orientation, Food Safety, Customer Service, POS System, Workplace Safety) and seed `user_profiles`, `user_documents`, `work_shifts`, and `user_training` rows for the demo account (`shamea@jollihr.test`).
9. WHEN the `db()` function is called and the new tables are absent, THE System SHALL auto-bootstrap them without requiring a manual `database.sql` import, consistent with the existing bootstrap behaviour.
