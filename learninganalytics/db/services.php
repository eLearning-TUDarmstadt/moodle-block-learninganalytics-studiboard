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
 * Web service definitions for the Learning Analytics block.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_learninganalytics_get_quiz_data' => [
        'classname'   => 'block_learninganalytics\external\get_quiz_data',
        'methodname'  => 'execute',
        'description' => 'Holt Quiz-Daten (Nutzer vs. Durchschnitt) für ein bestimmtes Quiz',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'block_learninganalytics_get_quiz_comparison' => [
        'classname'   => 'block_learninganalytics\external',
        'methodname'  => 'get_quiz_comparison',
        'description' => 'Holt Quiz-Daten für den Vergleich (legacy)',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
