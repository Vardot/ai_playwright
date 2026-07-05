Feature: AI Playwright agents surface and permissions
  As a site administrator
  I want the AI agents list and the permissions page to load cleanly
  So that I can confirm the module integrates with the Drupal AI framework

  # AI Playwright ships one AI Agent function-call TOOL (browser_preview) that
  # the Drupal Canvas AI orchestrator can call to look at a rendered page. These
  # scenarios assert the AI agents admin surface loads with no PHP errors and
  # that the module's own permissions are registered - they never trigger a live
  # LLM call or a real browser capture, so they pass in CI with no provider key.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The AI agents configuration page loads with no PHP errors
    When I navigate to "/admin/config/ai/agents"
    Then the "drupal page heading" element should be visible
     And I the page should not have PHP errors

  Scenario: The administer permission is registered
    When I navigate to "/admin/people/permissions"
    Then I should see "Administer AI Playwright"
     And I the page should not have PHP errors

  Scenario: The browser-tool permission is registered
    When I navigate to "/admin/people/permissions"
    Then I should see "Use the AI Playwright browser tool"
     And I the page should not have PHP errors
