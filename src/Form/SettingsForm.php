<?php

declare(strict_types=1);

namespace Drupal\ai_playwright\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_playwright\PlaywrightRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures the AI Playwright browser tool.
 */
class SettingsForm extends ConfigFormBase {

  private const SETTINGS = 'ai_playwright.settings';

  /**
   * The Playwright runner.
   */
  protected PlaywrightRunner $runner;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->runner = $container->get('ai_playwright.runner');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_playwright_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['browser'] = [
      '#type' => 'details',
      '#title' => $this->t('Browser'),
      '#open' => TRUE,
      '#description' => $this->t('How the server runs the headless browser. Node.js 18+ with Playwright (Chromium) must be installed on the server.'),
    ];
    $form['browser']['internal_base'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Internal base URL'),
      '#default_value' => $config->get('internal_base') ?: 'http://localhost',
      '#required' => TRUE,
      '#description' => $this->t('The base URL the browser uses to reach this site from the server, usually the local loopback. Example: <code>http://localhost</code> or <code>http://127.0.0.1:8080</code>.'),
    ];
    $form['browser']['node_binary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node.js binary'),
      '#default_value' => $config->get('node_binary') ?: 'node',
      '#required' => TRUE,
      '#description' => $this->t('The Node.js executable. A bare <code>node</code> is resolved from PATH; set an absolute path when the web server has none.'),
    ];
    $form['browser']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Capture timeout (seconds)'),
      '#default_value' => (int) ($config->get('timeout') ?: 90),
      '#min' => 5,
      '#max' => 300,
    ];

    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security'),
      '#open' => TRUE,
    ];
    $form['security']['allow_external_urls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow opening absolute off-site URLs'),
      '#default_value' => (bool) $config->get('allow_external_urls'),
      '#description' => $this->t('Off by default. When off, the tool only ever opens paths on this site (resolved against the internal base URL), which is the safe, intended use.'),
    ];

    $form['test'] = [
      '#type' => 'details',
      '#title' => $this->t('Test'),
      '#open' => TRUE,
      '#description' => $this->t('Runs one capture of the front page with the <em>saved</em> settings. Save your changes first.'),
    ];
    $form['test']['test_browser'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test browser capture'),
      '#submit' => ['::testBrowser'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    // The browser opens this base, so require a plain http(s) URL with a host.
    $base = trim((string) $form_state->getValue('internal_base'));
    $parts = parse_url($base);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], TRUE) || empty($parts['host'])) {
      $form_state->setErrorByName('internal_base', $this->t('The internal base URL must be a full http(s) URL, e.g. <code>http://localhost</code>.'));
    }
  }

  /**
   * Submit handler: runs one browser capture with the saved settings.
   */
  public function testBrowser(array &$form, FormStateInterface $form_state): void {
    $result = $this->runner->capture('/');
    if (!$result['ok']) {
      $this->messenger()->addError($this->t('Browser test failed: @msg', ['@msg' => $result['error']]));
      return;
    }
    $this->messenger()->addStatus($this->t('Browser opened %url. Title: %title. Console errors: %errors.', [
      '%url' => $result['url'],
      '%title' => $result['title'] !== '' ? $result['title'] : '(none)',
      '%errors' => $result['console_errors'] ? count($result['console_errors']) : 0,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(self::SETTINGS)
      ->set('internal_base', rtrim(trim((string) $form_state->getValue('internal_base')), '/'))
      ->set('node_binary', trim((string) $form_state->getValue('node_binary')))
      ->set('timeout', (int) $form_state->getValue('timeout'))
      ->set('allow_external_urls', (bool) $form_state->getValue('allow_external_urls'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
