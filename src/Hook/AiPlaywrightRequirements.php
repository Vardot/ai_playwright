<?php

declare(strict_types=1);

namespace Drupal\ai_playwright\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\Process\Process;

/**
 * Status report entry for the Node.js binary the browser tool needs.
 */
class AiPlaywrightRequirements {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   *
   * Reports whether the Node.js binary the browser tool needs is reachable.
   * This is best-effort: Playwright itself is only exercised when the tool
   * runs, and a missing runtime fails the tool gracefully rather than breaking
   * the site.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];
    $node = trim((string) ($this->configFactory->get('ai_playwright.settings')->get('node_binary') ?: 'node'));
    $version = '';
    try {
      $process = new Process([$node, '--version'], NULL, NULL, NULL, 10);
      $process->run();
      if ($process->isSuccessful()) {
        $version = trim($process->getOutput());
      }
    }
    catch (\Throwable) {
      // Treated as "not found" below.
    }

    if ($version === '') {
      $requirements['ai_playwright_node'] = [
        'title' => $this->t('AI Playwright'),
        'value' => $this->t('Node.js not found'),
        'severity' => RequirementSeverity::Warning,
        'description' => $this->t('The browser tool needs Node.js 18+ and Playwright on the server. Install them, then set the Node binary on <a href=":url">the settings page</a>.', [
          ':url' => Url::fromRoute('ai_playwright.settings')->toString(),
        ]),
      ];
    }
    else {
      $requirements['ai_playwright_node'] = [
        'title' => $this->t('AI Playwright'),
        'value' => $this->t('Node.js @version', ['@version' => $version]),
        'severity' => RequirementSeverity::OK,
      ];
    }

    return $requirements;
  }

}
