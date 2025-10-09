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

defined('MOODLE_INTERNAL') || die();

/**
 * Class get_course_enrolments
 *
 * This class extends the external_api and provides functionality
 * to retrieve enrolment statistics by month within a date range.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class get_user_counts extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'startdate' => new external_value(PARAM_TEXT, 'Start date in format YYYY-MM'),
            'enddate' => new external_value(PARAM_TEXT, 'End date in format YYYY-MM', VALUE_DEFAULT, null),
            'cumulative' => new external_value(PARAM_BOOL, 'Whether to return cumulative counts', VALUE_DEFAULT, false)
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
            'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
            'count' => new external_value(PARAM_INT, 'Number of enrolments in this month'),
            ])
        );
    }

    /**
     * Get enrolment statistics by month within date range.
     * @param string $startdate Start date in YYYY-MM format
     * @param string $enddate End date in YYYY-MM format
     * @param bool $cumulative Whether to return cumulative counts
     * @return array Enrolments count by month
     * @throws \moodle_exception If parameters are invalid or database operation fails.
     */
    public static function execute($startdate, $enddate, $cumulative) {
        global $DB;

        //wenn enddate null ist, dann auf startdate setzen
        if ($enddate === null) {
            $enddate = $startdate;
        }
        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'startdate' => $startdate,
            'enddate' => $enddate,
            'cumulative' => $cumulative,
        ]);

        try {

            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}$/', $params['startdate']) || 
                !preg_match('/^\d{4}-\d{2}$/', $params['enddate'])) {
                throw new \moodle_exception('Invalid date format. Use YYYY-MM');
            }

            // Convert to timestamps for SQL query
            $startTimestamp = strtotime($params['startdate'] . '-01');
            $endTimestamp = strtotime($params['enddate'] . '-01 +1 month -1 second');

            if ($startTimestamp === false || $endTimestamp === false) {
                throw new \moodle_exception('Invalid date values');
            }
            if($cumulative) {
                // Get all relevant enrolments before start date for cumulative count
                $sql = "
                    SELECT COUNT(*) AS count
                    FROM {user} u
                    WHERE u.firstaccess < :starttime
                    AND u.firstaccess > 0
                    AND u.auth = 'oidc'
                    ";
                $params = [
                    'starttime' => $startTimestamp
                ];

                $records = $DB->get_records_sql($sql, $params);
                $cummulativecount = 0;
                if($records){
                    foreach($records as $r){
                        $cummulativecount += $r->count;
                    }
                }
                $result[] = [
                    'date' => $startdate,
                    'count' => $cummulativecount
                ];

            } else {
                // Get all relevant enrolments within time range
                $sql = "
                    SELECT u.firstaccess
                    FROM {user} u
                    WHERE u.firstaccess >= :starttime
                    AND u.firstaccess <= :endtime
                    AND u.firstaccess > 0
                    AND u.auth = 'oidc'

                ";
                $params = [
                    'starttime' => $startTimestamp,
                    'endtime' => $endTimestamp,
                ];

                $records = $DB->get_records_sql($sql, $params);

                // Group enrolments by month (in PHP, DB-neutral)
                $by_month = [];

                foreach ($records as $r) {
                    // Format Unix timestamp to 'YYYY-MM'
                    $timestamp = (int)$r->firstaccess; // Convert to integer
                    $month = date('Y-m', $timestamp);
                    if (!isset($by_month[$month])) {
                        $by_month[$month] = 0;
                    }
                    $by_month[$month]++;
                }
                // Sort chronologically by month
                ksort($by_month);

                // Convert to array structure that matches execute_returns()
                $result = [];
                foreach ($by_month as $date => $count) {
                    $result[] = [
                        'date' => $date,
                        'count' => $count
                    ];
                }
            }



            return $result;
        } catch (\dml_exception $e) {
            throw new \moodle_exception('Database error: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \moodle_exception('Error: ' . $e->getMessage());
        }
    }
}