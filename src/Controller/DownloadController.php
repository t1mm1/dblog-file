<?php

namespace Drupal\dblog_file\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\dblog_file\Logger\Logger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * File download controller.
 */
class DownloadController extends ControllerBase {

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Download file function.
   *
   * @return Response
   */
  public function download() {
    $file_path = $this->fileSystem->realpath(Logger::$fileUri);

    if (!file_exists($file_path)) {
      throw new NotFoundHttpException(t('File not found.'));
    }

    $content = file_get_contents($file_path);
    if ($content === FALSE) {
      throw new NotFoundHttpException(t('Cannot read file.'));
    }

    $response = new Response($content);
    $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="dblog-file.log"');
    $response->headers->set('Content-Length', filesize($file_path));

    return $response;
  }

}
