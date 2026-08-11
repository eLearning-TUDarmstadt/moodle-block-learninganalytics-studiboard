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

/**
 * Learning analytics block.
 *
 * Delegates data assembly to analytics_service and handles only block-specific
 * tasks (caching, CSS, template rendering).
 *
 * @package   block_learninganalytics
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_learninganalytics extends block_base {

    /**
     * Set the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_learninganalytics');
    }

    /**
     * Build block content by delegating to analytics_service and rendering the template.
     *
     * @return stdClass Block content object with text and footer.
     */
    public function get_content() {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $courses = enrol_get_users_courses($USER->id, true);
        $data = \block_learninganalytics\local\analytics_service::get_block_data($courses, (int) $USER->id);

        $this->page->requires->css('/blocks/learninganalytics/styles.css');

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template('block_learninganalytics/block', $data);

        return $this->content;
    }
}
