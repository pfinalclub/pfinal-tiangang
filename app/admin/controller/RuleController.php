<?php

namespace app\admin\controller;

use support\Request;
use support\Response;

/**
 * WAF 规则管理控制器
 */
class RuleController
{
    /**
     * 规则列表
     */
    public function index(Request $request): Response
    {
        $rules = [
            [
                'id' => 1,
                'name' => 'SQL注入检测',
                'type' => 'sql_injection',
                'status' => 'enabled',
                'priority' => 80,
                'pattern' => '/(union|select|insert|update|delete|drop|create|alter)\\s+/i',
                'description' => '检测SQL注入攻击',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ],
            [
                'id' => 2,
                'name' => 'XSS攻击检测',
                'type' => 'xss',
                'status' => 'enabled',
                'priority' => 70,
                'pattern' => '/<script[^>]*>.*?<\\/script>/i',
                'description' => '检测跨站脚本攻击',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ],
            [
                'id' => 3,
                'name' => '频率限制',
                'type' => 'rate_limit',
                'status' => 'enabled',
                'priority' => 90,
                'pattern' => '',
                'description' => '限制请求频率',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ],
            [
                'id' => 4,
                'name' => 'IP黑名单',
                'type' => 'ip_blacklist',
                'status' => 'enabled',
                'priority' => 100,
                'pattern' => '',
                'description' => 'IP地址黑名单',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ]
        ];

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $rules,
                'total' => count($rules)
            ]
        ]);
    }

    /**
     * 创建规则
     */
    public function create(Request $request): Response
    {
        $data = $request->post();
        
        // 验证数据
        if (empty($data['name']) || empty($data['type'])) {
            return json([
                'code' => 1,
                'msg' => '规则名称和类型不能为空'
            ]);
        }

        // 模拟创建规则
        $rule = [
            'id' => rand(1000, 9999),
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'] ?? 'enabled',
            'priority' => $data['priority'] ?? 50,
            'pattern' => $data['pattern'] ?? '',
            'description' => $data['description'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return json([
            'code' => 0,
            'msg' => '规则创建成功',
            'data' => $rule
        ]);
    }

    /**
     * 更新规则
     */
    public function update(Request $request): Response
    {
        $id = $request->get('id');
        $data = $request->post();
        
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '规则ID不能为空'
            ]);
        }

        // 模拟更新规则
        $rule = [
            'id' => $id,
            'name' => $data['name'] ?? '规则名称',
            'type' => $data['type'] ?? 'custom',
            'status' => $data['status'] ?? 'enabled',
            'priority' => $data['priority'] ?? 50,
            'pattern' => $data['pattern'] ?? '',
            'description' => $data['description'] ?? '',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return json([
            'code' => 0,
            'msg' => '规则更新成功',
            'data' => $rule
        ]);
    }

    /**
     * 删除规则
     */
    public function delete(Request $request): Response
    {
        $id = $request->get('id');
        
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '规则ID不能为空'
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '规则删除成功'
        ]);
    }

    /**
     * 启用/禁用规则
     */
    public function toggle(Request $request): Response
    {
        $id = $request->get('id');
        $status = $request->get('status', 'enabled');
        
        if (empty($id)) {
            return json([
                'code' => 1,
                'msg' => '规则ID不能为空'
            ]);
        }

        return json([
            'code' => 0,
            'msg' => '规则状态更新成功',
            'data' => [
                'id' => $id,
                'status' => $status
            ]
        ]);
    }
}
