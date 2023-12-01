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

namespace mod_realtimequiz\output;

use cm_info;
use coding_exception;
use context;
use context_module;
use html_table;
use html_table_cell;
use html_writer;
use mod_realtimequiz\access_manager;
use mod_realtimequiz\form\preflight_check_form;
use mod_realtimequiz\question\display_options;
use mod_realtimequiz\realtimequiz_attempt;
use moodle_url;
use plugin_renderer_base;
use popup_action;
use question_display_options;
use mod_realtimequiz\realtimequiz_settings;
use renderable;
use single_button;
use stdClass;

/**
 * The main renderer for the realtimequiz module.
 *
 * @package   mod_realtimequiz
 * @category  output
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Builds the review page
     *
     * @param realtimequiz_attempt $attemptobj an instance of realtimequiz_attempt.
     * @param array $slots of slots to be displayed.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param display_options $displayoptions instance of display_options.
     * @param array $summarydata contains all table data
     * @return string HTML to display.
     */
    public function review_page(realtimequiz_attempt $attemptobj, $slots, $page, $showall,
            $lastpage, display_options $displayoptions, $summarydata) {
        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
                $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
                $attemptobj);

        $output .= $this->review_next_navigation($attemptobj, $page, $lastpage, $showall);
        $output .= $this->footer();
        return $output;
    }
/*******************************************************************************************
/* TTT added the following function todisplay only the for part of the feedback */

    public function review_page_RT(realtimequiz_attempt $attemptobj, $slots, $page, $showall,
            $lastpage, display_options $displayoptions, $summarydata) {
        //TTT made a few changes by commenting out the lines below
        $output = '';
        //$output .= $this->header();
        //$output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
                $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
                $attemptobj);

        //$output .= $this->review_next_navigation($attemptobj, $page, $lastpage, $showall);
        //$output .= $this->footer();
        return $output;
    }
