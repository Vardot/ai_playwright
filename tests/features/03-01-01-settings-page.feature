Feature: AI Playwright module - the single configuration page
  As a site builder
  I want one settings page to point the browser tool at this site
  So that the Drupal Canvas AI assistant can look at the pages it builds

  # AI Playwright ships exactly one config page at /admin/config/ai/playwright:
  # the internal base URL, the Node.js binary, the capture timeout, an off-site
  # toggle and a "Test browser capture" action. Everything else runs through the
  # Drupal Canvas AI assistant via the browser_preview AI Agent tool.

  Background:
    Given I am a logged in user with the "Webmaster" user
     And I navigate to "/admin/config/ai/playwright"

  Scenario: The settings form is reachable for administrators
    Then the "ai playwright settings form" element should be visible
     And the "drupal page heading" element should contain text "AI Playwright"
     And I the page should not have PHP errors

  Scenario: The page exposes the internal base, Node binary and timeout fields
    Then the "ai playwright settings internal base" element should be visible
     And I should see a "Internal base URL" field
     And I should see a "Node.js binary" field
     And I should see a "Capture timeout" field

  Scenario: The page exposes the Test and Save actions
    Then I should see the button "Test browser capture"
     And I should see the button "Save configuration"

  Scenario: Saving the form persists the configuration with no errors
    When I press "Save configuration"
    Then I should see "The configuration options have been saved."
     And the "ai playwright settings form errors" element should have a count of 0
     And I the page should not have PHP errors

  Scenario: The settings page meets basic accessibility
    Then every form field should have an accessible label
     And the page should have no serious accessibility violations
