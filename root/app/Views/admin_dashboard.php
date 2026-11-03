<?php $this->extend('_layout_admin'); ?>
<?= $this->section('content') ?>
<div class="page-dashboard">
    <div class="dash-hero">
        <div class="dash-hero-content">
            <span class="dash-hero-eyebrow">Dashboard</span>
            <h1 class="dash-hero-title">Good morning, John 👋</h1>
            <p class="dash-hero-text">Here's what's happening with your store today.</p>
            <a href="dashboard-analytics.html" class="dash-hero-btn">
                View analytics <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div class="dash-hero-stats">
            <div class="dash-hero-stat">
                <span class="dash-hero-stat-value">$1,280</span>
                <span class="dash-hero-stat-label">Today's Revenue</span>
                <span class="dash-hero-stat-trend"><i class="ph ph-trend-up"></i> 12%</span>
            </div>
            <div class="dash-hero-stat">
                <span class="dash-hero-stat-value">46</span>
                <span class="dash-hero-stat-label">New Orders</span>
                <span class="dash-hero-stat-trend"><i class="ph ph-trend-up"></i> 8%</span>
            </div>
            <div class="dash-hero-stat">
                <span class="dash-hero-stat-value">2,341</span>
                <span class="dash-hero-stat-label">Visitors</span>
                <span class="dash-hero-stat-trend"><i class="ph ph-trend-up"></i> 21%</span>
            </div>
        </div>
    </div>
    <div class="dash-kpi-grid">
        <div class="dash-kpi kpi-primary">
            <div class="dash-kpi-top">
                <span class="dash-kpi-icon"><i class="ph ph-shopping-cart"></i></span>
                <span class="dash-kpi-trend up"><i class="ph ph-trend-up"></i> 12.5%</span>
            </div>
            <div class="dash-kpi-value">$12,426</div>
            <div class="dash-kpi-label">Total Sales</div>
            <div class="dash-kpi-spark">
                <span style="height: 35%"></span>
                <span style="height: 55%"></span>
                <span style="height: 42%"></span>
                <span style="height: 68%"></span>
                <span style="height: 50%"></span>
                <span style="height: 80%"></span>
                <span style="height: 100%"></span>
            </div>
        </div>

        <div class="dash-kpi kpi-success">
            <div class="dash-kpi-top">
                <span class="dash-kpi-icon"><i class="ph ph-currency-dollar"></i></span>
                <span class="dash-kpi-trend up"><i class="ph ph-trend-up"></i> 8.2%</span>
            </div>
            <div class="dash-kpi-value">$8,234</div>
            <div class="dash-kpi-label">Revenue</div>
            <div class="dash-kpi-spark">
                <span style="height: 45%"></span>
                <span style="height: 38%"></span>
                <span style="height: 62%"></span>
                <span style="height: 50%"></span>
                <span style="height: 74%"></span>
                <span style="height: 66%"></span>
                <span style="height: 100%"></span>
            </div>
        </div>

        <div class="dash-kpi kpi-warning">
            <div class="dash-kpi-top">
                <span class="dash-kpi-icon"><i class="ph ph-package"></i></span>
                <span class="dash-kpi-trend down"><i class="ph ph-trend-down"></i> 3.1%</span>
            </div>
            <div class="dash-kpi-value">1,248</div>
            <div class="dash-kpi-label">Orders</div>
            <div class="dash-kpi-spark">
                <span style="height: 80%"></span>
                <span style="height: 95%"></span>
                <span style="height: 70%"></span>
                <span style="height: 85%"></span>
                <span style="height: 60%"></span>
                <span style="height: 72%"></span>
                <span style="height: 55%"></span>
            </div>
        </div>

        <div class="dash-kpi kpi-info">
            <div class="dash-kpi-top">
                <span class="dash-kpi-icon"><i class="ph ph-users"></i></span>
                <span class="dash-kpi-trend up"><i class="ph ph-trend-up"></i> 5.8%</span>
            </div>
            <div class="dash-kpi-value">5,432</div>
            <div class="dash-kpi-label">Customers</div>
            <div class="dash-kpi-spark">
                <span style="height: 30%"></span>
                <span style="height: 48%"></span>
                <span style="height: 40%"></span>
                <span style="height: 58%"></span>
                <span style="height: 66%"></span>
                <span style="height: 82%"></span>
                <span style="height: 100%"></span>
            </div>
        </div>
    </div>
    <div class="dash-analytics">
        <!-- Revenue Chart -->
        <div class="card dash-chart-card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Revenue Overview</h5>
                    <span class="dash-card-subtitle">Income vs expenses this year</span>
                </div>
                <div class="dash-seg" role="group" aria-label="Chart range">
                    <button class="dash-seg-btn" data-range="week">Week</button>
                    <button class="dash-seg-btn active" data-range="month">Month</button>
                    <button class="dash-seg-btn" data-range="year">Year</button>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" id="revenueChart" style="min-height: 325px;"><div id="apexcharts6lhvq5cs" class="apexcharts-canvas apexcharts6lhvq5cs apexcharts-theme-light" style="width: 754px; height: 310px;"><svg xmlns="http://www.w3.org/2000/svg" class="apexcharts-svg" xmlns:data="ApexChartsNS" transform="translate(0, 0)" width="754" height="310" role="application" aria-label="area chart with 2 data series: Revenue, Expenses" tabindex="0"><foreignObject x="0" y="0" width="754" height="310"><style type="text/css">.apexcharts-flip-y {
                                        transform: scaleY(-1) translateY(-100%);
                                        transform-origin: top;
                                        transform-box: fill-box;
                                    }
                                    .apexcharts-flip-x {
                                        transform: scaleX(-1);
                                        transform-origin: center;
                                        transform-box: fill-box;
                                    }
                                    .apexcharts-legend {
                                        display: flex;
                                        overflow: auto;
                                        padding: 0 10px;
                                    }
                                    .apexcharts-legend.apexcharts-legend-group-horizontal {
                                        flex-direction: column;
                                    }
                                    .apexcharts-legend-group {
                                        display: flex;
                                    }
                                    .apexcharts-legend-group-vertical {
                                        flex-direction: column-reverse;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom, .apexcharts-legend.apx-legend-position-top {
                                        flex-wrap: wrap
                                    }
                                    .apexcharts-legend.apx-legend-position-right, .apexcharts-legend.apx-legend-position-left {
                                        flex-direction: column;
                                        bottom: 0;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-left, .apexcharts-legend.apx-legend-position-top.apexcharts-align-left, .apexcharts-legend.apx-legend-position-right, .apexcharts-legend.apx-legend-position-left {
                                        justify-content: flex-start;
                                        align-items: flex-start;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-center, .apexcharts-legend.apx-legend-position-top.apexcharts-align-center {
                                        justify-content: center;
                                        align-items: center;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-right, .apexcharts-legend.apx-legend-position-top.apexcharts-align-right {
                                        justify-content: flex-end;
                                        align-items: flex-end;
                                    }
                                    .apexcharts-legend-series {
                                        cursor: pointer;
                                        line-height: normal;
                                        display: flex;
                                        align-items: center;
                                    }
                                    .apexcharts-legend-text {
                                        position: relative;
                                        font-size: 14px;
                                    }
                                    .apexcharts-legend-text *, .apexcharts-legend-marker * {
                                        pointer-events: none;
                                    }
                                    .apexcharts-legend-marker {
                                        position: relative;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        cursor: pointer;
                                        margin-right: 1px;
                                    }

                                    .apexcharts-legend-series.apexcharts-no-click {
                                        cursor: auto;
                                    }
                                    .apexcharts-legend .apexcharts-hidden-zero-series, .apexcharts-legend .apexcharts-hidden-null-series {
                                        display: none !important;
                                    }
                                    .apexcharts-inactive-legend {
                                        opacity: 0.45;
                                    } </style></foreignObject><rect x="0" y="0" width="0" height="0" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fefefe"></rect><g class="apexcharts-yaxis" rel="0" transform="translate(31.0859375, 0)"><g class="apexcharts-yaxis-texts-g"><text x="20" y="59" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$12k</tspan><title>$12k</title></text><text x="20" y="102.146" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$10k</tspan><title>$10k</title></text><text x="20" y="145.292" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$8.0k</tspan><title>$8.0k</title></text><text x="20" y="188.438" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$6.0k</tspan><title>$6.0k</title></text><text x="20" y="231.584" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$4.0k</tspan><title>$4.0k</title></text><text x="20" y="274.73" text-anchor="end" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-yaxis-label " style="font-family: inherit;"><tspan>$2.0k</tspan><title>$2.0k</title></text></g></g><g class="apexcharts-inner apexcharts-graphical" transform="translate(61.0859375, 55)"><defs><clipPath id="gridRectMask6lhvq5cs"><rect x="-3.5" y="-3.5" width="677.171875" height="222.73" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="gridRectBarMask6lhvq5cs"><rect x="-3.5" y="-3.5" width="677.171875" height="222.73" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="gridRectMarkerMask6lhvq5cs"><rect x="-3.5" y="0" width="677.171875" height="215.73" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="forecastMask6lhvq5cs"></clipPath><clipPath id="nonForecastMask6lhvq5cs"></clipPath><linearGradient id="SvgjsGradient1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgba(167,139,250,0.35)" stop-opacity="0.35"></stop><stop offset="0.9" stop-color="rgba(255,255,255,0.02)" stop-opacity="0.02"></stop><stop offset="1" stop-color="rgba(255,255,255,0.02)" stop-opacity="0.02"></stop></linearGradient><linearGradient id="SvgjsGradient2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="rgba(251,113,133,0.35)" stop-opacity="0.35"></stop><stop offset="0.9" stop-color="rgba(255,255,255,0.02)" stop-opacity="0.02"></stop><stop offset="1" stop-color="rgba(255,255,255,0.02)" stop-opacity="0.02"></stop></linearGradient></defs><line x1="0" y1="0" x2="0" y2="215.73" stroke="#b6b6b6" stroke-dasharray="3" stroke-linecap="butt" class="apexcharts-xcrosshairs" x="0" y="0" width="1" height="215.73" fill="#B1B9C4" filter="none" fill-opacity="0.9" stroke-width="1"></line><g class="apexcharts-grid"><g class="apexcharts-gridlines-horizontal"><line x1="0" y1="43.146" x2="670.171875" y2="43.146" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line><line x1="0" y1="86.292" x2="670.171875" y2="86.292" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line><line x1="0" y1="129.438" x2="670.171875" y2="129.438" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line><line x1="0" y1="172.584" x2="670.171875" y2="172.584" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line><line x1="0" y1="215.73000000000002" x2="670.171875" y2="215.73000000000002" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line></g><g class="apexcharts-gridlines-vertical"></g><line x1="0" y1="215.73" x2="670.171875" y2="215.73" stroke="transparent" stroke-dasharray="0" stroke-linecap="butt"></line><line x1="0" y1="1" x2="0" y2="215.73" stroke="transparent" stroke-dasharray="0" stroke-linecap="butt"></line></g><g class="apexcharts-grid-borders"><line x1="0" y1="0" x2="670.171875" y2="0" stroke="#2c2350" stroke-dasharray="4" stroke-linecap="butt" class="apexcharts-gridline"></line></g><g class="apexcharts-area-series apexcharts-plot-series"><g class="apexcharts-series" zIndex="0" seriesName="Revenue" data:longestSeries="true" rel="1" data:realIndex="0"><path d="M 0 168.26939999999996C 21.323650568181815 168.26939999999996 39.60106534090909 133.75259999999997 60.92471590909091 133.75259999999997C 82.24836647727273 133.75259999999997 100.52578125 153.1683 121.84943181818181 153.1683C 143.17308238636363 153.1683 161.4504971590909 125.12339999999998 182.77414772727272 125.12339999999998C 204.09779829545454 125.12339999999998 222.3752130681818 148.85369999999998 243.69886363636363 148.85369999999998C 265.0225142045455 148.85369999999998 283.2999289772727 99.23579999999998 304.62357954545456 99.23579999999998C 325.94723011363635 99.23579999999998 344.22464488636365 112.1796 365.54829545454544 112.1796C 386.8719460227273 112.1796 405.1493607954545 84.13469999999998 426.4730113636364 84.13469999999998C 447.79666193181816 84.13469999999998 466.07407670454546 103.5504 487.39772727272725 103.5504C 508.72137784090904 103.5504 526.9987926136363 53.932499999999976 548.3224431818181 53.932499999999976C 569.64609375 53.932499999999976 587.9235085227273 66.87629999999999 609.2471590909091 66.87629999999999C 630.570809659091 66.87629999999999 648.8482244318182 38.831399999999974 670.171875 38.831399999999974C 670.171875 38.831399999999974 670.171875 38.831399999999974 670.171875 215.73 L 0 215.73z" fill="url(#SvgjsGradient1)" fill-opacity="1" stroke="none" stroke-opacity="1" stroke-linecap="butt" stroke-width="0" stroke-dasharray="0" class="apexcharts-area" index="0" clip-path="url(#gridRectMask6lhvq5cs)" pathTo="M 0 168.26939999999996C 21.323650568181815 168.26939999999996 39.60106534090909 133.75259999999997 60.92471590909091 133.75259999999997C 82.24836647727273 133.75259999999997 100.52578125 153.1683 121.84943181818181 153.1683C 143.17308238636363 153.1683 161.4504971590909 125.12339999999998 182.77414772727272 125.12339999999998C 204.09779829545454 125.12339999999998 222.3752130681818 148.85369999999998 243.69886363636363 148.85369999999998C 265.0225142045455 148.85369999999998 283.2999289772727 99.23579999999998 304.62357954545456 99.23579999999998C 325.94723011363635 99.23579999999998 344.22464488636365 112.1796 365.54829545454544 112.1796C 386.8719460227273 112.1796 405.1493607954545 84.13469999999998 426.4730113636364 84.13469999999998C 447.79666193181816 84.13469999999998 466.07407670454546 103.5504 487.39772727272725 103.5504C 508.72137784090904 103.5504 526.9987926136363 53.932499999999976 548.3224431818181 53.932499999999976C 569.64609375 53.932499999999976 587.9235085227273 66.87629999999999 609.2471590909091 66.87629999999999C 630.570809659091 66.87629999999999 648.8482244318182 38.831399999999974 670.171875 38.831399999999974C 670.171875 38.831399999999974 670.171875 38.831399999999974 670.171875 215.73 L 0 215.73z" pathFrom="M 0 215.73 L 0 215.73 L 60.92471590909091 215.73 L 121.84943181818181 215.73 L 182.77414772727272 215.73 L 243.69886363636363 215.73 L 304.62357954545456 215.73 L 365.54829545454544 215.73 L 426.4730113636364 215.73 L 487.39772727272725 215.73 L 548.3224431818181 215.73 L 609.2471590909091 215.73 L 670.171875 215.73z"></path><path d="M 0 168.26939999999996C 21.323650568181815 168.26939999999996 39.60106534090909 133.75259999999997 60.92471590909091 133.75259999999997C 82.24836647727273 133.75259999999997 100.52578125 153.1683 121.84943181818181 153.1683C 143.17308238636363 153.1683 161.4504971590909 125.12339999999998 182.77414772727272 125.12339999999998C 204.09779829545454 125.12339999999998 222.3752130681818 148.85369999999998 243.69886363636363 148.85369999999998C 265.0225142045455 148.85369999999998 283.2999289772727 99.23579999999998 304.62357954545456 99.23579999999998C 325.94723011363635 99.23579999999998 344.22464488636365 112.1796 365.54829545454544 112.1796C 386.8719460227273 112.1796 405.1493607954545 84.13469999999998 426.4730113636364 84.13469999999998C 447.79666193181816 84.13469999999998 466.07407670454546 103.5504 487.39772727272725 103.5504C 508.72137784090904 103.5504 526.9987926136363 53.932499999999976 548.3224431818181 53.932499999999976C 569.64609375 53.932499999999976 587.9235085227273 66.87629999999999 609.2471590909091 66.87629999999999C 630.570809659091 66.87629999999999 648.8482244318182 38.831399999999974 670.171875 38.831399999999974" fill="none" fill-opacity="1" stroke="#a78bfa" stroke-opacity="1" stroke-linecap="butt" stroke-width="3" class="apexcharts-area" index="0" clip-path="url(#gridRectMask6lhvq5cs)" pathTo="M 0 168.26939999999996C 21.323650568181815 168.26939999999996 39.60106534090909 133.75259999999997 60.92471590909091 133.75259999999997C 82.24836647727273 133.75259999999997 100.52578125 153.1683 121.84943181818181 153.1683C 143.17308238636363 153.1683 161.4504971590909 125.12339999999998 182.77414772727272 125.12339999999998C 204.09779829545454 125.12339999999998 222.3752130681818 148.85369999999998 243.69886363636363 148.85369999999998C 265.0225142045455 148.85369999999998 283.2999289772727 99.23579999999998 304.62357954545456 99.23579999999998C 325.94723011363635 99.23579999999998 344.22464488636365 112.1796 365.54829545454544 112.1796C 386.8719460227273 112.1796 405.1493607954545 84.13469999999998 426.4730113636364 84.13469999999998C 447.79666193181816 84.13469999999998 466.07407670454546 103.5504 487.39772727272725 103.5504C 508.72137784090904 103.5504 526.9987926136363 53.932499999999976 548.3224431818181 53.932499999999976C 569.64609375 53.932499999999976 587.9235085227273 66.87629999999999 609.2471590909091 66.87629999999999C 630.570809659091 66.87629999999999 648.8482244318182 38.831399999999974 670.171875 38.831399999999974" pathFrom="M 0 215.73 L 0 215.73 L 60.92471590909091 215.73 L 121.84943181818181 215.73 L 182.77414772727272 215.73 L 243.69886363636363 215.73 L 304.62357954545456 215.73 L 365.54829545454544 215.73 L 426.4730113636364 215.73 L 487.39772727272725 215.73 L 548.3224431818181 215.73 L 609.2471590909091 215.73 L 670.171875 215.73" fill-rule="evenodd"></path><g class="apexcharts-series-markers-wrap apexcharts-hidden-element-shown" data:realIndex="0"><g class="apexcharts-series-markers"><path d="M0,0" fill="#a78bfa" fill-opacity="1" stroke="#fff" stroke-opacity="0.9" stroke-linecap="butt" stroke-width="2" stroke-dasharray="0" cx="0" cy="0" shape="circle" class="apexcharts-marker wz2vqxu96 no-pointer-events" default-marker-size="0"></path></g></g></g><g class="apexcharts-series" zIndex="1" seriesName="Expenses" data:longestSeries="true" rel="2" data:realIndex="1"><path d="M 0 198.47159999999997C 21.323650568181815 198.47159999999997 39.60106534090909 189.8424 60.92471590909091 189.8424C 82.24836647727273 189.8424 100.52578125 196.31429999999997 121.84943181818181 196.31429999999997C 143.17308238636363 196.31429999999997 161.4504971590909 185.52779999999998 182.77414772727272 185.52779999999998C 204.09779829545454 185.52779999999998 222.3752130681818 191.99969999999996 243.69886363636363 191.99969999999996C 265.0225142045455 191.99969999999996 283.2999289772727 176.8986 304.62357954545456 176.8986C 325.94723011363635 176.8986 344.22464488636365 183.3705 365.54829545454544 183.3705C 386.8719460227273 183.3705 405.1493607954545 168.26939999999996 426.4730113636364 168.26939999999996C 447.79666193181816 168.26939999999996 466.07407670454546 174.74129999999997 487.39772727272725 174.74129999999997C 508.72137784090904 174.74129999999997 526.9987926136363 155.32559999999998 548.3224431818181 155.32559999999998C 569.64609375 155.32559999999998 587.9235085227273 168.26939999999996 609.2471590909091 168.26939999999996C 630.570809659091 168.26939999999996 648.8482244318182 148.85369999999998 670.171875 148.85369999999998C 670.171875 148.85369999999998 670.171875 148.85369999999998 670.171875 215.73 L 0 215.73z" fill="url(#SvgjsGradient2)" fill-opacity="1" stroke="none" stroke-opacity="1" stroke-linecap="butt" stroke-width="0" stroke-dasharray="0" class="apexcharts-area" index="1" clip-path="url(#gridRectMask6lhvq5cs)" pathTo="M 0 198.47159999999997C 21.323650568181815 198.47159999999997 39.60106534090909 189.8424 60.92471590909091 189.8424C 82.24836647727273 189.8424 100.52578125 196.31429999999997 121.84943181818181 196.31429999999997C 143.17308238636363 196.31429999999997 161.4504971590909 185.52779999999998 182.77414772727272 185.52779999999998C 204.09779829545454 185.52779999999998 222.3752130681818 191.99969999999996 243.69886363636363 191.99969999999996C 265.0225142045455 191.99969999999996 283.2999289772727 176.8986 304.62357954545456 176.8986C 325.94723011363635 176.8986 344.22464488636365 183.3705 365.54829545454544 183.3705C 386.8719460227273 183.3705 405.1493607954545 168.26939999999996 426.4730113636364 168.26939999999996C 447.79666193181816 168.26939999999996 466.07407670454546 174.74129999999997 487.39772727272725 174.74129999999997C 508.72137784090904 174.74129999999997 526.9987926136363 155.32559999999998 548.3224431818181 155.32559999999998C 569.64609375 155.32559999999998 587.9235085227273 168.26939999999996 609.2471590909091 168.26939999999996C 630.570809659091 168.26939999999996 648.8482244318182 148.85369999999998 670.171875 148.85369999999998C 670.171875 148.85369999999998 670.171875 148.85369999999998 670.171875 215.73 L 0 215.73z" pathFrom="M 0 215.73 L 0 215.73 L 60.92471590909091 215.73 L 121.84943181818181 215.73 L 182.77414772727272 215.73 L 243.69886363636363 215.73 L 304.62357954545456 215.73 L 365.54829545454544 215.73 L 426.4730113636364 215.73 L 487.39772727272725 215.73 L 548.3224431818181 215.73 L 609.2471590909091 215.73 L 670.171875 215.73z"></path><path d="M 0 198.47159999999997C 21.323650568181815 198.47159999999997 39.60106534090909 189.8424 60.92471590909091 189.8424C 82.24836647727273 189.8424 100.52578125 196.31429999999997 121.84943181818181 196.31429999999997C 143.17308238636363 196.31429999999997 161.4504971590909 185.52779999999998 182.77414772727272 185.52779999999998C 204.09779829545454 185.52779999999998 222.3752130681818 191.99969999999996 243.69886363636363 191.99969999999996C 265.0225142045455 191.99969999999996 283.2999289772727 176.8986 304.62357954545456 176.8986C 325.94723011363635 176.8986 344.22464488636365 183.3705 365.54829545454544 183.3705C 386.8719460227273 183.3705 405.1493607954545 168.26939999999996 426.4730113636364 168.26939999999996C 447.79666193181816 168.26939999999996 466.07407670454546 174.74129999999997 487.39772727272725 174.74129999999997C 508.72137784090904 174.74129999999997 526.9987926136363 155.32559999999998 548.3224431818181 155.32559999999998C 569.64609375 155.32559999999998 587.9235085227273 168.26939999999996 609.2471590909091 168.26939999999996C 630.570809659091 168.26939999999996 648.8482244318182 148.85369999999998 670.171875 148.85369999999998" fill="none" fill-opacity="1" stroke="#fb7185" stroke-opacity="1" stroke-linecap="butt" stroke-width="3" class="apexcharts-area" index="1" clip-path="url(#gridRectMask6lhvq5cs)" pathTo="M 0 198.47159999999997C 21.323650568181815 198.47159999999997 39.60106534090909 189.8424 60.92471590909091 189.8424C 82.24836647727273 189.8424 100.52578125 196.31429999999997 121.84943181818181 196.31429999999997C 143.17308238636363 196.31429999999997 161.4504971590909 185.52779999999998 182.77414772727272 185.52779999999998C 204.09779829545454 185.52779999999998 222.3752130681818 191.99969999999996 243.69886363636363 191.99969999999996C 265.0225142045455 191.99969999999996 283.2999289772727 176.8986 304.62357954545456 176.8986C 325.94723011363635 176.8986 344.22464488636365 183.3705 365.54829545454544 183.3705C 386.8719460227273 183.3705 405.1493607954545 168.26939999999996 426.4730113636364 168.26939999999996C 447.79666193181816 168.26939999999996 466.07407670454546 174.74129999999997 487.39772727272725 174.74129999999997C 508.72137784090904 174.74129999999997 526.9987926136363 155.32559999999998 548.3224431818181 155.32559999999998C 569.64609375 155.32559999999998 587.9235085227273 168.26939999999996 609.2471590909091 168.26939999999996C 630.570809659091 168.26939999999996 648.8482244318182 148.85369999999998 670.171875 148.85369999999998" pathFrom="M 0 215.73 L 0 215.73 L 60.92471590909091 215.73 L 121.84943181818181 215.73 L 182.77414772727272 215.73 L 243.69886363636363 215.73 L 304.62357954545456 215.73 L 365.54829545454544 215.73 L 426.4730113636364 215.73 L 487.39772727272725 215.73 L 548.3224431818181 215.73 L 609.2471590909091 215.73 L 670.171875 215.73" fill-rule="evenodd"></path><g class="apexcharts-series-markers-wrap apexcharts-hidden-element-shown" data:realIndex="1"><g class="apexcharts-series-markers"><path d="M0,0" fill="#fb7185" fill-opacity="1" stroke="#fff" stroke-opacity="0.9" stroke-linecap="butt" stroke-width="2" stroke-dasharray="0" cx="0" cy="0" shape="circle" class="apexcharts-marker wgl4k2wmh no-pointer-events" default-marker-size="0"></path></g></g></g><g class="apexcharts-datalabels" data:realIndex="0"></g><g class="apexcharts-datalabels" data:realIndex="1"></g></g><line x1="0" y1="0" x2="670.171875" y2="0" stroke="#b6b6b6" stroke-dasharray="0" stroke-width="1" stroke-linecap="butt" class="apexcharts-ycrosshairs"></line><line x1="0" y1="0" x2="670.171875" y2="0" stroke="#b6b6b6" stroke-dasharray="0" stroke-width="0" stroke-linecap="butt" class="apexcharts-ycrosshairs-hidden"></line><g class="apexcharts-xaxis" transform="translate(0, 0)"><g class="apexcharts-xaxis-texts-g" transform="translate(0, -4)"><text x="0" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Jan</tspan><title>Jan</title></text><text x="60.92471590909091" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Feb</tspan><title>Feb</title></text><text x="121.8494318181818" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Mar</tspan><title>Mar</title></text><text x="182.7741477272727" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Apr</tspan><title>Apr</title></text><text x="243.69886363636363" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>May</tspan><title>May</title></text><text x="304.62357954545456" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Jun</tspan><title>Jun</title></text><text x="365.54829545454544" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Jul</tspan><title>Jul</title></text><text x="426.4730113636363" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Aug</tspan><title>Aug</title></text><text x="487.3977272727272" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Sep</tspan><title>Sep</title></text><text x="548.322443181818" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Oct</tspan><title>Oct</title></text><text x="609.2471590909089" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Nov</tspan><title>Nov</title></text><text x="670.1718749999998" y="243.73" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="400" fill="#9d94bd" class="apexcharts-text apexcharts-xaxis-label " style="font-family: inherit;"><tspan>Dec</tspan><title>Dec</title></text></g></g><g class="apexcharts-yaxis-annotations apexcharts-hidden-element-shown"></g><g class="apexcharts-xaxis-annotations apexcharts-hidden-element-shown"></g><g class="apexcharts-point-annotations apexcharts-hidden-element-shown"></g></g></svg><div class="apexcharts-legend apexcharts-align-right apx-legend-position-top" style="right: 0px; position: absolute; left: 0px; top: 4px; max-height: 155px;"><div role="button" tabindex="0" aria-label="Revenue, visible. Press Enter or Space to toggle." aria-pressed="false" class="apexcharts-legend-series" rel="1" seriesname="Revenue" data:collapsed="false" style="margin: 4px 12px;"><span class="apexcharts-legend-marker" rel="1" data:collapsed="false" style="height: 16px; width: 16px; left: 0px; top: 0px;"><svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><path d="M 0, 0
           m -7, 0
           a 7,7 0 1,0 14,0
           a 7,7 0 1,0 -14,0" fill="#a78bfa" fill-opacity="1" stroke="#fff" stroke-opacity="0.9" stroke-linecap="butt" stroke-width="1" stroke-dasharray="0" cx="0" cy="0" shape="circle" class="apexcharts-legend-marker apexcharts-marker apexcharts-marker-circle" style="transform: translate(50%, 50%);"></path></svg></span><span class="apexcharts-legend-text" rel="1" i="0" data:default-text="Revenue" data:collapsed="false" style="color: rgb(55, 61, 63); font-size: 13px; font-weight: 400; font-family: inherit;">Revenue</span></div><div role="button" tabindex="0" aria-label="Expenses, visible. Press Enter or Space to toggle." aria-pressed="false" class="apexcharts-legend-series" rel="2" seriesname="Expenses" data:collapsed="false" style="margin: 4px 12px;"><span class="apexcharts-legend-marker" rel="2" data:collapsed="false" style="height: 16px; width: 16px; left: 0px; top: 0px;"><svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><path d="M 0, 0
           m -7, 0
           a 7,7 0 1,0 14,0
           a 7,7 0 1,0 -14,0" fill="#fb7185" fill-opacity="1" stroke="#fff" stroke-opacity="0.9" stroke-linecap="butt" stroke-width="1" stroke-dasharray="0" cx="0" cy="0" shape="circle" class="apexcharts-legend-marker apexcharts-marker apexcharts-marker-circle" style="transform: translate(50%, 50%);"></path></svg></span><span class="apexcharts-legend-text" rel="2" i="1" data:default-text="Expenses" data:collapsed="false" style="color: rgb(55, 61, 63); font-size: 13px; font-weight: 400; font-family: inherit;">Expenses</span></div></div><div class="apexcharts-sr-status" role="status" aria-live="polite" aria-atomic="true"></div><div class="apexcharts-tooltip apexcharts-theme-light" role="tooltip" aria-live="polite" aria-atomic="true" aria-hidden="true"><div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;"></div><div class="apexcharts-tooltip-series-group apexcharts-tooltip-series-group-0" style="order: 1;"><span class="apexcharts-tooltip-marker" shape="circle" style="color: rgb(167, 139, 250);"><svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="6" cy="6" r="5" fill="currentColor"></circle></svg></span><div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;"><div class="apexcharts-tooltip-y-group"><span class="apexcharts-tooltip-text-y-label"></span><span class="apexcharts-tooltip-text-y-value"></span></div><div class="apexcharts-tooltip-goals-group"><span class="apexcharts-tooltip-text-goals-label"></span><span class="apexcharts-tooltip-text-goals-value"></span></div><div class="apexcharts-tooltip-z-group"><span class="apexcharts-tooltip-text-z-label"></span><span class="apexcharts-tooltip-text-z-value"></span></div></div></div><div class="apexcharts-tooltip-series-group apexcharts-tooltip-series-group-1" style="order: 2;"><span class="apexcharts-tooltip-marker" shape="circle" style="color: rgb(251, 113, 133);"><svg viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="6" cy="6" r="5" fill="currentColor"></circle></svg></span><div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;"><div class="apexcharts-tooltip-y-group"><span class="apexcharts-tooltip-text-y-label"></span><span class="apexcharts-tooltip-text-y-value"></span></div><div class="apexcharts-tooltip-goals-group"><span class="apexcharts-tooltip-text-goals-label"></span><span class="apexcharts-tooltip-text-goals-value"></span></div><div class="apexcharts-tooltip-z-group"><span class="apexcharts-tooltip-text-z-label"></span><span class="apexcharts-tooltip-text-z-value"></span></div></div></div></div><div class="apexcharts-xaxistooltip apexcharts-xaxistooltip-bottom apexcharts-theme-light"><div class="apexcharts-xaxistooltip-text" style="font-family: inherit; font-size: 12px;"></div></div><div class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-light"><div class="apexcharts-yaxistooltip-text"></div></div></div></div>
            </div>
        </div>

        <!-- Sales Goal -->
        <div class="card dash-goal-card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Sales Goal</h5>
                    <span class="dash-card-subtitle">Monthly target progress</span>
                </div>
            </div>
            <div class="card-body">
                <div id="goalChart" style="min-height: 240px;"><div id="apexchartsnj0wxeoe" class="apexcharts-canvas apexchartsnj0wxeoe apexcharts-theme-light" style="width: 290px; height: 240px;"><svg xmlns="http://www.w3.org/2000/svg" class="apexcharts-svg" xmlns:data="ApexChartsNS" transform="translate(0, 0)" width="290" height="240" role="application" aria-label="radialBar chart with 1 data series" tabindex="0"><foreignObject x="0" y="0" width="290" height="240"><style type="text/css">.apexcharts-flip-y {
                                        transform: scaleY(-1) translateY(-100%);
                                        transform-origin: top;
                                        transform-box: fill-box;
                                    }
                                    .apexcharts-flip-x {
                                        transform: scaleX(-1);
                                        transform-origin: center;
                                        transform-box: fill-box;
                                    }
                                    .apexcharts-legend {
                                        display: flex;
                                        overflow: auto;
                                        padding: 0 10px;
                                    }
                                    .apexcharts-legend.apexcharts-legend-group-horizontal {
                                        flex-direction: column;
                                    }
                                    .apexcharts-legend-group {
                                        display: flex;
                                    }
                                    .apexcharts-legend-group-vertical {
                                        flex-direction: column-reverse;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom, .apexcharts-legend.apx-legend-position-top {
                                        flex-wrap: wrap
                                    }
                                    .apexcharts-legend.apx-legend-position-right, .apexcharts-legend.apx-legend-position-left {
                                        flex-direction: column;
                                        bottom: 0;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-left, .apexcharts-legend.apx-legend-position-top.apexcharts-align-left, .apexcharts-legend.apx-legend-position-right, .apexcharts-legend.apx-legend-position-left {
                                        justify-content: flex-start;
                                        align-items: flex-start;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-center, .apexcharts-legend.apx-legend-position-top.apexcharts-align-center {
                                        justify-content: center;
                                        align-items: center;
                                    }
                                    .apexcharts-legend.apx-legend-position-bottom.apexcharts-align-right, .apexcharts-legend.apx-legend-position-top.apexcharts-align-right {
                                        justify-content: flex-end;
                                        align-items: flex-end;
                                    }
                                    .apexcharts-legend-series {
                                        cursor: pointer;
                                        line-height: normal;
                                        display: flex;
                                        align-items: center;
                                    }
                                    .apexcharts-legend-text {
                                        position: relative;
                                        font-size: 14px;
                                    }
                                    .apexcharts-legend-text *, .apexcharts-legend-marker * {
                                        pointer-events: none;
                                    }
                                    .apexcharts-legend-marker {
                                        position: relative;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        cursor: pointer;
                                        margin-right: 1px;
                                    }

                                    .apexcharts-legend-series.apexcharts-no-click {
                                        cursor: auto;
                                    }
                                    .apexcharts-legend .apexcharts-hidden-zero-series, .apexcharts-legend .apexcharts-hidden-null-series {
                                        display: none !important;
                                    }
                                    .apexcharts-inactive-legend {
                                        opacity: 0.45;
                                    } </style></foreignObject><g class="apexcharts-inner apexcharts-graphical" transform="translate(25, 1)"><defs><clipPath id="gridRectMasknj0wxeoe"><rect x="-3" y="-3" width="246" height="244" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="gridRectBarMasknj0wxeoe"><rect x="-3" y="-3" width="246" height="244" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="gridRectMarkerMasknj0wxeoe"><rect x="-3" y="0" width="246" height="238" rx="0" ry="0" opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0" fill="#fff"></rect></clipPath><clipPath id="forecastMasknj0wxeoe"></clipPath><clipPath id="nonForecastMasknj0wxeoe"></clipPath><linearGradient id="SvgjsGradient3" x1="0" y1="1" x2="1" y2="1"><stop offset="0" stop-color="rgba(44,35,80,1)" stop-opacity="1"></stop><stop offset="1" stop-color="rgba(251,113,133,1)" stop-opacity="1"></stop><stop offset="1" stop-color="rgba(251,113,133,1)" stop-opacity="1"></stop></linearGradient><linearGradient id="SvgjsGradient4" x1="0" y1="1" x2="1" y2="1"><stop offset="0" stop-color="rgba(167,139,250,1)" stop-opacity="1"></stop><stop offset="1" stop-color="rgba(251,113,133,1)" stop-opacity="1"></stop><stop offset="1" stop-color="rgba(251,113,133,1)" stop-opacity="1"></stop></linearGradient></defs><g class="apexcharts-radialbar"><g><g class="apexcharts-tracks"><g class="apexcharts-radialbar-track apexcharts-track" rel="1"><path fill="none" fill-opacity="1" stroke="rgba(44,35,80,0.85)" stroke-opacity="1" stroke-linecap="round" stroke-width="13.575609756097563" stroke-dasharray="0" class="apexcharts-radialbar-area" id="apexcharts-radialbarTrack-0" data:pathOrig="M 34.737792229618094 150.0329057362395 A 90.73414634146341 90.73414634146341 0 1 1 205.2622077703819 150.0329057362395 " d="M 34.737792229618094 150.0329057362395 A 90.73414634146341 90.73414634146341 0 1 1 205.2622077703819 150.0329057362395 "></path></g></g><g><g class="apexcharts-series apexcharts-radial-series" seriesName="ofxmonthlyxgoal" rel="1" data:realIndex="0"><path fill="none" fill-opacity="0.85" stroke="url(#SvgjsGradient4)" stroke-opacity="1" stroke-linecap="round" stroke-width="13.575609756097563" stroke-dasharray="0" class="apexcharts-radialbar-area apexcharts-radialbar-slice-0" data:angle="172" data:value="78" data:cx="83.09509790472478" data:cy="36.11023275667708" index="0" j="0" data:pathOrig="M 34.737792229618094 150.0329057362395 A 90.73414634146341 90.73414634146341 0 0 1 200.11349610230474 76.40289851902946 " d="M 34.737792229618094 150.0329057362395 A 90.73414634146341 90.73414634146341 0 0 1 200.11349610230474 76.40289851902946 "></path></g><circle r="78.94634146341463" cx="120" cy="119" class="apexcharts-radialbar-hollow" fill="transparent"></circle><g class="apexcharts-datalabels-group" transform="translate(0, 0) scale(1)" style="opacity: 1;"><text x="120" y="143" text-anchor="middle" dominant-baseline="auto" font-size="12px" font-family="inherit" font-weight="600" fill="#9d94bd" class="apexcharts-text apexcharts-datalabel-label" style="font-family: inherit;">of monthly goal</text><text x="120" y="123" text-anchor="middle" dominant-baseline="auto" font-size="32px" font-family="inherit" font-weight="700" fill="#f4f1ff" class="apexcharts-text apexcharts-datalabel-value" style="font-family: inherit;">78%</text></g></g></g></g><line x1="0" y1="0" x2="240" y2="0" stroke="#b6b6b6" stroke-dasharray="0" stroke-width="1" stroke-linecap="butt" class="apexcharts-ycrosshairs"></line><line x1="0" y1="0" x2="240" y2="0" stroke="#b6b6b6" stroke-dasharray="0" stroke-width="0" stroke-linecap="butt" class="apexcharts-ycrosshairs-hidden"></line></g></svg><div class="apexcharts-legend"></div><div class="apexcharts-sr-status" role="status" aria-live="polite" aria-atomic="true"></div></div></div>
                <div class="dash-goal-stats">
                    <div class="dash-goal-stat">
                        <span class="dash-goal-stat-label">Target</span>
                        <span class="dash-goal-stat-value">$50,000</span>
                    </div>
                    <div class="dash-goal-divider"></div>
                    <div class="dash-goal-stat">
                        <span class="dash-goal-stat-label">Earned</span>
                        <span class="dash-goal-stat-value">$39,200</span>
                    </div>
                </div>
                <p class="dash-goal-hint"><i class="ph ph-lightning"></i> You're $10.8k away from your goal — keep pushing!</p>
            </div>
        </div>
    </div>
    <div class="dash-widgets">
        <!-- Traffic Sources -->
        <div class="card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Traffic Sources</h5>
                    <span class="dash-card-subtitle">Where your visitors come from</span>
                </div>
            </div>
            <div class="card-body">
                <div class="dash-source">
                    <span class="dash-source-icon accent"><i class="ph ph-globe"></i></span>
                    <div class="dash-source-info">
                        <div class="dash-source-row">
                            <span class="dash-source-name">Direct</span>
                            <span class="dash-source-value">42%</span>
                        </div>
                        <div class="dash-source-bar"><span class="accent" style="width: 42%"></span></div>
                    </div>
                </div>
                <div class="dash-source">
                    <span class="dash-source-icon success"><i class="ph ph-magnifying-glass"></i></span>
                    <div class="dash-source-info">
                        <div class="dash-source-row">
                            <span class="dash-source-name">Organic Search</span>
                            <span class="dash-source-value">28%</span>
                        </div>
                        <div class="dash-source-bar"><span class="success" style="width: 28%"></span></div>
                    </div>
                </div>
                <div class="dash-source">
                    <span class="dash-source-icon info"><i class="ph ph-share-network"></i></span>
                    <div class="dash-source-info">
                        <div class="dash-source-row">
                            <span class="dash-source-name">Social Media</span>
                            <span class="dash-source-value">16%</span>
                        </div>
                        <div class="dash-source-bar"><span class="info" style="width: 16%"></span></div>
                    </div>
                </div>
                <div class="dash-source">
                    <span class="dash-source-icon warning"><i class="ph ph-link"></i></span>
                    <div class="dash-source-info">
                        <div class="dash-source-row">
                            <span class="dash-source-name">Referrals</span>
                            <span class="dash-source-value">9%</span>
                        </div>
                        <div class="dash-source-bar"><span class="warning" style="width: 9%"></span></div>
                    </div>
                </div>
                <div class="dash-source">
                    <span class="dash-source-icon danger"><i class="ph ph-envelope"></i></span>
                    <div class="dash-source-info">
                        <div class="dash-source-row">
                            <span class="dash-source-name">Email</span>
                            <span class="dash-source-value">5%</span>
                        </div>
                        <div class="dash-source-bar"><span class="danger" style="width: 5%"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Recent Transactions</h5>
                    <span class="dash-card-subtitle">Latest account movements</span>
                </div>
                <a href="invoice-list.html" class="dash-link">View all <i class="ph ph-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="dash-txn">
                    <span class="dash-txn-icon success"><i class="ph ph-arrow-down-left"></i></span>
                    <div class="dash-txn-info">
                        <span class="dash-txn-name">Stripe Payout</span>
                        <span class="dash-txn-date">Today, 09:41</span>
                    </div>
                    <span class="dash-txn-amount plus">+$2,480.00</span>
                </div>
                <div class="dash-txn">
                    <span class="dash-txn-icon danger"><i class="ph ph-arrow-up-right"></i></span>
                    <div class="dash-txn-info">
                        <span class="dash-txn-name">Figma Subscription</span>
                        <span class="dash-txn-date">Today, 08:12</span>
                    </div>
                    <span class="dash-txn-amount minus">−$45.00</span>
                </div>
                <div class="dash-txn">
                    <span class="dash-txn-icon success"><i class="ph ph-arrow-down-left"></i></span>
                    <div class="dash-txn-info">
                        <span class="dash-txn-name">Invoice #INV-0092</span>
                        <span class="dash-txn-date">Yesterday, 16:30</span>
                    </div>
                    <span class="dash-txn-amount plus">+$890.00</span>
                </div>
                <div class="dash-txn">
                    <span class="dash-txn-icon danger"><i class="ph ph-arrow-up-right"></i></span>
                    <div class="dash-txn-info">
                        <span class="dash-txn-name">AWS Hosting</span>
                        <span class="dash-txn-date">Yesterday, 11:05</span>
                    </div>
                    <span class="dash-txn-amount minus">−$220.40</span>
                </div>
                <div class="dash-txn">
                    <span class="dash-txn-icon success"><i class="ph ph-arrow-down-left"></i></span>
                    <div class="dash-txn-info">
                        <span class="dash-txn-name">App Store Sales</span>
                        <span class="dash-txn-date">Jun 14, 19:22</span>
                    </div>
                    <span class="dash-txn-amount plus">+$1,132.60</span>
                </div>
            </div>
        </div>

        <!-- Tasks Today -->
        <div class="card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Tasks Today</h5>
                    <span class="dash-card-subtitle">2 of 6 completed</span>
                </div>
            </div>
            <div class="card-body">
                <label class="dash-task">
                    <input type="checkbox" checked="">
                    <span class="dash-task-text">Review Q2 sales report</span>
                    <span class="dash-task-time">9:00</span>
                </label>
                <label class="dash-task">
                    <input type="checkbox" checked="">
                    <span class="dash-task-text">Approve new user roles</span>
                    <span class="dash-task-time">10:30</span>
                </label>
                <label class="dash-task">
                    <input type="checkbox">
                    <span class="dash-task-text">Call with design team</span>
                    <span class="dash-task-time">13:00</span>
                </label>
                <label class="dash-task">
                    <input type="checkbox">
                    <span class="dash-task-text">Update pricing page copy</span>
                    <span class="dash-task-time">15:00</span>
                </label>
                <label class="dash-task">
                    <input type="checkbox">
                    <span class="dash-task-text">Prepare invoice batch</span>
                    <span class="dash-task-time">16:30</span>
                </label>
                <label class="dash-task">
                    <input type="checkbox">
                    <span class="dash-task-text">Plan next sprint backlog</span>
                    <span class="dash-task-time">17:00</span>
                </label>
                <a href="apps-todo.html" class="dash-task-add"><i class="ph ph-plus"></i> Add task</a>
            </div>
        </div>
    </div>
    <div class="dash-bottom">
        <!-- Recent Orders -->
        <div class="card dash-orders-card">
            <div class="dash-card-header">
                <div>
                    <h5 class="dash-card-title">Recent Orders</h5>
                    <span class="dash-card-subtitle">Latest transactions from your store</span>
                </div>
                <a href="invoice-list.html" class="dash-link">View all <i class="ph ph-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td><a href="#">#ORD-001</a></td>
                            <td>
                                <div class="table-user">
                                    <img src="assets/img/avatars/avatar-1.webp" alt="" class="table-user-avatar">
                                    <div>
                                        <div class="table-user-name">John Doe</div>
                                        <div class="table-user-email">john@example.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>Premium Plan</td>
                            <td>$299.00</td>
                            <td><span class="badge badge-soft-success">Completed</span></td>
                        </tr>
                        <tr>
                            <td><a href="#">#ORD-002</a></td>
                            <td>
                                <div class="table-user">
                                    <img src="assets/img/avatars/avatar-2.webp" alt="" class="table-user-avatar">
                                    <div>
                                        <div class="table-user-name">Jane Smith</div>
                                        <div class="table-user-email">jane@example.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>Basic Plan</td>
                            <td>$99.00</td>
                            <td><span class="badge badge-soft-warning">Pending</span></td>
                        </tr>
                        <tr>
                            <td><a href="#">#ORD-003</a></td>
                            <td>
                                <div class="table-user">
                                    <img src="assets/img/avatars/avatar-3.webp" alt="" class="table-user-avatar">
                                    <div>
                                        <div class="table-user-name">Mike Johnson</div>
                                        <div class="table-user-email">mike@example.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>Enterprise Plan</td>
                            <td>$999.00</td>
                            <td><span class="badge badge-soft-success">Completed</span></td>
                        </tr>
                        <tr>
                            <td><a href="#">#ORD-004</a></td>
                            <td>
                                <div class="table-user">
                                    <img src="assets/img/avatars/avatar-4.webp" alt="" class="table-user-avatar">
                                    <div>
                                        <div class="table-user-name">Sarah Wilson</div>
                                        <div class="table-user-email">sarah@example.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>Basic Plan</td>
                            <td>$99.00</td>
                            <td><span class="badge badge-soft-danger">Cancelled</span></td>
                        </tr>
                        <tr>
                            <td><a href="#">#ORD-005</a></td>
                            <td>
                                <div class="table-user">
                                    <img src="assets/img/avatars/avatar-5.webp" alt="" class="table-user-avatar">
                                    <div>
                                        <div class="table-user-name">Alex Brown</div>
                                        <div class="table-user-email">alex@example.com</div>
                                    </div>
                                </div>
                            </td>
                            <td>Premium Plan</td>
                            <td>$299.00</td>
                            <td><span class="badge badge-soft-info">Processing</span></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="dash-side">
            <!-- Top Products -->
            <div class="card">
                <div class="dash-card-header">
                    <div>
                        <h5 class="dash-card-title">Top Products</h5>
                        <span class="dash-card-subtitle">Best sellers this month</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="dash-product">
                        <img src="assets/img/products/product-thumb-1.webp" alt="Product" class="dash-product-img">
                        <div class="dash-product-info">
                            <div class="dash-product-row">
                                <span class="dash-product-name">Wireless Headset</span>
                                <span class="dash-product-sales">1,241 sold</span>
                            </div>
                            <div class="dash-product-bar"><span style="width: 88%"></span></div>
                        </div>
                    </div>
                    <div class="dash-product">
                        <img src="assets/img/products/product-thumb-2.webp" alt="Product" class="dash-product-img">
                        <div class="dash-product-info">
                            <div class="dash-product-row">
                                <span class="dash-product-name">Smart Watch Pro</span>
                                <span class="dash-product-sales">986 sold</span>
                            </div>
                            <div class="dash-product-bar"><span style="width: 70%"></span></div>
                        </div>
                    </div>
                    <div class="dash-product">
                        <img src="assets/img/products/product-thumb-3.webp" alt="Product" class="dash-product-img">
                        <div class="dash-product-info">
                            <div class="dash-product-row">
                                <span class="dash-product-name">Studio Camera</span>
                                <span class="dash-product-sales">742 sold</span>
                            </div>
                            <div class="dash-product-bar"><span style="width: 53%"></span></div>
                        </div>
                    </div>
                    <div class="dash-product">
                        <img src="assets/img/products/product-thumb-4.webp" alt="Product" class="dash-product-img">
                        <div class="dash-product-info">
                            <div class="dash-product-row">
                                <span class="dash-product-name">Gaming Keyboard</span>
                                <span class="dash-product-sales">518 sold</span>
                            </div>
                            <div class="dash-product-bar"><span style="width: 37%"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Activity Feed -->
            <div class="card">
                <div class="dash-card-header">
                    <div>
                        <h5 class="dash-card-title">Live Activity</h5>
                        <span class="dash-card-subtitle">What's happening right now</span>
                    </div>
                    <span class="dash-live-dot" title="Live"></span>
                </div>
                <div class="card-body">
                    <div class="dash-feed">
                        <div class="dash-feed-item">
                            <span class="dash-feed-dot success"></span>
                            <div class="dash-feed-body">
                                <div class="dash-feed-title">New order received <a href="#">#ORD-001</a></div>
                                <div class="dash-feed-time">5 minutes ago</div>
                            </div>
                        </div>
                        <div class="dash-feed-item">
                            <span class="dash-feed-dot primary"></span>
                            <div class="dash-feed-body">
                                <div class="dash-feed-title">New user registered <a href="#">John Doe</a></div>
                                <div class="dash-feed-time">15 minutes ago</div>
                            </div>
                        </div>
                        <div class="dash-feed-item">
                            <span class="dash-feed-dot warning"></span>
                            <div class="dash-feed-body">
                                <div class="dash-feed-title">Server CPU usage at 85%</div>
                                <div class="dash-feed-time">1 hour ago</div>
                            </div>
                        </div>
                        <div class="dash-feed-item">
                            <span class="dash-feed-dot info"></span>
                            <div class="dash-feed-body">
                                <div class="dash-feed-title">New comment on <a href="#">blog post</a></div>
                                <div class="dash-feed-time">2 hours ago</div>
                            </div>
                        </div>
                        <div class="dash-feed-item">
                            <span class="dash-feed-dot danger"></span>
                            <div class="dash-feed-body">
                                <div class="dash-feed-title">Order <a href="#">#ORD-004</a> cancelled</div>
                                <div class="dash-feed-time">3 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const styles = getComputedStyle(document.documentElement);
            const accentColor = styles.getPropertyValue('--accent-color').trim();
            const dangerColor = styles.getPropertyValue('--danger-color').trim();
            const successColor = styles.getPropertyValue('--success-color').trim();
            const borderColor = styles.getPropertyValue('--border-color').trim();
            const mutedColor = styles.getPropertyValue('--muted-color').trim();
            const headingColor = styles.getPropertyValue('--heading-color').trim();
            // Datasets for the segmented range toggle
            const ranges = {
                week: {
                    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    revenue: [1150, 980, 1420, 1280, 1690, 2100, 1840],
                    expenses: [620, 540, 760, 700, 820, 980, 890]
                },
                month: {
                    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    revenue: [4200, 5800, 4900, 6200, 5100, 7400, 6800, 8100, 7200, 9500, 8900, 10200],
                    expenses: [2800, 3200, 2900, 3400, 3100, 3800, 3500, 4200, 3900, 4800, 4200, 5100]
                },
                year: {
                    categories: ['2020', '2021', '2022', '2023', '2024', '2025', '2026'],
                    revenue: [28000, 41000, 52000, 61000, 74000, 89000, 96000],
                    expenses: [19000, 26000, 30000, 34000, 41000, 47000, 52000]
                }
            };
            // Revenue Chart
            const revenueOptions = {
                series: [{
                    name: 'Revenue',
                    data: ranges.month.revenue
                },
                    {
                        name: 'Expenses',
                        data: ranges.month.expenses
                    }
                ],
                chart: {
                    type: 'area',
                    height: 310,
                    fontFamily: 'inherit',
                    toolbar: {
                        show: false
                    },
                    zoom: {
                        enabled: false
                    }
                },
                colors: [accentColor, dangerColor],
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 3
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.02,
                        stops: [0, 90, 100]
                    }
                },
                xaxis: {
                    categories: ranges.month.categories,
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: mutedColor,
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: mutedColor,
                            fontSize: '12px'
                        },
                        formatter: function(value) {
                            return '$' + (value / 1000).toFixed(value >= 10000 ? 0 : 1) + 'k';
                        }
                    }
                },
                grid: {
                    borderColor: borderColor,
                    strokeDashArray: 4,
                    xaxis: {
                        lines: {
                            show: false
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '13px',
                    markers: {
                        width: 10,
                        height: 10,
                        radius: 10
                    },
                    itemMargin: {
                        horizontal: 12
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            };
            const revenueChart = new ApexCharts(document.querySelector('#revenueChart'), revenueOptions);
            revenueChart.render();
            // Segmented range toggle
            const segButtons = document.querySelectorAll('.dash-seg-btn');
            segButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    segButtons.forEach(function(b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    const range = ranges[this.dataset.range];
                    revenueChart.updateOptions({
                        xaxis: {
                            categories: range.categories
                        }
                    });
                    revenueChart.updateSeries([{
                        name: 'Revenue',
                        data: range.revenue
                    },
                        {
                            name: 'Expenses',
                            data: range.expenses
                        }
                    ]);
                });
            });
            // Sales Goal Radial Chart
            const goalOptions = {
                series: [78],
                chart: {
                    type: 'radialBar',
                    height: 240,
                    fontFamily: 'inherit',
                    sparkline: {
                        enabled: true
                    }
                },
                colors: [accentColor],
                plotOptions: {
                    radialBar: {
                        startAngle: -110,
                        endAngle: 110,
                        hollow: {
                            size: '68%'
                        },
                        track: {
                            background: borderColor,
                            strokeWidth: '100%'
                        },
                        dataLabels: {
                            name: {
                                show: true,
                                fontSize: '12px',
                                color: mutedColor,
                                offsetY: 24
                            },
                            value: {
                                fontSize: '32px',
                                fontWeight: 700,
                                color: headingColor,
                                offsetY: -12
                            }
                        }
                    }
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shade: 'light',
                        type: 'horizontal',
                        gradientToColors: [dangerColor],
                        stops: [0, 100]
                    }
                },
                stroke: {
                    lineCap: 'round'
                },
                labels: ['of monthly goal']
            };
            const goalChart = new ApexCharts(document.querySelector('#goalChart'), goalOptions);
            goalChart.render();
            // Update charts on theme change
            document.addEventListener('themeChanged', function() {
                const s = getComputedStyle(document.documentElement);
                const newBorder = s.getPropertyValue('--border-color').trim();
                const newMuted = s.getPropertyValue('--muted-color').trim();
                const newHeading = s.getPropertyValue('--heading-color').trim();
                const newAccent = s.getPropertyValue('--accent-color').trim();
                const newDanger = s.getPropertyValue('--danger-color').trim();
                revenueChart.updateOptions({
                    colors: [newAccent, newDanger],
                    grid: {
                        borderColor: newBorder
                    },
                    xaxis: {
                        labels: {
                            style: {
                                colors: newMuted
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: newMuted
                            }
                        }
                    }
                });
                goalChart.updateOptions({
                    colors: [newAccent],
                    plotOptions: {
                        radialBar: {
                            track: {
                                background: newBorder
                            },
                            dataLabels: {
                                name: {
                                    color: newMuted
                                },
                                value: {
                                    color: newHeading
                                }
                            }
                        }
                    },
                    fill: {
                        gradient: {
                            gradientToColors: [newDanger]
                        }
                    }
                });
            });
        });
    </script>
<?= $this->endSection() ?>