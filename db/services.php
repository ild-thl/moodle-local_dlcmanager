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
 * External functions and service description.
 *
 * @package   local_dlcmanager
 * @copyright 2024 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$functions = array(
    'local_dlcmanager_get_user_course_progress' => array(
        'classname'   => 'local_dlcmanager\external\get_user_course_progress',
        'description' => 'Get courses a user is enrolled in by username.',
        'type'        => 'read',
        'capabilities' => 'local/dlcmanager:viewusercourses',
    ),
    'local_dlcmanager_delete_user' => array(
        'classname'   => 'local_dlcmanager\external\delete_user',
        'description' => 'Deletes the requested user.',
        'type'        => 'write',
        'capabilities' => 'tool/dataprivacy:requestdeleteforotheruser',
    ),
    'local_dlcmanager_get_course_enrolments' => array(
        'classname'   => 'local_dlcmanager\external\get_course_enrolments',
        'description' => 'Get enrolment counts per month.',
        'type'        => 'read',
        'capabilities' => '',
    ),
    'local_dlcmanager_get_user_counts' => array(
        'classname'   => 'local_dlcmanager\external\get_user_counts',
        'description' => 'Get user counts per month.',
        'type'        => 'read',
        'capabilities' => '',
    ), 
    'local_dlcmanager_get_course_visits' => array(
        'classname'   => 'local_dlcmanager\external\get_course_visits',
        'description' => 'Get course visit statistics.',
        'type'        => 'read',
        'capabilities' => '',
    ),
    'local_dlcmanager_get_dlc_statistics' => array(
        'classname'   => 'local_dlcmanager\external\get_dlc_statistics',
        'description' => 'Get all DLC statistics (enrolments, visits, user counts) in one call.',
        'type'        => 'read',
        'capabilities' => '',
    ),

);