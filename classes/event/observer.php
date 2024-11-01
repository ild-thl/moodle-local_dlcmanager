<?php

namespace local_dlcmanager\event;

defined('MOODLE_INTERNAL') || die();

use cache;
use core\event\course_module_completion_updated;
use core\event\course_updated;
use core\event\course_deleted;
use local_ildmeta\event\ildmeta_updated;
use moodle_exception;

/**
 * Observer class for handling various Moodle events related to courses and user progress.
 *
 * This class listens to specific Moodle events and performs actions such as sending
 * course metadata to a hub or invalidating caches when a user completes a course module.
 */
class observer {
    /**
     * Handler for ildmeta updated events.
     *
     * If the course is marked as noindex, sends a course deleted event to the hub.
     * Otherwise, sends course metadata to the hub.
     *
     * @param ildmeta_updated $event The event data.
     */
    public static function course_metadata_updated(ildmeta_updated $event) {
        if ($event->other['noindex'] == 0) {
            self::send_course_metadata_to_hub($event->objectid, $event->other['uuid']);
            debugging("Sent course metadata for course ID {$event->objectid} to hub.", DEBUG_DEVELOPER);
        } else {
            self::send_course_deleted_to_hub($event->objectid, $event->other['uuid']);
            debugging("Sent course deletion for course ID {$event->objectid} to hub.", DEBUG_DEVELOPER);
        }
    }

    /**
     * Handler for course updated events.
     *
     * Retrieves the course UUID and noindex status from the ildmeta table and sends
     * the appropriate event to the hub.
     *
     * @param course_updated $event The event data.
     * @throws moodle_exception If a database operation fails.
     */
    public static function course_updated(course_updated $event) {
        global $DB;

        try {
            // Get UUID and noindex status from ildmeta table.
            $sql = "SELECT meta.uuid, meta.noindexcourse
                    FROM {ildmeta} meta
                    WHERE meta.courseid = :courseid";
            $params = ['courseid' => $event->objectid];
            $record = $DB->get_record_sql($sql, $params);

            if ($record) {
                if ($record->noindexcourse == 0) {
                    self::send_course_metadata_to_hub($event->objectid, $record->uuid);
                    debugging("Course updated: Sent metadata for course ID {$event->objectid} to hub.", DEBUG_DEVELOPER);
                } else {
                    self::send_course_deleted_to_hub($event->objectid, $record->uuid);
                    debugging("Course updated: Sent deletion for course ID {$event->objectid} to hub.", DEBUG_DEVELOPER);
                }
            } else {
                debugging("No metadata found for updated course with ID {$event->objectid}.", DEBUG_DEVELOPER);
            }
        } catch (\dml_exception $e) {
            debugging("Error processing course_updated event for course ID {$event->objectid}: " . $e->getMessage(), DEBUG_ALL);
            throw new moodle_exception('databaseerror', 'error', '', $e->getMessage());
        }
    }

    /**
     * Handler for course deleted events.
     *
     * Retrieves the course UUID from the ildmeta table and sends a course deleted
     * event to the hub.
     *
     * @param course_deleted $event The event data.
     * @throws moodle_exception If a database operation fails.
     */
    public static function course_deleted(course_deleted $event) {
        global $DB;

        try {
            // Get UUID from ildmeta table.
            $sql = "SELECT meta.uuid
                    FROM {ildmeta} meta
                    WHERE meta.courseid = :courseid";
            $params = ['courseid' => $event->objectid];
            $record = $DB->get_record_sql($sql, $params);

            if ($record) {
                self::send_course_deleted_to_hub($event->objectid, $record->uuid);
                debugging("Course deleted: Sent deletion for course ID {$event->objectid} to hub.", DEBUG_DEVELOPER);
            } else {
                debugging("No metadata found for deleted course with ID {$event->objectid}.", DEBUG_DEVELOPER);
            }
        } catch (\dml_exception $e) {
            debugging("Error processing course_deleted event for course ID {$event->objectid}: " . $e->getMessage(), DEBUG_ALL);
            throw new moodle_exception('databaseerror', 'error', '', $e->getMessage());
        }
    }

    /**
     * Sends course metadata to the hub by queuing an adhoc task.
     *
     * @param int $courseid The ID of the course.
     * @param string $uuid The UUID of the course.
     */
    private static function send_course_metadata_to_hub(int $courseid, string $uuid) {
        try {
            $task = \local_dlcmanager\task\send_course_metadata::instance($courseid, $uuid);
            \core\task\manager::queue_adhoc_task($task, true);
            debugging("Queued adhoc task to send metadata for course ID {$courseid} to hub.", DEBUG_DEVELOPER);
        } catch (\coding_exception $e) {
            debugging("Failed to queue adhoc task for sending course metadata: " . $e->getMessage(), DEBUG_ALL);
        }
    }

    /**
     * Sends a course deletion notice to the hub by queuing an adhoc task.
     *
     * @param int $courseid The ID of the course.
     * @param string $uuid The UUID of the course.
     */
    private static function send_course_deleted_to_hub(int $courseid, string $uuid) {
        try {
            $task = \local_dlcmanager\task\send_course_deleted::instance($courseid, $uuid);
            \core\task\manager::queue_adhoc_task($task, true);
            debugging("Queued adhoc task to send deletion for course ID {$courseid} to hub.", DEBUG_DEVELOPER);
        } catch (\coding_exception $e) {
            debugging("Failed to queue adhoc task for sending course deletion: " . $e->getMessage(), DEBUG_ALL);
        }
    }

    /**
     * Handler for course module completion updates.
     *
     * Invalidates the cached user courses and course progress when a user completes a course module.
     *
     * @param course_module_completion_updated $event The event data.
     * @throws moodle_exception If a database operation fails.
     */
    public static function course_module_completion_updated(course_module_completion_updated $event) {
        try {
            // Get the user ID and course ID from the event.
            $userid = $event->userid;
            $courseid = $event->courseid;

            // Initialize caches.
            $usercoursescache = cache::make('local_dlcmanager', 'user_courses');
            $courseprogresscache = cache::make('local_dlcmanager', 'course_progress');

            // Invalidate the user's courses cache.
            $usercoursescache->delete($userid);
            debugging("Invalidated user_courses cache for user ID '{$userid}'.", DEBUG_DEVELOPER);

            // Invalidate the specific course progress cache.
            $progresskey = "{$userid}_{$courseid}";
            $courseprogresscache->delete($progresskey);
            debugging("Invalidated course_progress cache for key '{$progresskey}'.", DEBUG_DEVELOPER);
        } catch (\Exception $e) {
            debugging("Unexpected error in course_module_completion_updated: " . $e->getMessage(), DEBUG_ALL);
            throw new moodle_exception('errorprocessingevent', 'local_dlcmanager', '', $e->getMessage());
        }
    }

    /**
     * Invalidate user course caches when a user enrolls or unenrolls from a course.
     *
     * @param \core\event\user_enrolment_created|\core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_changed($event) {
        try {
            // Get the user ID from the event.
            $userid = $event->relateduserid;

            // Invalidate the user's courses cache.
            $cache = cache::make('local_dlcmanager', 'user_courses');
            $cache->delete($userid);
            debugging("Invalidated user_courses cache for user with ID {$userid}.", DEBUG_DEVELOPER);
        } catch (\Exception $e) {
            debugging("Unexpected error in user_enrolment_changed: " . $e->getMessage(), DEBUG_ALL);
            throw new moodle_exception('errorprocessingevent', 'local_dlcmanager', '', $e->getMessage());
        }
    }
}
