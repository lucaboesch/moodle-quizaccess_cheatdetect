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

namespace quizaccess_cheatdetect;

use advanced_testcase;
use quizaccess_cheatdetect\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the quizaccess_cheatdetect implementation of the privacy API.
 *
 * @package    quizaccess_cheatdetect
 * @copyright  2026 CBlue SRL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     gnormand@cblue.be
 * @author     abrichard@cblue.be
 * @since      1.0.0
 * @covers \quizaccess_cheatdetect\privacy\provider
 */
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void {
        $this->resetAfterTest(true);
        parent::setUp();
    }

    /**
     * Create a quiz with one attempt for a user.
     *
     * @param int $userid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function create_quiz_with_attempt($userid): array {
        global $DB;

        $generator = self::getDataGenerator();

        $course = $generator->create_course();

        $generator->enrol_user($userid, $course->id, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $attemptid = $DB->insert_record('quiz_attempts', (object)[
            'quiz' => $quiz->id,
            'userid' => $userid,
            'attempt' => 1,
            'uniqueid' => 1,
            'layout' => '',
            'timestart' => time(),
            'timefinish' => time(),
            'timemodified' => time(),
            'state' => 'finished',
        ]);

        return [$quiz, $context, $attemptid];
    }

    /**
     * Test get_contexts_for_userid()
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_events', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'action' => 'focusloss',
            'timecreated' => time(),
            'timestamp' => time(),
        ]);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertCount(1, $contextlist->get_contextids());
        $this->assertEquals($context->id, $contextlist->get_contextids()[0]);
    }

    /**
     * Test export_user_data()
     *
     * @covers ::export_user_data
     */
    public function test_export_user_data(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_metrics', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'copycount' => 2,
            'focusloss' => 1,
            'timespent' => 45,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $approvedlist = new \core_privacy\tests\request\approved_contextlist(
            $user,
            'quizaccess_cheatdetect',
            [$context->id, $attemptid],
        );

        provider::export_user_data($approvedlist);

        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test delete_data_for_user()
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_events', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'eventtype' => 'copy',
            'timecreated' => time(),
            'timestamp' => time(),
        ]);

        file_put_contents('/Users/luca/Desktop/log0.txt', 'Blah 1' . $context->id);

        $approvedlist = new \core_privacy\tests\request\approved_contextlist(
            $user,
            'quizaccess_cheatdetect',
            [$context->id, $attemptid]
        );

        file_put_contents('/Users/luca/Desktop/log1.txt', 'Blah 2 ' . json_encode($approvedlist));

        provider::delete_data_for_user($approvedlist);
        $this->assertFalse(
            $DB->record_exists('quizaccess_cheatdetect_events', ['userid' => $user->id])
        );
    }

    /**
     * Test delete_data_for_all_users_in_context()
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_events', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'eventtype' => 'focusloss',
            'timecreated' => time(),
            'timestamp' => time(),
        ]);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse(
            $DB->record_exists('quizaccess_cheatdetect_events', ['userid' => $user->id])
        );
    }

    /**
     * Test get_users_in_context()
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_events', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'eventtype' => 'copy',
            'timecreated' => time(),
            'timestamp' => time(),
        ]);

        $userlist = new userlist($context, 'quizaccess_cheatdetect');
        provider::get_users_in_context($userlist);

        $this->assertContains($user->id, $userlist->get_userids());
    }

    /**
     * Test delete_data_for_users()
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$quiz, $context, $attemptid] = $this->create_quiz_with_attempt($user->id);

        $DB->insert_record('quizaccess_cheatdetect_events', (object)[
            'quizid' => $quiz->id,
            'attemptid' => $attemptid,
            'userid' => $user->id,
            'eventtype' => 'copy',
            'timecreated' => time(),
            'timestamp' => time(),
        ]);

        $userlist = new \core_privacy\local\request\approved_userlist(
            $context,
            'quizaccess_cheatdetect',
            [$user->id]
        );

        provider::delete_data_for_users($userlist);

        $this->assertFalse(
            $DB->record_exists('quizaccess_cheatdetect_events', ['userid' => $user->id])
        );
    }
}
