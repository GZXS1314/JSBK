<?php
// includes/redis_helper.php
/**
 * 追求极致的美学 - Redis 缓存核心组件 (企业级双层缓存优化版)
 */
require_once __DIR__ . '/config.php';

class Cache {
    
    // 【新增】L1 PHP 静态内存缓存，拦截同一页面内的重复 Redis 请求
    private static $localCache = [];
    
    private static function isEnabled() {
        return defined('REDIS_ENABLED') && REDIS_ENABLED === true;
    }

    public static function get($key) {
        // 第一层：优先查 PHP 内存，耗时 0ms
        if (isset(self::$localCache[$key])) {
            return self::$localCache[$key];
        }

        if (!self::isEnabled()) return false;

        $redis = getRedis();
        if (!$redis) return false;
        
        // 第二层：查 Redis
        $val = $redis->get(CACHE_PREFIX . $key);
        if ($val) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // 存入 L1 内存
                self::$localCache[$key] = $decoded;
                return $decoded;
            }
        }
        return false;
    }

    public static function set($key, $data, $ttl = 3600) {
        // 同步更新 L1 内存
        self::$localCache[$key] = $data;
        
        if (!self::isEnabled()) return false;
        $redis = getRedis();
        if (!$redis) return false;

        return $redis->setex(CACHE_PREFIX . $key, $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public static function del($key) {
        // 同步清理 L1 内存
        unset(self::$localCache[$key]);
        
        if (!self::isEnabled()) return false;
        $redis = getRedis();
        if (!$redis) return false;
        return $redis->del(CACHE_PREFIX . $key);
    }

    public static function clear($pattern) {
        // 清理 L1 内存中所有匹配的 key
        foreach (array_keys(self::$localCache) as $k) {
            if (strpos($k, $pattern) !== false) unset(self::$localCache[$k]);
        }

        if (!self::isEnabled()) return false;
        $redis = getRedis();
        if (!$redis) return false;
        
        $iterator = null;
        $fullPattern = CACHE_PREFIX . $pattern;
        
        while ($keys = $redis->scan($iterator, $fullPattern, 100)) {
            if (!empty($keys)) {
                call_user_func_array([$redis, 'del'], $keys); 
            }
        }
    }
}
?>