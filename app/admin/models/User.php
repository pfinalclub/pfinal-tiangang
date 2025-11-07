<?php

namespace app\admin\models;

use Illuminate\Database\Eloquent\Model;

/**
 * 用户模型
 * 
 * 使用 Illuminate Database ORM
 */
class User extends Model
{
    /**
     * 表名
     */
    protected $table = 'users';
    
    /**
     * 主键
     */
    protected $primaryKey = 'id';
    
    /**
     * 主键类型
     */
    protected $keyType = 'int';
    
    /**
     * 是否自动递增
     */
    public $incrementing = true;
    
    /**
     * 时间戳
     */
    public $timestamps = true;
    
    /**
     * 日期格式
     */
    protected $dateFormat = 'Y-m-d H:i:s';
    
    /**
     * 可批量赋值的字段
     */
    protected $fillable = [
        'username',
        'email',
        'real_name',
        'role',
        'status',
    ];
    
    /**
     * 隐藏的字段（不序列化）
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    /**
     * 类型转换
     */
    protected $casts = [
        'id' => 'integer',
        'status' => 'boolean',
        'login_count' => 'integer',
        'failed_login_count' => 'integer',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * 根据用户名查找用户（支持离线模式）
     */
    public static function findByUsername(string $username): ?self
    {
        try {
            // 检查数据库是否可用
            if (!\app\admin\config\Database::isAvailable()) {
                return null;
            }
            
            return static::where('username', $username)
                ->where('status', 1)
                ->first();
        } catch (\Exception $e) {
            // 数据库查询失败，返回 null（离线模式）
            error_log('User query failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
    
    /**
     * 检查用户是否被锁定
     */
    public function isLocked(): bool
    {
        if (!$this->locked_until) {
            return false;
        }
        
        return strtotime($this->locked_until) > time();
    }
    
    /**
     * 更新登录信息（支持离线模式）
     */
    public function updateLoginInfo(string $ip, ?string $userAgent = null): bool
    {
        // 检查数据库是否可用
        if (!\app\admin\config\Database::isAvailable()) {
            return false; // 离线模式下无法更新
        }
        
        try {
            $this->last_login_at = now();
            $this->last_login_ip = $ip;
            $this->login_count = ($this->login_count ?? 0) + 1;
            $this->failed_login_count = 0;
            
            return $this->save();
        } catch (\Exception $e) {
            error_log('Update login info failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 增加失败登录次数（支持离线模式）
     */
    public function incrementFailedLoginCount(): bool
    {
        // 检查数据库是否可用
        if (!\app\admin\config\Database::isAvailable()) {
            return false; // 离线模式下无法更新
        }
        
        try {
            $this->failed_login_count = ($this->failed_login_count ?? 0) + 1;
            return $this->save();
        } catch (\Exception $e) {
            error_log('Increment failed login count failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 锁定用户（支持离线模式）
     */
    public function lock(int $duration = 3600): bool
    {
        // 检查数据库是否可用
        if (!\app\admin\config\Database::isAvailable()) {
            return false; // 离线模式下无法锁定
        }
        
        try {
            $this->locked_until = date('Y-m-d H:i:s', time() + $duration);
            return $this->save();
        } catch (\Exception $e) {
            error_log('Lock user failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 解锁用户（支持离线模式）
     */
    public function unlock(): bool
    {
        // 检查数据库是否可用
        if (!\app\admin\config\Database::isAvailable()) {
            return false; // 离线模式下无法解锁
        }
        
        try {
            $this->locked_until = null;
            return $this->save();
        } catch (\Exception $e) {
            error_log('Unlock user failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 检查是否为管理员
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'waf_admin']);
    }
    
    /**
     * 检查是否为超级管理员
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

