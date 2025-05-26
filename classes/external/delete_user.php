<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core\check\performance\debugging;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

require_once(__DIR__ . '/../../../../config.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Class delete_user
 *
 * This class extends the external_api and provides functionality to request the deletion of a user.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class delete_user extends external_api {

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
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'True if the user was deleted'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Deletes a user from the system based on their username.
     * 
     * @param string $username The username of the user.
     * @return array 
     * @throws \moodle_exception If a database operation fails.
     */
    public static function execute($username) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['username' => $username]);

        // Check if the current user has the required capability
        require_capability('tool/dataprivacy:requestdeleteforotheruser', \context_system::instance());

        // Get user by username
        $user = $DB->get_record('user', ['username' => $params['username']], '*');

        if (!$user) {
            // User does not exist or was already deleted.
            debugging('User not found: ' . $params['username']);
            return [
                'deleted' => true,
            ];
        }

        try {
            // Delete the user
            $isDeleted = delete_user($user);

            if (!$isDeleted) {
                // If the user could not be deleted, return an error.
                return [
                    'deleted' => false,
                    'error' => 'User could not be deleted.',
                ];
            }
        } catch (\Exception $e) {
            return [
                'deleted' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'deleted' => true,
        ];
    }
}
