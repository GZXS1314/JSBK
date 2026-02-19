<?php
// admin/index.php
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
require_once '../includes/config.php';
requireLogin();

// 关闭错误显示以免破坏 JSON 响应
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

$pdo = getDB();

// 获取全局设置
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key_name']] = $row['value'];
}

/**
 * =================================================================
 * 1. 服务器状态获取 (逻辑保持不变)
 * =================================================================
 */
function getServerStats() {
    // ... (此处保持原有函数代码不变，篇幅原因省略，请保留原有的 getServerStats 函数) ...
    // 为了完整性，请确保这里包含原有的 getServerStats 代码
    $stats = [
        'os_name' => php_uname('s'),
        'os_release' => php_uname('r'),
        'distro_name' => 'Linux System',
        'cpu_load' => 0,       
        'cpu_percent' => 0,    
        'cpu_cores' => 1,      
        'mem_total' => 0,
        'mem_used' => 0,
        'mem_percent' => 0,
        'disk_total' => 0,
        'disk_used' => 0,
        'disk_percent' => 0,
        'uptime' => '未知'
    ];

    // --- 1. CPU Core ---
    if (is_file('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $stats['cpu_cores'] = count($matches[0]);
    }
    if ($stats['cpu_cores'] < 1) $stats['cpu_cores'] = 1;

    // --- 2. Load & Percent ---
    if (stristr(PHP_OS, 'win')) {
        $stats['distro_name'] = 'Windows NT ' . php_uname('r');
        $output = [];
        @exec('wmic cpu get loadpercentage /Value', $output);
        if (preg_match('/LoadPercentage=(\d+)/', implode($output), $matches)) {
            $stats['cpu_load'] = $matches[1]; 
            $stats['cpu_percent'] = $matches[1];
        }
    } else {
        if (@is_readable('/etc/os-release')) {
            $os_info = parse_ini_file('/etc/os-release');
            if (isset($os_info['PRETTY_NAME'])) $stats['distro_name'] = trim($os_info['PRETTY_NAME'], '"');
        }
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $stats['cpu_load'] = isset($load[0]) ? number_format($load[0], 2) : 0;
        } elseif (@is_readable('/proc/loadavg')) {
            $load = explode(' ', file_get_contents('/proc/loadavg'));
            $stats['cpu_load'] = isset($load[0]) ? number_format($load[0], 2) : 0;
        }
        $percent = ($stats['cpu_load'] / $stats['cpu_cores']) * 100;
        $stats['cpu_percent'] = round(min($percent, 100), 0);
    }

    // --- 3. Disk ---
    try {
        $dt = @disk_total_space(".");
        $df = @disk_free_space(".");
        if ($dt !== false) {
            $stats['disk_total'] = round($dt / 1073741824, 2);
            $free = round($df / 1073741824, 2);
            $stats['disk_used'] = $stats['disk_total'] - $free;
            $stats['disk_percent'] = ($stats['disk_total'] > 0) ? round(($stats['disk_used'] / $stats['disk_total']) * 100, 1) : 0;
        }
    } catch (Exception $e) {}

    // --- 4. RAM & Uptime ---
    if (!stristr(PHP_OS, 'win')) {
        $mem_data = '';
        if (@is_readable('/proc/meminfo')) {
            $mem_data = file_get_contents('/proc/meminfo');
        } elseif (function_exists('shell_exec')) {
            $mem_data = @shell_exec('cat /proc/meminfo');
        }
        if ($mem_data) {
            $mTotal = 0; $mAvail = 0;
            if (preg_match('/MemTotal:\s+(\d+)/i', $mem_data, $mt)) $mTotal = $mt[1];
            if (preg_match('/MemAvailable:\s+(\d+)/i', $mem_data, $ma)) $mAvail = $ma[1];
            if ($mAvail == 0 && preg_match('/MemFree:\s+(\d+)/i', $mem_data, $mf)) $mAvail = $mf[1];
            if ($mTotal > 0) {
                $stats['mem_total'] = round($mTotal / 1024 / 1024, 2);
                $stats['mem_used']  = round(($mTotal - $mAvail) / 1024 / 1024, 2);
                $stats['mem_percent'] = round(($stats['mem_used'] / $stats['mem_total']) * 100, 1);
            }
        }
        $uptime_str = '';
        if (@is_readable('/proc/uptime')) {
            $uptime_str = file_get_contents('/proc/uptime');
        }
        if ($uptime_str) {
            $num = floatval(explode(' ', $uptime_str)[0]);
            $days = floor($num / 86400);
            $hours = floor(($num % 86400) / 3600);
            $stats['uptime'] = "{$days}天 {$hours}小时";
        }
    }
    return $stats;
}

