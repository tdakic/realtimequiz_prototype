@mod @mod_realtimequiz
Feature: Attempt a realtimequiz where some questions require that the previous question has been answered.
  In order to complete a realtimequiz where questions require previous ones to be complete
  As a student
  I need later questions to appear once earlier ones have been answered.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
      | teacher  | Teacher   | One      | teacher@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
      | teacher  | C1     | teacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |

  @javascript
  Scenario: A question that requires the previous one is initally blocked
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 0               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "First question"
    And I should see "This question cannot be attempted until the previous question has been completed."
    And I should not see "Second question"
    And I log out
    And I am on the "Quiz 1 > student > Attempt 1" "mod_realtimequiz > Attempt review" page logged in as "teacher"
    And I should see "First question"
    And I should see "This question cannot be attempted until the previous question has been completed."
    And I should not see "Second question"
    And "Question 1" "link" should exist
    And "Question 2" "link" should not exist

  @javascript
  Scenario: A question requires the previous one becomes available when the first one is answered
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 0               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"
    And I click on "True" "radio" in the "First question" "question"
    And I press "Check"

    Then I should see "First question"
    And I should not see "This question cannot be attempted until the previous question has been completed."
    And I should see "Second question"
    And "Question 1" "link" should exist
    And "Question 2" "link" should exist

  @javascript
  Scenario: After realtimequiz submitted, all questions show on the review page
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz     | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 0               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"
    And I press "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"

    Then the state of "First question" question is shown as "Not answered"
    And the state of "Second question" question is shown as "Not answered"

  @javascript
  Scenario: A questions cannot be blocked in a deferred feedback realtimequiz (despite what is set in the DB).
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | deferredfeedback   |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 0               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "First question"
    And I should see "Second question"
    And I should not see "This question cannot be attempted until the previous question has been completed."

  @javascript
  Scenario: Questions cannot be blocked in a shuffled section (despite what is set in the DB).
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour | questionsperpage |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  | 2                |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 1               |
      | TF2      | 2    | 1               |
    And realtimequiz "Quiz 1" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 1       |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "First question"
    And I should see "Second question"
    And I should not see "This question cannot be attempted until the previous question has been completed."

  @javascript
  Scenario: Question dependency cannot apply to the first questions in section when the previous section is shuffled
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour | questionsperpage |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  | 2                |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 1               |
      | TF2      | 2    | 1               |
    And realtimequiz "Quiz 1" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section 1 | 1         | 1       |
      | Section 2 | 2         | 0       |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"
    And I press "Next page"

    Then I should see "Second question"
    And I should not see "This question cannot be attempted until the previous question has been completed."

  @javascript
  Scenario: A questions cannot be blocked in sequential realtimequiz (despite what is set in the DB).
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | truefalse   | TF1   | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour | navmethod  |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  | sequential |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | TF1      | 1    | 1               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "First question"
    And I should see "Second question"
    And I should not see "This question cannot be attempted until the previous question has been completed."

  @javascript
  Scenario: A questions not blocked if the previous one cannot finish, e.g. essay (despite what is set in the DB).
    Given the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext    |
      | Test questions   | essay       | Story | First question  |
      | Test questions   | truefalse   | TF2   | Second question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | Story    | 1    | 0               |
      | TF2      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "First question"
    And I should see "Second question"
    And I should not see "This question cannot be attempted until the previous question has been completed."

  @javascript
  Scenario: A questions not blocked if the previous one cannot finish, e.g. description (despite what is set in the DB).
    Given the following "questions" exist:
      | questioncategory | qtype       | name | questiontext   |
      | Test questions   | description | Info | Read me        |
      | Test questions   | truefalse   | TF1  | First question |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | preferredbehaviour |
      | realtimequiz       | Quiz 1 | Quiz 1 description | C1     | realtimequiz1    | immediatefeedback  |
    And realtimequiz "Quiz 1" contains the following questions:
      | question | page | requireprevious |
      | Info     | 1    | 0               |
      | TF1      | 1    | 1               |

    When I am on the "Quiz 1" "mod_realtimequiz > View" page logged in as "student"
    And I press "Attempt realtimequiz"

    Then I should see "Read me"
    And I should see "First question"
    And I should not see "This question cannot be attempted until the previous question has been completed."
