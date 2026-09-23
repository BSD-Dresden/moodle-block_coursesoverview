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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course progress on the course page itself.
 *
 * @package    block_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_coursesoverview\view;

/**
 * Shows one figure to the person doing the course and another to the person
 * looking after them, and lets neither see the other's.
 */
class block_coursesoverview extends block_base {
    /**
     * Set the fallback title. Both views replace it with their own.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_coursesoverview');
    }

    /**
     * Course pages only. Nothing here means anything on the dashboard.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course-view' => true];
    }

    /**
     * One per course is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * No settings. What a viewer sees follows from their capability.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Build whichever of the two views the viewer is entitled to.
     *
     * Organisers are decided by local/coursesoverview:view in this course,
     * the same capability that opens the participants list. Everybody else
     * who is actually enrolled sees their own progress, and anybody else
     * sees nothing at all, which leaves the block hidden.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $course = $this->page->course;

        if (empty($course->id) || $course->id == SITEID) {
            return $this->content;
        }

        $context = context_course::instance($course->id);

        if (has_capability('local/coursesoverview:view', $context)) {
            $this->title = get_string('titleorganiser', 'block_coursesoverview');
            $this->content->text = view::organiser($course, $context);

            return $this->content;
        }

        if (is_enrolled($context, $USER, '', true)) {
            $this->title = get_string('titleparticipant', 'block_coursesoverview');
            $this->content->text = view::participant($course);
        }

        return $this->content;
    }
}
