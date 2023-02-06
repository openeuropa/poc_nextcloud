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
   * Also converts '' to NULL. Other values remain unchanged.
   *
   * The purpose is to clean up integers that are delivered as strings, e.g.
   * from a database or from API responses.
   *
   * At the same time, non-integer-like values should not be silently converted,
   * because these point to bugs in the system. Such values are returned
   * unchanged, leaving further validation to the calling code, which results in
   * more useful error messages.
   *
   * @param mixed $value
   *   A value which could be a stringified integer, e.g. "6".
   *
   * @return mixed
   *   The converted value, e.g. 6 for "6".
   *   If the value was not a stringified integer, the original value is
   *   returned.
   */
  public static function toIntIfPossible(mixed $value): mixed {
    if (is_string($value)) {
      if ($value === '') {
        return NULL;
      }
      if ((string) (int) $value === $value) {
        return (int) $value;
      }
    }
    // Return the original value.
    // It is better if calling code does the validation, to have better context
    // for failure messages.
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
      static fn (int $a, int $b) => $a | $b,
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
      static fn (int $a, int $b) => $a & $b,
      ~0,
    );
  }

}
