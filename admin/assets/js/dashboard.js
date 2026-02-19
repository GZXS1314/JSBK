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
    // 1. Weather Widget Logic (天气组件)
    // ------------------------------------------------------------------------
    async function initWeather() {
        const timeout = (ms) => new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), ms));
        
        try {
            // Step 1: 获取定位
            const locRes = await Promise.race([fetch('https://ipapi.co/json/'), timeout(3000)]);
            if(!locRes.ok) throw new Error("Location API unavailable");
            const locData = await locRes.json();
            
            const lat = locData.latitude; 
            const lon = locData.longitude;
            const cityEl = document.getElementById('w-city');
            if(cityEl) cityEl.innerText = locData.city || "本地";

            // Step 2: 获取天气
            const weatherRes = await Promise.race([
                fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&hourly=relativehumidity_2m`),
                timeout(3000)
            ]);
            if(!weatherRes.ok) throw new Error("Weather API unavailable");
            const wData = await weatherRes.json();
            
            // Step 3: 渲染DOM
            document.getElementById('w-temp').innerText = Math.round(wData.current_weather.temperature) + "°";
            document.getElementById('w-wind').innerText = wData.current_weather.windspeed + " km/h";
            document.getElementById('w-hum').innerText = wData.hourly.relativehumidity_2m[new Date().getHours()] || 50;
            
            const wCode = wData.current_weather.weathercode;
            const iconEl = document.getElementById('w-icon');
            iconEl.className = 'fas';
            
            // 简单的天气图标映射
            if (wCode <= 1) iconEl.classList.add('fa-sun');
            else if (wCode <= 3) iconEl.classList.add('fa-cloud-sun');
            else if (wCode <= 67) iconEl.classList.add('fa-cloud-rain');
            else iconEl.classList.add('fa-cloud');

        } catch (e) {
            console.warn("Weather widget error:", e);
            const cityEl = document.getElementById('w-city');
            if(cityEl) cityEl.innerText = "暂无定位";
            
            const tempEl = document.getElementById('w-temp');
            if(tempEl) tempEl.innerText = "-";
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

});
