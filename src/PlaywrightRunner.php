<?php

declare(strict_types=1);

namespace Drupal\ai_playwright;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\Process\Process;

/**
 * Runs the bundled Playwright script and returns a page capture.
 *
 * The whole browser integration lives here: it resolves a safe URL on this
 * site, shells out to a bundled Node + Playwright script that drives headless
 * Chromium, saves the screenshot as a managed file, and returns the title,
 * visible text and console errors. The AI Agent tool (BrowserPreview) is a thin
 * wrapper over this service.
 *
 * Security: this shells out to Node and opens a browser, so callers must gate
 * the "use ai playwright" permission. The target is this site only unless an
 * administrator opts into off-site URLs, and every argument is passed as an
 * argv array (never an interpolated shell string).
 */
class PlaywrightRunner {

  /**
   * Constructs the runner.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected ModuleExtensionList $moduleExtensionList,
    protected FileSystemInterface $fileSystem,
    protected FileRepositoryInterface $fileRepository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
  ) {}

  /**
   * Captures a page with the headless browser.
   *
   * @param string $url
   *   A path on this site (e.g. "/" or "/about"), an absolute http(s) URL (only
   *   honoured when off-site URLs are enabled), or '' for the front page.
   * @param bool $authenticated
   *   When TRUE, give the browser a one-time auto-login as the current user so
   *   it sees the page as they would.
   *
   * @return array
   *   ['ok' => bool, 'error' => string, 'url' => string, 'title' => string,
   *   'text' => string, 'console_errors' => string[], 'fid' => int|null].
   */
  public function capture(string $url, bool $authenticated = TRUE): array {
    $target = $this->resolveUrl(trim($url));
    if ($target === NULL) {
      return $this->error('Could not resolve a URL to open. Pass a path like "/" or an absolute http(s) URL.');
    }

    $script = $this->scriptPath();
    if ($script === NULL) {
      return $this->error('The browser capture script is missing.');
    }

    $tmp = $this->fileSystem->getTempDirectory()
      . '/ai_playwright_' . substr(hash('sha256', $target . microtime()), 0, 12) . '.png';

    $command = [$this->nodeBinary(), $script, $target, $tmp];
    if ($authenticated) {
      $login = $this->currentUserLoginUrl();
      if ($login !== NULL) {
        $command[] = $login;
      }
    }

    // Run Node from the module directory so it resolves the bundled Playwright.
    $cwd = DRUPAL_ROOT . '/' . $this->moduleExtensionList->getPath('ai_playwright');
    $process = new Process($command, $cwd, NULL, NULL, $this->timeout());
    try {
      $process->run();
    }
    catch (\Throwable $e) {
      return $this->error('Could not start the browser: ' . $e->getMessage());
    }
    if (!$process->isSuccessful()) {
      $this->loggerFactory->get('ai_playwright')->error('browser_preview node failed: @err', [
        '@err' => $process->getErrorOutput() ?: $process->getOutput(),
      ]);
      return $this->error('The browser process failed. Check that Node.js and Playwright are installed on the server.');
    }

    $decoded = Json::decode(trim($process->getOutput()));
    if (!is_array($decoded) || empty($decoded['ok'])) {
      $reason = is_array($decoded) && !empty($decoded['error']) ? $decoded['error'] : 'unknown error';
      return $this->error('The browser could not open the page: ' . $reason);
    }

    // Persist the screenshot as a managed file so the agent can describe it.
    $fid = NULL;
    if (!empty($decoded['screenshot']) && is_file($decoded['screenshot'])) {
      $binary = @file_get_contents($decoded['screenshot']);
      @unlink($decoded['screenshot']);
      if ($binary !== FALSE && $binary !== '') {
        $fid = $this->saveScreenshot($binary);
      }
    }

    $errors = is_array($decoded['consoleErrors'] ?? NULL) ? array_map('strval', $decoded['consoleErrors']) : [];
    return [
      'ok' => TRUE,
      'error' => '',
      'url' => $target,
      'title' => (string) ($decoded['title'] ?? ''),
      'text' => (string) ($decoded['text'] ?? ''),
      'console_errors' => $errors,
      'fid' => $fid,
    ];
  }

