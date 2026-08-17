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

namespace block_learninganalytics\local;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Service for quiz-related learning analytics data.
 *
 * This class encapsulates read-only access to quiz structures and
 * aggregated result data that can be used by the block and its external
 * functions for comparisons and visualisations.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_service {

    /**
     * Build the list of quizzes for the given course.
     *
     * The returned structure is optimised for the front-end usage in the
     * learning analytics block and matches the previous inline data
     * structure that was assembled in {@see block_learninganalytics::get_content()}.
     *
     * @param stdClass $course The course record.
     * @return array[] List of quizzes, each containing keys 'id' and 'name'.
     */
    public static function get_quizzes_for_course(stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);

        $quizdata = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->modname !== 'quiz') {
                continue;
            }

            // Load basic quiz data from {quiz} table.
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id,name', IGNORE_MISSING);
            if (!$quiz) {
                continue;
            }

            // Keep the existing front-end data structure.
            $quizdata[] = [
                'id' => (int) $quiz->id,
                'name' => $cm->name,
            ];
        }

        return $quizdata;
    }

    /**
     * Get score information for a single quiz and user.
     *
     * Returns the user's score, the average score of all attempts and the
     * maximum possible score of the quiz.
     *
     * @param int $quizid The quiz id.
     * @param int $userid The user id.
     * @return array Array with keys user_score, average_score and max_score.
     */
    public static function get_quiz_scores(int $quizid, int $userid): array {
        global $DB;

        // 1. Load quiz record (grade is the maximum possible score).
        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'grade', MUST_EXIST);
        $maxscore = (float) $quiz->grade;

        // 2. Load the user's score.
        $usergrade = $DB->get_field('quiz_grades', 'grade', [
            'quiz' => $quizid,
            'userid' => $userid,
        ]);
        $userscore = ($usergrade !== false) ? (float) $usergrade : 0.0;

        // 3. Calculate the average score of all participants.
        $avgscore = $DB->get_field_sql(
            'SELECT AVG(grade) FROM {quiz_grades} WHERE quiz = ?',
            [$quizid]
        );
        $avgscore = ($avgscore !== null) ? (float) $avgscore : 0.0;

        return [
            'user_score' => $userscore,
            'average_score' => $avgscore,
            'max_score' => $maxscore,
        ];
    }
}

