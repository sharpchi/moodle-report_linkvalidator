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
 * This file contains functions used by the log reports
 *
 * This files lists the functions that are used during the log report generation.
 *
 * @package    report_log
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');

class report_linkvalidator {

    private $httpcodes = array(
            0   => 'Invalid or unknown error',
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            449 => 'Retry With',
            450 => 'Blocked by Windows Parental Controls',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            509 => 'Bandwidth Limit Exceeded',
            510 => 'Not Extended',
    );

    private $options = array();

    function __construct($course) {
        $this->course = $course;
        $this->modinfo = get_fast_modinfo($course);
        $this->sections = get_all_sections($course->id);
        $this->context = get_context_instance(CONTEXT_COURSE, $course->id);
        $this->data = $this->get_data();
    }

    public function download_csv($params) {
        echo 'csv time';
    }

    public function download_ods($params) {
        echo 'ods time';
    }

    public function download_xls($params) {
        echo 'xls time';
    }

    // insert data into table
    private function build_table() {
        global $CFG, $OUTPUT;

        $table = new html_table();
        $table->attributes['class'] = 'generaltable boxaligncenter';
        $table->cellpadding = 5;
        $table->id = 'linkvalidator';
        // set up table headings
        $table->head = array(
                get_string('title', 'report_linkvalidator'),
                get_string('url'),
                get_string('result', 'report_linkvalidator'),
        );

        $prevsecctionnum = 0;
        foreach ($this->modinfo->sections as $sectionnum=>$section) {
            foreach ($section as $cmid) {
                $cm = $this->modinfo->cms[$cmid];

                // get the course section
                if ($prevsecctionnum != $sectionnum) {
                    $sectionrow = new html_table_row();
                    $sectionrow->attributes['class'] = 'section';
                    $sectioncell = new html_table_cell();
                    $sectioncell->colspan = count($table->head);

                    $sectiontitle = get_section_name($this->course, $this->sections[$sectionnum]);

                    $sectioncell->text = $OUTPUT->heading($sectiontitle, 3);
                    $sectionrow->cells[] = $sectioncell;
                    $table->data[] = $sectionrow;

                    $prevsecctionnum = $sectionnum;
                }

                $dimmed = $cm->visible ? '' : 'class="dimmed"';
                $modulename = get_string('modulename', $cm->modname);

                // add a row for each activity in the section
                $reportrow = new html_table_row();

                // activity cell
                $activitycell = new html_table_cell();
                $activitycell->attributes['class'] = 'activity';

                $activityicon = $OUTPUT->pix_icon('icon', $modulename, $cm->modname, array('class'=>'icon'));

                $attributes = array();
                if (!$cm->visible) {
                    $attributes['class'] = 'dimmed';
                }

                $activitycell->text = $activityicon . html_writer::link("$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id", format_string($cm->name), $attributes);;

                $reportrow->cells[] = $activitycell;

                // fetch url content from activity
                $content = $this->parse_content($cm);
                // URL cell
                $urlcell = new html_table_cell();
                $urlcell->attributes['class'] = 'url';
                $urlcell->text = '';
                // add the urls to table
                foreach ($content as $url) {
                    $urlcell->text .= html_writer::link($url, format_string($url)) . '</br>';
                }
                $reportrow->cells[] = $urlcell;

                // error cell
                $errorcell = new html_table_cell();
                $errorcell->attributes['class'] = 'result';
                $errorcell->text = '';
                // pass the full content to test_url for validation
                $errorcontent = $this->test_urls($content);
                foreach ($errorcontent as $error) {
                    // add results to table
                    $errorcell->text .= $error . '</br>';
                }
                $reportrow->cells[] = $errorcell;

                $table->data[] = $reportrow;
            }
        }

        return $table;
    }

    // get data
    private function get_activity_links($activity) {

    }

    // validate and test the url
    private function test_urls($content) {
        $results = array();
        // set the curl handler options
        $options = array(
                CURLOPT_HEADER         => true,    // we want headers
                CURLOPT_NOBODY         => true,    // dont need body
                CURLOPT_RETURNTRANSFER => true,    // catch output (do NOT print!)
                CURLOPT_FOLLOWLOCATION => true,   // if the resource has moved, the teachers should update the link. false returns the first status code, true returns the last status code.
                CURLOPT_MAXREDIRS      => 5,  // fairly random number, but could prevent unwanted endless redirects with followlocation=true
                CURLOPT_CONNECTTIMEOUT => 5,   // fairly random number (seconds)... but could prevent waiting forever to get a result
                CURLOPT_TIMEOUT        => 6,   // fairly random number (seconds)... but could prevent waiting forever to get a result
        );

        $ch = curl_init();
        if ($ch === false) {
            $results[] = debugging('Error initializing cURL session', DEBUG_DEVELOPER);
        }
        curl_setopt_array($ch, $options);

        // returns int responsecode, or false (if url does not exist or connection timeout occurs)
        // NOTE: could potentially take up to 0-30 seconds , blocking further code execution (more or less depending on connection, target site, and local timeout settings))
        foreach ($content as $url) {
            // first do some quick sanity checks:
            if (!$url || !is_string($url)) {
                $results[] = 'URL is not a string';
                continue;
            }
            // quick check url is roughly a valid http request: ( http://blah/... )
            if (!preg_match('/^http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-\/]+)*(:[0-9]+)?(\/.*)?$/i', $url)) {
                $results[] = 'URL is invalid';
                continue;
            }
            // set the url to be tested
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // add the status code to the results, plus the description.
            $results[] = "{$code} - {$this->httpcodes[$code]}";
        }
        curl_close($ch);

        return $results;
    }

