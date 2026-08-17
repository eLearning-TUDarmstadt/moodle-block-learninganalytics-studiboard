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

/**
 * High-level orchestration service for learning analytics.
 *
 * This class coordinates the specialised local services (course, todo, quiz,
 * peer) and returns the aggregated data structure expected by the block
 * template. It does not duplicate business logic.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics_service {

    /**
     * Build the full data structure for the block content.
     *
     * Coordinates course_service, todo_service, quiz_service and peer_service
     * so that the returned array can be passed unchanged to the block
     * template (block.mustache).
     *
     * @param array $courses Array of course records (e.g. from enrol_get_users_courses).
     * @param int $userid The user id to build analytics for.
     * @return array Template data with keys: courses, overall_progress, overall_emoji,
     *               selected_course, important_todos, material_todos, course_details_json,
     *               selected_course_quizzes, current_course_id.
     */
    public static function get_block_data(array $courses, int $userid): array {
        $todos = todo_service::build_todos_for_courses($courses, $userid);
        $importanttodos = $todos['important_todos'];
        $materialtodos = $todos['material_todos'];

        $courselist = [];
        $coursedetails = [];
        $total = 0;
        $count = 0;

        $since = time() - (14 * DAYSECS);

        foreach ($courses as $course) {
            $progress = course_service::calculate_course_progress($course, $userid);
            $peer = peer_service::calculate_peer_activity_comparison($course, $userid, $since);
            $quizdata = quiz_service::get_quizzes_for_course($course);

            $courselist[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'progress' => $progress['progress'],
            ];

            $coursedetails[] = array_merge(
                ['id' => $course->id, 'fullname' => $course->fullname],
                $progress,
                [
                    'peer_percentile' => $peer['peer_percentile'],
                    'peer_score' => $peer['peer_score'],
                    'peer_nactive' => $peer['peer_nactive'],
                    'quizzes' => $quizdata,
                ]
            );

            $total += $progress['progress'];
            $count++;
        }

        $overall = $count ? (int) round($total / $count) : 0;

        $selectedcourse = [];
        if (!empty($courselist)) {
            $selectedcourse = [
                'id' => $courselist[0]['id'],
                'fullname' => $courselist[0]['fullname'],
                'progress' => $courselist[0]['progress'],
                'emoji' => self::get_emoji($courselist[0]['progress']),
            ];
        } else {
            $selectedcourse = [
                'id' => 0,
                'fullname' => '',
                'progress' => 0,
                'emoji' => self::get_emoji(0),
            ];
        }

        $selectedid = $selectedcourse['id'];
        foreach ($courselist as &$course) {
            $course['is_selected'] = ($course['id'] === $selectedid);
        }
        unset($course);

        $selectedcoursedetail = null;
        foreach ($coursedetails as $detail) {
            if ($detail['id'] == $selectedid) {
                $selectedcoursedetail = $detail;
                break;
            }
        }

        $selectedquizzes = $selectedcoursedetail ? ($selectedcoursedetail['quizzes'] ?? []) : [];

        return [
            'courses' => $courselist,
            'overall_progress' => $overall,
            'overall_emoji' => self::get_emoji($overall),
            'selected_course' => $selectedcourse,
            'important_todos' => $importanttodos,
            'material_todos' => $materialtodos,
            'course_details_json' => json_encode($coursedetails),
            'selected_course_quizzes' => $selectedquizzes,
            'current_course_id' => $selectedcourse['id'],
        ];
    }

    /**
     * Return an emoji for a progress value (display only).
     *
     * @param int $value Progress value 0–100.
     * @return string Single emoji character.
     */
    private static function get_emoji(int $value): string {
        if ($value >= 80) {
            return "😄";
        }
        if ($value >= 60) {
            return "🙂";
        }
        if ($value >= 40) {
            return "😐";
        }
        if ($value >= 20) {
            return "😟";
        }
        return "😢";
    }
}

