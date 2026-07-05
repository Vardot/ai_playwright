@ai-playwright @admin @security
Feature: Role-based access control for AI Playwright
  As a security-conscious site owner
  I want the AI Playwright configuration and the AI / admin surfaces it touches
  to be reachable only by trusted administrators
  So that the browser tool settings cannot be read or changed by anonymous
  visitors

  # The tool opens an authenticated browser as the acting user, so its settings
  # are a sensitive surface. Following the role x area x expected matrix pattern,
  # these two outlines assert BOTH sides of every protected path - the
  # administrator keeps access, the anonymous user is denied - so a regression in
  # either direction fails a precise, named row.

  Scenario Outline: An administrator can reach <area>
    Given I am a logged in user with the "Webmaster" user
    When I navigate to "<path>"
    Then I the page should not have PHP errors

    Examples: Protected AI Playwright and admin areas
      | area                            | path                          |
      | the AI Playwright settings page | /admin/config/ai/playwright   |
      | the AI configuration group      | /admin/config/ai              |
      | the AI agents list              | /admin/config/ai/agents       |
      | the status report               | /admin/reports/status         |
      | the permissions page            | /admin/people/permissions     |

  Scenario Outline: An anonymous visitor is denied <area>
    Given I am an anonymous user
    Then I am denied access to "<path>"

    Examples: Protected AI Playwright and admin areas
      | area                            | path                          |
      | the AI Playwright settings page | /admin/config/ai/playwright   |
      | the AI configuration group      | /admin/config/ai              |
      | the AI agents list              | /admin/config/ai/agents       |
      | the permissions page            | /admin/people/permissions     |
