/**
 * The traffic page (app/Views/admin_traffic.php): fills each card from admin/traffic/data/*, which forwards to the
 * API's v1/client/traffic/* for the session business only. Cards load apart, so a slow or failed one does not hold
 * up the rest. The period is kept in the address bar (?from=&to=), so a view can be linked to.
 * Dates are the business's local days, in the timezone the API answers with; visit times come in UTC and are shown
 * in it. The words come from the page (#tr-text), in its language.
 */
(function () {
    'use strict';

    const root = document.getElementById('traffic');
    if (!root) {
        return;
    }

    const TEXT = JSON.parse(document.getElementById('tr-text').textContent || '{}');
    const URL_BASE = root.dataset.url;
    const LOCALE = root.dataset.locale === 'th' ? 'th-TH' : 'en-GB';
    const PAGE_SIZE = 20;
    const COLOR = '#c97232';
    const DEVICE_ICONS = { desktop: 'bi-display', mobile: 'bi-phone', tablet: 'bi-tablet', bot: 'bi-robot' };

    const state = {
        from: root.dataset.from || '',
        to: root.dataset.to || '',
        timezone: 'Asia/Bangkok',
        currency: null,
        measure: 'visits',
        sourceMode: 'source',
        visitsStart: 0,
        visitsTotal: 0,
        series: [],
        previousSeries: [],
        loadId: 0
    };

    const numberFormat = new Intl.NumberFormat(LOCALE);
    let countryNames = null;
    try {
        countryNames = new Intl.DisplayNames([LOCALE], { type: 'region' });
    } catch (e) {
        countryNames = null;
    }

    // ---- helpers ------------------------------------------------------------------------------------------------

    /** A word of the page: t('column.page'), with {0}, {1}… filled from the rest of the arguments */
    function t(path) {
        const args = Array.prototype.slice.call(arguments, 1);
        const value = path.split('.').reduce(function (node, key) {
            return node && typeof node === 'object' ? node[key] : undefined;
        }, TEXT);
        return String(value === undefined ? path : value).replace(/\{(\d+)\}/g, function (all, i) {
            return args[i] !== undefined ? args[i] : all;
        });
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function num(value) {
        return numberFormat.format(Math.round(value || 0));
    }

    function pct(ratio, digits) {
        return (Math.round((ratio || 0) * 1000) / 10).toFixed(digits === undefined ? 1 : digits) + '%';
    }

    function money(value) {
        const amount = Math.round((value || 0) * 100) / 100;
        if (!state.currency) {
            return numberFormat.format(amount);
        }
        try {
            return new Intl.NumberFormat(LOCALE, { style: 'currency', currency: state.currency, maximumFractionDigits: 0 }).format(amount);
        } catch (e) {
            return state.currency + ' ' + numberFormat.format(amount);
        }
    }

    function duration(seconds) {
        seconds = Math.max(0, Math.round(seconds || 0));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) {
            return h + 'h ' + String(m).padStart(2, '0') + 'm';
        }
        if (m > 0) {
            return m + 'm ' + String(s).padStart(2, '0') + 's';
        }
        return s + 's';
    }

    function country(code) {
        if (!code) {
            return t('unknown');
        }
        try {
            return (countryNames && countryNames.of(code)) || code;
        } catch (e) {
            return code;
        }
    }

    /** The country's flag as the two regional-indicator letters, which the system's emoji font draws */
    function flag(code) {
        if (!code || !/^[A-Za-z]{2}$/.test(code)) {
            return '<i class="bi bi-question-circle tr-flag text-muted"></i>';
        }
        const letters = code.toUpperCase().split('').map(function (c) {
            return String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65);
        }).join('');
        return '<span class="tr-flag" aria-hidden="true">' + letters + '</span>';
    }

    function device(type) {
        return t('device.' + (type || 'unknown'));
    }

    /** A UTC time from the API ("2026-11-09 14:02:00") in the business's timezone */
    function localTime(utc) {
        const date = new Date(String(utc).replace(' ', 'T') + 'Z');
        const day = new Intl.DateTimeFormat(LOCALE, { timeZone: state.timezone, day: 'numeric', month: 'short' }).format(date);
        const time = new Intl.DateTimeFormat(LOCALE, { timeZone: state.timezone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date);
        return { day: day, time: time };
    }

    function dayFormat(ymd, options) {
        const [y, m, d] = ymd.split('-').map(Number);
        return new Intl.DateTimeFormat(LOCALE, Object.assign({ timeZone: 'UTC' }, options)).format(new Date(Date.UTC(y, m - 1, d)));
    }

    function shortDay(ymd) {
        return dayFormat(ymd, { day: 'numeric', month: 'short' });
    }

    function longDay(ymd) {
        return dayFormat(ymd, { day: 'numeric', month: 'short', year: 'numeric' });
    }

    /** YYYY-MM-DD of today in the business's timezone, moved by some days */
    function today(offsetDays) {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: state.timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        const [y, m, d] = parts.split('-').map(Number);
        return new Date(Date.UTC(y, m - 1, d + (offsetDays || 0))).toISOString().slice(0, 10);
    }

    function cssVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }

    function isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    function message(text, icon) {
        return '<div class="tr-empty"><i class="bi ' + (icon || 'bi-moon-stars') + '"></i><span>' + esc(text) + '</span></div>';
    }

    function loading(el) {
        if (el) {
            el.innerHTML = '<div class="tr-loading"><span class="spinner-border spinner-border-sm"></span></div>';
        }
    }

    function failed(el, error) {
        if (el) {
            el.innerHTML = message(error && error.message ? error.message : t('load-failed'), 'bi-exclamation-triangle');
        }
    }

    /** POST to admin/traffic/data/{endpoint}; resolves with the API's data, rejects with its message */
    function post(endpoint, body) {
        // the page's period, unless the request names its own; the business is the session's, set by the server
        const payload = {};
        if (state.from) {
            payload.from = state.from;
        }
        if (state.to) {
            payload.to = state.to;
        }
        Object.assign(payload, body || {});
        return fetch(URL_BASE + endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (json) {
                if (!response.ok || json.status !== 'success') {
                    throw new Error(json.message || t('load-failed'));
                }
                return json.data || {};
            });
        });
    }

    function syncAddress() {
        const params = new URLSearchParams();
        if (state.from) {
            params.set('from', state.from);
        }
        if (state.to) {
            params.set('to', state.to);
        }
        const query = params.toString();
        history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    }

    // ---- headline numbers and the chart -------------------------------------------------------------------------

    /** kind: count (more is better), up (a rate, higher is better) or down (a rate, lower is better) */
    function trend(el, now, before, kind) {
        el.classList.remove('positive', 'negative');
        if (kind !== 'count') {
            if (before === 0 && now === 0) {
                el.innerHTML = '&nbsp;';
                return;
            }
            const points = Math.round((now - before) * 1000) / 10;
            el.textContent = t('pts', (points > 0 ? '+' : points < 0 ? '−' : '±') + Math.abs(points).toFixed(1));
            if (points !== 0) {
                el.classList.add((points > 0) === (kind === 'up') ? 'positive' : 'negative');
            }
            return;
        }
        if (!before) {
            el.textContent = now > 0 ? t('new') : '–';
            return;
        }
        const change = Math.round(((now - before) / before) * 1000) / 10;
        el.textContent = (change > 0 ? '+' : change < 0 ? '−' : '±') + Math.abs(change).toFixed(1) + '%';
        if (change !== 0) {
            el.classList.add(change > 0 ? 'positive' : 'negative');
        }
    }

    function renderKpis(data) {
        const totals = data.totals;
        const previous = data.previous;
        const show = {
            visits: [num(totals.visits), 'count'],
            visitors: [num(totals.visitors), 'count'],
            pageviews: [num(totals.pageviews), 'count'],
            avg_visit_s: [duration(totals.avg_visit_s), 'count'],
            orders: [num(totals.orders), 'count'],
            revenue: [money(totals.revenue), 'count'],
            conversion_rate: [pct(totals.conversion_rate, 2), 'up'],
            bounce_rate: [pct(totals.bounce_rate), 'down']
        };
        Object.keys(show).forEach(function (key) {
            const item = root.querySelector('[data-kpi="' + key + '"]');
            item.querySelector('.tr-kpi-value').textContent = show[key][0];
            trend(item.querySelector('.tr-kpi-trend'), totals[key] || 0, previous[key] || 0, show[key][1]);
        });
        document.getElementById('tr-period-note').textContent = t('compared', longDay(data.from), longDay(data.to),
            longDay(previous.from), longDay(previous.to), state.timezone.replace('_', ' '));
    }

    /** A day's value of the chart's measure; conversion is worked out from the day's orders and visits */
    function measureOf(day, measure) {
        if (measure === 'conversion') {
            return day.visits > 0 ? Math.round((day.orders / day.visits) * 10000) / 100 : 0;
        }
        return day[measure] || 0;
    }

    function formatMeasure(value, measure) {
        if (measure === 'revenue') {
            return money(value);
        }
        if (measure === 'conversion') {
            return (Math.round(value * 10) / 10).toFixed(1) + '%';
        }
        return num(value);
    }

    let chart = null;

    function renderChart() {
        const el = document.getElementById('tr-chart');
        const measure = state.measure;
        const current = state.series.map(function (day) {
            return { x: shortDay(day.day), y: measureOf(day, measure) };
        });
        const previous = state.previousSeries.map(function (day, i) {
            return { x: current[i] ? current[i].x : shortDay(day.day), y: measureOf(day, measure) };
        });
        const muted = cssVar('--muted-color', '#5f6e83');
        const options = {
            chart: { type: 'area', height: 280, toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', foreColor: muted, animations: { speed: 300 } },
            series: [
                { name: t('this-period'), data: current },
                { name: t('period-before'), data: previous.slice(0, current.length) }
            ],
            colors: [COLOR, muted],
            stroke: { width: [2.5, 1.5], curve: 'smooth', dashArray: [0, 5] },
            fill: { type: 'solid', opacity: [0.12, 0] },
            dataLabels: { enabled: false },
            markers: { size: current.length <= 31 ? 3 : 0, strokeWidth: 0 },
            grid: { borderColor: cssVar('--border-color', '#dde5f1'), strokeDashArray: 3 },
            legend: { position: 'top', horizontalAlign: 'right' },
            xaxis: { tickAmount: Math.min(current.length, 10), labels: { rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false }, tooltip: { enabled: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: function (v) { return formatMeasure(v, measure); } } },
            tooltip: {
                theme: isDark() ? 'dark' : 'light',
                shared: true,
                x: {
                    formatter: function (value, opts) {
                        const day = state.series[opts.dataPointIndex];
                        const before = state.previousSeries[opts.dataPointIndex];
                        return day && before ? t('vs', longDay(day.day), longDay(before.day)) : (day ? longDay(day.day) : value);
                    }
                },
                y: { formatter: function (v) { return formatMeasure(v, measure); } }
            }
        };
        if (!current.some(function (p) { return p.y > 0; }) && !previous.some(function (p) { return p.y > 0; })) {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            el.innerHTML = message(t('empty.visits'));
            return;
        }
        if (chart) {
            chart.updateOptions(options, true, true);
            return;
        }
        el.innerHTML = '';
        chart = new ApexCharts(el, options);
        chart.render();
    }

    function loadOverview(loadId) {
        loading(document.getElementById('tr-chart'));
        loading(document.getElementById('tr-funnel'));
        if (chart) {
            chart.destroy();
            chart = null;
        }
        return post('overview').then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            state.timezone = data.timezone || state.timezone;
            state.currency = data.currency_code || null;
            state.from = data.from;
            state.to = data.to;
            state.series = data.series || [];
            syncAddress();
            updatePicker();
            renderKpis(data);
            renderFunnel(data.funnel);
            // the period before, day by day, for the dashed line
            return post('overview', { from: data.previous.from, to: data.previous.to }).then(function (before) {
                if (loadId !== state.loadId) {
                    return;
                }
                state.previousSeries = before.series || [];
                renderChart();
            }, function () {
                state.previousSeries = [];
                renderChart();
            });
        }).catch(function (error) {
            if (loadId !== state.loadId) {
                return;
            }
            root.querySelectorAll('.tr-kpi-value').forEach(function (el) {
                el.textContent = '–';
            });
            document.getElementById('tr-period-note').textContent = error.message;
            failed(document.getElementById('tr-chart'), error);
            failed(document.getElementById('tr-funnel'), error);
        });
    }

    function setMeasure(measure) {
        state.measure = measure;
        root.querySelectorAll('[data-tr-measure]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.trMeasure === measure);
        });
    }

    // ---- funnel -------------------------------------------------------------------------------------------------

    function renderFunnel(funnel) {
        const el = document.getElementById('tr-funnel');
        if (!funnel || !funnel.steps || funnel.steps[0].visits === 0) {
            el.innerHTML = message(t('empty.shopping'), 'bi-cart');
            return;
        }
        const steps = funnel.steps;
        const top = steps[0].visits;
        // the step most visitors leave at: the lowest share going on, of steps that someone reached
        let drop = null;
        steps.forEach(function (step, i) {
            if (i > 0 && steps[i - 1].visits > 0 && (drop === null || step.rate_from_previous < steps[drop].rate_from_previous)) {
                drop = i;
            }
        });
        const bars = steps.map(function (step) {
            const width = top > 0 ? Math.max(step.visits > 0 ? 1.5 : 0, (step.visits / top) * 100) : 0;
            const rate = step.rate_from_previous === null ? ''
                : '<span class="tr-funnel-rate' + (step.rate_from_previous < 0.25 ? ' text-danger' : '') + '" title="' + esc(t('funnel.of-before')) + '">' + pct(step.rate_from_previous, 0) + '</span>';
            return '<div class="tr-funnel-step">'
                + '<div class="tr-funnel-label">' + esc(t('funnel.' + step.step)) + '</div>'
                + '<div class="tr-funnel-track"><div class="tr-funnel-bar" style="width:' + width.toFixed(2) + '%"></div></div>'
                + '<div class="tr-funnel-count">' + num(step.visits) + rate + '</div>'
                + '</div>';
        }).join('');
        const dropNote = drop === null ? ''
            : '<div class="tr-note tr-funnel-drop"><i class="bi bi-arrow-down-right me-1"></i>'
                + esc(t('funnel.biggest-drop', t('funnel.' + steps[drop - 1].step), t('funnel.' + steps[drop].step), pct(steps[drop].rate_from_previous, 0))) + '</div>';
        el.innerHTML = '<div class="tr-funnel-wrap"><div class="tr-funnel">' + bars + dropNote + '</div>'
            + '<div class="tr-conversion"><span class="tr-kpi-label">' + esc(t('funnel.conversion')) + '</span>'
            + '<span class="tr-conversion-value">' + pct(funnel.conversion_rate, 2) + '</span>'
            + '<div class="tr-note">' + esc(t('funnel.conversion-note', num(funnel.orders), num(top))) + '</div>'
            + (state.currency ? '<div class="mt-2 fw-semibold">' + esc(money(funnel.revenue)) + '</div>' : '')
            + '</div></div>';
    }

    // ---- breakdowns ---------------------------------------------------------------------------------------------

    function listTable(rows, columns) {
        const max = rows.reduce(function (m, row) {
            return Math.max(m, row[columns.bar] || 0);
        }, 0);
        return '<div class="table-responsive"><table class="table align-middle mb-0 tr-table"><thead><tr>'
            + columns.head.map(function (h, i) {
                return '<th' + (i > 0 ? ' class="text-end"' : '') + '>' + esc(t('column.' + h)) + '</th>';
            }).join('') + '</tr></thead><tbody>'
            + rows.map(function (row) {
                const share = max > 0 ? ((row[columns.bar] || 0) / max) * 100 : 0;
                return '<tr><td class="tr-bar-cell"><div class="tr-row-bar" style="width:' + share.toFixed(1) + '%"></div><div class="tr-row-label">' + columns.label(row) + '</div></td>'
                    + columns.cells(row).map(function (cell) {
                        return '<td class="text-end text-nowrap">' + cell + '</td>';
                    }).join('') + '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function loadBreakdown(dimension, el, render, loadId, limit) {
        loading(el);
        return post('breakdown', { dimension: dimension, limit: limit || 10 }).then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            const rows = data.rows || [];
            el.innerHTML = rows.length === 0 ? message(t('empty.visits')) : render(rows);
            if (rows.length > 0 && dimension === 'device') {
                drawDevices(rows);
            }
        }).catch(function (error) {
            if (loadId === state.loadId) {
                failed(el, error);
            }
        });
    }

    function renderPages(rows) {
        return listTable(rows, {
            head: ['page', 'views', 'avg-time', 'bounce'],
            bar: 'pageviews',
            label: function (row) {
                return '<span class="tr-path" title="' + esc(row.value) + '">' + esc(row.value) + '</span>';
            },
            cells: function (row) {
                return [num(row.pageviews), duration(row.avg_time_on_page_s), pct(row.bounce_rate, 0)];
            }
        });
    }

    function renderSources(rows) {
        const isSite = state.sourceMode === 'referrer';
        return listTable(rows, {
            head: [isSite ? 'site' : 'source', 'visits', 'orders', 'conversion'],
            bar: 'visits',
            label: function (row) {
                return isSite
                    ? '<a href="https://' + esc(row.value) + '" target="_blank" rel="noopener noreferrer">' + esc(row.value) + '</a>'
                    : esc(t('source.' + row.value));
            },
            cells: function (row) {
                return [num(row.visits), num(row.orders), pct(row.visits > 0 ? row.orders / row.visits : 0)];
            }
        });
    }

    function renderCountries(rows) {
        return listTable(rows, {
            head: ['country', 'visits', 'orders', 'conversion'],
            bar: 'visits',
            label: function (row) {
                return flag(row.value) + esc(country(row.value));
            },
            cells: function (row) {
                return [num(row.visits), num(row.orders), pct(row.visits > 0 ? row.orders / row.visits : 0)];
            }
        });
    }

    let deviceChart = null;

    function renderDevices(rows) {
        const total = rows.reduce(function (sum, row) {
            return sum + row.visits;
        }, 0);
        return '<div class="tr-devices"><div id="tr-device-chart"></div><ul class="tr-device-list">'
            + rows.map(function (row) {
                return '<li><i class="bi ' + (DEVICE_ICONS[row.value] || 'bi-question-circle') + '"></i><span>' + esc(device(row.value)) + '</span>'
                    + '<strong>' + pct(total > 0 ? row.visits / total : 0, 0) + '</strong>'
                    + '<span class="tr-note" title="' + esc(t('column.conversion')) + '">' + pct(row.visits > 0 ? row.orders / row.visits : 0) + '</span></li>';
            }).join('') + '</ul></div>';
    }

    function drawDevices(rows) {
        if (deviceChart) {
            deviceChart.destroy();
        }
        const el = document.getElementById('tr-device-chart');
        if (!el) {
            return;
        }
        deviceChart = new ApexCharts(el, {
            chart: { type: 'donut', height: 190, fontFamily: 'inherit' },
            series: rows.map(function (row) { return row.visits; }),
            labels: rows.map(function (row) { return device(row.value); }),
            colors: [COLOR, '#3b82a0', '#9aa9b9', '#6b5b95'],
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 2, colors: [cssVar('--card-bg', '#ffffff')] },
            plotOptions: { pie: { donut: { size: '68%' } } },
            tooltip: { theme: isDark() ? 'dark' : 'light', y: { formatter: function (v) { return t('visits-count', num(v)); } } }
        });
        deviceChart.render();
    }

    function loadSources(loadId) {
        return loadBreakdown(state.sourceMode, document.getElementById('tr-sources'), renderSources, loadId, 10);
    }

    // ---- visits -------------------------------------------------------------------------------------------------

    function cameFrom(visit) {
        let text = visit.referrer_host ? esc(visit.referrer_host) : esc(t('source.' + (visit.source_type || 'direct')));
        if (visit.utm_campaign || visit.utm_source) {
            text += '<div class="tr-note">' + esc([visit.utm_source, visit.utm_medium, visit.utm_campaign].filter(Boolean).join(' / ')) + '</div>';
        }
        return text;
    }

    function trail(visit) {
        const pages = visit.pages.length > 0 ? visit.pages : [{ path: visit.entry_path }];
        const shown = pages.slice(0, 4).map(function (page) {
            return '<span class="tr-crumb" title="' + esc(page.path) + '">' + esc(page.path) + '</span>';
        });
        if (pages.length > 4) {
            shown.push('<span class="tr-note">' + esc(t('more', pages.length - 4)) + '</span>');
        }
        return '<div class="tr-trail">' + shown.join('<i class="bi bi-chevron-right"></i>') + '</div>';
    }

    function badges(visit) {
        const out = [];
        if (visit.is_returning) {
            out.push('<span class="badge badge-soft-info">' + esc(t('returning')) + '</span>');
        }
        if (visit.is_bounce) {
            out.push('<span class="badge badge-soft-secondary">' + esc(t('bounced')) + '</span>');
        }
        if (visit.funnel_stage > 0) {
            const ordered = visit.funnel_stage >= 4;
            out.push('<span class="badge ' + (ordered ? 'badge-soft-success' : 'badge-soft-warning') + '">' + esc(t('stage.' + Math.min(visit.funnel_stage, 4)))
                + (ordered && visit.order_value !== null ? ' · ' + esc(visit.order_currency || '') + ' ' + num(visit.order_value) : '') + '</span>');
        }
        return out.join(' ');
    }

    function visitRow(visit, index) {
        const when = localTime(visit.started_at);
        const ended = localTime(visit.ended_at);
        const place = visit.city ? esc(visit.city) + ', ' + esc(country(visit.country_code)) : esc(country(visit.country_code));
        const detailId = 'tr-visit-' + index;
        const details = [
            ['where', flag(visit.country_code) + place],
            ['pages', visit.pages.map(function (page) {
                return '<div>' + esc(page.path) + ' <span class="tr-note">· ' + page.views + '× · ' + duration(page.visible_s) + '</span></div>';
            }).join('') || '–'],
            ['device', esc([device(visit.device_type), visit.browser, visit.os].filter(Boolean).join(' · '))],
            ['language', esc(visit.language || '–')],
            ['ended', esc(ended.day + ' ' + ended.time)]
        ];
        return '<tr class="tr-clickable" data-bs-toggle="collapse" data-bs-target="#' + detailId + '" aria-expanded="false">'
            + '<td class="text-nowrap"><div class="fw-semibold">' + when.time + '</div><div class="tr-note">' + when.day + '</div></td>'
            + '<td>' + cameFrom(visit) + '</td>'
            + '<td>' + trail(visit) + '<div class="mt-1">' + badges(visit) + '</div></td>'
            + '<td class="text-nowrap" title="' + esc(country(visit.country_code)) + '">' + flag(visit.country_code)
            + '<i class="bi ' + (DEVICE_ICONS[visit.device_type] || 'bi-question-circle') + ' text-muted" title="' + esc(device(visit.device_type)) + '"></i></td>'
            + '<td class="text-end text-nowrap">' + duration(visit.duration_s) + '<div class="tr-note">'
            + esc(visit.pageviews === 1 ? t('one-page') : t('page-count', visit.pageviews)) + '</div></td>'
            + '<td class="text-end"><i class="bi bi-chevron-down tr-chevron"></i></td></tr>'
            + '<tr class="collapse tr-detail" id="' + detailId + '"><td colspan="6"><dl class="tr-detail-grid">'
            + details.map(function (item) {
                return '<dt>' + esc(t('detail.' + item[0])) + '</dt><dd>' + item[1] + '</dd>';
            }).join('') + '</dl></td></tr>';
    }

    function loadVisits(loadId) {
        const body = document.getElementById('tr-visits');
        body.innerHTML = '<tr><td colspan="6"><div class="tr-loading"><span class="spinner-border spinner-border-sm"></span></div></td></tr>';
        return post('visits', { start: state.visitsStart, length: PAGE_SIZE }).then(function (data) {
            if (loadId !== state.loadId) {
                return;
            }
            state.visitsTotal = data.records_total || 0;
            state.timezone = data.timezone || state.timezone;
            const rows = data.rows || [];
            body.innerHTML = rows.length === 0
                ? '<tr><td colspan="6">' + message(t('empty.visits')) + '</td></tr>'
                : rows.map(visitRow).join('');
            const first = state.visitsTotal === 0 ? 0 : state.visitsStart + 1;
            const last = Math.min(state.visitsStart + PAGE_SIZE, state.visitsTotal);
            document.getElementById('tr-visits-count').textContent = state.visitsTotal === 0 ? '' : t('range', num(first), num(last), num(state.visitsTotal));
            document.getElementById('tr-visits-note').textContent = t('times-in', state.timezone.replace('_', ' '));
            document.getElementById('tr-visits-prev').disabled = state.visitsStart === 0;
            document.getElementById('tr-visits-next').disabled = last >= state.visitsTotal;
        }).catch(function (error) {
            if (loadId === state.loadId) {
                body.innerHTML = '<tr><td colspan="6">' + message(error.message, 'bi-exclamation-triangle') + '</td></tr>';
            }
        });
    }

    // ---- filters ------------------------------------------------------------------------------------------------

    let picker = null;

    function updatePicker() {
        if (picker && state.from && state.to) {
            picker.setDate([state.from, state.to], false);
        }
        root.querySelectorAll('[data-tr-days]').forEach(function (button) {
            const days = Number(button.dataset.trDays);
            button.classList.toggle('active', state.to === today(0) && state.from === today(-(days - 1)));
        });
    }

    function initFilters() {
        if (window.flatpickr) {
            picker = flatpickr(document.getElementById('tr-range'), {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                // the field shows the dates in the page's language (flatpickr has no Thai here); the value stays Y-m-d
                formatDate: function (date, format) {
                    const ymd = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                    return format === 'Y-m-d' ? ymd : longDay(ymd);
                },
                locale: { rangeSeparator: ' – ' },
                maxDate: 'today',
                onClose: function (dates, text, instance) {
                    if (dates.length === 2) {
                        state.from = instance.formatDate(dates[0], 'Y-m-d');
                        state.to = instance.formatDate(dates[1], 'Y-m-d');
                        reload();
                    } else {
                        updatePicker();
                    }
                }
            });
        }
        root.querySelectorAll('[data-tr-days]').forEach(function (button) {
            button.addEventListener('click', function () {
                const days = Number(button.dataset.trDays);
                state.to = today(0);
                state.from = today(-(days - 1));
                reload();
            });
        });
        root.querySelectorAll('[data-tr-measure]').forEach(function (button) {
            button.addEventListener('click', function () {
                setMeasure(button.dataset.trMeasure);
                renderChart();
            });
        });
        root.querySelectorAll('[data-tr-source]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.sourceMode = button.dataset.trSource;
                root.querySelectorAll('[data-tr-source]').forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                loadSources(state.loadId);
            });
        });
        document.getElementById('tr-visits-prev').addEventListener('click', function () {
            state.visitsStart = Math.max(0, state.visitsStart - PAGE_SIZE);
            loadVisits(state.loadId);
        });
        document.getElementById('tr-visits-next').addEventListener('click', function () {
            state.visitsStart += PAGE_SIZE;
            loadVisits(state.loadId);
        });
    }

    // ---- loading everything -------------------------------------------------------------------------------------

    function reload() {
        state.loadId += 1;
        state.visitsStart = 0;
        const loadId = state.loadId;
        syncAddress();
        root.querySelectorAll('.tr-kpi-value').forEach(function (el) {
            el.innerHTML = '<span class="tr-skeleton"></span>';
        });
        root.querySelectorAll('.tr-kpi-trend').forEach(function (el) {
            el.innerHTML = '&nbsp;';
            el.classList.remove('positive', 'negative');
        });
        loadOverview(loadId);
        loadBreakdown('path', document.getElementById('tr-pages'), renderPages, loadId, 10);
        loadSources(loadId);
        loadBreakdown('country', document.getElementById('tr-countries'), renderCountries, loadId, 10);
        loadBreakdown('device', document.getElementById('tr-devices'), renderDevices, loadId, 5);
        loadVisits(loadId);
    }

    // the chart and donut follow the light/dark theme
    new MutationObserver(function () {
        if (chart) {
            chart.destroy();
            chart = null;
            renderChart();
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    initFilters();
    reload();
})();
