<?php

namespace Drupal\dblog_file\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dblog_file\Logger\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dblog file settings form.
 *
 * @package Drupal\dblog_file\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Types of messages.
   *
   * @var array
   */
  protected $types = [
    LogLevel::EMERGENCY => 'Emergency',
    LogLevel::ALERT => 'Alert',
    LogLevel::CRITICAL => 'Critical',
    LogLevel::ERROR => 'Error',
    LogLevel::WARNING => 'Warning',
    LogLevel::NOTICE => 'Notice',
    LogLevel::INFO => 'Info',
    LogLevel::DEBUG => 'Debug',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    FileSystemInterface $file_system,
  ) {
    parent::__construct(
      $config_factory,
      $typed_config_manager
    );
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dblog_file.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dblog_file_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dblog_file.settings');

    $file_path = $this->fileSystem->realpath(Logger::$fileUri);
    if (file_exists($file_path)) {
      $form['message'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        'content' => [
          '#type' => 'markup',
          '#markup' => $this->t('This is an experimental module designed to save recent log messages into a report log file for easier monitoring and analysis.<br />
Select the types of alert messages you want to include in the log file.<br />
You can <a href=":url" target="_blank">download the most recent log messages</a> as a report directly from the interface.', [
            ':url' => Url::fromRoute('dblog_file.download')->toString(),
          ]),
        ],
        '#weight' => -1,
      ];
    }

    $form['general'] = [
      '#type' => 'details',
      '#title' => t('General'),
      '#open' => TRUE,
    ];

    $form['general']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Enabled'),
      '#description' => t('Enable saving logs into the file.'),
      '#default_value' => $config->get('enabled'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['general']['count'] = [
      '#type' => 'number',
      '#title' => t('Count of lines'),
      '#description' => t('Set count of lines to save.'),
      '#default_value' => $config->get('count'),
      '#min' => 1,
      '#max' => 25000,
    ];

    $form['types'] = [
      '#type' => 'details',
      '#title' => $this->t('Types'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#weight' => 1,
    ];

    $options = [
      LogLevel::EMERGENCY => 'Emergency',
      LogLevel::ALERT => 'Alert',
      LogLevel::CRITICAL => 'Critical',
      LogLevel::ERROR => 'Error',
      LogLevel::WARNING => 'Warning',
      LogLevel::NOTICE => 'Notice',
      LogLevel::INFO => 'Info',
      LogLevel::DEBUG => 'Debug',
    ];
    $form['types']['list'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Types'),
      '#description' => $this->t('Select the types.'),
      '#options' => $options,
      '#default_value' => $config->get('types') ?: [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $settings = $this->config('dblog_file.settings');
    $settings->set('enabled', $form_state->getValue('enabled'));
    $settings->set('count', $form_state->getValue('count'));
    $settings->set('types', array_keys(array_filter($form_state->getValue(['types', 'list']))));
    $settings->save();
  }

}
