<?php

namespace app\admin\Base;

/**
 * 基础服务类
 * 
 * 提供通用的服务方法
 */
abstract class BaseService
{
    /**
     * 记录日志
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger($level, $message, $context);
        }
    }
    
    /**
     * 验证数据
     */
    protected function validate(array $data, array $rules): void
    {
        foreach ($rules as $field => $rule) {
            if (isset($rule['required']) && $rule['required'] && empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
            
            if (isset($data[$field]) && isset($rule['type'])) {
                $this->validateType($data[$field], $field, $rule['type']);
            }
            
            if (isset($data[$field]) && isset($rule['pattern'])) {
                if (!preg_match($rule['pattern'], $data[$field])) {
                    throw new \InvalidArgumentException("Field '{$field}' format is invalid");
                }
            }
        }
    }
    
    /**
     * 验证类型
     */
    private function validateType($value, string $field, string $type): void
    {
        $valid = false;
        
        switch ($type) {
            case 'string':
                $valid = is_string($value);
                break;
            case 'int':
            case 'integer':
                $valid = is_int($value) || (is_string($value) && ctype_digit($value));
                break;
            case 'float':
            case 'double':
                $valid = is_float($value) || is_numeric($value);
                break;
            case 'bool':
            case 'boolean':
                $valid = is_bool($value) || in_array($value, ['0', '1', 'true', 'false', 0, 1], true);
                break;
            case 'array':
                $valid = is_array($value);
                break;
            case 'url':
                $valid = is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
                break;
            case 'email':
                $valid = is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                break;
            default:
                throw new \InvalidArgumentException("Unsupported validation type: {$type}");
        }
        
        if (!$valid) {
            throw new \InvalidArgumentException("Field '{$field}' must be of type {$type}");
        }
    }
}

