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
 * Upgrade script for local_dlcmanager.
 *
 * @package     local_dlcmanager
 * @copyright   2024 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../lib.php');

/**
 * Upgrades the database according to the current version.
 *
 * @param int $oldversion
 * @return boolean
 */
function xmldb_local_dlcmanager_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024110119) {
        // Create the API User role and user
        $roleid = local_dlcmanager_create_api_user_role();
        local_dlcmanager_create_api_user($roleid);

        // Moodle savepoint reached.
        upgrade_plugin_savepoint(true, 2024110119, 'local', 'dlcmanager');
    }

    return true;
}
