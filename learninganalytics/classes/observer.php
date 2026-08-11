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

namespace block_learninganalytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the Learning Analytics block.
 *
 * Marks the corresponding todo as done for the current user when
 * a course module is viewed.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Triggered when a course module is viewed.
     *
     * Marks the corresponding todo item as completed for the current user.
     *
     * @param \core\event\course_module_viewed $event The event.
     * @return bool True.
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): bool {
        global $DB, $USER;

        // Context instance is typically the course module id.
        $cmid = $event->contextinstanceid ?? 0;
        if (empty($cmid) || empty($USER->id)) {
            return true;
        }

        $record = (object)[
            'userid'        => $USER->id,
            'cmid'          => $cmid,
            'timecompleted' => time(),
        ];

        if (!$DB->record_exists('block_la_todos_done', [
            'userid' => $USER->id,
            'cmid'   => $cmid,
        ])) {
            $DB->insert_record('block_la_todos_done', $record);
        }

        return true;
    }
}