/**************************************************************************************
    /**
     * Renders the review question pop-up.
     *
     * @param realtimequiz_attempt $attemptobj an instance of realtimequiz_attempt.
     * @param int $slot which question to display.
     * @param int $seq which step of the question attempt to show. null = latest.
     * @param display_options $displayoptions instance of display_options.
     * @param array $summarydata contains all table data
     * @return string HTML to display.
     */
    public function review_question_page(realtimequiz_attempt $attemptobj, $slot, $seq,
            display_options $displayoptions, $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, 0);

        if (!is_null($seq)) {

            $output .= $attemptobj->render_question_at_step($slot, $seq, true, $this);
        } else {

            $output .= $attemptobj->render_question($slot, true, $this);
        }

        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param realtimequiz_attempt $attemptobj an instance of realtimequiz_attempt.
     * @param string $message Why the review is not allowed.
     * @return string html to output.
     */
    public function review_question_not_allowed(realtimequiz_attempt $attemptobj, $message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_realtimequiz_name(), true,
                ["context" => $attemptobj->get_realtimequizobj()->get_context()]));
        $output .= $this->notification($message);
        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Filters the summarydata array.
     *
     * @param array $summarydata contains row data for table
     * @param int $page the current page number
     * @return array updated version of the $summarydata array.
     */
    protected function filter_review_summary_table($summarydata, $page) {
        if ($page == 0) {
            return $summarydata;
        }

        // Only show some of summary table on subsequent pages.
        foreach ($summarydata as $key => $rowdata) {
            if (!in_array($key, ['user', 'attemptlist'])) {
                unset($summarydata[$key]);
            }
        }

        return $summarydata;
    }

    /**
     * Outputs the table containing data from summary data array
     *
     * @param array $summarydata contains row data for table
     * @param int $page contains the current page number
     * @return string HTML to display.
     */
    public function review_summary_table($summarydata, $page) {
        $summarydata = $this->filter_review_summary_table($summarydata, $page);
        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', [
                'class' => 'generaltable generalbox realtimequizreviewsummary']);
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }

            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }

            $output .= html_writer::tag('tr',
                    html_writer::tag('th', $title, ['class' => 'cell', 'scope' => 'row']) .
                    html_writer::tag('td', $content, ['class' => 'cell'])
            );
        }

        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Renders each question
     *
     * @param realtimequiz_attempt $attemptobj instance of realtimequiz_attempt
     * @param bool $reviewing
     * @param array $slots array of integers relating to questions
     * @param int $page current page number
     * @param bool $showall if true shows attempt on single page
     * @param display_options $displayoptions instance of display_options
     */
    public function questions(realtimequiz_attempt $attemptobj, $reviewing, $slots, $page, $showall,
            display_options $displayoptions) {
        $output = '';
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, $reviewing, $this,
                    $attemptobj->review_url($slot, $page, $showall));
        }
        return $output;
    }

    /**
     * Renders the main bit of the review page.
     *
     * @param int $page current page number
     * @param bool $showall if true display attempt on one page
     * @param display_options $displayoptions instance of display_options
     * @param string $content the rendered display of each question
     * @param realtimequiz_attempt $attemptobj instance of realtimequiz_attempt
     * @return string HTML to display.
     */
    public function review_form($page, $showall, $displayoptions, $content, $attemptobj) {
        if ($displayoptions->flags != question_display_options::EDITABLE) {
            return $content;
        }

        $this->page->requires->js_init_call('M.mod_realtimequiz.init_review_form', null, false,
                realtimequiz_get_js_module());

        $output = '';

        $output .= html_writer::start_tag('form', ['action' => $attemptobj->review_url(null,
                $page, $showall), 'method' => 'post', 'class' => 'questionflagsaveform']);
        $output .= html_writer::start_tag('div');
        $output .= $content;
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()]);
        $output .= html_writer::start_tag('div', ['class' => 'submitbtns']);
        $output .= html_writer::empty_tag('input', ['type' => 'submit',
                'class' => 'questionflagsavebutton btn btn-secondary', 'name' => 'savingflags',
                'value' => get_string('saveflags', 'question')]);
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Returns either a link or button.
     *
     * @param realtimequiz_attempt $attemptobj instance of realtimequiz_attempt
     */
    public function finish_review_link(realtimequiz_attempt $attemptobj) {
        $url = $attemptobj->view_url();

        if ($attemptobj->get_access_manager(time())->attempt_must_be_in_popup()) {
            $this->page->requires->js_init_call('M.mod_realtimequiz.secure_window.init_close_button',
                    [$url->out(false)], false, realtimequiz_get_js_module());
            return html_writer::empty_tag('input', ['type' => 'button',
                    'value' => get_string('finishreview', 'realtimequiz'),
                    'id' => 'secureclosebutton',
                    'class' => 'mod_realtimequiz-next-nav btn btn-primary']);

        } else {
            return html_writer::link($url, get_string('finishreview', 'realtimequiz'),
                    ['class' => 'mod_realtimequiz-next-nav']);
        }
    }

    /**
     * Creates the navigation links/buttons at the bottom of the review attempt page.
     *
     * Note, the name of this function is no longer accurate, but when the design
     * changed, it was decided to keep the old name for backwards compatibility.
     *
     * @param realtimequiz_attempt $attemptobj instance of realtimequiz_attempt
     * @param int $page the current page
     * @param bool $lastpage if true current page is the last page
     * @param bool|null $showall if true, the URL will be to review the entire attempt on one page,
     *      and $page will be ignored. If null, a sensible default will be chosen.
     *
     * @return string HTML fragment.
     */
    public function review_next_navigation(realtimequiz_attempt $attemptobj, $page, $lastpage, $showall = null) {
        $nav = '';
        if ($page > 0) {
            $nav .= link_arrow_left(get_string('navigateprevious', 'realtimequiz'),
                    $attemptobj->review_url(null, $page - 1, $showall), false, 'mod_realtimequiz-prev-nav');
        }
        if ($lastpage) {
            $nav .= $this->finish_review_link($attemptobj);
        } else {
            $nav .= link_arrow_right(get_string('navigatenext', 'realtimequiz'),
                    $attemptobj->review_url(null, $page + 1, $showall), false, 'mod_realtimequiz-next-nav');
        }
        return html_writer::tag('div', $nav, ['class' => 'submitbtns']);
    }

    /**
     * Return the HTML of the realtimequiz timer.
     *
     * @param realtimequiz_attempt $attemptobj instance of realtimequiz_attempt
     * @param int $timenow timestamp to use as 'now'.
     * @return string HTML content.
     */
    public function countdown_timer(realtimequiz_attempt $attemptobj, $timenow) {

        $timeleft = $attemptobj->get_time_left_display($timenow);
        if ($timeleft !== false) {
            $ispreview = $attemptobj->is_preview();
            $timerstartvalue = $timeleft;
            if (!$ispreview) {
                // Make sure the timer starts just above zero. If $timeleft was <= 0, then
                // this will just have the effect of causing the realtimequiz to be submitted immediately.
                $timerstartvalue = max($timerstartvalue, 1);
            }
            $this->initialise_timer($timerstartvalue, $ispreview);
        }

        return $this->output->render_from_template('mod_realtimequiz/timer', (object) []);
    }

    /**
     * Create a preview link
     *
     * @param moodle_url $url URL to restart the attempt.
     */
    public function restart_preview_button($url) {
        return $this->single_button($url, get_string('startnewpreview', 'realtimequiz'));
    }

    /**
     * Outputs the navigation block panel
     *
     * @param navigation_panel_base $panel
     */
    public function navigation_panel(navigation_panel_base $panel) {

        $output = '';
        $userpicture = $panel->user_picture();
        if ($userpicture) {
            $fullname = fullname($userpicture->user);
            if ($userpicture->size) {
                $fullname = html_writer::div($fullname);
            }
            $output .= html_writer::tag('div', $this->render($userpicture) . $fullname,
                    ['id' => 'user-picture', 'class' => 'clearfix']);
        }
        $output .= $panel->render_before_button_bits($this);

        $bcc = $panel->get_button_container_class();
        $output .= html_writer::start_tag('div', ['class' => "qn_buttons clearfix $bcc"]);
        foreach ($panel->get_question_buttons() as $button) {
            $output .= $this->render($button);
        }
        $output .= html_writer::end_tag('div');

        $output .= html_writer::tag('div', $panel->render_end_bits($this),
                ['class' => 'othernav']);

        $this->page->requires->js_init_call('M.mod_realtimequiz.nav.init', null, false,
                realtimequiz_get_js_module());

        return $output;
    }

    /**
     * Display a realtimequiz navigation button.
     *
     * @param navigation_question_button $button
     * @return string HTML fragment.
     */
    protected function render_navigation_question_button(navigation_question_button $button) {
        $classes = ['qnbutton', $button->stateclass, $button->navmethod, 'btn'];
        $extrainfo = [];

        if ($button->currentpage) {
            $classes[] = 'thispage';
            $extrainfo[] = get_string('onthispage', 'realtimequiz');
        }

        // Flagged?
        if ($button->flagged) {
            $classes[] = 'flagged';
            $flaglabel = get_string('flagged', 'question');
        } else {
            $flaglabel = '';
        }
        $extrainfo[] = html_writer::tag('span', $flaglabel, ['class' => 'flagstate']);

        if ($button->isrealquestion) {
            $qnostring = 'questionnonav';
        } else {
            $qnostring = 'questionnonavinfo';
        }

        $tooltip = get_string('questionx', 'question', s($button->number)) . ' - ' . $button->statestring;

        $a = new stdClass();
        $a->number = s($button->number);
        $a->attributes = implode(' ', $extrainfo);
        $tagcontents = html_writer::tag('span', '', ['class' => 'thispageholder']) .
                html_writer::tag('span', '', ['class' => 'trafficlight']) .
                get_string($qnostring, 'realtimequiz', $a);
        $tagattributes = ['class' => implode(' ', $classes), 'id' => $button->id,
                'title' => $tooltip, 'data-realtimequiz-page' => $button->page];

        if ($button->url) {
            return html_writer::link($button->url, $tagcontents, $tagattributes);
        } else {
            return html_writer::tag('span', $tagcontents, $tagattributes);
        }
    }

    /**
     * Display a realtimequiz navigation heading.
     *
     * @param navigation_section_heading $heading the heading.
     * @return string HTML fragment.
     */
    protected function render_navigation_section_heading(navigation_section_heading $heading) {
        if (empty($heading->heading)) {
            $headingtext = get_string('sectionnoname', 'realtimequiz');
            $class = ' dimmed_text';
        } else {
            $headingtext = $heading->heading;
            $class = '';
        }
        return $this->heading($headingtext, 3, 'mod_realtimequiz-section-heading' . $class);
    }

    /**
     * Renders a list of links the other attempts.
     *
     * @param links_to_other_attempts $links
     * @return string HTML fragment.
     */
    protected function render_links_to_other_attempts(
            links_to_other_attempts $links) {
        $attemptlinks = [];
        foreach ($links->links as $attempt => $url) {
            if (!$url) {
                $attemptlinks[] = html_writer::tag('strong', $attempt);
            } else {
                if ($url instanceof renderable) {
                    $attemptlinks[] = $this->render($url);
                } else {
                    $attemptlinks[] = html_writer::link($url, $attempt);
                }
            }
        }
        return implode(', ', $attemptlinks);
    }

    /**
     * Render the 'start attempt' page.
     *
     * The student gets here if their interaction with the preflight check
     * from fails in some way (e.g. they typed the wrong password).
     *
     * @param \mod_realtimequiz\realtimequiz_settings $realtimequizobj
     * @param preflight_check_form $mform
     * @return string
     */
    public function start_attempt_page(realtimequiz_settings $realtimequizobj, preflight_check_form $mform) {
        $output = '';
        $output .= $this->header();
        $output .= $this->during_attempt_tertiary_nav($realtimequizobj->view_url());
        $output .= $this->heading(format_string($realtimequizobj->get_realtimequiz_name(), true,
                ["context" => $realtimequizobj->get_context()]));
        $output .= $this->realtimequiz_intro($realtimequizobj->get_realtimequiz(), $realtimequizobj->get_cm());
        $output .= $mform->render();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Attempt Page
     *
     * @param realtimequiz_attempt $attemptobj Instance of realtimequiz_attempt
     * @param int $page Current page number
     * @param access_manager $accessmanager Instance of access_manager
     * @param array $messages An array of messages
     * @param array $slots Contains an array of integers that relate to questions
     * @param int $id The ID of an attempt
     * @param int $nextpage The number of the next page
     * @return string HTML to output.
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
            $nextpage) {
              //TTT
        $output = '';
        //$output .= $this->header();
        //$output .= $this->during_attempt_tertiary_nav($attemptobj->view_url());
        //$output .= $this->realtimequiz_notices($messages);
        //$output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        //$output .= $this->footer();
        return $output;
    }

    /**
     * Render the tertiary navigation for pages during the attempt.
     *
     * @param string|moodle_url $realtimequizviewurl url of the view.php page for this realtimequiz.
     * @return string HTML to output.
     */
    public function during_attempt_tertiary_nav($realtimequizviewurl): string {
        $output = '';
        $output .= html_writer::start_div('container-fluid tertiary-navigation');
        $output .= html_writer::start_div('row');
        $output .= html_writer::start_div('navitem');
        $output .= html_writer::link($realtimequizviewurl, get_string('back'),
                ['class' => 'btn btn-secondary']);
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        $output .= html_writer::end_div();
        return $output;
    }

    /**
     * Returns any notices.
     *
     * @param array $messages
     */
    public function realtimequiz_notices($messages) {
        if (!$messages) {
            return '';
        }
        return $this->notification(
                html_writer::tag('p', get_string('accessnoticesheader', 'realtimequiz')) . $this->access_messages($messages),
                'warning',
                false
        );
    }

    /**
     * Outputs the form for making an attempt
     *
     * @param realtimequiz_attempt $attemptobj
     * @param int $page Current page number
     * @param array $slots Array of integers relating to questions
     * @param int $id ID of the attempt
     * @param int $nextpage Next page number
     */
    public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
        $output = '';

        // Start the form.
        // TTT

        $output .= html_writer::start_tag('form',
                ['action' => new moodle_url($attemptobj->processattempt_url(),
                        ['cmid' => $attemptobj->get_cmid()]), 'method' => 'post',
                        'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                        'id' => 'responseform']);

                        /*$output .= html_writer::start_tag('form',
                                    ['action' => $attemptobj->process_attempt(time(), false, 0, $page),
                                           'method' => 'post',
                                            'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                                            'id' => 'responseform']); */
      /*  $timestamp = time();
        $output .= html_writer::start_tag('form',
                ['action' => $attemptobj->save_attempt_page($timestamp, False),
                        'method' => 'post',
                        'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                        'id' => 'responseform']);
*/
        $output .= html_writer::start_tag('div');

        // Print all the questions.
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, false, $this,
                    $attemptobj->attempt_url($slot, $page));
        }

        $navmethod = $attemptobj->get_realtimequiz()->navmethod;
        $output .= $this->attempt_navigation_buttons($page, $attemptobj->is_last_page($page), $navmethod);

        // Some hidden fields to track what is going on.
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attempt',
                'value' => $attemptobj->get_attemptid()]);
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'thispage',
                'value' => $page, 'id' => 'followingpage']);
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'nextpage',
                'value' => $nextpage]);
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'timeup',
                'value' => '0', 'id' => 'timeup']);
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()]);
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mdlscrollto',
                'value' => '', 'id' => 'mdlscrollto']);

        // Add a hidden field with questionids. Do this at the end of the form, so
        // if you navigate before the form has finished loading, it does not wipe all
        // the student's answers.
        $output .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'slots',
                'value' => implode(',', $attemptobj->get_active_slots($page))]);

        // Finish the form.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        $output .= $this->connection_warning();

        return $output;
    }

    /**
     * Display the prev/next buttons that go at the bottom of each page of the attempt.
     *
     * @param int $page the page number. Starts at 0 for the first page.
     * @param bool $lastpage is this the last page in the realtimequiz?
     * @param string $navmethod Optional realtimequiz attribute, 'free' (default) or 'sequential'
     * @return string HTML fragment.
     */
    protected function attempt_navigation_buttons($page, $lastpage, $navmethod = 'free') {
        $output = '';

        $output .= html_writer::start_tag('div', ['class' => 'submitbtns']);
        if ($page > 0 && $navmethod == 'free') {
            $output .= html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'previous',
                    'value' => get_string('navigateprevious', 'realtimequiz'), 'class' => 'mod_realtimequiz-prev-nav btn btn-secondary',
                    'id' => 'mod_realtimequiz-prev-nav']);
            $this->page->requires->js_call_amd('core_form/submit', 'init', ['mod_realtimequiz-prev-nav']);
        }
        if ($lastpage) {
            $nextlabel = get_string('endtest', 'realtimequiz');
        } else {
            $nextlabel = get_string('navigatenext', 'realtimequiz');
        }
        //TTT changed 'type' => 'submit' to 'type' => 'button' ?
        $output .= html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'next',
                'value' => $nextlabel, 'class' => 'mod_realtimequiz-next-nav btn btn-primary', 'id' => 'mod_realtimequiz-next-nav']);
        $output .= html_writer::end_tag('div');
        $this->page->requires->js_call_amd('core_form/submit', 'init', ['mod_realtimequiz-next-nav']);

        return $output;
    }

    /**
     * Render a button which allows students to redo a question in the attempt.
     *
     * @param int $slot the number of the slot to generate the button for.
     * @param bool $disabled if true, output the button disabled.
     * @return string HTML fragment.
     */
    public function redo_question_button($slot, $disabled) {
        $attributes = ['type' => 'submit', 'name' => 'redoslot' . $slot,
                'value' => get_string('redoquestion', 'realtimequiz'),
                'class' => 'mod_realtimequiz-redo_question_button btn btn-secondary',
                'id' => 'redoslot' . $slot . '-submit',
                'data-savescrollposition' => 'true',
            ];
        if ($disabled) {
            $attributes['disabled'] = 'disabled';
        } else {
            $this->page->requires->js_call_amd('core_question/question_engine', 'initSubmitButton', [$attributes['id']]);
        }
        return html_writer::div(html_writer::empty_tag('input', $attributes));
    }

    /**
     * Initialise the JavaScript required to initialise the countdown timer.
     *
     * @param int $timerstartvalue time remaining, in seconds.
     * @param bool $ispreview true if this is a preview attempt.
     */
    public function initialise_timer($timerstartvalue, $ispreview) {
        $options = [$timerstartvalue, (bool) $ispreview];
        $this->page->requires->js_init_call('M.mod_realtimequiz.timer.init', $options, false, realtimequiz_get_js_module());
    }

    /**
     * Output a page with an optional message, and JavaScript code to close the
     * current window and redirect the parent window to a new URL.
     *
     * @param moodle_url $url the URL to redirect the parent window to.
     * @param string $message message to display before closing the window. (optional)
     * @return string HTML to output.
     */
    public function close_attempt_popup($url, $message = '') {
        $output = '';
        $output .= $this->header();
        $output .= $this->box_start();

        if ($message) {
            $output .= html_writer::tag('p', $message);
            $output .= html_writer::tag('p', get_string('windowclosing', 'realtimequiz'));
            $delay = 5;
        } else {
            $output .= html_writer::tag('p', get_string('pleaseclose', 'realtimequiz'));
            $delay = 0;
        }
        $this->page->requires->js_init_call('M.mod_realtimequiz.secure_window.close',
                [$url, $delay], false, realtimequiz_get_js_module());

        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @return string HTML to output.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message, ['class' => 'text-left']);
        }
        return $output;
    }

    /*
     * Summary Page
     */
    /**
     * Create the summary page
     *
     * @param realtimequiz_attempt $attemptobj
     * @param display_options $displayoptions
     */
    public function summary_page($attemptobj, $displayoptions) {
        $output = '';
        $output .= $this->header();
        $output .= $this->during_attempt_tertiary_nav($attemptobj->view_url());
        $output .= $this->heading(format_string($attemptobj->get_realtimequiz_name()));
        $output .= $this->heading(get_string('summaryofattempt', 'realtimequiz'), 3);
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= $this->summary_page_controls($attemptobj);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param realtimequiz_attempt $attemptobj
     * @param display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable realtimequizsummaryofattempt boxaligncenter';
        $table->head = [get_string('question', 'realtimequiz'), get_string('status', 'realtimequiz')];
        $table->align = ['left', 'left'];
        $table->size = ['', ''];
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'realtimequiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $tablewidth = count($table->align);
        $table->data = [];

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            // Add a section headings if we need one here.
            $heading = $attemptobj->get_heading_before_slot($slot);
            if ($heading !== null) {
                // There is a heading here.
                $rowclasses = 'realtimequizsummaryheading';
                if ($heading) {
                    $heading = format_string($heading);
                } else {
                    if (count($attemptobj->get_realtimequizobj()->get_sections()) > 1) {
                        // If this is the start of an unnamed section, and the realtimequiz has more
                        // than one section, then add a default heading.
                        $heading = get_string('sectionnoname', 'realtimequiz');
                        $rowclasses .= ' dimmed_text';
                    }
                }
                $cell = new html_table_cell(format_string($heading));
                $cell->header = true;
                $cell->colspan = $tablewidth;
                $table->data[] = [$cell];
                $table->rowclasses[] = $rowclasses;
            }

            // Don't display information items.
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }

            // Real question, show it.
            $flag = '';
            if ($attemptobj->is_question_flagged($slot)) {
                // Quiz has custom JS manipulating these image tags - so we can't use the pix_icon method here.
                $flag = html_writer::empty_tag('img', ['src' => $this->image_url('i/flagged'),
                        'alt' => get_string('flagged', 'question'), 'class' => 'questionflag icon-post']);
            }
            if ($attemptobj->can_navigate_to($slot)) {
                $row = [html_writer::link($attemptobj->attempt_url($slot),
                        $attemptobj->get_question_number($slot) . $flag),
                        $attemptobj->get_question_status($slot, $displayoptions->correctness)];
            } else {
                $row = [$attemptobj->get_question_number($slot) . $flag,
                        $attemptobj->get_question_status($slot, $displayoptions->correctness)];
            }
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = 'realtimequizsummary' . $slot . ' ' . $attemptobj->get_question_state_class(
                            $slot, $displayoptions->correctness);
        }

        // Print the summary table.
        return html_writer::table($table);
    }

    /**
     * Creates any controls the page should have.
     *
     * @param realtimequiz_attempt $attemptobj
     */
    public function summary_page_controls($attemptobj) {
        $output = '';

        // Return to place button.
        if ($attemptobj->get_state() == realtimequiz_attempt::IN_PROGRESS) {
            $button = new single_button(
                    new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage())),
                    get_string('returnattempt', 'realtimequiz'));
            $output .= $this->container($this->container($this->render($button),
                    'controls'), 'submitbtns mdl-align');
        }

        // Finish attempt button.
        $options = [
                'attempt' => $attemptobj->get_attemptid(),
                'finishattempt' => 1,
                'timeup' => 0,
                'slots' => '',
                'cmid' => $attemptobj->get_cmid(),
                'sesskey' => sesskey(),
        ];

        $button = new single_button(
                new moodle_url($attemptobj->processattempt_url(), $options),
                get_string('submitallandfinish', 'realtimequiz'));
        $button->class = 'btn-finishattempt';
        $button->formid = 'frm-finishattempt';
        if ($attemptobj->get_state() == realtimequiz_attempt::IN_PROGRESS) {
            $totalunanswered = 0;
            if ($attemptobj->get_realtimequiz()->navmethod == 'free') {
                // Only count the unanswered question if the navigation method is set to free.
                $totalunanswered = $attemptobj->get_number_of_unanswered_questions();
            }
            $this->page->requires->js_call_amd('mod_realtimequiz/submission_confirmation', 'init', [$totalunanswered]);
        }
        $button->type = \single_button::BUTTON_PRIMARY;

        $duedate = $attemptobj->get_due_date();
        $message = '';
        if ($attemptobj->get_state() == realtimequiz_attempt::OVERDUE) {
            $message = get_string('overduemustbesubmittedby', 'realtimequiz', userdate($duedate));

        } else {
            if ($duedate) {
                $message = get_string('mustbesubmittedby', 'realtimequiz', userdate($duedate));
            }
        }

        $output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->container($message . $this->container(
                        $this->render($button), 'controls'), 'submitbtns mdl-align');

        return $output;
    }

    /*
     * View Page
     */
    /**
     * Generates the view page
     *
     * @param stdClass $course the course settings row from the database.
     * @param stdClass $realtimequiz the realtimequiz settings row from the database.
     * @param stdClass $cm the course_module settings row from the database.
     * @param context_module $context the realtimequiz context.
     * @param view_page $viewobj
     * @return string HTML to display
     */
    public function view_page($course, $realtimequiz, $cm, $context, $viewobj) {
        $output = '';

        $output .= $this->view_page_tertiary_nav($viewobj);
        $output .= $this->view_information($realtimequiz, $cm, $context, $viewobj->infomessages);
        $output .= $this->view_table($realtimequiz, $context, $viewobj);
        $output .= $this->view_result_info($realtimequiz, $context, $cm, $viewobj);
        $output .= $this->box($this->view_page_buttons($viewobj), 'realtimequizattempt');
        return $output;
    }

    /**
     * Render the tertiary navigation for the view page.
     *
     * @param view_page $viewobj the information required to display the view page.
     * @return string HTML to output.
     */
    public function view_page_tertiary_nav(view_page $viewobj): string {
        $content = '';

        if ($viewobj->buttontext) {
            $attemptbtn = $this->start_attempt_button($viewobj->buttontext,
                    $viewobj->startattempturl, $viewobj->preflightcheckform,
                    $viewobj->popuprequired, $viewobj->popupoptions);
            $content .= $attemptbtn;
        }

        if ($viewobj->canedit && !$viewobj->realtimequizhasquestions) {
            $content .= html_writer::link($viewobj->editurl, get_string('addquestion', 'realtimequiz'),
                    ['class' => 'btn btn-secondary']);
        }

        if ($content) {
            return html_writer::div(html_writer::div($content, 'row'), 'container-fluid tertiary-navigation');
        } else {
            return '';
        }
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear
     * at the end of the review page.
     *
     * @param view_page $viewobj the information required to display the view page.
     * @return string HTML to output.
     */
    public function view_page_buttons(view_page $viewobj) {
        $output = '';

        if (!$viewobj->realtimequizhasquestions) {
            $output .= html_writer::div(
                    $this->notification(get_string('noquestions', 'realtimequiz'), 'warning', false),
                    'text-left mb-3');
        }
        $output .= $this->access_messages($viewobj->preventmessages);

        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl,
                    get_string('backtocourse', 'realtimequiz'), 'get',
                    ['class' => 'continuebutton']);
        }

        return $output;
    }

    /**
     * Generates the view attempt button
     *
     * @param string $buttontext the label to display on the button.
     * @param moodle_url $url The URL to POST to in order to start the attempt.
     * @param preflight_check_form|null $preflightcheckform deprecated.
     * @param bool $popuprequired whether the attempt needs to be opened in a pop-up.
     * @param array $popupoptions the options to use if we are opening a popup.
     * @return string HTML fragment.
     */
    public function start_attempt_button($buttontext, moodle_url $url,
            preflight_check_form $preflightcheckform = null,
            $popuprequired = false, $popupoptions = null) {

        $button = new single_button($url, $buttontext, 'post', single_button::BUTTON_PRIMARY);
        $button->class .= ' realtimequizstartbuttondiv';
        if ($popuprequired) {
            $button->class .= ' realtimequizsecuremoderequired';
        }

        $popupjsoptions = null;
        if ($popuprequired && $popupoptions) {
            $action = new popup_action('click', $url, 'popup', $popupoptions);
            $popupjsoptions = $action->get_js_options();
        }

        $this->page->requires->js_call_amd('mod_realtimequiz/preflightcheck', 'init',
                ['.realtimequizstartbuttondiv [type=submit]', get_string('startattempt', 'realtimequiz'),
                        '#mod_realtimequiz_preflight_form', $popupjsoptions]);

        return $this->render($button) . ($preflightcheckform ? $preflightcheckform->render() : '');
    }

    /**
     * Generate a message saying that this realtimequiz has no questions, with a button to
     * go to the edit page, if the user has the right capability.
     *
     * @param bool $canedit can the current user edit the realtimequiz?
     * @param moodle_url $editurl URL of the edit realtimequiz page.
     * @return string HTML to output.
     *
     * @deprecated since Moodle 4.0 MDL-71915 - please do not use this function any more.
     */
    public function no_questions_message($canedit, $editurl) {
        debugging('no_questions_message() is deprecated, please use generate_no_questions_message() instead.', DEBUG_DEVELOPER);

        $output = html_writer::start_tag('div', ['class' => 'card text-center mb-3']);
        $output .= html_writer::start_tag('div', ['class' => 'card-body']);

        $output .= $this->notification(get_string('noquestions', 'realtimequiz'), 'warning', false);
        if ($canedit) {
            $output .= $this->single_button($editurl, get_string('editrealtimequiz', 'realtimequiz'), 'get');
        }
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Outputs an error message for any guests accessing the realtimequiz
     *
     * @param stdClass $course the course settings row from the database.
     * @param stdClass $realtimequiz the realtimequiz settings row from the database.
     * @param stdClass $cm the course_module settings row from the database.
     * @param context_module $context the realtimequiz context.
     * @param array $messages Array containing any messages
     * @param view_page $viewobj
     */
    public function view_page_guest($course, $realtimequiz, $cm, $context, $messages, $viewobj) {
        $output = '';
        $output .= $this->view_page_tertiary_nav($viewobj);
        $output .= $this->view_information($realtimequiz, $cm, $context, $messages);
        $guestno = html_writer::tag('p', get_string('guestsno', 'realtimequiz'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $referer = get_local_referer(false);
        $output .= $this->confirm($guestno . "\n\n" . $liketologin . "\n", get_login_url(), $referer);
        return $output;
    }

    /**
     * Outputs and error message for anyone who is not enrolled on the course.
     *
     * @param stdClass $course the course settings row from the database.
     * @param stdClass $realtimequiz the realtimequiz settings row from the database.
     * @param stdClass $cm the course_module settings row from the database.
     * @param context_module $context the realtimequiz context.
     * @param array $messages Array containing any messages
     * @param view_page $viewobj
     */
    public function view_page_notenrolled($course, $realtimequiz, $cm, $context, $messages, $viewobj) {
        global $CFG;
        $output = '';
        $output .= $this->view_page_tertiary_nav($viewobj);
        $output .= $this->view_information($realtimequiz, $cm, $context, $messages);
        $youneedtoenrol = html_writer::tag('p', get_string('youneedtoenrol', 'realtimequiz'));
        $button = html_writer::tag('p',
                $this->continue_button($CFG->wwwroot . '/course/view.php?id=' . $course->id));
        $output .= $this->box($youneedtoenrol . "\n\n" . $button . "\n", 'generalbox', 'notice');
        return $output;
    }

    /**
     * Output the page information
     *
     * @param stdClass $realtimequiz the realtimequiz settings.
     * @param cm_info|stdClass $cm the course_module object.
     * @param context $context the realtimequiz context.
     * @param array $messages any access messages that should be described.
     * @param bool $realtimequizhasquestions does realtimequiz has questions added.
     * @return string HTML to output.
     */
    public function view_information($realtimequiz, $cm, $context, $messages, bool $realtimequizhasquestions = false) {
        $output = '';

        // Output any access messages.
        if ($messages) {
            $output .= $this->box($this->access_messages($messages), 'realtimequizinfo');
        }

        // Show number of attempts summary to those who can view reports.
        if (has_capability('mod/realtimequiz:viewreports', $context)) {
            if ($strattemptnum = $this->realtimequiz_attempt_summary_link_to_reports($realtimequiz, $cm,
                    $context)) {
                $output .= html_writer::tag('div', $strattemptnum,
                        ['class' => 'realtimequizattemptcounts']);
            }
        }

        if (has_any_capability(['mod/realtimequiz:manageoverrides', 'mod/realtimequiz:viewoverrides'], $context)) {
            if ($overrideinfo = $this->realtimequiz_override_summary_links($realtimequiz, $cm)) {
                $output .= html_writer::tag('div', $overrideinfo, ['class' => 'realtimequizattemptcounts']);
            }
        }

        return $output;
    }

    /**
     * Output the realtimequiz intro.
     *
     * @param stdClass $realtimequiz the realtimequiz settings.
     * @param stdClass $cm the course_module object.
     * @return string HTML to output.
     */
    public function realtimequiz_intro($realtimequiz, $cm) {
        if (html_is_blank($realtimequiz->intro)) {
            return '';
        }

        return $this->box(format_module_intro('realtimequiz', $realtimequiz, $cm->id), 'generalbox', 'intro');
    }

    /**
     * Generates the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'realtimequiz'), 3);
    }

    /**
     * Generates the table of data
     *
     * @param stdClass $realtimequiz the realtimequiz settings.
     * @param context_module $context the realtimequiz context.
     * @param view_page $viewobj
     */
    public function view_table($realtimequiz, $context, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable realtimequizattemptsummary';
        $table->head = [];
        $table->align = [];
        $table->size = [];
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'realtimequiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'realtimequiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'realtimequiz') . ' / ' .
                    realtimequiz_format_grade($realtimequiz, $realtimequiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('gradenoun') . ' / ' .
                    realtimequiz_format_grade($realtimequiz, $realtimequiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'realtimequiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'realtimequiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = [];

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'realtimequiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {
                    $row[] = realtimequiz_format_grade($realtimequiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Outside the if because we may be showing feedback but not grades.
            $attemptgrade = realtimequiz_rescale_grade($attemptobj->get_sum_marks(), $realtimequiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                            && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                            && $attemptobj->get_state() == realtimequiz_attempt::FINISHED
                            && $attemptgrade == $viewobj->mygrade
                            && $realtimequiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = realtimequiz_format_grade($realtimequiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(),
                        $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = realtimequiz_feedback_for_grade($attemptgrade, $realtimequiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    /**
     * Generate a brief textual description of the current state of an attempt.
     *
     * @param realtimequiz_attempt $attemptobj the attempt
     * @return string the appropriate lang string to describe the state.
     */
    public function attempt_state($attemptobj) {
        switch ($attemptobj->get_state()) {
            case realtimequiz_attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'realtimequiz');

            case realtimequiz_attempt::OVERDUE:
                return get_string('stateoverdue', 'realtimequiz') . html_writer::tag('span',
                                get_string('stateoverduedetails', 'realtimequiz',
                                        userdate($attemptobj->get_due_date())),
                                ['class' => 'statedetails']);

            case realtimequiz_attempt::FINISHED:
                return get_string('statefinished', 'realtimequiz') . html_writer::tag('span',
                                get_string('statefinisheddetails', 'realtimequiz',
                                        userdate($attemptobj->get_submitted_date())),
                                ['class' => 'statedetails']);

            case realtimequiz_attempt::ABANDONED:
                return get_string('stateabandoned', 'realtimequiz');

            default:
                throw new coding_exception('Unexpected attempt state');
        }
    }

    /**
     * Generates data pertaining to realtimequiz results
     *
     * @param stdClass $realtimequiz Array containing realtimequiz data
     * @param context_module $context The realtimequiz context.
     * @param stdClass|cm_info $cm The course module information.
     * @param view_page $viewobj
     * @return string HTML to display.
     */
    public function view_result_info($realtimequiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = realtimequiz_get_grading_option_name($realtimequiz->grademethod);
                $a->mygrade = realtimequiz_format_grade($realtimequiz, $viewobj->mygrade);
                $a->realtimequizgrade = realtimequiz_format_grade($realtimequiz, $realtimequiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'realtimequiz', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = realtimequiz_format_grade($realtimequiz, $viewobj->mygrade);
                $a->maxgrade = realtimequiz_format_grade($realtimequiz, $realtimequiz->grade);
                $a = get_string('outofshort', 'realtimequiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'realtimequiz', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'),
                            ['class' => 'overriddennotice']) . "\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'realtimequiz'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'realtimequizteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'realtimequiz'), 3);
            $resultinfo .= html_writer::div(
                            realtimequiz_feedback_for_grade($viewobj->mygrade, $realtimequiz, $context),
                            'realtimequizgradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }

    /**
     * Output either a link to the review page for an attempt, or a button to
     * open the review in a popup window.
     *
     * @param moodle_url $url of the target page.
     * @param bool $reviewinpopup whether a pop-up is required.
     * @param array $popupoptions options to pass to the popup_action constructor.
     * @return string HTML to output.
     */
    public function review_link($url, $reviewinpopup, $popupoptions) {
        if ($reviewinpopup) {
            $button = new single_button($url, get_string('review', 'realtimequiz'));
            $button->add_action(new popup_action('click', $url, 'realtimequizpopup', $popupoptions));
            return $this->render($button);

        } else {
            return html_writer::link($url, get_string('review', 'realtimequiz'),
                    ['title' => get_string('reviewthisattempt', 'realtimequiz')]);
        }
    }

    /**
     * Displayed where there might normally be a review link, to explain why the
     * review is not available at this time.
     *
     * @param string $message optional message explaining why the review is not possible.
     * @return string HTML to output.
     */
    public function no_review_message($message) {
        return html_writer::nonempty_tag('span', $message,
                ['class' => 'noreviewmessage']);
    }

    /**
     * Returns the same as {@see realtimequiz_num_attempt_summary()} but wrapped in a link to the realtimequiz reports.
     *
     * @param stdClass $realtimequiz the realtimequiz object. Only $realtimequiz->id is used at the moment.
     * @param stdClass $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid
     * fields are used at the moment.
     * @param context $context the realtimequiz context.
     * @param bool $returnzero if false (default), when no attempts have been made '' is returned
     *      instead of 'Attempts: 0'.
     * @param int $currentgroup if there is a concept of current group where this method is being
     *      called (e.g. a report) pass it in here. Default 0 which means no current group.
     * @return string HTML fragment for the link.
     */
    public function realtimequiz_attempt_summary_link_to_reports($realtimequiz, $cm, $context,
            $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = realtimequiz_num_attempt_summary($realtimequiz, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/realtimequiz/report/reportlib.php');
        $url = new moodle_url('/mod/realtimequiz/report.php', [
                'id' => $cm->id, 'mode' => realtimequiz_report_default_report($context)]);
        return html_writer::link($url, $summary);
    }

    /**
     * Render a summary of the number of group and user overrides, with corresponding links.
     *
     * @param stdClass $realtimequiz the realtimequiz settings.
     * @param cm_info|stdClass $cm the cm object.
     * @param int $currentgroup currently selected group, if there is one.
     * @return string HTML fragment for the link.
     */
    public function realtimequiz_override_summary_links(stdClass $realtimequiz, cm_info|stdClass $cm, $currentgroup = 0): string {

        $baseurl = new moodle_url('/mod/realtimequiz/overrides.php', ['cmid' => $cm->id]);
        $counts = realtimequiz_override_summary($realtimequiz, $cm, $currentgroup);

        $links = [];
        if ($counts['group']) {
            $links[] = html_writer::link(new moodle_url($baseurl, ['mode' => 'group']),
                    get_string('overridessummarygroup', 'realtimequiz', $counts['group']));
        }
        if ($counts['user']) {
            $links[] = html_writer::link(new moodle_url($baseurl, ['mode' => 'user']),
                    get_string('overridessummaryuser', 'realtimequiz', $counts['user']));
        }

        if (!$links) {
            return '';
        }

        $links = implode(', ', $links);
        switch ($counts['mode']) {
            case 'onegroup':
                return get_string('overridessummarythisgroup', 'realtimequiz', $links);

            case 'somegroups':
                return get_string('overridessummaryyourgroups', 'realtimequiz', $links);

            case 'allgroups':
                return get_string('overridessummary', 'realtimequiz', $links);

            default:
                throw new coding_exception('Unexpected mode ' . $counts['mode']);
        }
    }

    /**
     * Outputs a chart.
     *
     * @param \core\chart_base $chart The chart.
     * @param string $title The title to display above the graph.
     * @param array $attrs extra container html attributes.
     * @return string HTML of the graph.
     */
    public function chart(\core\chart_base $chart, $title, $attrs = []) {
        return $this->heading($title, 3) . html_writer::tag('div',
                        $this->render($chart), array_merge(['class' => 'graph'], $attrs));
    }

    /**
     * Output a graph, or a message saying that GD is required.
     *
     * @param moodle_url $url the URL of the graph.
     * @param string $title the title to display above the graph.
     * @return string HTML of the graph.
     */
    public function graph(moodle_url $url, $title) {
        $graph = html_writer::empty_tag('img', ['src' => $url, 'alt' => $title]);

        return $this->heading($title, 3) . html_writer::tag('div', $graph, ['class' => 'graph']);
    }

    /**
     * Output the connection warning messages, which are initially hidden, and
     * only revealed by JavaScript if necessary.
     */
    public function connection_warning() {
        $options = ['filter' => false, 'newlines' => false];
        $warning = format_text(get_string('connectionerror', 'realtimequiz'), FORMAT_MARKDOWN, $options);
        $ok = format_text(get_string('connectionok', 'realtimequiz'), FORMAT_MARKDOWN, $options);
        return html_writer::tag('div', $warning,
                        ['id' => 'connection-error', 'style' => 'display: none;', 'role' => 'alert']) .
                html_writer::tag('div', $ok, ['id' => 'connection-ok', 'style' => 'display: none;', 'role' => 'alert']);
    }

    /**
     * Deprecated version of render_links_to_other_attempts.
     *
     * @param links_to_other_attempts $links
     * @return string HTML fragment.
     * @deprecated since Moodle 4.2. Please use render_links_to_other_attempts instead.
     * @todo MDL-76612 Final deprecation in Moodle 4.6
     */
    protected function render_mod_realtimequiz_links_to_other_attempts(links_to_other_attempts $links) {
        return $this->render_links_to_other_attempts($links);
    }

    /**
     * Deprecated version of render_navigation_question_button.
     *
     * @param navigation_question_button $button
     * @return string HTML fragment.
     * @deprecated since Moodle 4.2. Please use render_links_to_other_attempts instead.
     * @todo MDL-76612 Final deprecation in Moodle 4.6
     */
    protected function render_realtimequiz_nav_question_button(navigation_question_button $button) {
        return $this->render_navigation_question_button($button);
    }

    /**
     * Deprecated version of render_navigation_section_heading.
     *
     * @param navigation_section_heading $heading the heading.
     * @return string HTML fragment.
     * @deprecated since Moodle 4.2. Please use render_links_to_other_attempts instead.
     * @todo MDL-76612 Final deprecation in Moodle 4.6
     */
    protected function render_realtimequiz_nav_section_heading(navigation_section_heading $heading) {
        return $this->render_navigation_section_heading($heading);
    }
}