  /**
   * Resolves the requested URL/path to an absolute URL to open.
   *
   * A relative path is always resolved against the configured internal base, so
   * the browser can only reach this site. An absolute http(s) URL is honoured
   * only when off-site URLs are enabled in config; anything with another scheme
   * (file://, gopher://, ...) is rejected.
   *
   * @param string $url
   *   A path, an absolute http(s) URL, or '' for the front page.
   *
   * @return string|null
   *   An absolute URL to open, or NULL when none could be resolved.
   */
  public function resolveUrl(string $url): ?string {
    $base = $this->internalBase();
    if ($url === '') {
      return $base . '/';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
      // An absolute URL: only http(s), and only when off-site is allowed.
      $parts = parse_url($url);
      $scheme = strtolower((string) ($parts['scheme'] ?? ''));
      if (!in_array($scheme, ['http', 'https'], TRUE) || empty($parts['host'])) {
        return NULL;
      }
      if (!$this->allowExternalUrls()) {
        $this->loggerFactory->get('ai_playwright')->warning(
          'Refused an off-site browser_preview URL @url; off-site URLs are disabled.',
          ['@url' => $url],
        );
        return NULL;
      }
      return $url;
    }
    // A relative path is resolved against this site's internal base.
    return $base . '/' . ltrim($url, '/');
  }

  /**
   * Returns the absolute path to the bundled Node script, or NULL if missing.
   */
  protected function scriptPath(): ?string {
    $module_path = $this->moduleExtensionList->getPath('ai_playwright');
    $script = DRUPAL_ROOT . '/' . $module_path . '/scripts/browser-capture.mjs';
    return is_file($script) ? $script : NULL;
  }

  /**
   * A one-time auto-login URL for the current user, on the internal base.
   *
   * Lets the bundled browser view the page as the current user. Only the acting
   * user's own link is generated, and only its path/query is kept (rewritten
   * onto the internal base the browser uses), never an external host.
   *
   * @return string|null
   *   The login URL, or NULL for the anonymous user or on failure.
   */
  protected function currentUserLoginUrl(): ?string {
    if ($this->currentUser->isAnonymous()) {
      return NULL;
    }
    try {
      $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      if (!$account) {
        return NULL;
      }
      // The user.reset.login route logs the user straight in (the same form
      // `drush user:login` uses); the plain password-reset URL would not
      // establish a session, leaving the browser anonymous.
      $timestamp = $this->time->getRequestTime();
      $url = Url::fromRoute('user.reset.login', [
        'uid' => $account->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($account, $timestamp),
      ], ['absolute' => TRUE])->toString();
      $path = parse_url($url, PHP_URL_PATH) ?: '';
      $query = parse_url($url, PHP_URL_QUERY);
      if ($path === '') {
        return NULL;
      }
      return $this->internalBase() . $path . ($query ? '?' . $query : '');
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * Persists screenshot bytes as a managed File and returns its id.
   *
   * @param string $binary
   *   The PNG bytes.
   *
   * @return int|null
   *   The file id, or NULL on failure.
   */
  protected function saveScreenshot(string $binary): ?int {
    try {
      $scheme = $this->configFactory->get('ai_playwright.settings')->get('screenshot_scheme') ?: 'public';
      $dir = $scheme . '://ai_playwright';
      $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $name = 'browser_' . substr(hash('sha256', $binary), 0, 12) . '.png';
      $file = $this->fileRepository->writeData($binary, $dir . '/' . $name, FileExists::Rename);
      return (int) $file->id();
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_playwright')->warning('browser_preview could not save the screenshot: @msg', [
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Returns the configured internal base URL (no trailing slash).
   */
  protected function internalBase(): string {
    $base = trim((string) ($this->configFactory->get('ai_playwright.settings')->get('internal_base') ?: 'http://localhost'));
    return rtrim($base, '/');
  }

  /**
   * Returns the configured Node binary.
   */
  protected function nodeBinary(): string {
    return trim((string) ($this->configFactory->get('ai_playwright.settings')->get('node_binary') ?: 'node'));
  }

  /**
   * Returns the configured capture timeout in seconds.
   */
  protected function timeout(): int {
    $timeout = (int) $this->configFactory->get('ai_playwright.settings')->get('timeout');
    return $timeout > 0 ? $timeout : 90;
  }

  /**
   * Whether opening absolute off-site URLs is allowed.
   */
  protected function allowExternalUrls(): bool {
    return (bool) $this->configFactory->get('ai_playwright.settings')->get('allow_external_urls');
  }

  /**
   * Builds a failed-capture result array.
   *
   * @param string $message
   *   The failure reason.
   *
   * @return array
   *   A capture result with ok = FALSE.
   */
  protected function error(string $message): array {
    return [
      'ok' => FALSE,
      'error' => $message,
      'url' => '',
      'title' => '',
      'text' => '',
      'console_errors' => [],
      'fid' => NULL,
    ];
  }

}
