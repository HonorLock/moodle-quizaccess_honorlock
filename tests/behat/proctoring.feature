@mod @mod_quiz @quizaccess @quizaccess_honorlock
Feature: Regression tests of Honorlock exam proctoring

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname | email                |
      | student   | Student   | One      | student@example.com  |
      | teacher   | Teacher   | One      | teacher@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student  | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | essay     | TF1  | First Essay     |
      | Test questions   | truefalse | TF2  | Second question |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber |
      | quiz     | Quiz 1 | Quiz 1 description | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 0               |
      | TF2      | 2    | 0               |

  @javascript
  Scenario: Honorlock Proctoring is hidden when not activated
    When I am on the "Quiz 1" "quiz activity editing" page logged in as admin
    And I expand all fieldsets
    Then I should not see "Honorlock"
    And I press "Save and display"
    And I log out

    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I press "Attempt quiz"
    Then I should not see "HONORLOCK BEHAT PREFLIGHT"
    And I set the following fields to these values:
      | Answer text | My first long essay |
    And I press "Next page"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Finish attempt"
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I follow "Finish review"

  @javascript
  Scenario: Honorlock Proctoring integration is present in attempt when activated
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Quiz > Honorlock Proctoring Service" in site administration
    And I press "Activate"
    And I set the following fields to these values:
      | Honorlock Client ID     | aaa-bbb-ccc |
      | Honorlock Client Secret | xxxxxxxxxxx |
    And I press "Activate"
    And I should see "Copy the following text and pass it to Honorlock as it will only ever be shown here once."
    And I should see "aaa-bbb-ccc"
    And I should see "https://app.honorlock.com"
    And I press "Continue"
    And I should see "aaa-bbb-ccc"
    And I should see "https://app.honorlock.com"
    And I should see "honorlock_api"
    And I should see "Honorlock LTI"

    When I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    Then I should see "Honorlock Proctoring is not enabled"
    And I press "Save and display"

    When I use behat magic to enable Honorlock Proctoring in quiz "Quiz 1"
    And I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    Then I should see "Honorlock Proctoring is enabled"
    And I press "Save and display"
    And I log out

    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I should see "Honorlock Proctoring is mandatory for this quiz"
    And I press "Attempt quiz"
    And I should see "Honorlock Proctoring is mandatory for this quiz"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I set the following fields to these values:
      | HONORLOCK BEHAT PREFLIGHT | 1 |
    And I press "Start attempt"
    Then I should see "Not yet answered"
    And I set the following fields to these values:
      | Answer text | My first long essay |
    And I press "Next page"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Finish attempt"
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I follow "Finish review"

  @javascript
  Scenario: Honorlock Proctoring integration is complatible with timelimit rule
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Quiz > Honorlock Proctoring Service" in site administration
    And I press "Activate"
    And I set the following fields to these values:
      | Honorlock Client ID     | aaa-bbb-ccc |
      | Honorlock Client Secret | xxxxxxxxxxx |
    And I press "Activate"
    And I should see "Copy the following text and pass it to Honorlock as it will only ever be shown here once."
    And I should see "aaa-bbb-ccc"
    And I should see "https://app.honorlock.com"
    And I press "Continue"
    And I should see "aaa-bbb-ccc"
    And I should see "https://app.honorlock.com"
    And I should see "honorlock_api"
    And I should see "Honorlock LTI"
    And I use behat magic to enable Honorlock Proctoring in quiz "Quiz 1"
    And I am on the "Quiz 1" "quiz activity editing" page logged in as teacher
    And I expand all fieldsets
    And I should see "Honorlock Proctoring is enabled"
    And I set the following fields to these values:
      | id_timelimit_enabled | 1  |
      | id_timelimit_number  | 20 |
    And I press "Save and display"
    And I log out

    When I am on the "Quiz 1" "mod_quiz > View" page logged in as "student"
    And I should see "Honorlock Proctoring is mandatory for this quiz"
    And I should see "Time limit: 20 mins"
    And I press "Attempt quiz"
    And I should see "Honorlock Proctoring is mandatory for this quiz"
    And I should see "Your attempt will have a time limit"
    And I click on "Start attempt" "button" in the "Start attempt" "dialogue"
    And I should see "Your attempt will have a time limit"
    And I set the following fields to these values:
      | HONORLOCK BEHAT PREFLIGHT | 1 |
    And I press "Start attempt"
    Then I should see "Not yet answered"
    And I set the following fields to these values:
      | Answer text | My first long essay |
    And I press "Next page"
    And I set the following fields to these values:
      | False | 1 |
    And I press "Finish attempt"
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I follow "Finish review"
