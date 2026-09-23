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
 * The two things this block can show.
 *
 * @package    block_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_coursesoverview;

use context_course;
use html_writer;
use local_coursesoverview\helper;
use local_coursesoverview\progress;
use moodle_url;
use stdClass;

/**
 * Builds the participant's own progress and the organiser's group summary.
 *
 * Both count course completion criteria, never visible activities. That is the
 * whole point of the block: one figure that holds up whether you are the
 * person doing the course, the person supervising it, or the person reading
 * the Excel export.
 */
class view {
    /** @var int How many unfinished participants the organiser view lists by name. */
    const MAXOPEN = 10;

    /**
     * The participant's own standing.
     *
     * @param stdClass $course
     * @return string HTML, empty when there is nothing to say
     */
    public static function participant(stdClass $course): string {
        global $USER;

        $steps = self::steps($course, $USER->id);

        if (empty($steps)) {
            return '';
        }

        $total = count($steps);
        $done = 0;
        $next = null;

        foreach ($steps as $step) {
            if ($step['state'] === 'done') {
                $done++;
            } else if ($next === null && $step['state'] === 'open') {
                $next = $step;
            }
        }

        $out = self::bar($steps);

        $a = (object) [
            'done' => $done,
            'total' => $total,
            'percent' => (int) round(($done / $total) * 100),
        ];
        $out .= html_writer::tag(
            'p',
            get_string('figure', 'block_coursesoverview', $a),
            ['class' => 'cov-figure']
        );

        if ($done >= $total) {
            $out .= html_writer::tag('p', get_string('alldone', 'block_coursesoverview'), ['class' => 'cov-next']);
        } else if ($next !== null) {
            $name = s($next['name']);
            $label = $next['url'] ? html_writer::link($next['url'], $name) : $name;
            $out .= html_writer::tag(
                'p',
                get_string('nextstep', 'block_coursesoverview', $label),
                ['class' => 'cov-next']
            );
        }

        $out .= self::deadline($course);

        return $out;
    }

    /**
     * How an organiser's own people are getting on.
     *
     * @param stdClass $course
     * @param context_course $context
     * @return string HTML, empty when there is nothing to say
     */
    public static function organiser(stdClass $course, context_course $context): string {
        $criteria = progress::criteria($course);
        $total = count($criteria);

        if (!$total) {
            return '';
        }

        [$groupids, $groupnames] = helper::visible_groups($context);

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $participants = get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            $groupids,
            'u.id, ' . ltrim($namefields, ' ,'),
            null,
            0,
            0,
            true
        );

        if (empty($participants)) {
            return html_writer::tag('p', get_string('nobody', 'block_coursesoverview'), ['class' => 'cov-figure']);
        }

        $counts = progress::completed_counts($course->id, progress::criteria_ids($criteria));

        $open = [];
        $complete = 0;

        foreach ($participants as $user) {
            $count = (int) ($counts[$user->id] ?? 0);
            if ($count >= $total) {
                $complete++;
            } else {
                $open[] = ['name' => fullname($user), 'done' => $count];
            }
        }

        // The people who still need a nudge belong at the top, least done
        // first. Everybody who is finished needs no name, only a number.
        usort($open, function ($a, $b) {
            return [$a['done'], $a['name']] <=> [$b['done'], $b['name']];
        });

        $a = (object) [
            'done' => $complete,
            'total' => count($participants),
        ];
        $out = html_writer::tag(
            'p',
            get_string('summary', 'block_coursesoverview', $a),
            ['class' => 'cov-figure']
        );

        if ($groupnames) {
            $out .= html_writer::tag(
                'p',
                get_string('groupscope', 'block_coursesoverview', s(implode(', ', $groupnames))),
                ['class' => 'cov-scope']
            );
        }

        if ($open) {
            $items = [];

            foreach (array_slice($open, 0, self::MAXOPEN) as $entry) {
                $items[] = s($entry['name']) . ' ' .
                    html_writer::tag('span', $entry['done'] . ' / ' . $total, ['class' => 'cov-count']);
            }

            if (count($open) > self::MAXOPEN) {
                $items[] = html_writer::tag(
                    'em',
                    get_string('andmore', 'block_coursesoverview', count($open) - self::MAXOPEN)
                );
            }

            $out .= html_writer::tag('p', get_string('stillopen', 'block_coursesoverview'), ['class' => 'cov-openhead']);
            $out .= html_writer::alist($items, ['class' => 'cov-openlist']);
        }

