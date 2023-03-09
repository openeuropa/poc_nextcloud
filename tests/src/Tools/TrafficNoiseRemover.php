<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud\Tools;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

/**
 * Tool to remove noise from recorded traffic.
 */
class TrafficNoiseRemover {

  /**
   * First new auto-increment id by type.
   *
   * @var int[]
   */
  private $autoIncrementFirstNew = [];

  /**
   * Constructor.
   *
   * @param array $noiseMaps
   *   Noise maps describing where noise could be removed.
   */
  public function __construct(
    private array $noiseMaps,
  ) {}

  /**
   * Creates a new instance based on the traffic-noise-map.yml file.
   *
   * @return self
   *   New instance.
   */
  public static function createForTrafficItems(): self {
    $map = Yaml::parseFile(dirname(__DIR__, 2) . '/fixtures/traffic-noise-map.yml', Yaml::PARSE_CUSTOM_TAGS);
    return new self($map);
  }

  /**
   * Removes noise from multiple traffic records.
   *
   * @param array $items
   *   Traffic records.
   *
   * @return array
   *   Processed traffic records.
   */
  public function removeNoise(array $items): array {
    // Create a mutable clone, to avoid mutating the original object.
    $clone = clone $this;
    return $clone->removeNoiseMutable($items);
  }

  /**
   * Mutable method to remove noise.
   *
   * @param array $items
   *   Traffic records.
   *
   * @return array
   *   Processed traffic records.
   */
  private function removeNoiseMutable(array $items): array {
    foreach ($items as &$item) {
      foreach ($this->noiseMaps as $name => $map) {
        $copy = $item;
        if ($this->removeNoiseRecursive($copy, $map)) {
          $item = $copy;
        }
      }
    }
    return $items;
  }

  /**
   * Removes noise, recursively.
   *
   * @param array $data
   *   Data that should be cleaned up.
   * @param array $map
   *   Map that describes where to remove noise.
   *   The map contains different templates.
   *
   * @return bool
   *   TRUE on success, FALSE if the map does not match.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  private function removeNoiseRecursive(array &$data, array $map): bool {
    foreach ($map as $key => $map_value) {
      $value = $data[$key] ?? NULL;
      if ($value === $map_value) {
        continue;
      }
      if ($map_value === NULL) {
        return FALSE;
      }
      if ($map_value instanceof TaggedValue) {
        $tagged_value = $map_value->getValue();
        switch ($map_value->getTag()) {
          case 'ignore_if_greater_than':
            if (is_numeric($value)) {
              if ($value > $tagged_value) {
                $data[$key] = '*** > ' . $tagged_value . ' ***';
              }
            }
            continue 2;

          case 'ignore_scalar':
            if (is_scalar($value)) {
              $data[$key] = $tagged_value;
            }
            continue 2;

          case 'ignore_array':
            if (is_array($value)) {
              $data[$key] = $tagged_value;
            }
            continue 2;

          case 'pattern':
            if (!preg_match($tagged_value, $value, $m, PREG_OFFSET_CAPTURE)) {
              return FALSE;
            }
            if (array_is_list($m)) {
              continue 2;
            }
            foreach ($m as $type => [$id, $position]) {
              if (is_string($type) && $id === (string) (int) $id) {
                $new_id = $this->processAutoIncrementId((int) $id, $type);
                $value = substr_replace($value, (string) $new_id, (int) $position, strlen((string) $id));
              }
            }
            $data[$key] = $value;
            continue 2;

          case 'auto_increment_new':
          case 'auto_increment':
            if (!is_int($value)) {
              return FALSE;
            }
            $data[$key] = $this->processAutoIncrementId($value, $tagged_value, $map_value->getTag() === 'auto_increment_new');
            continue 2;

          case 'auto_increment_keys':
            if (!is_array($value)) {
              return FALSE;
            }
            $data[$key] = [];
            foreach ($value as $id => $item) {
              if (!is_int($id)) {
                return FALSE;
              }
              if (!$this->removeNoiseRecursive($item, reset($tagged_value))) {
                return FALSE;
              }
              $id = $this->processAutoIncrementId($id, key($tagged_value));
              $data[$key][$id] = $item;
            }
            continue 2;

          default:
            // @todo Handle auto-increment value.
            continue 2;
        }
      }
      if (is_array($map_value)) {
        if (!is_array($value)) {
          return FALSE;
        }
        if (!$this->removeNoiseRecursive($data[$key], $map_value)) {
          return FALSE;
        }
        continue;
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Modifies auto-increment ids.
   *
   * @param int $id
   *   The id found in a response.
   * @param string $type
   *   The type, which can be thought of as a virtual table name.
   * @param bool $is_new
   *   TRUE, if this is a newly created id, FALSE otherwise.
   *   Look for `!auto_increment_new` in the traffic-noise-map.yml.
   *
   * @return int
   *   Replacement id.
   *   For "old" ids, the original value is returned.
   *   For "new" ids, the value is increased by a constant difference, such that
   *   the first new id for any type is always replaced by 100001.
   */
  private function processAutoIncrementId(int $id, string $type, bool $is_new = FALSE): int {
    $first_new_id = $this->autoIncrementFirstNew[$type] ??= ($is_new ? $id : NULL);
    if ($first_new_id === NULL) {
      return $id;
    }
    if ($id < $first_new_id) {
      return $id;
    }
    return $id + 100001 - $first_new_id;
  }

}
