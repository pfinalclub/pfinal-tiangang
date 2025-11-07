<?php

/**
 * 简单的测试框架
 * 提供基本的断言方法
 */
class SimpleTestFramework
{
    protected function assertTrue($condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \Exception("断言失败: {$message}");
        }
    }
    
    protected function assertFalse($condition, string $message = ''): void
    {
        if ($condition) {
            throw new \Exception("断言失败: {$message}");
        }
    }
    
    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \Exception("断言失败: 期望 {$expected}，实际 {$actual}. {$message}");
        }
    }
    
    protected function assertNotEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            throw new \Exception("断言失败: 值不应相等. {$message}");
        }
    }
    
    protected function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
        if (!($actual instanceof $expected)) {
            $actualType = is_object($actual) ? get_class($actual) : gettype($actual);
            throw new \Exception("断言失败: 期望 {$expected} 的实例，实际是 {$actualType}. {$message}");
        }
    }
    
    protected function assertArrayHasKey($key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception("断言失败: 数组应包含键 '{$key}'. {$message}");
        }
    }
    
    protected function assertArrayNotHasKey($key, array $array, string $message = ''): void
    {
        if (array_key_exists($key, $array)) {
            throw new \Exception("断言失败: 数组不应包含键 '{$key}'. {$message}");
        }
    }
    
    protected function assertLessThan($expected, $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            throw new \Exception("断言失败: {$actual} 应小于 {$expected}. {$message}");
        }
    }
    
    protected function assertLessThanOrEqual($expected, $actual, string $message = ''): void
    {
        if ($actual > $expected) {
            throw new \Exception("断言失败: {$actual} 应小于或等于 {$expected}. {$message}");
        }
    }
    
    protected function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new \Exception("断言失败: {$actual} 应大于 {$expected}. {$message}");
        }
    }
    
    protected function assertStringNotContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            throw new \Exception("断言失败: 字符串不应包含 '{$needle}'. {$message}");
        }
    }
    
    protected function assertIsArray($actual, string $message = ''): void
    {
        if (!is_array($actual)) {
            throw new \Exception("断言失败: 期望数组，实际是 " . gettype($actual) . ". {$message}");
        }
    }
    
    protected function assertIsBool($actual, string $message = ''): void
    {
        if (!is_bool($actual)) {
            throw new \Exception("断言失败: 期望布尔值，实际是 " . gettype($actual) . ". {$message}");
        }
    }
    
    protected function assertNotNull($actual, string $message = ''): void
    {
        if ($actual === null) {
            throw new \Exception("断言失败: 值不应为 null. {$message}");
        }
    }
    
    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception("断言失败: 字符串应包含 '{$needle}'. {$message}");
        }
    }
    
    protected function fail(string $message = ''): void
    {
        throw new \Exception("测试失败: {$message}");
    }
}

