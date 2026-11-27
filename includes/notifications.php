<?php
require_once __DIR__ . '/db.php';

/**
 * Insert a notification for a user.
 *
 * @param int    $user_id  Target user's ID.
 * @param string $message  Plain-text message (max 255 chars).
 */
function notify_user(int $user_id, string $message): void
{
    $st = db()->prepare(
        'INSERT INTO notifications (user_id, message) VALUES (?, ?)'
    );
    $st->execute([$user_id, mb_substr($message, 0, 255)]);
}

/**
 * Fetch all unread notifications for a user, newest first.
 *
 * @param  int   $user_id
 * @return array  Each row: id, message, created_at
 */
function get_notifications(int $user_id): array
{
    $st = db()->prepare(
        'SELECT id, message, created_at
         FROM notifications
         WHERE user_id = ? AND is_read = 0
         ORDER BY created_at DESC'
    );
    $st->execute([$user_id]);
    return $st->fetchAll();
}

/**
 * Mark one or all unread notifications as read for a user.
 *
 * @param int      $user_id
 * @param int|null $id      Specific notification ID, or null to mark all.
 */
function mark_notifications_read(int $user_id, ?int $id = null): void
{
    if ($id !== null) {
        $st = db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $st->execute([$id, $user_id]);
    } else {
        $st = db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'
        );
        $st->execute([$user_id]);
    }
}
