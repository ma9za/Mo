<?php
/**
 * Scheduler Manager
 * Handles posting schedules and cron job logic
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

class Scheduler {
    /**
     * Parse schedule JSON
     * Format: ["09:00", "14:30", "20:00"]
     */
    public static function parseSchedule($scheduleJson) {
        if (empty($scheduleJson)) {
            return [];
        }

        try {
            $schedule = json_decode($scheduleJson, true);
            if (!is_array($schedule)) {
                return [];
            }
            return $schedule;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Check if it's time to post
     */
    public static function isTimeToPost($scheduleJson, $lastPostAt = null) {
        $schedule = self::parseSchedule($scheduleJson);

        if (empty($schedule)) {
            return false;
        }

        $timezone = env('TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        $currentTime = date('H:i');
        $currentDate = date('Y-m-d');

        // Check if current time matches any scheduled time
        foreach ($schedule as $scheduledTime) {
            if ($currentTime === $scheduledTime) {
                // Check if we already posted at this time today
                if ($lastPostAt !== null) {
                    $lastPostDate = date('Y-m-d', strtotime($lastPostAt));
                    if ($lastPostDate === $currentDate) {
                        // Already posted today at this time
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Get next scheduled post time
     */
    public static function getNextPostTime($scheduleJson) {
        $schedule = self::parseSchedule($scheduleJson);

        if (empty($schedule)) {
            return null;
        }

        $timezone = env('TIMEZONE', 'UTC');
        date_default_timezone_set($timezone);

        $currentTime = date('H:i');
        sort($schedule);

        // Find next scheduled time today
        foreach ($schedule as $scheduledTime) {
            if ($scheduledTime > $currentTime) {
                return date('Y-m-d') . ' ' . $scheduledTime;
            }
        }

        // If no time left today, return first time tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        return $tomorrow . ' ' . $schedule[0];
    }

    /**
     * Get all bots that need to post now
     */
    public static function getBotsToPost() {
        $db = Database::getInstance();

        $bots = $db->fetchAll(
            "SELECT * FROM bots WHERE is_enabled = 1 AND is_verified = 1"
        );

        $botsToPost = [];

        foreach ($bots as $bot) {
            if (self::isTimeToPost($bot['schedule_json'], $bot['last_post_at'])) {
                $botsToPost[] = $bot;
            }
        }

        return $botsToPost;
    }

    /**
     * Format schedule for display
     */
    public static function formatSchedule($scheduleJson) {
        $schedule = self::parseSchedule($scheduleJson);

        if (empty($schedule)) {
            return 'No schedule';
        }

        return implode(', ', $schedule);
    }

    /**
     * Validate schedule format
     */
    public static function validateSchedule($scheduleJson) {
        try {
            $schedule = json_decode($scheduleJson, true);

            if (!is_array($schedule)) {
                return false;
            }

            foreach ($schedule as $time) {
                if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                    return false;
                }

                list($hour, $minute) = explode(':', $time);
                if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
