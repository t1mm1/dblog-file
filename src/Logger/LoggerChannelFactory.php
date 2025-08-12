<?php

namespace Drupal\dblog_file\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logger file override.
 */
class LoggerChannelFactory implements LoggerChannelFactoryInterface {

  /**
   * Original logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $originalFactory;

  /**
   * Original config factory.
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
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $original_factory
   *   Original logger factory.
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
    LoggerChannelFactoryInterface $original_factory,
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $file_system,
    AccountInterface $current_user,
    RequestStack $request_stack,
  ) {
    $this->originalFactory = $original_factory;
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function get($channel) : LoggerInterface {
    $originalLogger = $this->originalFactory->get($channel);
    return new Logger(
      $originalLogger,
      $this->configFactory,
      $this->fileSystem,
      $this->currentUser,
      $this->requestStack,
    );
  }

  /**
   * @inheritDoc
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) : LoggerChannelFactoryInterface {
    $this->originalFactory->addLogger($logger, $priority);
    return $this;
  }

}
