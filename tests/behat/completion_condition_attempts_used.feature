@mod @mod_realtimequiz @core_completion
Feature: Set a realtimequiz to be marked complete when the student uses all attempts allowed
  In order to ensure a student has learned the material before being marked complete
  As a teacher
  I need to set a realtimequiz to complete when the student receives a passing grade, or completed_fail if they use all attempts without passing

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following config values are set as admin:
      | grade_item_advanced | hiddenuntil |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And the following "activities" exist:
      | activity | name           | course | idnumber | attempts | gradepass | completion | completionusegrade | completionpassgrade | completionattemptsexhausted |
      | realtimequiz     | Test realtimequiz name | C1     | realtimequiz1    | 2        | 5.00      | 2          | 1                  | 1                   | 1                           |
    And realtimequiz "Test realtimequiz name" contains the following questions:
      | question       | page |
      | First question | 1    |
    And user "student1" has attempted "Test realtimequiz name" with responses:
      | slot | response |
      |   1  | False    |

  Scenario Outline: Student attempts the realtimequiz - pass and fails
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And the "Receive a grade" completion condition of "Test realtimequiz name" is displayed as "done"
    And the "Receive a passing grade" completion condition of "Test realtimequiz name" is displayed as "failed"
    And the "Receive a pass grade or complete all available attempts" completion condition of "Test realtimequiz name" is displayed as "todo"
    And I follow "Test realtimequiz name"
    And I press "Re-attempt realtimequiz"
    And I set the field "<answer>" to "1"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I am on "Course 1" course homepage
    Then the "Receive a grade" completion condition of "Test realtimequiz name" is displayed as "done"
    And the "Receive a passing grade" completion condition of "Test realtimequiz name" is displayed as "<passcompletionexpected>"
    And the "Receive a pass grade or complete all available attempts" completion condition of "Test realtimequiz name" is displayed as "done"
    And I follow "Test realtimequiz name"
    And the "Receive a grade" completion condition of "Test realtimequiz name" is displayed as "done"
    And the "Receive a passing grade" completion condition of "Test realtimequiz name" is displayed as "<passcompletionexpected>"
    And the "Receive a pass grade or complete all available attempts" completion condition of "Test realtimequiz name" is displayed as "done"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test realtimequiz name"
    And "Test realtimequiz name" should have the "Receive a pass grade or complete all available attempts" completion condition
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Activity completion" "link"
    And "<expectedactivitycompletion>" "icon" should exist in the "Student 1" "table_row"

    Examples:
      | answer | passcompletionexpected | expectedactivitycompletion                 |
      | False  | failed                 | Completed (did not achieve pass grade)     |
      | True   | done                   | Completed (achieved pass grade)            |
