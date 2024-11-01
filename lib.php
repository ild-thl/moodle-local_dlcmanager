<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Create the API User role.
 *
 * @return int The ID of the created role
 */
function local_dlcmanager_create_api_user_role() {
    global $DB;

    // Define the role name and shortname
    $rolename = 'API User';
    $roleshortname = 'apiuser';

    // Check if the role already exists
    $existingrole = $DB->get_record('role', array('shortname' => $roleshortname));
    if ($existingrole) {
        return $existingrole->id;
    }

    // Create the role
    $roleid = create_role($rolename, $roleshortname, 'Role for API users with limited capabilities', 'guest');

    // Make this role assignable on system level
    set_role_contextlevels($roleid, array(CONTEXT_SYSTEM));

    // Define the capabilities to assign to the role
    $capabilities = array(
        'local/dlcmanager:viewusercourses' => CAP_ALLOW,
        'webservice/rest:use' => CAP_ALLOW,
    );

    // Assign the capabilities to the role
    foreach ($capabilities as $capability => $permission) {
        assign_capability($capability, $permission, $roleid, \context_system::instance());
    }

    return $roleid;
}

/**
 * Create an API user and assign the API User role.
 *
 * @param int $roleid The ID of the API User role
 * @return int The ID of the created user
 */
function local_dlcmanager_create_api_user($roleid) {
    global $DB, $CFG;

    // Generate a random password
    $randompassword = random_string(34);

    // Define the user data
    $userdata = new stdClass();
    $userdata->username = 'apiuser';
    $userdata->password = hash_internal_user_password($randompassword);
    $userdata->firstname = 'API';
    $userdata->lastname = 'User';
    $userdata->email = 'apiuser@example.com';
    $userdata->auth = 'manual';
    $userdata->confirmed = 1;
    $userdata->mnethostid = $DB->get_field('mnet_host', 'id', array('wwwroot' => $CFG->wwwroot));

    // Check if the user already exists
    $existinguser = $DB->get_record('user', array('username' => $userdata->username));
    if ($existinguser) {
        return $existinguser->id;
    }

    // Create the user
    $userid = user_create_user($userdata, false, false);

    // Assign the role to the user
    role_assign($roleid, $userid, \context_system::instance());

    return $userid;
}
