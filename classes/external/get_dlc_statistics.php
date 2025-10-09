<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

require_once(__DIR__ . '/../../../../config.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Class get_dlc_statistics
 *
 * This class extends the external_api and provides functionality
 * to retrieve all DLC statistics (enrolments, visits, user counts) in one call.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class get_dlc_statistics extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'startdate' => new external_value(PARAM_TEXT, 'Start date in format YYYY-MM'),
            'enddate' => new external_value(PARAM_TEXT, 'End date in format YYYY-MM', VALUE_DEFAULT, null),
            'cumulative_users' => new external_value(PARAM_BOOL, 'Whether to return cumulative user counts', VALUE_DEFAULT, false)
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'course_enrolments' => new external_multiple_structure(
                new external_single_structure([
                    'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
                    'count' => new external_value(PARAM_INT, 'Number of enrolments in this month'),
                ])
            ),
            'course_visits' => new external_multiple_structure(
                new external_single_structure([
                    'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
                    'count' => new external_value(PARAM_INT, 'Number of unique users visiting courses'),
                    'total_visits' => new external_value(PARAM_INT, 'Total number of course visits'),
                ])
            ),
            'user_counts' => new external_multiple_structure(
                new external_single_structure([
                    'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
                    'count' => new external_value(PARAM_INT, 'Number of users in this month'),
                ])
            )
        ]);
    }

    /**
     * Get all DLC statistics (enrolments, visits, user counts) within date range.
     * @param string $startdate Start date in YYYY-MM format
     * @param string $enddate End date in YYYY-MM format
     * @param bool $cumulative_users Whether to return cumulative user counts
     * @return array All statistics combined
     * @throws \moodle_exception If parameters are invalid or any service fails.
     */
    public static function execute($startdate, $enddate = null, $cumulative_users = false) {
        
        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'startdate' => $startdate,
            'enddate' => $enddate,
            'cumulative_users' => $cumulative_users
        ]);

        // Set enddate to startdate if null
        if ($params['enddate'] === null) {
            $params['enddate'] = $params['startdate'];
        }

        try {
            // Get course enrolments
            $course_enrolments = get_course_enrolments::execute($params['startdate'], $params['enddate']);

            // Get course visits
            $course_visits = get_course_visits::execute($params['startdate'], $params['enddate']);

            // Get user counts
            $user_counts = get_user_counts::execute($params['startdate'], $params['enddate'], $params['cumulative_users']);

            // Get user counts
            $user_count_start = get_user_counts::execute($params['startdate'], null, true);

            // Return combined results
            return [
                'course_enrolments' => $course_enrolments,
                'course_visits' => $course_visits,
                'user_counts' => $user_counts,
                'user_count_start' => $user_count_start
            ];

        } catch (\Exception $e) {
            throw new \moodle_exception('Error retrieving DLC statistics: ' . $e->getMessage());
        }
    }
}
