<?php
namespace local_dlcmanager\event;

defined('MOODLE_INTERNAL') || die();

class observer {
    public static function course_metadata_updated(\local_ildmeta\event\ildmeta_updated $event) {
        // If the course is marked as noindex, we should send a course deleted event to the hub.
        if ($event->other['noindex'] == 0) {
            self::send_course_metadata_to_hub($event->objectid, $event->other['uuid']);
        } else {
            self::send_course_deleted_to_hub($event->objectid, $event->other['uuid']);
        }
    }

    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        // Get uuid from database table ildmeta.
        $sql = "SELECT meta.uuid
                FROM {ildmeta} meta
                WHERE meta.courseid = :courseid";
        $params = ['courseid' => $event->objectid];
        $record = $DB->get_record_sql($sql, $params);

        if ($record) {
            self::send_course_deleted_to_hub($event->objectid, $record->uuid);
        } else {
            mtrace("No metadata found for deleted course with id " . $event->objectid);
        }
    }

    private static function send_course_metadata_to_hub(int $courseid, string $uuid) {
        $task = \local_dlcmanager\task\send_course_metadata::instance($courseid, $uuid);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    private static function send_course_deleted_to_hub(int $courseid, string $uuid) {
        $task = \local_dlcmanager\task\send_course_deleted::instance($courseid, $uuid);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}