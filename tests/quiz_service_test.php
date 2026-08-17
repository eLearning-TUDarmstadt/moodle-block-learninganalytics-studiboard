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
require_once($CFG->dirroot . '/blocks/learninganalytics/classes/local/quiz_service.php');

/**
 * Unit tests for quiz_service.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_learninganalytics\local\quiz_service
 */
final class quiz_service_test extends \advanced_testcase {

    /**
     * Test get_quizzes_for_course returns empty array when course has no quizzes.
     */
    public function test_get_quizzes_for_course_empty(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $result = \block_learninganalytics\local\quiz_service::get_quizzes_for_course($course);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_quizzes_for_course returns quiz list with id and name when course has a quiz.
     */
    public function test_get_quizzes_for_course_with_quiz(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $generator->create_module('quiz', ['course' => $course->id, 'name' => 'Test Quiz']);

        $result = \block_learninganalytics\local\quiz_service::get_quizzes_for_course($course);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertIsInt($result[0]['id']);
        $this->assertSame('Test Quiz', $result[0]['name']);
    }

    /**
     * Test get_quiz_scores returns expected keys and zero scores when no attempts exist.
     */
    public function test_get_quiz_scores_no_attempts(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);

        $result = \block_learninganalytics\local\quiz_service::get_quiz_scores($quiz->id, $user->id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_score', $result);
        $this->assertArrayHasKey('average_score', $result);
        $this->assertArrayHasKey('max_score', $result);
        $this->assertSame(0.0, $result['user_score']);
        $this->assertSame(0.0, $result['average_score']);
        $this->assertGreaterThanOrEqual(0.0, $result['max_score']);
    }

    /**
     * Test get_quiz_scores returns user and average when grades exist.
     */
    public function test_get_quiz_scores_with_grades(): void {
        global $DB;
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'grade' => 10.0]);

        $DB->insert_record('quiz_grades', (object)[
            'quiz' => $quiz->id,
            'userid' => $user1->id,
            'grade' => 8.0,
            'timemodified' => time(),
        ]);
        $DB->insert_record('quiz_grades', (object)[
            'quiz' => $quiz->id,
            'userid' => $user2->id,
            'grade' => 6.0,
            'timemodified' => time(),
        ]);

        $result = \block_learninganalytics\local\quiz_service::get_quiz_scores($quiz->id, $user1->id);
        $this->assertSame(8.0, $result['user_score']);
        $this->assertSame(10.0, $result['max_score']);
        $this->assertSame(7.0, $result['average_score']);
    }
}
