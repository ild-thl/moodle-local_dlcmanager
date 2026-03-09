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
 * Class update_user_email
 *
 * This class extends the external_api and provides functionality to update a user's email address.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class update_user_email extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {

        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'The username of the user'),
            'email' => new external_value(PARAM_EMAIL, 'The new email address')
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'updated' => new external_value(PARAM_BOOL, 'True if the user email was updated'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Updates a user's email address based on their username.
     *
     * @param string $username The username of the user.
     * @param string $email The new email address.
     * @return array
     * @throws \moodle_exception If a database operation fails.
     */
    public static function execute($username, $email) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'username' => $username,
            'email' => $email
        ]);

        // Check if the current user has the required capability
        require_capability('moodle/user:update', \context_system::instance());

        // Get user by username
        $user = $DB->get_record('user', ['username' => $params['username']], '*');

        if (!$user) {
            // User does not exist
            return [
                'updated' => false,
                'error' => 'User not found: ' . $params['username'],
            ];
        }

        // Check if user is deleted
        if ($user->deleted) {
            return [
                'updated' => false,
                'error' => 'Cannot update email for deleted user.',
            ];
        }

        try {
            // Check if email is already in use by another user
            $existing = $DB->get_record('user', ['email' => $params['email']], '*');
            if ($existing && $existing->id != $user->id && $existing->deleted == 0) {
                return [
                    'updated' => false,
                    'error' => 'Email address is already in use by another user.',
                ];
            }

            // Update the user's email
            $user->email = $params['email'];
            $user->timemodified = time();

            $result = $DB->update_record('user', $user);

            if (!$result) {
                return [
                    'updated' => false,
                    'error' => 'Database update failed.',
                ];
            }

            // Trigger user updated event
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

