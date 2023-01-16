<?php

declare(strict_types = 1);

namespace Drupal\Tests\poc_nextcloud\Tools;

use PHPUnit\Framework\Assert;

/**
 * Static methods to convert value objects into yaml-friendly arrays.
 *
 * The conversion is meant for basic value objects only.
 */
class ValueExporter {

  /**
   * Asserts that two values are identical when exported.
   *
   * @param mixed $expected
   *   Expected value.
   * @param mixed $actual
   *   Actual value.
   */
  public static function assertSameExport(mixed $expected, mixed $actual): void {
    Assert::assertSame(
      self::export($expected),
      self::export($actual),
    );
  }

  /**
   * Converts a value into a yaml-friendly array.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return mixed
   *   The yaml-friendly value.
   */
  public static function export(mixed $value): mixed {
    return match (gettype($value)) {
      'object' => self::export(self::exportObject($value)),
      'array' => array_map([self::class, 'export'], $value),
      default => $value,
    };
  }

  /**
   * Converts value objects to array, so they can be printed as yml.
   *
   * @param object $object
   *   Value object to be exported.
   *
   * @return array
   *   Array representing the public values of the object, suitable for yml.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  private static function exportObject(object $object): array {
    $reflectionClass = new \ReflectionClass($object);
    $result = ['class' => get_class($object)];
    foreach ($reflectionClass->getProperties() as $property) {
      if ($property->isPublic() && !$property->isStatic()) {
        $result['$' . $property->getName()] = $object->{$property->getName()};
      }
    }
    foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      if (!$method->isStatic() && !$method->isAbstract() && $method->getParameters() === [] && str_starts_with($method->getName(), 'get') || str_starts_with($method->getName(), 'is')) {
        $result[$method->getName() . '()'] = $object->{$method->getName()}();
      }
    }
    foreach ($result as &$value) {
      if (is_object($value)) {
        $value = self::export($value);
      }
    }
    return $result;
  }

}
