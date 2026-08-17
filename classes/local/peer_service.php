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
 * Service for peer activity and comparison metrics.
 *
 * This class will provide methods to calculate peer-based metrics such
 * as activity scores, percentiles and cohort sizes, based on the log
 * data available in Moodle.
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_service {

    /**
     * Get default action weights for peer activity scoring.
     *
     * @return int[] Map of action name to weight.
     */
    public static function get_activity_weights(): array {
        return [
            'submitted' => 5,
            'created' => 3,
            'updated' => 2,
            'viewed' => 1,
        ];
    }

    /**
     * Calculate a user's weighted activity score in a course for the given period.
     *
     * Score = SUM( count(action) * weight(action) ).
     *
     * @param int $courseid The course id.
     * @param int $userid The user id.
     * @param int $since Unix timestamp (inclusive).
     * @return int The weighted activity score.
     */
    public static function get_user_activity_score(int $courseid, int $userid, int $since): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return 0;
        }

        $weights = self::get_activity_weights();
        if (empty($weights)) {
            return 0;
        }

        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($weights), SQL_PARAMS_NAMED, 'act');

        $sql = "
            SELECT action, COUNT(1) AS cnt
              FROM {logstore_standard_log}
             WHERE courseid = :courseid
               AND userid = :userid
               AND timecreated >= :since
               AND action $insql
          GROUP BY action
        ";

        $params = array_merge([
            'courseid' => $courseid,
            'userid' => $userid,
            'since' => $since,
        ], $inparams);

        $rows = $DB->get_records_sql($sql, $params);

        $score = 0;
        foreach ($rows as $r) {
            $action = (string) $r->action;
            $cnt = (int) $r->cnt;
            $w = (int) ($weights[$action] ?? 0);
            $score += $cnt * $w;
        }

        return $score;
    }

    /**
     * Calculate peer activity comparison metrics for a user in a course.
     *
     * The calculation is based on weighted actions in the standard log store.
     * If the "student" role exists, peers are restricted to that role in the
     * course context; otherwise, all users with matching log entries in the
     * period are considered.
     *
     * @param stdClass $course The course record.
     * @param int $userid The current user id.
     * @param int $since Unix timestamp (inclusive).
     * @return array Array with keys peer_score, peer_percentile, peer_nactive.
     */
    public static function calculate_peer_activity_comparison(stdClass $course, int $userid, int $since): array {
        global $DB;

        $out = [
            'peer_score' => 0,
            'peer_percentile' => 0,
            'peer_nactive' => 0,
        ];

        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            return $out;
        }

        $weights = self::get_activity_weights();
        if (empty($weights)) {
            return $out;
        }

        $userscore = self::get_user_activity_score((int) $course->id, $userid, $since);

        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($weights), SQL_PARAMS_NAMED, 'act');

        $contextid = \context_course::instance($course->id)->id;
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);

        $join = '';
        $roleparams = [];
        if (!empty($studentroleid)) {
            $join = " JOIN {role_assignments} ra
                        ON ra.userid = l.userid
                       AND ra.contextid = :contextid
                       AND ra.roleid = :roleid ";
            $roleparams = [
                'contextid' => $contextid,
                'roleid' => (int) $studentroleid,
            ];
        }

        $sql = "
            SELECT l.userid, l.action, COUNT(1) AS cnt
              FROM {logstore_standard_log} l
                   $join
             WHERE l.courseid = :courseid
               AND l.timecreated >= :since
               AND l.userid <> :userid
               AND l.action $insql
          GROUP BY l.userid, l.action
        ";

        $params = array_merge([
            'courseid' => (int) $course->id,
            'since' => $since,
            'userid' => $userid,
        ], $roleparams, $inparams);

        $rows = $DB->get_records_sql($sql, $params);

        $scores = [];
        foreach ($rows as $r) {
            $peerid = (int) $r->userid;
            $action = (string) $r->action;
            $cnt = (int) $r->cnt;
            $w = (int) ($weights[$action] ?? 0);

            if (!isset($scores[$peerid])) {
                $scores[$peerid] = 0;
            }
            $scores[$peerid] += $cnt * $w;
        }

        $n = count($scores);
        if ($n === 0) {
            return $out;
        }

        $sum = array_sum($scores);
        $mean = $sum / $n;

        $below = 0;
        foreach ($scores as $s) {
            if ($s < $userscore) {
                $below++;
            }
        }
        $percentile = (int) round(100 * ($below / $n));

        $out['peer_score'] = (int) round($mean);
        $out['peer_percentile'] = $percentile;
        $out['peer_nactive'] = $n;

        return $out;
    }
}

