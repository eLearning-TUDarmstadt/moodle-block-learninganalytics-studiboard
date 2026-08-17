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
 * Service for building and managing todo items.
 *
 * This class translates raw course and module information into todo entries
 * that can be displayed by the block,
 * including important items, materials and completion status.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class todo_service {

    /**
     * Build todo items for a set of courses and a specific user.
     *
     * The returned arrays match the previous data structure produced in
     * {@see block_learninganalytics::get_content()} so that templates and
     * JavaScript can remain unchanged.
     *
     * @param \stdClass[] $courses Course records (typically from enrol_get_users_courses()).
     * @param int $userid The user id to build the todo list for.
     * @return array Array with keys 'important_todos' and 'material_todos'.
     */
    public static function build_todos_for_courses(array $courses, int $userid): array {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $importanttodos = [];
        $materialtodos = [];

        $categories = course_service::get_module_categories();
        $allowed = array_merge($categories['important'], $categories['material']);

        foreach ($courses as $course) {
            $completion = new \completion_info($course);
            $modinfo = get_fast_modinfo($course);

            foreach ($modinfo->get_cms() as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                if (!in_array($cm->modname, $allowed)) {
                    continue;
                }

                $iswiki = course_service::is_real_group_wiki($cm, $completion, $userid);

                $important = in_array($cm->modname, $categories['important']) || $iswiki;
                $material = !$important;

                $done = $important
                    ? course_service::is_important_completed($cm, $completion, $userid)
                    : course_service::is_material_completed($cm, $completion, $userid);

                $due = course_service::get_duedate($cm);

                $item = [
                    'name' => $cm->name,
                    'course' => $course->fullname,
                    'courseid' => $course->id,
                    'moduleid' => $cm->id,
                    'modname' => $cm->modname,
                    'icon' => self::get_module_icon($cm->modname),
                    'completed' => $done,
                    'overdue' => $due && $due < time() && !$done,
                    'duedate' => $due ? userdate($due) : null,
                    'duedate_ts' => $due,
                    'url' => $cm->url?->out(false),
                ];

                if ($important) {
                    $importanttodos[] = $item;
                } else if ($material) {
                    $materialtodos[] = $item;
                }
            }
        }

        self::sort_important_todos($importanttodos);
        self::sort_material_todos($materialtodos);

        return [
            'important_todos' => $importanttodos,
            'material_todos' => $materialtodos,
        ];
    }

    /**
     * Get an icon for a module type.
     *
     * This mirrors the existing icon selection used by the block so that
     * the displayed todo list remains unchanged.
     *
     * @param string $modname The activity module name.
     * @return string The icon.
     */
    private static function get_module_icon(string $modname): string {
        return match ($modname) {
            'assign' => '📝',
            'quiz' => '📋',
            'forum' => '💬',
            'wiki' => '📝',
            default => '📌',
        };
    }

    /**
     * Sort important todo items: incomplete first, overdue first, then due date.
     *
     * @param array $todos Todo items to sort, by reference.
     * @return void
     */
    private static function sort_important_todos(array &$todos): void {
        usort($todos, function(array $a, array $b): int {
            $priority = function(array $item): int {
                if (!empty($item['completed'])) {
                    return 3;
                }
                if (!empty($item['overdue'])) {
                    return 0;
                }
                if (!empty($item['duedate_ts'])) {
                    return 1;
                }
                return 2;
            };

            $pa = $priority($a);
            $pb = $priority($b);

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            $duea = $a['duedate_ts'] ?? PHP_INT_MAX;
            $dueb = $b['duedate_ts'] ?? PHP_INT_MAX;

            return $duea <=> $dueb;
        });
    }

    /**
     * Sort material todo items: incomplete first, then due date.
     *
     * @param array $todos Todo items to sort, by reference.
     * @return void
     */
    private static function sort_material_todos(array &$todos): void {
        usort($todos, function(array $a, array $b): int {
            $priority = function(array $item): int {
                if (!empty($item['completed'])) {
                    return 3;
                }
                if (!empty($item['duedate_ts'])) {
                    return 1;
                }
                return 2;
            };

            $pa = $priority($a);
            $pb = $priority($b);

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            $duea = $a['duedate_ts'] ?? PHP_INT_MAX;
            $dueb = $b['duedate_ts'] ?? PHP_INT_MAX;

            return $duea <=> $dueb;
        });
    }
}