function getSoftVersions($pdo) {
    // ... (保留原有 getSoftVersions 代码) ...
    $ver = ['php' => PHP_VERSION, 'mysql' => 'Unknown', 'redis' => '未安装'];
    try {
        $v = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        if (preg_match('/(\d+\.\d+\.\d+)/', $v, $m)) $ver['mysql'] = "MySQL " . $m[1];
    } catch (Exception $e) {}
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            if (@$redis->connect('127.0.0.1', 6379, 0.2)) {
                $info = $redis->info();
                $ver['redis'] = "Redis " . ($info['redis_version'] ?? '?');
            }
        } catch (Exception $e) {}
    }
    return $ver;
}

$server = getServerStats();
$soft_ver = getSoftVersions($pdo);

// 业务数据
$count_article = $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
$count_comment = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$count_photo   = $pdo->query("SELECT COUNT(*) FROM photos")->fetchColumn();
$sum_views     = $pdo->query("SELECT SUM(views) FROM articles")->fetchColumn() ?: 0;

// 图表数据
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));
$stmt_trend = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, COUNT(*) as count FROM articles WHERE created_at >= ? GROUP BY date ORDER BY date ASC");
$stmt_trend->execute([$seven_days_ago]);
$trend_raw = $stmt_trend->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_dates = [];
$chart_vals = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_dates[] = date('m-d', strtotime($d));
    $chart_vals[] = isset($trend_raw[$d]) ? $trend_raw[$d] : 0;
}

require 'header.php';
?>

<!-- 引入 ECharts 库 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js"></script>
<!-- 引入自定义 CSS -->
<link rel="stylesheet" href="assets/css/dashboard.css">

