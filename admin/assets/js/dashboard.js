/**
 * ============================================================================
 * Dashboard Logic
 * ============================================================================
 * @description: 后台首页交互逻辑 (图表、天气等)
 * @author:      jiang shuo
 * @update:      2026-1-1
 * @dependence:  ECharts 5.x
 * @global:      dbConfig (Passed from PHP)
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ------------------------------------------------------------------------
    // 1. Weather Widget Logic (强迫症福音版：纯后端无感定位，告别一切红字)
    // ------------------------------------------------------------------------
    async function initWeather() {
        const cityEl = document.getElementById('w-city');
        const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), ms));

        // 核心渲染天气函数
        async function fetchWeather(lat, lon, cityName) {
            try {
                if(cityEl) cityEl.innerText = cityName || "本地";
                const weatherRes = await Promise.race([
                    fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&hourly=relativehumidity_2m`),
                    timeout(4000)
                ]);
                if(!weatherRes.ok) throw new Error("Weather API Error");
                const wData = await weatherRes.json();
                
                document.getElementById('w-temp').innerText = Math.round(wData.current_weather.temperature) + "°";
                document.getElementById('w-wind').innerText = wData.current_weather.windspeed + " km/h";
                document.getElementById('w-hum').innerText = wData.hourly.relativehumidity_2m[new Date().getHours()] || 50;
                
                const wCode = wData.current_weather.weathercode;
                const iconEl = document.getElementById('w-icon');
                iconEl.className = 'fas';
                if (wCode <= 1) iconEl.classList.add('fa-sun');
                else if (wCode <= 3) iconEl.classList.add('fa-cloud-sun');
                else if (wCode <= 67) iconEl.classList.add('fa-cloud-rain');
                else iconEl.classList.add('fa-cloud');
                
            } catch (e) {
                console.warn("天气加载失败:", e);
                if(cityEl) cityEl.innerText = "天气加载失败";
                document.getElementById('w-temp').innerText = "-°";
            }
        }

        // 彻底抛弃 navigator.geolocation，直接读取后端 PHP 喂到嘴边的坐标
        if (window.dbConfig && window.dbConfig.userGeo) {
            const geo = window.dbConfig.userGeo;
            console.log("📍 [完美定位] 直接使用后端坐标:", geo.city);
            fetchWeather(geo.lat, geo.lon, geo.city);
        } else {
            console.warn("📍 后端未能获取到有效坐标");
            if(cityEl) cityEl.innerText = "定位失败";
            document.getElementById('w-temp').innerText = "-°";
        }
    }
    initWeather();

    // ------------------------------------------------------------------------
    // 2. Main Trend Chart (主趋势图)
    // ------------------------------------------------------------------------
    if (document.getElementById('main-chart') && typeof echarts !== 'undefined') {
        var myChart = echarts.init(document.getElementById('main-chart'));
        
        var option = {
            grid: { top: '15%', left: '2%', right: '3%', bottom: '2%', containLabel: true },
            tooltip: { trigger: 'axis' },
            xAxis: { 
                type: 'category', 
                data: dbConfig.chart.dates, // 来自全局配置
                axisLine: { show: false }, 
                axisTick: { show: false }, 
                axisLabel: { color: '#94a3b8' } 
            },
            yAxis: { 
                type: 'value', 
                splitLine: { lineStyle: { type: 'dashed', color: '#eee' } } 
            },
            series: [{
                data: dbConfig.chart.values, // 来自全局配置
                type: 'line',
                smooth: true,
                symbol: 'circle',
                symbolSize: 6,
                itemStyle: { color: '#6366f1' },
                lineStyle: { width: 3, color: '#6366f1' },
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(99, 102, 241, 0.3)' },
                        { offset: 1, color: 'rgba(99, 102, 241, 0.0)' }
                    ])
                }
            }]
        };
        myChart.setOption(option);
    }

    // ------------------------------------------------------------------------
    // 3. Server Status Mini Gauges (服务器状态仪表盘)
    // ------------------------------------------------------------------------
    function renderMiniGauge(id, val, percent, color, showPercentSymbol = true) {
        if (!document.getElementById(id) || typeof echarts === 'undefined') return null;
        
        var chart = echarts.init(document.getElementById(id));
        var displayLabel = showPercentSymbol ? val + '%' : val;
        
        var opt = {
            series: [{
                type: 'pie',
                radius: ['70%', '100%'],
                avoidLabelOverlap: false,
                label: { 
                    show: true, 
                    position: 'center', 
                    formatter: function() { return displayLabel; }, 
                    fontSize: 11, 
                    fontWeight: 'bold', 
                    color: '#475569' 
                },
                emphasis: { scale: false },
                data: [
                    { value: percent, itemStyle: { color: color } },
                    { value: 100 - percent, itemStyle: { color: '#e2e8f0' }, label: { show: false } }
                ]
            }]
        };
        chart.setOption(opt);
        return chart;
    }

    // 初始化三个小仪表盘 (数据来自全局 dbConfig)
    var cpuChart = renderMiniGauge('chart-cpu', dbConfig.server.cpu_percent, dbConfig.server.cpu_percent, '#ef4444', true);
    var memChart = renderMiniGauge('chart-mem', dbConfig.server.mem_percent, dbConfig.server.mem_percent, '#f59e0b');
    var diskChart = renderMiniGauge('chart-disk', dbConfig.server.disk_percent, dbConfig.server.disk_percent, '#10b981');

    // ------------------------------------------------------------------------
    // 4. Resize Handler (窗口缩放适配)
    // ------------------------------------------------------------------------
    window.addEventListener('resize', function() {
        if (myChart) myChart.resize();
        if (cpuChart) cpuChart.resize();
        if (memChart) memChart.resize();
        if (diskChart) diskChart.resize();
    });
// ------------------------------------------------------------------------
    // 5. Dashboard Update Checker (V3 - Pill Design Switch)
    // ------------------------------------------------------------------------
    window.checkVersionOnDashboard = function() {
        // 注意这里选择器的变化
        const module = document.querySelector('.update-pill'); 
        const icon = document.getElementById('dash-update-icon');
        const text = document.getElementById('dash-update-text');
        const dot = document.getElementById('avatar-update-dot');
        
        if (!module || !icon || !text) return;

        // 初始化状态
        module.className = 'auth-badge update-pill'; 
        icon.className = 'fas fa-sync-alt fa-spin-fast';
        text.innerText = 'Checking...';
        if(dot) dot.style.display = 'none';

        fetch('updater.php?action=check')
            .then(res => res.json())
            .then(data => {
                icon.classList.remove('fa-spin-fast');
                
                if (data.status === 'success' && data.has_update) {
                    module.classList.add('has-update');
                    icon.className = 'fas fa-arrow-alt-circle-up';
                    text.innerHTML = `升级 v${data.info.version}`;
                    if(dot) dot.style.display = 'block';
                    
                    if (typeof showUpdateModal === 'function') {
                        window.updateDataCache = data.info; 
                        showUpdateModal(data.info);
                    }
                } else {
                    module.classList.add('is-latest');
                    icon.className = 'fas fa-check-circle';
                    text.innerText = `最新版本`;
                    
                    setTimeout(() => {
                        module.className = 'auth-badge update-pill';
                        icon.className = 'fas fa-code-branch';
                        text.innerText = `v${data.current_version || '1.0.0'}`;
                    }, 3000);
                }
            })
            .catch(err => {
                icon.className = 'fas fa-exclamation-triangle';
                text.innerText = 'Error';
            });
    };

    setTimeout(checkVersionOnDashboard, 1500);
});
