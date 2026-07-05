<?php

declare(strict_types=1);

namespace Drupal\ai_playwright\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\ai_playwright\PlaywrightRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Agent tool: open a page in a real browser and capture what it renders.
 *
 * Gives the agent eyes on a live page: it drives headless Chromium (via the
 * bundled Node + Playwright script) to a page on this site, grabs a full-page
 * screenshot, the page title, the visible text and any console errors, saves
 * the screenshot as a managed file, and returns all of that. The agent can then
 * call describe_image on the returned file id to "see" the result and verify or
 * refine its build.
 *
 * Security: the underlying runner shells out to Node and opens a browser, so
 * this tool is gated on the "use ai playwright" permission, targets this site
 * only (unless off-site URLs are enabled in config), and passes every argument
 * as an argv array.
 */
#[FunctionCall(
  id: 'ai_playwright:browser_preview',
  function_name: 'ai_playwright_browser_preview',
  name: 'Browser preview (Playwright)',
  description: 'Opens a real headless browser on a page of THIS site and returns a full-page screenshot (as a file id you can pass to describe_image), the page title, the visible text and any console errors. Leave "url" empty for the front page, or pass a path like "/" or "/about". Use it to SEE how a built page actually renders and verify or refine it.',
  group: 'information_tools',
  module_dependencies: ['ai_playwright', 'file'],
  context_definitions: [
    'url' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('URL or path'),
      description: new TranslatableMarkup('A path on this site (e.g. "/" or "/about"). Leave empty for the front page.'),
      required: FALSE,
    ),
    'task' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('What to look for'),
      description: new TranslatableMarkup('Optional note of what you want to check on the page (echoed back for context).'),
      required: FALSE,
    ),
  ],
)]
final class BrowserPreview extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The Playwright runner.
   */
  protected PlaywrightRunner $runner;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The tool's readable result.
   */
  protected string $result = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->runner = $container->get('ai_playwright.runner');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (
      !$this->currentUser->hasPermission('use ai playwright')
      && !$this->currentUser->hasPermission('administer ai agents')
      && !$this->currentUser->hasPermission('use Drupal Canvas AI')
    ) {
      throw new \Exception('You do not have permission to use the AI Playwright browser tool.');
    }

    $task = trim((string) $this->getContextValue('task'));
    $result = $this->runner->capture((string) $this->getContextValue('url'));

    if (!$result['ok']) {
      $this->result = $result['error'];
      return;
    }

    $lines = [];
    $lines[] = 'Opened: ' . $result['url'];
    if ($task !== '') {
      $lines[] = 'Looking for: ' . $task;
    }
    $lines[] = 'Title: ' . $result['title'];
    $lines[] = $result['fid'] !== NULL
      ? 'Screenshot saved as file id ' . $result['fid'] . ' - call describe_image with this id to see the page.'
      : 'Screenshot could not be saved.';
    $lines[] = 'Console errors: ' . ($result['console_errors'] ? implode(' | ', $result['console_errors']) : 'none');
    $lines[] = "Visible text:\n" . $result['text'];
    $this->result = implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->result;
  }

}