<div class="dashboard-grid">

    <!-- 1. 天气 -->
    <div class="b-card area-weather no-hover-bg">
        <div class="card-head" style="color: rgba(255,255,255,0.8); margin-bottom: 0;">
            <span id="w-date"><?= date('m/d D') ?></span>
        </div>
        <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center;">
            <i class="fas fa-cloud" id="w-icon" style="font-size: 40px; opacity: 0.9;"></i>
            <div class="w-temp" id="w-temp">--°</div>
            <div class="weather-loc">
                <i class="fas fa-location-arrow" style="font-size: 12px;"></i> 
                <span id="w-city">获取中...</span>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 12px; background: rgba(255,255,255,0.15); padding: 8px 12px; border-radius: 8px;">
            <span><i class="fas fa-wind"></i> <span id="w-wind">-</span></span>
            <span><i class="fas fa-tint"></i> <span id="w-hum">-</span>%</span>
        </div>
    </div>

    <!-- 2. 服务器 -->
    <div class="b-card area-server">
        <div class="card-head"><i class="fas fa-server"></i> 资源监控</div>
        <div class="server-layout">
            <div class="server-charts">
                <!-- CPU Chart -->
                <div style="text-align:center;">
                    <div class="server-circle" id="chart-cpu"></div>
                    <div style="font-size:11px; margin-top:5px; color:var(--text-secondary);">
                        Load: <?= $server['cpu_load'] ?>
                    </div>
                </div>
                <!-- RAM Chart -->
                <div style="text-align:center;">
                    <div class="server-circle" id="chart-mem"></div>
                    <div style="font-size:11px; margin-top:5px; color:var(--text-secondary);">RAM</div>
                </div>
                <!-- DISK Chart -->
                <div style="text-align:center;">
                    <div class="server-circle" id="chart-disk"></div>
                    <div style="font-size:11px; margin-top:5px; color:var(--text-secondary);">DISK</div>
                </div>
            </div>

            <div class="server-details">
                <div class="svr-item">
                    <div>
                        <div class="svr-label"><i class="fab fa-linux"></i> 系统</div>
                        <div class="svr-value-sys" title="<?= $server['distro_name'] ?>"><?= $server['distro_name'] ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="svr-label"><i class="fas fa-clock"></i> 运行时长</div>
                        <div class="svr-value" style="font-size: 12px;"><?= $server['uptime'] ?></div>
                    </div>
                </div>

                <div style="padding: 0 5px;">
                    <div class="svr-label" style="justify-content: space-between; margin-bottom: 2px;">
                        <span>内存 (<?= $server['mem_used'] ?>G / <?= $server['mem_total'] ?>G)</span>
                        <span><?= $server['mem_percent'] ?>%</span>
                    </div>
                    <div class="svr-bar-bg"><div class="svr-bar-fill" style="width: <?= $server['mem_percent'] ?>%; background: #6366f1;"></div></div>
                </div>
                
                <div style="padding: 0 5px;">
                    <div class="svr-label" style="justify-content: space-between; margin-bottom: 2px;">
                        <span>磁盘 (<?= $server['disk_used'] ?>G / <?= $server['disk_total'] ?>G)</span>
                        <span><?= $server['disk_percent'] ?>%</span>
                    </div>
                    <div class="svr-bar-bg"><div class="svr-bar-fill" style="width: <?= $server['disk_percent'] ?>%; background: #10b981;"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. 趋势图 -->
    <div class="b-card area-chart">
        <div class="card-head">
            <span><i class="fas fa-chart-line"></i> 数据发布趋势</span>
            <span style="font-size: 11px; font-weight: 400; color: var(--text-tertiary);">近7天</span>
        </div>
        <div id="main-chart" style="flex: 1; width: 100%;"></div>
    </div>

    <!-- 4. 环境 -->
    <div class="b-card area-env">
        <div class="card-head"><i class="fas fa-microchip"></i> 核心组件</div>
        <div class="env-grid">
            <div class="env-item">
                <i class="fab fa-php env-icon" style="color: #777bb4;"></i>
                <div class="env-name">PHP</div>
                <div class="env-ver"><?= $soft_ver['php'] ?></div>
            </div>
            <div class="env-item">
                <i class="fas fa-database env-icon" style="color: #00758f;"></i>
                <div class="env-name">MySQL</div>
                <div class="env-ver"><?= $soft_ver['mysql'] ?></div>
            </div>
            <div class="env-item">
                <i class="fas fa-layer-group env-icon" style="color: #e11d48;"></i>
                <div class="env-name">Redis</div>
                <div class="env-ver"><?= $soft_ver['redis'] ?></div>
            </div>
            <div class="env-item">
                <i class="fas fa-shield-alt env-icon" style="color: #059669;"></i>
                <div class="env-name">SSL/Sec</div>
                <div class="env-ver"><?= isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Enabled' : 'Disabled' ?></div>
            </div>
        </div>
    </div>

    <!-- 5. 作者 -->
    <div class="b-card area-author no-hover-bg">
        <div class="auth-box">
            <img src="<?= htmlspecialchars($settings['author_avatar'] ?: '../assets/default_avatar.png') ?>" class="auth-img" alt="Avatar">
            <div style="font-size: 18px; font-weight: 700;"><?= htmlspecialchars($settings['author_name'] ?? 'Admin') ?></div>
            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 15px;">Administrator</div>
            <div style="display: flex; gap: 8px; width: 100%;">
                <a href="../index.php" target="_blank" style="flex: 1; background: rgba(255,255,255,0.25); border-radius: 8px; padding: 8px; color: white; text-decoration: none; font-size: 12px; font-weight: 500; transition: background 0.2s;">
                    <i class="fas fa-home"></i> 首页
                </a>
                <a href="settings.php" style="flex: 1; background: rgba(255,255,255,0.25); border-radius: 8px; padding: 8px; color: white; text-decoration: none; font-size: 12px; font-weight: 500; transition: background 0.2s;">
                    <i class="fas fa-cog"></i> 设置
                </a>
            </div>
        </div>
    </div>

    <!-- 6. 统计 -->
    <div class="b-card area-stats">
        <div class="card-head"><i class="fas fa-poll"></i> 站点统计</div>
        <div class="stat-grid">
            <div class="stat-cell">
                <div class="stat-n"><?= number_format($count_article) ?></div>
                <div class="stat-l"><i class="fas fa-file-alt" style="color: #3b82f6;"></i> 文章</div>
                <i class="fas fa-file-alt stat-icon"></i>
            </div>
            <div class="stat-cell">
                <div class="stat-n"><?= number_format($count_comment) ?></div>
                <div class="stat-l"><i class="fas fa-comments" style="color: #10b981;"></i> 评论</div>
                <i class="fas fa-comments stat-icon"></i>
            </div>
            <div class="stat-cell">
                <div class="stat-n"><?= number_format($count_photo) ?></div>
                <div class="stat-l"><i class="fas fa-image" style="color: #f43f5e;"></i> 照片</div>
                <i class="fas fa-image stat-icon"></i>
            </div>
            <div class="stat-cell">
                <div class="stat-n" style="color: #f59e0b;"><?= number_format($sum_views) ?></div>
                <div class="stat-l"><i class="fas fa-eye" style="color: #f59e0b;"></i> 总阅</div>
                <i class="fas fa-eye stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- 7. 快捷 -->
    <div class="b-card area-quick">
        <div class="card-head"><i class="fas fa-rocket"></i> 快捷操作</div>
        <div class="quick-grid">
            <a href="articles.php?action=add" class="q-btn"><i class="fas fa-pen"></i> 写文章</a>
            <a href="photos.php" class="q-btn"><i class="fas fa-upload"></i> 传照片</a>
            <a href="comments.php" class="q-btn"><i class="fas fa-comments"></i> 审评论</a>
            <a href="friends.php" class="q-btn"><i class="fas fa-link"></i> 审友链</a>
        </div>
    </div>

</div>

<!-- 关键：将 PHP 数据注入为全局 JS 变量 -->
<script>
    window.dbConfig = {
        chart: {
            dates: <?= json_encode($chart_dates) ?>,
            values: <?= json_encode($chart_vals) ?>
        },
        server: {
            cpu_percent: <?= $server['cpu_percent'] ?>,
            mem_percent: <?= $server['mem_percent'] ?>,
            disk_percent: <?= $server['disk_percent'] ?>
        }
    };
</script>

<!-- 引入业务逻辑 JS -->
<script src="assets/js/dashboard.js"></script>

<?php require 'footer.php'; ?>
