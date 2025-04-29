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
require_once $CFG->dirroot . '/completion/completion_completion.php';

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
                'activity_progress' => new external_value(PARAM_FLOAT, 'Progress of the user in the course based on completed activities', VALUE_OPTIONAL),
                'criteria_progress' => new external_value(PARAM_FLOAT, 'Progress of the user in the course based on completed criteria', VALUE_OPTIONAL),
                'completion_time' => new external_value(PARAM_INT, 'Time of course completion', VALUE_OPTIONAL)
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
                    'activity_progress' => $progress['activity_progress'] ?? null,
                    'criteria_progress' => $progress['criteria_progress'] ?? null,
                    'completion_time' => $progress['completion_time'] ?? null
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
     * This method evaluates the user's progress in a course by calculating
     * activity progress, criteria progress, and determining if the course
     * has been completed. If the course is already completed, it returns
     * the completion time along with progress details.
     *
     * @param int $userid The ID of the user whose progress is being calculated.
     * @param int $courseid The ID of the course for which progress is being calculated.
     * @return array|null An associative array containing:
     *                    - 'activity_progress' (float): The progress of activities in the course.
     *                    - 'criteria_progress' (float): The progress of criteria in the course.
     *                    - 'completion_time' (int|null): The timestamp of course completion, or null if not completed.
     *                    Returns null if course completion is not enabled or activity progress cannot be calculated.
     */
    private static function calculate_course_progress($userid, $courseid) {
        $course = self::create_course_object($courseid);
        $completioninfo = new \completion_info($course);

        if (!self::is_course_completion_enabled($completioninfo)) {
            return null;
        }

        $activityprogress = self::calculate_activity_progress($userid, $completioninfo, $courseid);
        if ($activityprogress === null) {
            return null;
        }

        $completiontime = self::get_course_completion_time($userid, $courseid);
        if ($completiontime !== null) {
            // If the course is already completed, return early with completion time. We know that criteria progress must be 1.0.
            return [
                'activity_progress' => $activityprogress,
                'criteria_progress' => 1.0,
                'completion_time' => $completiontime
            ];
        }

        $criteriaprogress = self::calculate_criteria_progress($userid, $completioninfo);

        return [
            'activity_progress' => $activityprogress,
            'criteria_progress' => $criteriaprogress,
            'completion_time' => null
        ];
    }

    /**
     * Creates a course object with the specified course ID.
     *
     * @param int $courseid The ID of the course to be assigned to the object.
     * @return \stdClass An object representing the course with the specified ID.
     */
    private static function create_course_object($courseid) {
        $course = new \stdClass();
        $course->id = $courseid;
        return $course;
    }

    /**
     * Checks if course completion is enabled for the given completion information.
     *
     * @param completion_info $completioninfo The completion information object to check.
     * @return bool True if course completion is enabled, false otherwise.
     */
    private static function is_course_completion_enabled($completioninfo) {
        if (!$completioninfo->is_enabled()) {
            return false;
        }
        return true;
    }

    /**
     * Retrieves the course completion time for a specific user and course.
     *
     * This method checks if a user has completed a given course and, if so,
     * returns the timestamp of when the course was completed. If the course
     * is not completed, it returns null.
     *
     * @param int $userid The ID of the user.
     * @param int $courseid The ID of the course.
     * @return int|null The timestamp of course completion, or null if not completed.
     */
    private static function get_course_completion_time($userid, $courseid) {
        $ccompletion = new \completion_completion([
            'userid' => $userid,
            'course' => $courseid
        ]);
        if ($ccompletion->is_complete()) {
            return $ccompletion->timecompleted;
        }
        return null;
    }

    /**
     * Calculates the progress of a user's activity completion within a course.
     *
     * This function determines the ratio of completed activities to the total number
     * of activities with completion tracking enabled for a specific user in a course.
     * For course ID 5, it filters out activities with a course module ID of 46.
     *
     * @param int $userid The ID of the user whose progress is being calculated.
     * @param \completion_info $completioninfo The completion information object for the course.
     * @param int $courseid The ID of the course for which progress is being calculated.
     * @return float|null The progress ratio as a float (completed activities / total activities),
     *                    or null if there are no activities with completion tracking enabled.
     */
    private static function calculate_activity_progress($userid, $completioninfo, $courseid) {
        $activities = $completioninfo->get_activities();
        if (empty($activities)) {
            return null;
        }

        $totalactivities = count($activities);
        $completedactivities = 0;

        foreach ($activities as $activity) {
            $data = $completioninfo->get_data($activity, false, $userid);

            // Filter specific activities for course 5
            if ($courseid == 5 && $data->coursemoduleid != 46) {
                continue;
            }

            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $completedactivities++;
            }
        }

        return (float)$completedactivities / (float)$totalactivities;
    }

    /**
     * Calculates the progress of a user based on course completion criteria.
     *
     * This method evaluates the completion status of all criteria for a given user
     * in a course and returns the progress as a fraction of completed criteria
     * over the total criteria.
     *
     * @param int $userid The ID of the user whose progress is being calculated.
     * @param \completion_info $completioninfo The completion information object for the course.
     * @return float|null The progress as a fraction (e.g., 0.75 for 75% completion),
     *                    or null if no criteria are found.
     */
    private static function calculate_criteria_progress($userid, $completioninfo) {
        $criteria = $completioninfo->get_criteria();
        if (empty($criteria)) {
            return null;
        }

        $totalcriteria = count($criteria);
        $completedcriteria = 0;

        foreach ($criteria as $criterion) {
            $completion = $completioninfo->get_user_completion($userid, $criterion);
            if ($completion->is_complete()) {
                $completedcriteria++;
            }
        }

        return (float)$completedcriteria / (float)$totalcriteria;
    }
}
