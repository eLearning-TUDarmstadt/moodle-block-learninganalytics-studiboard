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
 * Service for course-related learning analytics data and operations.
 *
 * This class encapsulates read-only access to course information that is
 * relevant for the learning analytics block, in particular the calculation
 * of course progress and helper checks for activity completion.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_service {

    /**
     * Get the module categories used by the block.
     *
     * @return array associative array with keys 'important' and 'material'.
     */
    public static function get_module_categories(): array {
        return [
            'important' => [
                'assign',
                'quiz',
            ],
            'material' => [
                'resource', 'folder', 'page', 'book', 'url', 'label',
                'glossary', 'chat', 'forum', 'wiki',
                'choice', 'survey', 'lesson', 'workshop', 'data',
                'h5pactivity', 'scorm', 'imscp',
            ],
        ];
    }

    /**
     * Safely calculate a ratio for done/total.
     *
     * @param int $done Number of completed items.
     * @param int $total Total number of items.
     * @return float Value in the range [0.0, 1.0].
     */
    public static function ratio(int $done, int $total): float {
        if ($total === 0) {
            return 1.0;
        }
        return min(1.0, max(0.0, $done / $total));
    }

    /**
     * Check whether a user has edited a page in a given wiki.
     *
     * @param int $wikiid The wiki id.
     * @param int $userid The user id.
     * @return bool True if the user has at least one version in the wiki.
     */
    public static function has_user_edited_wiki(int $wikiid, int $userid): bool {
        global $DB;

        $sql = "
            SELECT 1
              FROM {wiki_versions} wv
              JOIN {wiki_pages} wp ON wp.id = wv.pageid
              JOIN {wiki_subwikis} sw ON sw.id = wp.subwikiid
             WHERE sw.wikiid = ?
               AND wv.userid = ?
        ";

        return $DB->record_exists_sql($sql, [$wikiid, $userid]);
    }

    /**
     * Determine whether the given wiki activity is a "real" group wiki.
     *
     * @param \cm_info $cm The course module.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The user id.
     * @return bool True if the activity behaves as a collaborative group wiki.
     */
    public static function is_real_group_wiki(\cm_info $cm, \completion_info $completion, int $userid): bool {
        global $DB, $CFG;

        if ($cm->modname !== 'wiki') {
            return false;
        }

        require_once($CFG->libdir . '/grouplib.php');

        if (groups_get_activity_groupmode($cm) == NOGROUPS) {
            return false;
        }

        $wiki = $DB->get_record('wiki', ['id' => $cm->instance]);
        if (!$wiki || $wiki->wikimode !== 'collaborative') {
            return false;
        }

        if (!empty($wiki->completionedits) || !empty($wiki->completionpages)) {
            return true;
        }

        return self::has_user_edited_wiki($cm->instance, $userid);
    }

    /**
     * Check whether an "important" activity is completed for the user.
     *
     * @param \cm_info $cm The course module.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The user id.
     * @return bool True if the activity is considered completed.
     */
    public static function is_important_completed(\cm_info $cm, \completion_info $completion, int $userid): bool {
        global $DB;

        $data = $completion->get_data($cm, true, $userid);
        if (in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            return true;
        }

        switch ($cm->modname) {
            case 'assign':
                return $DB->record_exists('assign_submission', [
                    'assignment' => $cm->instance,
                    'userid' => $userid,
                    'status' => 'submitted',
                ]);

            case 'quiz':
                return $DB->record_exists('quiz_attempts', [
                    'quiz' => $cm->instance,
                    'userid' => $userid,
                    'state' => 'finished',
                ]);

            case 'wiki':
                return self::has_user_edited_wiki($cm->instance, $userid);
        }

        return false;
    }

    /**
     * Check whether a "material" activity is completed for the user.
     *
     * @param \cm_info $cm The course module.
     * @param \completion_info $completion The course completion info.
     * @param int $userid The user id.
     * @return bool True if the activity is considered completed.
     */
    public static function is_material_completed(\cm_info $cm, \completion_info $completion, int $userid): bool {
        global $DB;

        if ($cm->modname === 'label') {
            return true;
        }

        $data = $completion->get_data($cm, true, $userid);
        if (in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            return true;
        }

        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            return $DB->record_exists('logstore_standard_log', [
                'userid' => $userid,
                'contextinstanceid' => $cm->id,
                'action' => 'viewed',
            ]);
        }

        return false;
    }

    /**
     * Get the due date timestamp for supported activity types.
     *
     * @param \cm_info $cm The course module.
     * @return int|null Unix timestamp or null if not applicable.
     */
    public static function get_duedate(\cm_info $cm): ?int {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                return $DB->get_field('assign', 'duedate', ['id' => $cm->instance]);
            case 'quiz':
                return $DB->get_field('quiz', 'timeclose', ['id' => $cm->instance]);
        }

        return null;
    }

    /**
     * Calculate course progress for the given user.
     *
     * This mirrors the original logic from block_learninganalytics and
     * returns the same data structure, so that the front-end behaviour
     * remains unchanged.
     *
     * @param stdClass $course The course record.
     * @param int $userid The user id.
     * @return array Associative array with progress details.
     */
    public static function calculate_course_progress(stdClass $course, int $userid): array {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course);

        $stats = [
            'abgaben' => [0, 0],
            'tests' => [0, 0],
            'wiki' => [0, 0],
            'material' => [0, 0],
        ];

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $iswiki = self::is_real_group_wiki($cm, $completion, $userid);

            if ($cm->modname === 'assign') {
                $stats['abgaben'][0]++;
                if (self::is_important_completed($cm, $completion, $userid)) {
                    $stats['abgaben'][1]++;
                }
            }

            if ($cm->modname === 'quiz') {
                $stats['tests'][0]++;
                if (self::is_important_completed($cm, $completion, $userid)) {
                    $stats['tests'][1]++;
                }
            }

            if ($iswiki) {
                $stats['wiki'][0]++;
                if (self::is_important_completed($cm, $completion, $userid)) {
                    $stats['wiki'][1]++;
                }
            }

            if (in_array($cm->modname, ['resource', 'folder', 'page', 'book', 'url', 'forum', 'wiki', 'label'])) {
                $stats['material'][0]++;
                if (self::is_material_completed($cm, $completion, $userid)) {
                    $stats['material'][1]++;
                }
            }
        }

        $ab = self::ratio($stats['abgaben'][1], $stats['abgaben'][0]);
        $te = self::ratio($stats['tests'][1], $stats['tests'][0]);
        $wi = self::ratio($stats['wiki'][1], $stats['wiki'][0]);
        $ma = self::ratio($stats['material'][1], $stats['material'][0]);

        $important = ($stats['wiki'][0] > 0)
            ? (0.5 * $ab + 0.3 * $te + 0.2 * $wi)
            : (0.7 * $ab + 0.3 * $te);

        $progress = (int) round(100 * (0.7 * $important + 0.3 * $ma));

        return [
            'progress' => $progress,
            'abgaben' => (int) round($ab * 100),
            'tests' => (int) round($te * 100),
            'wiki' => $stats['wiki'][0] > 0 ? (int) round($wi * 100) : null,
            'material' => (int) round($ma * 100),
            'haswiki' => $stats['wiki'][0] > 0,
        ];
    }
}


