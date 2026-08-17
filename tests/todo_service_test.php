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
require_once($CFG->dirroot . '/blocks/learninganalytics/classes/local/todo_service.php');

/**
 * Unit tests for todo_service.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_learninganalytics\local\todo_service
 */
final class todo_service_test extends \advanced_testcase {

    /**
     * Test build_todos_for_courses returns both keys and empty lists when given no courses.
     */
    public function test_build_todos_for_courses_empty_courses(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();

        $result = \block_learninganalytics\local\todo_service::build_todos_for_courses([], $user->id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('important_todos', $result);
        $this->assertArrayHasKey('material_todos', $result);
        $this->assertIsArray($result['important_todos']);
        $this->assertIsArray($result['material_todos']);
        $this->assertEmpty($result['important_todos']);
        $this->assertEmpty($result['material_todos']);
    }

    /**
     * Test build_todos_for_courses returns structure when course has no relevant modules.
     */
    public function test_build_todos_for_courses_course_no_modules(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $result = \block_learninganalytics\local\todo_service::build_todos_for_courses([$course], $user->id);
        $this->assertArrayHasKey('important_todos', $result);
        $this->assertArrayHasKey('material_todos', $result);
        $this->assertIsArray($result['important_todos']);
        $this->assertIsArray($result['material_todos']);
    }
}
