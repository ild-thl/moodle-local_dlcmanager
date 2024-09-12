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
 * Event observer definitions for the local_dlcmanager plugin.
 *
 * @package   local_dlcmanager
 * @copyright 2024 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$observers = array(
    array(
        'eventname' => '\local_ildmeta\event\ildmeta_updated',
        'callback'  => '\local_dlcmanager\event\observer::course_metadata_updated',
    ),
    array(
        'eventname' => '\core\event\course_updated',
        'callback'  => '\local_dlcmanager\event\observer::course_updated',
    ),
    array(
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\local_dlcmanager\event\observer::course_deleted',
    ),
);