    private function parse_content($coursemodule) {
        global $DB;

        $content = $DB->get_record($coursemodule->modname, array('id'=>$coursemodule->instance), '*', MUST_EXIST);

        $fields = array();
        foreach ($content as $field) {
            // a more readably-formatted version of the pattern is on http://daringfireball.net/2010/07/improved_regex_for_matching_urls
            $pattern  = '/http(s)?:\/\/[a-z0-9-]+(.[a-z0-9-\/]+)*(:[0-9]+)?(\/.*)?/i';

            if (preg_match_all($pattern, $field, $matches)) {
                $fields[] = $matches[0]; // only the full match is needed
            }
        }
        $urls = array();
        foreach ($fields as $k => $v) { // reduce to a single level array
            $urls[] = $v[0];
        }

        return $urls;
    }

    /**
     * This function is used to generate and display selector form
     *
     * @return void
     */
    public function print_selector_form($params) {
        var_dump($params);
        global $CFG;

        // Prepare the list of action options.
        $actions = array(
                'errorsonly' => get_string('errorsonly', 'report_linkvalidator'),
                'all' => get_string('all', 'report_linkvalidator'),
                );

        echo "<form class=\"logselectform\" action=\"$CFG->wwwroot/report/linkvalidator/index.php\" method=\"get\">\n";
        echo "<div>\n";
        echo "<input type=\"hidden\" name=\"chooselog\" value=\"1\" />\n";
        echo html_writer::label(get_string('actions'), 'menumodaction', false, array('class' => 'accesshide'));
        echo html_writer::select($actions, 'filter', $params['filter'], get_string("actions", 'report_linkvalidator'));

        $logformats = array('showashtml' => get_string('displayonpage'),
                'downloadascsv' => get_string('downloadtext'),
                'downloadasods' => get_string('downloadods'),
                'downloadasexcel' => get_string('downloadexcel'));

        echo html_writer::label(get_string('logsformat', 'report_log'), 'menulogformat', false, array('class' => 'accesshide'));
        echo html_writer::select($logformats, 'logformat', $params['logformat'], false);
        echo '<input type="submit" value="'.get_string('gettheselogs').'" />';
        echo '</div>';
        echo '</form>';
    }


    // data model that can be printed in any format. arrays of results.
    // filtered by options
    private function get_data() {
        global $CFG;

        $table = array();

        $prevsecctionnum = 0;
        foreach ($this->modinfo->sections as $sectionnum=>$section) {
            foreach ($section as $cmid) {
                $cm = $this->modinfo->cms[$cmid];

                // get the course section
                if ($prevsecctionnum != $sectionnum) {
                    $sectionrow = new stdClass();
                    $sectionrow->sectiontitle = get_section_name($this->course, $this->sections[$sectionnum]);
                    $table[] = $sectionrow;
                    $prevsecctionnum = $sectionnum;
                }
                // add a row for each activity in the section
                $reportrow = new stdClass();

                // activity cell
                $activitycell = new stdClass();

                $activitycell->text = format_string($cm->name);
                $reportrow->modname = $cm->modname;
                $reportrow->cells['activity'] = $activitycell;
                $reportrow->visible = $cm->visible;
                $reportrow->cmid = $cm->id;
                $reportrow->cmname = $cm->name;

                // fetch url content from activity
                $content = $this->parse_content($cm);
                // URL cell
                $urlcell = new stdClass();
                // add the urls to table
                foreach ($content as $k=>$url) {
                    $urlcell->$k = $url;
                }
                $reportrow->cells['url'] = $urlcell;

                // error cell
                $resultcell = new stdClass();
                // pass the full content to test_url for validation
                $results = $this->test_urls($content);
                foreach ($results as $k=>$result) {
                    // add results to table
                    $resultcell->$k = $result;
                }
                $reportrow->cells['result'] = $resultcell;

                $table[] = $reportrow;
            }
        }
        return $table;
    }
}
