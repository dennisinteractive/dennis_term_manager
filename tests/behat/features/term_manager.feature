@cms @api @vocabulary @term_manager
Feature: Term Manager
  In order to import an export terms
  As an administrator
  I need to visit the term manager pages

  Scenario: Check that the pages load and the desired layout is shown
    Given I am logged in as a user with the "administrator" role
    And I am on "/admin/structure/taxonomy/term_manager"
    And should see "Export"
    And should see "Import"
    And I click "Term Manager Export Form."
    And I should be on "/admin/structure/taxonomy/term_manager/export"
    And I should see an "form.term-manager-export" element
    And I should see "Click the \"Export\" button to create the csv."
    And I am on "/admin/structure/taxonomy/term_manager"
    And I click "Term Manager Form."
    And I should be on "/admin/structure/taxonomy/term_manager/import"
    And I should see an "#edit-csv-file-upload" element
    And I should see an "#edit-submit" element
