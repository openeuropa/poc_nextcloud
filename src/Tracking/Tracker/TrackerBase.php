<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud\Tracking\Tracker;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\poc_nextcloud\Database\SchemaProviderInterface;
use Drupal\poc_nextcloud\Job\Collector\JobCollectorInterface;
use Drupal\poc_nextcloud\Job\DependentPostDeleteJob;
use Drupal\poc_nextcloud\Job\DependentPreDeleteJob;
use Drupal\poc_nextcloud\Job\Provider\JobProviderInterface;
use Drupal\poc_nextcloud\Job\TrackingTableOpJob;
use Drupal\poc_nextcloud\Tracking\RecordSubmit\TrackingRecordSubmitInterface;
use Drupal\poc_nextcloud\Tracking\Tracker;
use Drupal\poc_nextcloud\Tracking\TrackingTable;
use Psr\Container\ContainerInterface;

/**
 * Sync job that works with a tracking table.
 *
 * @template RecordType as array
 */
abstract class TrackerBase implements SchemaProviderInterface, JobProviderInterface {

  /**
   * Constructor.
   *
   * @param class-string $trackingRecordSubmitClass
   *   Class or service id for the service that writes changes to the remote end
   *   (Nextcloud).
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTable $trackingTable
   *   Tracking table.
   * @param \Drupal\poc_nextcloud\Tracking\TrackingTableRelationship[] $relationships
   *   Other tracking tables this table depends on, by alias.
   */
  protected function __construct(
    private string $trackingRecordSubmitClass,
    protected TrackingTable $trackingTable,
    private array $relationships = [],
  ) {}

  /**
   * Builds a select query to get current records.
   *
   * @param string $alias
   *   Alias for the tracking table.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   Select query.
   */
  public function selectCurrent(string $alias = 't'): SelectInterface {
    return $this->trackingTable->select($alias, $this->relationships)
      ->condition($alias . '.pending_operation', Tracker::OP_INSERT, '!=');
  }

  /**
   * {@inheritdoc}
   */
  public function collectJobs(JobCollectorInterface $collector, ContainerInterface $container): void {
    // @todo More sophisticated way to determine order.
    $dependedness = $this->relationships ? 10 : 0;
    $submit = $container->get($this->trackingRecordSubmitClass);
    assert($submit instanceof TrackingRecordSubmitInterface);
    foreach ($this->relationships as $alias => $relationship) {
      if ($relationship->isAutoDelete()) {
        $collector->addJob(FALSE, $dependedness, new DependentPostDeleteJob(
          $this->trackingTable,
          $relationship,
        ));
      }
      else {
        $collector->addJob(FALSE, -$dependedness, new DependentPreDeleteJob(
          $this->trackingTable,
          $submit,
          $this->relationships,
          $alias,
        ));
      }
    }
    foreach ([[Tracker::OP_DELETE], [Tracker::OP_INSERT, Tracker::OP_UPDATE]] as $phase => $ops) {
      foreach ($ops as $op) {
        $collector->addJob((bool) $phase, $dependedness, new TrackingTableOpJob(
          $this->trackingTable,
          $submit,
          $this->relationships,
          $op,
        ));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema(): array {
    $table_schema = [
      'fields' => [
        'pending_operation' => [
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
      ],
      'primary key' => $this->trackingTable->getPrimaryKey(),
    ];
    $this->alterTableSchema($table_schema);
    $table_name = $this->trackingTable->getTableName();
    return [$table_name => $table_schema];
  }

  /**
   * Alters the schema of the database table.
   *
   * @param array $table_schema
   *   Table definition for hook_schema().
   *   Already contains some defaults that are the same for each tracking table.
   */
  abstract protected function alterTableSchema(array &$table_schema): void;

}
