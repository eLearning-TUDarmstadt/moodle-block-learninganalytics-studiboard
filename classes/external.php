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

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * Legacy external API for quiz data (web service).
 *
 * Returns quiz comparison data for a given quiz (user vs. average).
 * Called via AJAX when the user selects a quiz. Kept for backward compatibility;
 * the preferred endpoint is block_learninganalytics\external\get_quiz_data.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Parameter definition for get_quiz_data.
     *
     * @return external_function_parameters
     */
    public static function get_quiz_data_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'The id of the selected quiz'),
        ]);
    }

    /**
     * Returns quiz data for a given quiz (user score vs. course average).
     *
     * Called via AJAX when the user selects a quiz in the block.
     *
     * @param int $quizid The id of the selected quiz.
     * @return array User score, average score, max score, or error key on failure.
     */
    public static function get_quiz_data($quizid) {
        global $DB, $USER;

        try {
            $params = self::validate_parameters(self::get_quiz_data_parameters(), [
                'quizid' => $quizid,
            ]);

            // Load quiz record (grade = maximum score).
            $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], 'grade', MUST_EXIST);
            $maxscore = (float)$quiz->grade;

            // Load current user's grade.
            $usergrade = $DB->get_field('quiz_grades', 'grade', [
                'quiz'   => $params['quizid'],
                'userid' => $USER->id,
            ]);
            $userscore = ($usergrade !== false) ? (float)$usergrade : 0.0;

            // Compute average over all participants.
            $avgscore = $DB->get_field_sql(
                'SELECT AVG(grade) FROM {quiz_grades} WHERE quiz = ?',
                [$params['quizid']],
            );
            $avgscore = ($avgscore !== null) ? (float)$avgscore : 0.0;

            return [
                'user_score'    => $userscore,
                'average_score' => $avgscore,
                'max_score'     => $maxscore,
            ];
        } catch (\Exception $e) {
            // On error (e.g. invalid quiz), return an error message.
            return [
                'error' => 'Daten für dieses Quiz nicht verfügbar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Return structure for get_quiz_data.
     *
     * @return external_single_structure
     */
    public static function get_quiz_data_returns() {
        return new external_single_structure([
            'user_score'    => new external_value(PARAM_FLOAT, 'User score'),
            'average_score' => new external_value(PARAM_FLOAT, 'Average score of all participants'),
            'max_score'     => new external_value(PARAM_FLOAT, 'Maximum score'),
        ]);
    }

    /**
     * Parameter definition for get_quiz_comparison (legacy).
     *
     * @return external_function_parameters
     */
    public static function get_quiz_comparison_parameters() {
        return new external_function_parameters([
            'quizid' => new external_value(PARAM_INT, 'The id of the selected quiz'),
            'courseid' => new external_value(PARAM_INT, 'The id of the course'),
        ]);
    }

    /**
     * Legacy: returns quiz comparison data (user vs. average).
     *
     * @param int $quizid The id of the selected quiz.
     * @param int $courseid The id of the course.
     * @return array User score, average score, max score.
     */
    public static function get_quiz_comparison($quizid, $courseid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_quiz_comparison_parameters(), [
            'quizid' => $quizid,
            'courseid' => $courseid,
        ]);

        // Get max score from quiz (sumgrades).
        $quiz = $DB->get_record('quiz', ['id' => $params['quizid']], 'sumgrades', MUST_EXIST);
        $maxscore = (float)$quiz->sumgrades;

        // Get current user's grade.
        $usergrade = $DB->get_field('quiz_grades', 'grade', [
            'quiz' => $params['quizid'],
            'userid' => $USER->id,
        ]);
        $userscore = $usergrade ? (float)$usergrade : 0;

        // Compute average.
        $avgscore = $DB->get_field_sql("SELECT AVG(grade) FROM {quiz_grades} WHERE quiz = ?", [$params['quizid']]);
        $avgscore = $avgscore ? (float)$avgscore : 0;

        return [
            'user_score' => $userscore,
            'average_score' => round($avgscore, 2),
            'max_score' => $maxscore,
        ];
    }

    /**
     * Return structure for get_quiz_comparison (legacy).
     *
     * @return external_single_structure
     */
    public static function get_quiz_comparison_returns() {
        return new external_single_structure([
            'user_score' => new external_value(PARAM_FLOAT, 'User score'),
            'average_score' => new external_value(PARAM_FLOAT, 'Average score'),
            'max_score' => new external_value(PARAM_FLOAT, 'Maximum score'),
        ]);
    }
}
