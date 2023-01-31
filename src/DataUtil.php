<?php

declare(strict_types = 1);

namespace Drupal\poc_nextcloud;

/**
 * Class with static utility methods.
 */
class DataUtil {

  /**
   * Converts stringified integers to true integers.
   *
   * Empty string becomes NULL.
   * Other values remain unchanged. The idea is that calling code can do further
   * validation, and produce error messages with more contextual information.
   *
   * @param mixed $value
   *   A value which could be a stringified integer, e.g. "6".
   *
   * @return mixed
   *   The converted value, e.g. 6 for "6".
   *   If the value was not a stringified integer, the original value is
   *   returned.
   */
  public static function parseIntIfPossible(mixed $value): mixed {
    if (is_string($value)) {
      if ($value === '') {
        return NULL;
      }
      if ((string) (int) $value === $value) {
        return (int) $value;
      }
    }
    return $value;
  }

  /**
   * Bitwise OR for an arbitrary number of arguments.
   *
   * This is needed for group folder permissions.
   *
   * @param int ...$args
   *   Bitmasks to combine.
   *
   * @return int
   *   Result bitmask, or 0 (all bits at 0) if no arguments were given.
   */
  public static function bitwiseOr(int ...$args): int {
    return array_reduce(
      $args,
      fn (int $a, int $b) => $a | $b,
      0,
    );
  }

  /**
   * Bitwise AND for an arbitrary number of arguments.
   *
   * @param int ...$args
   *   Bitmasks to combine.
   *
   * @return int
   *   Result bitmask, or -1 === ~0 (all bits at 1), if no arguments were given.
   */
  public static function bitwiseAnd(int ...$args): int {
    return array_reduce(
      $args,
      fn (int $a, int $b) => $a & $b,
      ~0,
    );
  }

}
