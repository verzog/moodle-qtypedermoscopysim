@qtype @qtype_dermoscopysim
Feature: The Dermoscopy simulator question type is available
  In order to assess dermoscopic examination skills
  As a teacher
  I need to be able to add Dermoscopy simulator questions to the question bank

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | teacher1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: The question type is offered when creating a new question
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    Then I should see "Dermoscopy simulator"

  @javascript
  Scenario: The editing form for a new Dermoscopy simulator question loads
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher1"
    When I press "Create a new question ..."
    And I set the field "item_qtype_dermoscopysim" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Clinical photograph"
    And I should see "Excision margin assessment"
