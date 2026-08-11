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
 * Marks a todo item as done for the current user (AJAX/callback endpoint).
 *
 * Expects cmid (course module id) via GET/POST and sesskey; inserts into
 * block_la_todos_done if not already present and returns JSON status.
 *
 * @package    block_learninganalytics
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();
require_sesskey();

global $USER, $DB;

$cmid = required_param('cmid', PARAM_INT);

$record = (object)[
    'userid' => $USER->id,
    'cmid' => $cmid,
    'timecompleted' => time(),
];

if (!$DB->record_exists('block_la_todos_done', [
    'userid' => $USER->id,
    'cmid' => $cmid,
])) {
    $DB->insert_record('block_la_todos_done', $record);
}

echo json_encode(['status' => 'ok']);
exit;
