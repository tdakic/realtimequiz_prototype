@mod @mod_realtimequiz
Feature: Quiz reset
  In order to reuse past realtimequizzes
  As a teacher
  I need to remove all previous data.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
      | student1 | Sam1      | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |
    And the following "activities" exist:
      | activity | name           | intro                 | course | idnumber |
      | realtimequiz     | Test realtimequiz name | Test realtimequiz description | C1     | realtimequiz1    |
    And realtimequiz "Test realtimequiz name" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And user "student1" has attempted "Test realtimequiz name" with responses:
      | slot | response |
      |   1  | True     |

  Scenario: Use course reset to clear all attempt data
    When I log in as "teacher1"
    And I am on the "Course 1" "reset" page
    And I set the following fields to these values:
        | Delete all realtimequiz attempts | 1 |
    And I press "Reset course"
    And I press "Continue"
    And I am on the "Test realtimequiz name" "mod_realtimequiz > Grades report" page
    Then I should see "Attempts: 0"

  Scenario: Use course reset to remove user overrides.
    Given the following "mod_realtimequiz > user overrides" exist:
      | realtimequiz           | user     | attempts |
      | Test realtimequiz name | student1 | 2        |
    When I log in as "teacher1"
    And I am on the "Course 1" "reset" page
    And I set the field "Delete all user overrides" to "1"
    And I press "Reset course"
    And I press "Continue"
    And I am on the "Test realtimequiz name" "mod_realtimequiz > User overrides" page
    Then I should not see "Sam1 Student1"

  Scenario: Use course reset to remove group overrides.
    Given the following "mod_realtimequiz > group overrides" exist:
      | realtimequiz           | group | attempts |
      | Test realtimequiz name | G1    | 2        |
    When I log in as "teacher1"
    And I am on the "Course 1" "reset" page
    And I set the following fields to these values:
        | Delete all group overrides | 1 |
    And I press "Reset course"
    And I press "Continue"
    And I am on the "Test realtimequiz name" "mod_realtimequiz > Group overrides" page
    Then I should not see "Group 1"
