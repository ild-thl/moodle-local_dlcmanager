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
 * Class update_user_names
 *
 * Updates a user's first and last name by username.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class update_user_names extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'The username of the user'),
            'firstname' => new external_value(PARAM_NOTAGS, 'The new first name'),
            'lastname' => new external_value(PARAM_NOTAGS, 'The new last name'),
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'updated' => new external_value(PARAM_BOOL, 'True if the user names were updated'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Updates a user's first and last name based on their username.
     *
     * @param string $username The username of the user.
     * @param string $firstname The new first name.
     * @param string $lastname The new last name.
     * @return array
     */
    public static function execute($username, $firstname, $lastname) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ]);

        require_capability('moodle/user:update', \context_system::instance());

        $user = $DB->get_record('user', ['username' => $params['username']], '*');

        if (!$user) {
            return [
                'updated' => false,
                'error' => 'User not found: ' . $params['username'],
            ];
        }

        if ($user->deleted) {
            return [
                'updated' => false,
                'error' => 'Cannot update names for deleted user.',
            ];
        }

        try {
            $user->firstname = $params['firstname'];
            $user->lastname = $params['lastname'];
            $user->timemodified = time();

            $result = $DB->update_record('user', $user);

            if (!$result) {
                return [
                    'updated' => false,
                    'error' => 'Database update failed.',
                ];
            }

            \core\event\user_updated::create_from_userid($user->id)->trigger();
        } catch (\Exception $e) {
            return [
                'updated' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'updated' => true,
        ];
    }
}
