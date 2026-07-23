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
 * Class anonymize_user
 *
 * This class extends the external_api and provides functionality to anonymize a user.
 *
 * @package    local_dlcmanager
 * @category   external
 */
class anonymize_user extends external_api {

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'username' => new external_value(PARAM_USERNAME, 'The username of the user'),
            'email' => new external_value(PARAM_EMAIL, 'The anonymized email address'),
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'anonymized' => new external_value(PARAM_BOOL, 'True if the user was anonymized'),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Anonymizes a user based on their username.
     *
     * @param string $username The username of the user.
     * @param string $email The anonymized email address.
     * @return array
     * @throws \moodle_exception If a database operation fails.
     */
    public static function execute($username, $email) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'username' => $username,
            'email' => $email
        ]);

        require_capability('moodle/user:update', \context_system::instance());

        $user = $DB->get_record('user', ['username' => $params['username']], '*');

        if (!$user) {
            // User does not exist in Moodle -> treat as success
            return [
                'anonymized' => true,
            ];
        }

        if ($user->deleted) {
            return [
                'anonymized' => false,
                'error' => 'Cannot anonymize a deleted user.',
            ];
        }

        try {
            $existing = $DB->get_record('user', ['email' => $params['email']], '*');
            if ($existing && $existing->id != $user->id && $existing->deleted == 0) {
                return [
                    'anonymized' => false,
                    'error' => 'Email address is already in use by another user.',
                ];
            }

            $user->email = $params['email'];
            $user->firstname = 'Gelöschtes Profil';
            $user->lastname = '';
            $user->suspended = 1;
            $user->timemodified = time();

            $result = $DB->update_record('user', $user);

            if (!$result) {
                return [
                    'anonymized' => false,
                    'error' => 'Database update failed.',
                ];
            }

            \core\event\user_updated::create_from_userid($user->id)->trigger();
        } catch (\Exception $e) {
            return [
                'anonymized' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'anonymized' => true,
        ];
    }
}
