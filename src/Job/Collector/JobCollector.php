<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Job\Collector;

use Drupal\poc_nextcloud\Job\ProgressiveJobInterface;
use Drupal\poc_nextcloud\Job\Provider\JobProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Job collector service.
 */
class JobCollector implements JobCollectorInterface {

  /**
   * Jobs grouped by phase and position.
   *
   * @var \Drupal\poc_nextcloud\Job\ProgressiveJobInterface[][][]
   */
  private array $jobs = [];

  /**
   * Constructor.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   Container for to pass to job providers.
   */
  public function __construct(
    private ContainerInterface $container,
  ) {}

  /**
   * Adds jobs from a job-having object.
   *
   * This is not part of the interface, because it is not called by the job
   * providers.
   *
   * @param \Drupal\poc_nextcloud\Job\Provider\JobProviderInterface $provider
   *   An object that can provide a sync job.
   *   Usually this is a tagged service.
   */
  public function addJobProvider(JobProviderInterface $provider): void {
    $provider->collectJobs($this, $this->container);
  }

  /**
   * {@inheritdoc}
   */
  public function addJob(bool $phase, int $position, ProgressiveJobInterface $job): void {
    $this->jobs[$phase][$position][] = $job;
  }

  /**
   * Gets the collected jobs in correct order.
   *
   * This is not part of the interface, because it is not called by the job
   * providers.
   *
   * @return \Drupal\poc_nextcloud\Job\ProgressiveJobInterface[]
   *   Jobs, in the order in which they should be performed.
   */
  public function getJobs(): array {
    ksort($this->jobs);
    array_walk($this->jobs, 'ksort');
    return array_merge(...array_merge(...$this->jobs));
  }

}
