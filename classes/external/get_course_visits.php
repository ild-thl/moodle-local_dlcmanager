<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

class get_course_visits extends external_api {

    /**
     * Parameterbeschreibung
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'startdate' => new external_value(PARAM_TEXT, 'Start date in format YYYY-MM'),
            'enddate' => new external_value(PARAM_TEXT, 'End date in format YYYY-MM'),
        ]);
    }

    /**
     * Rückgabebeschreibung
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'date' => new external_value(PARAM_TEXT, 'Month in format YYYY-MM'),
                'count' => new external_value(PARAM_INT, 'Number of unique users visiting courses'),
                'total_visits' => new external_value(PARAM_INT, 'Total number of course visits'),
            ])
        );
    }

    /**
     * Hauptfunktion
     */
    public static function execute($startdate, $enddate) {
        global $DB;

        // Parameter validieren
        $params = self::validate_parameters(self::execute_parameters(), [
            'startdate' => $startdate,
            'enddate' => $enddate
        ]);

        // Datum prüfen
        if (!preg_match('/^\d{4}-\d{2}$/', $params['startdate']) || 
            !preg_match('/^\d{4}-\d{2}$/', $params['enddate'])) {
            throw new \moodle_exception('Invalid date format. Use YYYY-MM');
        }

        $startTimestamp = strtotime($params['startdate'] . '-01');
        $endTimestamp = strtotime($params['enddate'] . '-01 +1 month -1 second');

        if ($startTimestamp === false || $endTimestamp === false) {
            throw new \moodle_exception('Invalid date values');
        }

        try {
            // Alle relevanten Kursbesuche holen
            $sql = "
                SELECT userid, timecreated
                FROM {logstore_standard_log}
                WHERE courseid > 1
                  AND action = 'viewed'
                  AND target = 'course'
                  AND timecreated BETWEEN :starttime AND :endtime
            ";
            $queryParams = [
                'starttime' => $startTimestamp,
                'endtime' => $endTimestamp,
            ];

            $records = $DB->get_records_sql($sql, $queryParams);

            // Gruppierung nach Monat in PHP
            $visits_by_month = [];

            foreach ($records as $r) {
                $month = date('Y-m', (int)$r->timecreated);
                if (!isset($visits_by_month[$month])) {
                    $visits_by_month[$month] = [
                        'unique_visitors_set' => [],
                        'total_visits' => 0
                    ];
                }
                $visits_by_month[$month]['unique_visitors_set'][$r->userid] = true;
                $visits_by_month[$month]['total_visits']++;
            }

            // Array für die Rückgabe vorbereiten
            $result = [];
            ksort($visits_by_month);

            foreach ($visits_by_month as $month => $data) {
                $result[] = [
                    'date' => $month,
                    'count' => count($data['unique_visitors_set']),
                    'total_visits' => $data['total_visits'],
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
