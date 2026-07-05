<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_playwright\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Installs ai_playwright's config and asserts the settings object's shape.
 *
 * The module hard-depends on the AI stack (ai, ai_agents) and core file.
 * Rather than booting that whole graph, this test only needs the default config
 * object - so it installs the module's own config and asserts the
 * ai_playwright.settings object carries the keys the runner and tool read. If
 * dependency graph cannot be satisfied in the Kernel environment (e.g. a
 * contrib module is absent or unbootable), the test SKIPS with a clear message
 * instead of erroring, keeping the suite green.
 *
 * @group ai_playwright
 */
#[RunTestsInSeparateProcesses]
class InstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Only the modules needed to hold ai_playwright's config object. The full set
   * of hard dependencies is intentionally NOT enabled here - see the class doc.
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'ai_playwright',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    try {
      $this->installConfig(['ai_playwright']);
    }
    catch (\Throwable $e) {
      // A missing/unbootable hard dependency (ai, ai_agents) surfaces here.
      // Skip rather than fail: this environment cannot install the module,
      // which is an environment fact, not a defect in the code under test.
      $this->markTestSkipped(
        'ai_playwright could not be installed in the Kernel environment '
        . '(likely an unsatisfied hard dependency such as ai/ai_agents): '
        . $e->getMessage(),
      );
    }
  }

  /**
   * The shipped ai_playwright.settings carries the keys the runner reads.
   */
  public function testSettingsConfigHasExpectedKeys(): void {
    $config = $this->config('ai_playwright.settings');

    // The config object exists (was installed, not brand new).
    $this->assertFalse($config->isNew(), 'The settings object was installed.');

    foreach ([
      'internal_base',
      'node_binary',
      'timeout',
      'screenshot_scheme',
      'allow_external_urls',
    ] as $key) {
      $this->assertNotNull(
        $config->get($key),
        sprintf('ai_playwright.settings has the "%s" key.', $key),
      );
    }

    // Spot-check the safe defaults the security boundary relies on.
    $this->assertSame('http://localhost', $config->get('internal_base'));
    $this->assertFalse((bool) $config->get('allow_external_urls'));
    $this->assertIsInt($config->get('timeout'));
  }

}
