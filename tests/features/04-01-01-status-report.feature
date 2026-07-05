Feature: AI Playwright module status report
  As a site administrator
  I want the status report to report the AI Playwright module state
  So that I can see at a glance whether the Node.js runtime is available

  # hook_requirements() registers an "AI Playwright" line on the status report:
  # the detected Node.js version (OK) when Node is reachable, or "Node.js not
  # found" (a warning, not an error) otherwise. Either way the "AI Playwright"
  # title renders and the page must be free of PHP errors, so this scenario
  # asserts the stable parts and never couples to whether Node is installed in a
  # given environment.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The status report lists the AI Playwright requirement with no PHP errors
    When I navigate to "/admin/reports/status"
    Then the "drupal page heading" element should contain text "Status report"
     And I should see "AI Playwright"
     And I the page should not have PHP errors
