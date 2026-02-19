<?php
// includes/redis_helper.php
/**
                _ _                     ____  _                             
               | (_) __ _ _ __   __ _  / ___|| |__  _   _  ___              
            _  | | |/ _` | '_ \ / _` | \___ \| '_ \| | | |/ _ \             
           | |_| | | (_| | | | | (_| |  ___) | | | | |_| | (_) |            
            \___/|_|\__,_|_| |_|\__, | |____/|_| |_|\__,_|\___/             
   ____   _____          _  __  |___/   _____   _   _  _          ____ ____ 
  / ___| |__  /         | | \ \/ / / | |___ /  / | | || |        / ___/ ___|
 | |  _    / /       _  | |  \  /  | |   |_ \  | | | || |_      | |  | |    
 | |_| |  / /_   _  | |_| |  /  \  | |  ___) | | | |__   _|  _  | |__| |___ 
  \____| /____| (_)  \___/  /_/\_\ |_| |____/  |_|    |_|   (_)  \____\____|
                                                                            
                               追求极致的美学                               
**/
require_once __DIR__ . '/config.php';

class Cache {
    
    // 检查 Redis 是否全局启用
    private static function isEnabled() {
        static $enabled = null;
        if ($enabled === null) {
            try {
                $pdo = getDB();
                // 默认启用 (value IS NULL OR value = '1')
                $stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'redis_enabled'");
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                // 必须明确设置了值为 '1' 才开启，否则默认关闭
                $enabled = ($res && $res['value'] == '1');
            } catch (Exception $e) {
                // 数据库出错时，为了安全起见，默认禁用缓存
                $enabled = false;
            }
        }
        return $enabled;
    }

    // 读取缓存
    public static function get($key) {
        if (!self::isEnabled()) return false; // 如果关闭开关，直接返回 false

        $redis = getRedis();
        if (!$redis) return false;
        
        $val = $redis->get(CACHE_PREFIX . $key);
        return $val ? json_decode($val, true) : false;
    }

    // 写入缓存 (默认缓存 1 小时)
    public static function set($key, $data, $ttl = 3600) {
        if (!self::isEnabled()) return false; // 如果关闭开关，不写入

        $redis = getRedis();
        if (!$redis) return false;

        // JSON 序列化存储
        return $redis->setex(CACHE_PREFIX . $key, $ttl, json_encode($data));
    }

    // 删除缓存
    public static function del($key) {
        // 删除操作通常不需要检查开关，因为可能是在清理旧数据，
        // 但为了保持一致性，如果 Redis 关了，连连接都不应该建立。
        // 不过如果是手动清理操作，可能需要强制连接。
        // 这里我们还是保持一致，关了就不操作。
        if (!self::isEnabled()) return false; 

        $redis = getRedis();
        if (!$redis) return false;
        return $redis->del(CACHE_PREFIX . $key);
    }

    // 模糊删除 (用于批量清除，例如清理 article_*)
    public static function clear($pattern) {
        if (!self::isEnabled()) return false;

        $redis = getRedis();
        if (!$redis) return false;
        
        $keys = $redis->keys(CACHE_PREFIX . $pattern);
        if (!empty($keys)) {
            foreach ($keys as $k) {
                $redis->del($k);
            }
        }
    }
}
?>
