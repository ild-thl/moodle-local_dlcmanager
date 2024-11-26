<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use cache;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/completionlib.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Class get_user_course_progress
 *
 * This class extends the external_api and provides functionality
 * to retrieve the enrolled courses and their progress of a user by username.
 * The courses are returned as an array of objects containing the course ID,
 * the course UUID as defined by the ildmeta plugin, and the user's progress in the course.
 * Only courses that are intended to be indexed by the ildmeta plugin are included.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class get_user_course_progress extends external_api {

    /** @var cache User courses cache */
    private static $usercoursescache;

    /** @var cache Course progress cache */
    private static $courseprogresscache;

    /**
     * Initialize caches.
     */
    private static function initialize_caches() {
        if (self::$usercoursescache === null) {
            self::$usercoursescache = cache::make('local_dlcmanager', 'user_courses');
        }
        if (self::$courseprogresscache === null) {
            self::$courseprogresscache = cache::make('local_dlcmanager', 'course_progress');
        }
    }

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {

        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'The username of the user')
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'id of course'),
                'uuid' => new external_value(PARAM_RAW, 'uuid of course as defined by ildmeta data'),
                'progress' => new external_value(PARAM_FLOAT, 'Progress of the user in the course', VALUE_OPTIONAL)
            ])
        );
    }

    /**
     * Get courses indexed by ildmeta plugin that a user is enrolled in.
     * @param string $username The username of the user.
     * @return array An array of courses the user is enrolled in.
     * @throws \moodle_exception If a database operation fails.
     */
    public static function execute($username) {
        global $DB;
        self::initialize_caches();
        $result = [];

        try {
            $params = self::validate_parameters(self::execute_parameters(), ['username' => $username]);

            // Check if the current user has the required capability
            require_capability('local/dlcmanager:viewusercourses', \context_system::instance());

            // Get user by username
            $user = $DB->get_record('user', ['username' => $params['username']], '*');

            if (!$user) {
                return $result;
            }

            // Attempt to get user courses from cache
            $cachekey = $user->id;
            $courses = self::$usercoursescache->get($cachekey);

            if ($courses === false) {
                // Get courses indexed by ildmeta plugin that the user is enrolled in
                $courses = self::get_user_courses($user->id);
                // Cache the courses
                self::$usercoursescache->set($cachekey, $courses);
            }

            foreach ($courses as $course) {
                // Attempt to get progress from cache
                $progresskey = "{$user->id}_{$course->courseid}";
                $progress = self::$courseprogresscache->get($progresskey);

                if ($progress === false) {
                    // Calculate course progress
                    $progress = self::calculate_course_progress($user->id, $course->courseid);
                    // Cache the progress
                    self::$courseprogresscache->set($progresskey, $progress);
                }

                // Add course and progress to result
                $result[] = [
                    'id' => $course->courseid,
                    'uuid' => $course->uuid,
                    'progress' => $progress
                ];
            }

            return $result;
        } catch (\dml_exception $e) {
            throw new \moodle_exception('Database error: ' . $e->getMessage());
        }
    }

    /**
     * Gets the courses a user is enrolled in and retrieves corresponding ildmeta records.
     *
     * @param int $userid The ID of the user whose courses are to be fetched.
     * @return array An array of ildmeta records for the courses the user is enrolled in.
     */
    private static function get_user_courses($userid) {
        global $DB;

        // Get courses the user is enrolled in
        $courses = enrol_get_users_courses($userid, false, 'id');

        // Extract course IDs
        $courseids = array_map(function ($course) {
            return $course->id;
        }, $courses);

        if (empty($courseids)) {
            return [];
        }

        // Get ildmeta records for these courses
        list($insql, $inparams) = $DB->get_in_or_equal($courseids);
        $sql = "SELECT m.uuid, m.courseid 
                FROM {ildmeta} m
                WHERE m.courseid $insql
                AND m.noindexcourse = '0'";

        $courses = $DB->get_records_sql($sql, $inparams);
        if ($courses === false) {
            return [];
        }
        return $courses;
    }

    /**
     * Calculates the progress of a user in a specific course.
     *
     * This function calculates the progress of a user in a course by determining
     * the ratio of completed course modules to the total number of course modules.
     * The progress is returned as a float between 0 and 1, or null if course completion
     * is not activated or no modules exist that are available to be completed.
     *
     * @param int $userid The ID of the user.
     * @param int $courseid The ID of the course.
     * @return float|null The progress of the user in the course, as a number between 0 and 1,
     *                    or null if course completion is not activated or no modules exist.
     */
    private static function calculate_course_progress($userid, $courseid) {
        // Create a course object
        $course = new \stdClass();
        $course->id = $courseid;

        // Initialize completion info
        $completioninfo = new \completion_info($course);

        // Check if course completion is enabled
        if (!$completioninfo->is_enabled()) {
            debugging('Course completion is not enabled', DEBUG_DEVELOPER);
            return null;
        }

        // Get all activities with completion tracking enabled
        $activities = $completioninfo->get_activities();
        if (empty($activities)) {
            debugging('No activities with completion tracking enabled', DEBUG_DEVELOPER);
            return null;
        }

        // Count total activities and completed activities
        $totalactivities = count($activities);
        $completedactivities = 0;

        foreach ($activities as $activity) {
            $data = $completioninfo->get_data($activity, false, $userid);
            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $completedactivities++;
            }
        }

        debugging("Progress: $completedactivities / $totalactivities", DEBUG_DEVELOPER);

        // Calculate progress as a number between 0 and 1
        $progress = (float)$completedactivities / (float)$totalactivities;

        debugging("Progress: $progress", DEBUG_DEVELOPER);

        return $progress;
    }
}
