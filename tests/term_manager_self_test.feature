Feature: Term manager
  In order to save time managing terms
  As a SEO expert
  I want to upload a CSV file with bulk actions to be run against the taxonomy

  @api @seo @smoke @term_manager_self_test
  Scenario: Check that term manager works as expected.
    # Initialize taxonomy tree.
    Given I run the drush command "tm-create"
    # Test resulting tree.
    Then I run the drush command "tm-test-create"
    # Run actions.
    Given I run the drush command "tm-actions"
    # Test resulting actions.
    Given I run the drush command "tm-test-actions"
    # Clean up.
    Then I run the drush command "tm-cleanup"