        $out .= self::deadline($course);
        $out .= self::links($course);

        return $out;
    }

    /**
     * One cell per criterion, coloured by state.
     *
     * Locked steps are counted but not named. The bar shows how much course
     * there is; it does not give away what is in it.
     *
     * @param array $steps as returned by steps()
     * @return string HTML
     */
    protected static function bar(array $steps): string {
        $cells = '';

        foreach ($steps as $step) {
            if ($step['state'] === 'locked') {
                $title = get_string('statelocked', 'block_coursesoverview');
            } else {
                $title = $step['name'] . ': ' . get_string('state' . $step['state'], 'block_coursesoverview');
            }

            $cells .= html_writer::tag('span', '', [
                'class' => 'cov-cell cov-' . $step['state'],
                'title' => $title,
            ]);
        }

        return html_writer::tag('div', $cells, ['class' => 'cov-bar']);
    }

    /**
     * The course's criteria, as steps with a state for one user.
     *
     * A step is locked when the activity behind it is not available to that
     * user yet. Criteria that are not activities are never locked.
     *
     * @param stdClass $course
     * @param int $userid
     * @return array of ['name' => string, 'state' => string, 'url' => moodle_url|null]
     */
    protected static function steps(stdClass $course, int $userid): array {
        $criteria = progress::criteria($course);

        if (empty($criteria)) {
            return [];
        }

        $done = progress::completed_for_user($course->id, $userid, progress::criteria_ids($criteria));
        $modinfo = get_fast_modinfo($course, $userid);
        $steps = [];

        foreach ($criteria as $criterion) {
            $name = method_exists($criterion, 'get_title') ? (string) $criterion->get_title() : '';
            $url = null;
            $locked = false;

            if ((int) $criterion->criteriatype === COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                $cmid = (int) $criterion->moduleinstance;

                if (!isset($modinfo->cms[$cmid])) {
                    // The activity is gone from the course but the criterion
                    // has outlived it. Counting it would puzzle everybody.
                    continue;
                }

                $cm = $modinfo->cms[$cmid];
                $name = $cm->get_formatted_name();
                $locked = !$cm->uservisible;

                if (!$locked && $cm->url instanceof moodle_url) {
                    $url = $cm->url;
                }
            }

            if (!empty($done[$criterion->id])) {
                $state = 'done';
            } else if ($locked) {
                $state = 'locked';
            } else {
                $state = 'open';
            }

            $steps[] = ['name' => $name, 'state' => $state, 'url' => $url];
        }

        return $steps;
    }

    /**
     * A line about the course end date, when there is one.
     *
     * @param stdClass $course
     * @return string HTML
     */
    protected static function deadline(stdClass $course): string {
        if (empty($course->enddate)) {
            return '';
        }

        $date = helper::format_date((int) $course->enddate);
        $clock = \core\di::get(\core\clock::class);
        $left = (int) $course->enddate - $clock->time();

        if ($left < 0) {
            $text = get_string('ended', 'block_coursesoverview', $date);
        } else {
            $a = (object) ['days' => (int) ceil($left / DAYSECS), 'date' => $date];
            $key = $a->days <= 1 ? 'daysleftone' : 'daysleft';
            $text = get_string($key, 'block_coursesoverview', $a);
        }

        return html_writer::tag('p', $text, ['class' => 'cov-deadline']);
    }

    /**
     * Links on to the full list and the export.
     *
     * @param stdClass $course
     * @return string HTML
     */
    protected static function links(stdClass $course): string {
        $list = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);
        $export = new moodle_url($list, ['download' => 'excel']);

        $links = html_writer::link($list, get_string('fulllist', 'block_coursesoverview')) . ' · ' .
            html_writer::link($export, get_string('exportexcel', 'local_coursesoverview'));

        return html_writer::tag('p', $links, ['class' => 'cov-links']);
    }
}
