<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

require_once(__DIR__ . '/../../../../config.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Class get_course_users
 *
 * This class extends the external_api and provides functionality
 * to retrieve user IDs of enrolled users by month within a date range.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class get_course_users extends external_api
{

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters()
    {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'startdate' => new external_value(PARAM_TEXT, 'Start date in format YYYY-MM'),
            'enddate' => new external_value(PARAM_TEXT, 'End date in format YYYY-MM')
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_multiple_structure
     */
    public static function execute_returns()
    {
        return new external_multiple_structure(
            new external_single_structure([
                'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
                'users' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'User ID'),
                        'username' => new external_value(PARAM_TEXT, 'Username')
                    ])
                ),
            ])
        );
    }

    /**
     * Get enrolled user IDs by month within date range for a specific course.
     * @param int $courseid Course ID
     * @param string $startdate Start date in YYYY-MM format
     * @param string $enddate End date in YYYY-MM format
     * @return array User IDs grouped by month
     * @throws \moodle_exception If parameters are invalid or database operation fails.
     */
    public static function execute($courseid, $startdate, $enddate)
    {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'startdate' => $startdate,
            'enddate' => $enddate
        ]);

        try {

            // Validate date format
            if (
                !preg_match('/^\d{4}-\d{2}$/', $params['startdate']) ||
                !preg_match('/^\d{4}-\d{2}$/', $params['enddate'])
            ) {
                throw new \moodle_exception('Invalid date format. Use YYYY-MM');
            }

            // Convert to timestamps for SQL query
            $startTimestamp = strtotime($params['startdate'] . '-01');
            $endTimestamp = strtotime($params['enddate'] . '-01 +1 month -1 second');

            if ($startTimestamp === false || $endTimestamp === false) {
                throw new \moodle_exception('Invalid date values');
            }

            // Validate course exists
            if (!$DB->record_exists('course', ['id' => $params['courseid']])) {
                throw new \moodle_exception('Course not found');
            }

            // Get all relevant user enrolments within time range for the specific course
            $sql = "
                SELECT ue.userid, ue.timecreated, u.username
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid
                WHERE e.courseid = :courseid
                AND ue.timestart >= :starttime
                AND ue.timestart <= :endtime
                AND ue.status = 0
                AND e.enrol != 'manual'
            ";
            $sqlparams = [
                'courseid' => $params['courseid'],
                'starttime' => $startTimestamp,
                'endtime' => $endTimestamp,
            ];

            $records = $DB->get_records_sql($sql, $sqlparams);

            // Group users by month (in PHP, DB-neutral)
            $users_by_month = [];

            foreach ($records as $r) {
                // Format Unix timestamp to 'YYYY-MM'
                $timestamp = (int)$r->timecreated;
                $month = date('Y-m', $timestamp);
                
                if (!isset($users_by_month[$month])) {
                    $users_by_month[$month] = [];
                }
                
                // Add user data to this month (avoid duplicates by userid)
                $userid = (int)$r->userid;
                $user_exists = false;
                foreach ($users_by_month[$month] as $existing_user) {
                    if ($existing_user['id'] === $userid) {
                        $user_exists = true;
                        break;
                    }
                }
                
                if (!$user_exists) {
                    $users_by_month[$month][] = [
                        'id' => $userid,
                        'username' => $r->username
                    ];
                }
            }

            // Sort chronologically by month
            ksort($users_by_month);

            // Convert to array structure that matches execute_returns()
            $result = [];
            foreach ($users_by_month as $date => $users) {
                $result[] = [
                    'date' => $date,
                    'users' => array_values($users) // Ensure sequential array indices
                ];
            }

            return $result;
        } catch (\dml_exception $e) {
            throw new \moodle_exception('Database error: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \moodle_exception('Error: ' . $e->getMessage());
        }
    }
}
