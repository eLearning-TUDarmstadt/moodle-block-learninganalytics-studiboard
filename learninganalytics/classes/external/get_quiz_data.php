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

namespace block_learninganalytics\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use block_learninganalytics\local\quiz_service;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External API entry point for quiz data.
 *
 * This class exposes a web service method that delegates to the internal
 * quiz service in order to provide data for AJAX-based visualisations
 * in the learning analytics block.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_quiz_data extends external_api {

    /**
     * Returns the parameter definition for the execute() method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'The id of the selected quiz'),
        ]);
    }

    /**
     * Execute the web service call.
     *
     * This method validates parameters and context and delegates to the quiz
     * service to fetch the score data for the current user.
     *
     * @param int $quizid The id of the selected quiz.
     * @return array The quiz data for the current user.
     */
    public static function execute(int $quizid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
        ]);

        // For now we validate at system context level. If needed, this can be
        // tightened to course context once the calling context is extended.
        self::validate_context(\context_system::instance());

        return quiz_service::get_quiz_scores((int) $params['quizid'], (int) $USER->id);
    }

    /**
     * Returns the description of the execute() result structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'user_score' => new external_value(
                PARAM_FLOAT,
                'Score of the current user for the quiz'
            ),
            'average_score' => new external_value(
                PARAM_FLOAT,
                'Average score of all participants in the quiz'
            ),
            'max_score' => new external_value(
                PARAM_FLOAT,
                'Maximum possible score for the quiz'
            ),
        ]);
    }
}

