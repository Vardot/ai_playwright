<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_playwright\Unit;

use Drupal\ai_playwright\PlaywrightRunner;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversDefaultClass \Drupal\ai_playwright\PlaywrightRunner
 */
#[Group('ai_playwright')]
class ResolveUrlTest extends UnitTestCase {

  /**
   * Builds a runner whose config returns the given base + off-site flag.
   *
   * @param string $base
   *   The internal_base config value.
   * @param bool $allowExternal
   *   The allow_external_urls config value.
   *
   * @return \Drupal\ai_playwright\PlaywrightRunner
   *   The runner under test.
   */
  protected function runner(string $base, bool $allowExternal): PlaywrightRunner {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['internal_base', $base],
      ['allow_external_urls', $allowExternal],
    ]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('ai_playwright.settings')->willReturn($config);

    $logger = $this->createStub(LoggerChannelInterface::class);
    $loggerFactory = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger);

    // Only config + logger are touched by resolveUrl(); the rest can be bare
    // mocks created without their concrete constructors.
    return new PlaywrightRunner(
      $configFactory,
      $loggerFactory,
      $this->createStub('Drupal\Core\Extension\ModuleExtensionList'),
      $this->createStub('Drupal\Core\File\FileSystemInterface'),
      $this->createStub('Drupal\file\FileRepositoryInterface'),
      $this->createStub('Drupal\Core\Entity\EntityTypeManagerInterface'),
      $this->createStub('Drupal\Core\Session\AccountInterface'),
      $this->createStub('Drupal\Component\Datetime\TimeInterface'),
    );
  }

  /**
   * @covers ::resolveUrl
   */
  #[DataProvider('resolveProvider')]
  public function testResolveUrl(string $base, bool $allowExternal, string $input, ?string $expected): void {
    $this->assertSame($expected, $this->runner($base, $allowExternal)->resolveUrl($input));
  }

  /**
   * Data provider for ::testResolveUrl().
   *
   * @return array<string, array{0:string, 1:bool, 2:string, 3:?string}>
   *   Each case: internal_base, allow_external, input, expected result.
   */
  public static function resolveProvider(): array {
    return [
      'empty is front page' => ['http://localhost', FALSE, '', 'http://localhost/'],
      'relative path' => ['http://localhost', FALSE, '/about', 'http://localhost/about'],
      'relative path no slash' => ['http://localhost', FALSE, 'about', 'http://localhost/about'],
      'base trailing slash trimmed' => ['http://localhost/', FALSE, '/x', 'http://localhost/x'],
      'off-site blocked by default' => ['http://localhost', FALSE, 'https://evil.example/x', NULL],
      'off-site allowed when enabled' => ['http://localhost', TRUE, 'https://ok.example/x', 'https://ok.example/x'],
      'file scheme rejected' => ['http://localhost', TRUE, 'file:///etc/passwd', NULL],
      'gopher scheme rejected' => ['http://localhost', TRUE, 'gopher://x/', NULL],
    ];
  }

}
