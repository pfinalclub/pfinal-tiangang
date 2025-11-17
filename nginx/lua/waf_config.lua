-- WAF 配置动态读取脚本
-- 用于 Nginx + OpenResty，从天罡 WAF 管理接口动态读取域名映射配置

local cjson = require "cjson"
local http = require "resty.http"

local _M = {}

-- 配置缓存（使用 shared dict）
local config_cache = ngx.shared.waf_config
local CACHE_KEY = "domain_mappings"
local CACHE_TTL = 60  -- 缓存 60 秒

-- WAF 管理接口地址
local WAF_API_HOST = os.getenv("WAF_API_HOST") or "127.0.0.1:8989"
local WAF_API_ENDPOINT = "http://" .. WAF_API_HOST .. "/admin/api/nginx/config"

-- 从 WAF 管理接口获取配置
function _M.fetch_config()
    local httpc = http.new()
    httpc:set_timeout(5000)  -- 5秒超时
    
    local res, err = httpc:request_uri(WAF_API_ENDPOINT, {
        method = "GET",
        headers = {
            ["Content-Type"] = "application/json",
            ["User-Agent"] = "Nginx-Lua-WAF/1.0",
        }
    })
    
    if not res then
        ngx.log(ngx.ERR, "[WAF Config] Failed to fetch config: ", err)
        return nil, err
    end
    
    if res.status ~= 200 then
        ngx.log(ngx.ERR, "[WAF Config] API returned status: ", res.status)
        return nil, "API error: " .. res.status
    end
    
    local ok, data = pcall(cjson.decode, res.body)
    if not ok then
        ngx.log(ngx.ERR, "[WAF Config] Failed to decode JSON: ", data)
        return nil, "Invalid JSON response"
    end
    
    if data.code ~= 0 then
        ngx.log(ngx.ERR, "[WAF Config] API error: ", data.message or "unknown")
        return nil, data.message or "API returned error"
    end
    
    return data.data
end

-- 获取配置（带缓存）
function _M.get_config()
    -- 先从缓存读取
    local cached = config_cache:get(CACHE_KEY)
    if cached then
        ngx.log(ngx.DEBUG, "[WAF Config] Using cached config")
        local ok, config = pcall(cjson.decode, cached)
        if ok then
            return config
        end
        ngx.log(ngx.WARN, "[WAF Config] Cached config is invalid, fetching new")
    end
    
    -- 缓存未命中，从 API 获取
    ngx.log(ngx.INFO, "[WAF Config] Fetching config from API: ", WAF_API_ENDPOINT)
    local config, err = _M.fetch_config()
    
    if not config then
        -- 获取失败，尝试使用备份缓存（即使过期）
        local old_cached = config_cache:get(CACHE_KEY .. ":backup")
        if old_cached then
            ngx.log(ngx.WARN, "[WAF Config] Using old backup config due to fetch error: ", err)
            local ok, backup_config = pcall(cjson.decode, old_cached)
            if ok then
                return backup_config
            end
        end
        
        -- 返回空配置，避免崩溃
        ngx.log(ngx.ERR, "[WAF Config] No valid config available, using defaults")
        return {
            version = 0,
            default_backend = "http://127.0.0.1:8080",
            domain_mappings = {}
        }
    end
    
    -- 保存到缓存
    local ok, json_str = pcall(cjson.encode, config)
    if ok then
        config_cache:set(CACHE_KEY, json_str, CACHE_TTL)
        config_cache:set(CACHE_KEY .. ":backup", json_str, 86400)  -- 备份缓存 24 小时
        ngx.log(ngx.INFO, "[WAF Config] Config cached successfully, version: ", config.version or "unknown")
    else
        ngx.log(ngx.ERR, "[WAF Config] Failed to encode config for caching")
    end
    
    return config
end

-- 根据 Host 获取后端地址
function _M.get_backend(host)
    if not host or host == "" then
        ngx.log(ngx.WARN, "[WAF Config] No host header provided")
        return nil, "No host header"
    end
    
    -- 移除端口号并转小写
    host = host:gsub(":%d+$", "")
    host = host:lower()
    
    -- 获取配置
    local config = _M.get_config()
    if not config then
        ngx.log(ngx.ERR, "[WAF Config] Failed to get config")
        return nil, "Config unavailable"
    end
    
    -- 查找域名映射
    local mappings = config.domain_mappings or {}
    local mapping = mappings[host]
    
    if mapping and mapping.backend then
        ngx.log(ngx.INFO, "[WAF Config] Found mapping: ", host, " -> ", mapping.backend)
        return mapping.backend, nil
    end
    
    -- 使用默认后端
    local default = config.default_backend or "http://127.0.0.1:8080"
    ngx.log(ngx.INFO, "[WAF Config] Using default backend: ", host, " -> ", default)
    return default, nil
end

-- 手动刷新缓存（通过 API 调用）
function _M.refresh_cache()
    config_cache:delete(CACHE_KEY)
    ngx.log(ngx.INFO, "[WAF Config] Cache cleared, will reload on next request")
    
    -- 立即重新加载
    local config = _M.get_config()
    if config then
        return true, "Cache refreshed successfully"
    else
        return false, "Failed to reload config"
    end
end

-- 获取缓存统计信息
function _M.get_cache_stats()
    local cached = config_cache:get(CACHE_KEY)
    local backup = config_cache:get(CACHE_KEY .. ":backup")
    
    return {
        has_cache = cached ~= nil,
        has_backup = backup ~= nil,
        cache_keys = config_cache:get_keys(100)
    }
end

return _M

