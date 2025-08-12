<?php

namespace Drupal\dblog_file\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proxy logger class.
 */
class Logger implements LoggerInterface {

  /**
   * File uri for logs.
   *
   * @var string
   */
  public static $fileUri = 'public://dblog-file.log';

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $originalLogger;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Logger constructor.
   *
   * @param \Psr\Log\LoggerInterface $original_logger
   *   The original logger.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file handler.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    LoggerInterface $original_logger,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    AccountInterface $current_user,
    RequestStack $request_stack,
  ) {
    $this->originalLogger = $original_logger;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Run original logger.
    $this->originalLogger->log($level, $message, $context);

    // Clear the file if it needs.
    $this->clearLineFile();

    // Save to file.
    $line = $this->getLine($level, $message, $context);
    if ($line) {
      $this->saveLineFile($level, $line);
    }
  }

  /**
   * Placeholder for line.
   *
   * @param string $level
   *   Type of log.
   * @param string $message
   *   The message with placeholders.
   * @param array $context
   *   Context of placeholders.
   *
   * @return string
   *   Final message.
   */
  function getLine(string $level, string $message, array $context): string {
    $request = $this->requestStack->getCurrentRequest();

    $date = date('Y-m-d H:i:s');
    $user = $this->currentUser->getDisplayName();
    $level = strtolower($level);
    $client_ip = !empty($request) && $request->getClientIp() ? $request->getClientIp() : '[NONE]';
    $message = $this->interpolateMessage($message, $context);
    $context = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[NONE]';

    return sprintf(
      "[%s] [%s] [%s] [%s] %s Original context: %s\n",
      $date,
      $level,
      $user,
      $client_ip,
      $message,
      $context
    );
  }

  /**
   * Placeholder for message.
   *
   * @param string $message
   *   The message with placeholders.
   * @param array $context
   *   Context of placeholders.
   *
   * @return string
   *   Final message.
   */
  function interpolateMessage(string $message, array $context): string {
    $replace = [];
    foreach ($context as $key => $val) {
      if (is_null($val) || is_scalar($val)) {
        $replace[$key] = (string) $val;
      }
      elseif (is_object($val) && method_exists($val, '__toString')) {
        $replace[$key] = (string) $val;
      }
      elseif (is_array($val)) {
        $replace[$key] = '[array]';
      }
      else {
        $replace[$key] = '[complex value]';
      }
    }

    return strtr($message, $replace);
  }

  /**
   * Save log into file.
   *
   * @param string $level
   *    The level of log.
   * @param string $line
   *   The message of log.
   *
   * @return bool|int
   *   The result of log file update.
   */
  function saveLineFile(string $level, string $line): bool|int {
    $enabled = $this->configFactory->get('dblog_file.settings')->get('enabled');
    if (empty($enabled)) {
      return FALSE;
    }

    $types = $this->configFactory->get('dblog_file.settings')->get('types');
    if (empty($types) || !in_array($level, $types)) {
      return FALSE;
    }

    return file_put_contents(static::$fileUri, $line, FILE_APPEND | LOCK_EX);
  }

  /**
   * Clear file lines.
   *
   * @return bool|int
   *   The result of file clear.
   */
  public function clearLineFile(): bool|int {
    $file_path = $this->fileSystem->realpath(static::$fileUri);
    if (!file_exists($file_path)) {
      return FALSE;
    }

    $count = $this->configFactory->get('dblog_file.settings')->get('count');
    $lines = file($file_path, FILE_IGNORE_NEW_LINES);
    if (empty($lines) || !is_array($lines) || count($lines) < $count) {
      return FALSE;
    }

    array_shift($lines);
    return file_put_contents($file_path, implode(PHP_EOL, $lines) . PHP_EOL);
  }

  /**
   * {@inheritdoc}
   */
  public function emergency($message, array $context = []): void {
    $this->log(LogLevel::EMERGENCY, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function alert($message, array $context = []): void {
    $this->log(LogLevel::ALERT, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function critical($message, array $context = []): void {
    $this->log(LogLevel::CRITICAL, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function error($message, array $context = []): void {
    $this->log(LogLevel::ERROR, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function warning($message, array $context = []): void {
    $this->log(LogLevel::WARNING, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function notice($message, array $context = []): void {
    $this->log(LogLevel::NOTICE, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function info($message, array $context = []): void {
    $this->log(LogLevel::INFO, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function debug($message, array $context = []): void {
    $this->log(LogLevel::DEBUG, $message, $context);
  }

}
