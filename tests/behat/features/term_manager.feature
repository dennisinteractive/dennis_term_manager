@cms @api @vocabulary @term_manager
Feature: Term Manager
  In order to import an export terms
  As an administrator
  I need to visit the term manager pages

  Scenario: Check that the pages load and the desired layout is shown
    Given I am logged in as a user with the "administrator" role
    And I am on "/admin/structure/taxonomy/term_manager"
    Then the response status code should be 200
    And I should see text matching "Export"
    And I should see text matching "Import"
    And I should see the link "Export"
    And I should see the link "Import"
    And I click "Export"
    And I should be on "/admin/structure/taxonomy/term_manager/export"
    Then the response status code should be 200
    And I should see an ".view-taxonomy-export" element
    And I am on "/admin/structure/taxonomy/term_manager"
    And I click "Import"
    And I should be on "/admin/structure/taxonomy/term_manager/import"
    Then the response status code should be 200
    And I should see an "#edit-csv-file-upload" element
    And I should see an "#edit-submit" element
