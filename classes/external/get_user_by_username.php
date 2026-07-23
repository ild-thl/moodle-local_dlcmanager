<?php

declare(strict_types=1);

namespace local_dlcmanager\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

require_once(__DIR__ . '/../../../../config.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Class get_user_by_username
 *
 * This class extends the external_api and provides functionality to get user information by username.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class get_user_by_username extends external_api {

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
            'found' => new external_value(PARAM_BOOL, 'True if the user was found'),
            'id' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
            'username' => new external_value(PARAM_USERNAME, 'Username', VALUE_OPTIONAL),
            'email' => new external_value(PARAM_EMAIL, 'Email address', VALUE_OPTIONAL),
            'firstname' => new external_value(PARAM_TEXT, 'First name', VALUE_OPTIONAL),
            'lastname' => new external_value(PARAM_TEXT, 'Last name', VALUE_OPTIONAL),
            'deleted' => new external_value(PARAM_BOOL, 'Whether user is deleted', VALUE_OPTIONAL),
            'suspended' => new external_value(PARAM_BOOL, 'Whether user is suspended', VALUE_OPTIONAL),
            'timecreated' => new external_value(PARAM_INT, 'Time created', VALUE_OPTIONAL),
            'timemodified' => new external_value(PARAM_INT, 'Time modified', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Gets user information by username.
     *
     * @param string $username The username of the user.
     * @return array
     * @throws \moodle_exception If a database operation fails.
     */
    public static function execute($username) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['username' => $username]);

        // Check if the current user has the required capability
        require_capability('moodle/user:viewalldetails', \context_system::instance());

        // Get user by username
        $user = $DB->get_record('user', ['username' => $params['username']], '*');

        if (!$user) {
            // User does not exist
            return [
                'found' => false,
                'error' => 'User not found: ' . $params['username'],
            ];
        }

        try {
            return [
                'found' => true,
                'id' => (int)$user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'deleted' => (bool)$user->deleted,
                'suspended' => (bool)$user->suspended,
                'timecreated' => (int)$user->timecreated,
                'timemodified' => (int)$user->timemodified,
            ];
        } catch (\Exception $e) {
            return [
                'found' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

