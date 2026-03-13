<?php
// This file is part of the Honorlock Proctoring plugin for Moodle - https://moodle.org/
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

namespace quizaccess_honorlock\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;

/**
 * Honorlock Proctoring test for module.
 *
 * @package   quizaccess_honorlock
 * @copyright 2023 Honorlock (https://honorlock.com/)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Setup test data.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test get_metadata function returns collection
     *
     * @covers \quizaccess_honorlock\privacy\provider::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = new collection('quizaccess_honorlock');
        $provider = new provider();
        $provider->get_metadata($collection);

        $this->assertInstanceOf(collection::class, $collection);
    }

    /**
     * Test get_contexts_for_userid function returns contextlist
     *
     * @covers \quizaccess_honorlock\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $provider = new provider();
        $result = $provider->get_contexts_for_userid($user1->id);

        $this->assertInstanceOf(contextlist::class, $result);
    }

    /**
     * Test export_user_data function returns null
     *
     * @covers \quizaccess_honorlock\privacy\provider::export_user_data
     */
    public function test_export_user_data(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $context = \context_module::instance($quiz->cmid);

        $contextlist = new approved_contextlist($user1, 'quizaccess_honorlock', [$context->id]);
        $provider = new provider();
        $result = $provider->export_user_data($contextlist);

        $this->assertNull($result);
    }

    /**
     * Test delete_data_for_all_users_in_context function returns null
     *
     * @covers \quizaccess_honorlock\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $context = \context_module::instance($quiz->cmid);
        $provider = new provider();
        $result = $provider->delete_data_for_all_users_in_context($context);

        $this->assertNull($result);
    }

    /**
     * Test delete_data_for_user function returns null
     *
     * @covers \quizaccess_honorlock\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $contextlist = new approved_contextlist($user1, 'quizaccess_honorlock', [1]);

        $provider = new provider();
        $result = $provider->delete_data_for_user($contextlist);

        $this->assertNull($result);
    }

    /**
     * Test get_users_in_context function returns null
     *
     * @covers \quizaccess_honorlock\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $context = \context_module::instance($quiz->cmid);
        $userlist = new userlist($context, 'quizaccess_honorlock');

        $provider = new provider();
        $result = $provider->get_users_in_context($userlist);

        $this->assertNull($result);
    }

    /**
     * Test delete_data_for_users function returns null
     *
     * @covers \quizaccess_honorlock\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $context = \context_module::instance($quiz->cmid);
        $userlist = new approved_userlist($context, 'quizaccess_honorlock', [$user1->id]);

        $provider = new provider();
        $result = $provider->delete_data_for_users($userlist);

        $this->assertNull($result);
    }
}
