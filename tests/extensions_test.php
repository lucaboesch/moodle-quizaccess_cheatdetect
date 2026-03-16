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

/**
 * Unit tests for the quizaccess_cheatdetect implementation of extensions.
 *
 * @package    quizaccess_cheatdetect
 * @copyright  2026 CBlue SRL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     gnormand@cblue.be
 * @author     abrichard@cblue.be
 * @since      1.0.0
 * @covers \quizaccess_cheatdetect\persistent\extension
 */
final class extensions_test extends \advanced_testcase {
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
     * Test inserting extensions
     *
     * @return void
     * @throws \dml_exception
     * @covers \quizaccess_cheatdetect\persistent\extension
     */
    public function test_insert_extension_detection(): void {
        global $DB;

        $record = (object)[
            'userid' => 4,
            'attemptid' => 20,
            'quizid' => 8,
            'extension_name' => 'SuspiciousExtension',
            'extension_uid' => 1,
            'timecreated' => time(),
        ];

        $id = $DB->insert_record('quizaccess_cheatdetect_extensions', $record);

        $this->assertNotEmpty($id);

        $stored = $DB->get_record('quizaccess_cheatdetect_extensions', ['id' => $id]);

        $this->assertEquals('SuspiciousExtension', $stored->extension_name);
        $this->assertEquals(1, $stored->extension_uid);
    }
}
