Feature: AI Playwright module admin pages load correctly
  As a site administrator
  I want the AI Playwright settings page and the core admin surfaces to be
  reachable
  So that I can confirm the module is correctly installed and raises no PHP
  errors

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Webmaster can access the AI Playwright settings page
    When I navigate to "/admin/config/ai/playwright"
    Then I should see "AI Playwright"
     And the "ai playwright settings form" element should be visible
     And I the page should not have PHP errors

  Scenario: The module is enabled on the modules page
    When I navigate to "/admin/modules"
    Then I should see "AI Playwright"
     And I the page should not have PHP errors

  Scenario: The AI Playwright settings link is discoverable from the AI configuration section
    When I navigate to "/admin/config/ai"
    Then the "ai playwright admin menu link" element should be visible
     And I the page should not have PHP errors
