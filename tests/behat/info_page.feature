@mod @mod_realtimequiz
Feature: Display of information before starting a realtimequiz
  As a student
  In order to start a realtimequiz with confidence
  I need information about the realtimequiz settings before I start an attempt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext               |
      | Test questions   | truefalse   | TF1   | Text of the first question |

  Scenario: Check the pass grade is displayed
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | 60.00     |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    Then I should see "Grade to pass: 60.00 out of 100.00"

  Scenario: Check the pass grade is displayed with custom decimal separator
    Given the following "language customisations" exist:
      | component       | stringid | value |
      | core_langconfig | decsep   | #     |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | 60#00     |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    Then I should see "Grade to pass: 60#00 out of 100#00"

  Scenario: Check the pass grade is not displayed if not set
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    |           |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    Then I should not see "Grade to pass: 0.00"
