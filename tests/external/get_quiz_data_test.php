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

global $CFG;
require_once($CFG->dirroot . '/blocks/learninganalytics/classes/external/get_quiz_data.php');

/**
 * Tests for the get_quiz_data external API.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_learninganalytics\external\get_quiz_data
 */
final class get_quiz_data_test extends \advanced_testcase {

    /**
     * Test execute returns structure with user_score, average_score, max_score.
     */
    public function test_execute_returns_expected_structure(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->setUser($user);
        $generator->enrol_user($user->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 10.0]);

        $result = \block_learninganalytics\external\get_quiz_data::execute($quiz->id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_score', $result);
        $this->assertArrayHasKey('average_score', $result);
        $this->assertArrayHasKey('max_score', $result);
        $this->assertIsFloat($result['user_score']);
        $this->assertIsFloat($result['average_score']);
        $this->assertIsFloat($result['max_score']);
        $this->assertSame(10.0, $result['max_score']);
    }

    /**
     * Test execute uses current user for user_score.
     */
    public function test_execute_uses_current_user(): void {
        global $DB;
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->setUser($user);
        $generator->enrol_user($user->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 10.0]);
        $DB->insert_record('quiz_grades', (object)[
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'grade' => 7.5,
            'timemodified' => time(),
        ]);

        $result = \block_learninganalytics\external\get_quiz_data::execute($quiz->id);
        $this->assertSame(7.5, $result['user_score']);
    }

    /**
     * Test execute_parameters returns correct parameter definition.
     */
    public function test_execute_parameters(): void {
        $params = \block_learninganalytics\external\get_quiz_data::execute_parameters();
        $this->assertInstanceOf(\external_function_parameters::class, $params);
    }

    /**
     * Test execute_returns returns correct return structure.
     */
    public function test_execute_returns(): void {
        $returns = \block_learninganalytics\external\get_quiz_data::execute_returns();
        $this->assertInstanceOf(\external_single_structure::class, $returns);
    }
}
