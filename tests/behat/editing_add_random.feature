@mod @mod_realtimequiz @javascript
Feature: Adding random questions to a realtimequiz based on category and tags
  In order to have better assessment
  As a teacher
  I want to display questions that are randomly picked from the question bank

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | teacher1 | Teacher   | 1        | t1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity   | name   | intro                                           | course | idnumber |
      | realtimequiz       | Quiz 1 | Quiz 1 for testing the Add random question form | C1     | realtimequiz1    |
    And the following "question categories" exist:
      | contextlevel | reference | name                 |
      | Course       | C1        | Questions Category 1 |
      | Course       | C1        | Questions Category 2 |
    And the following "question categories" exist:
      | contextlevel | reference | name        | questioncategory     |
      | Course       | C1        | Subcategory | Questions Category 1 |
    And the following "questions" exist:
      | questioncategory     | qtype | name                | user     | questiontext    |
      | Questions Category 1 | essay | question 1 name     | admin    | Question 1 text |
      | Questions Category 1 | essay | question 2 name     | teacher1 | Question 2 text |
      | Subcategory          | essay | question 3 name     | teacher1 | Question 3 text |
      | Subcategory          | essay | question 4 name     | teacher1 | Question 4 text |
      | Questions Category 1 | essay | "listen" & "answer" | teacher1 | Question 5 text |
    And the following "core_question > Tags" exist:
      | question            | tag |
      | question 1 name     | foo |
      | question 2 name     | bar |
      | question 3 name     | foo |
      | question 4 name     | bar |
      | "listen" & "answer" | foo |

  Scenario: Available tags are shown in the autocomplete tag field
    Given I am on the "Quiz 1" "mod_realtimequiz > Edit" page logged in as "teacher1"
    When I open the "last" add to realtimequiz menu
    And I follow "a random question"
    And I open the autocomplete suggestions list
    Then "foo" "autocomplete_suggestions" should exist
    And "bar" "autocomplete_suggestions" should exist

  Scenario: Questions can be filtered by tags
    Given I am on the "Quiz 1" "mod_realtimequiz > Edit" page logged in as "teacher1"
    When I open the "last" add to realtimequiz menu
    And I follow "a random question"
    And I set the field "Category" to "Top for Course 1"
    And I wait until the page is ready
    And I open the autocomplete suggestions list
    And I click on "foo" item in the autocomplete list
    Then I should see "question 1 name"
    And I should see "question 3 name"
    And I should not see "question 2 name"
    And I should not see "question 4 name"
    And I set the field "Category" to "Questions Category 1"
    And I wait until the page is ready
    And I should see "question 1 name"
    And I should not see "question 3 name"
    And I should not see "question 2 name"
    And I should not see "question 4 name"
    And I click on "Include questions from subcategories too" "checkbox"
    And I wait until the page is ready
    And I should see "question 1 name"
    And I should see "question 3 name"
    And I should not see "question 2 name"
    And I should not see "question 4 name"

  Scenario: A random question can be added to the realtimequiz
    Given I am on the "Quiz 1" "mod_realtimequiz > Edit" page logged in as "teacher1"
    When I open the "last" add to realtimequiz menu
    And I follow "a random question"
    And I set the field "Tags" to "foo"
    And I press "Add random question"
    And I should see "Random (Questions Category 1, tags: foo)" on realtimequiz page "1"
    And I click on "(See questions)" "link"
    Then I should see "Questions Category 1"
    And I should see "foo"
    And I should see "question 1 name"
    And I should see "\"listen\" & \"answer\""

  Scenario: Teacher without moodle/question:useall should not see the add a random question menu item
    Given the following "permission overrides" exist:
      | capability             | permission | role           | contextlevel | reference |
      | moodle/question:useall | Prevent    | editingteacher | Course       | C1        |
    And I log in as "teacher1"
    And I am on the "Quiz 1" "mod_realtimequiz > Edit" page
    When I open the "last" add to realtimequiz menu
    Then I should not see "a random question"

  Scenario: A random question can be added to the realtimequiz by creating a new category
    Given I am on the "Quiz 1" "mod_realtimequiz > Edit" page logged in as "teacher1"
    When I open the "last" add to realtimequiz menu
    And I follow "a random question"
    And I follow "New category"
    And I set the following fields to these values:
      | Name            | New Random category |
      | Parent category |  Top for Quiz 1     |
    And I press "Create category and add random question"
    And I should see "Random (New Random category)" on realtimequiz page "1"
    And I click on "(See questions)" "link"
    Then I should see "Top for Quiz 1"
