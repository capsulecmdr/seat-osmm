{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')
  <script>
    console.log(@json($publicInfo));
    console.log(@json($blueprints));
  </script>
  <style>
    .card-ribbon {
    position: relative;
    overflow: hidden;
    }

    .card-ribbon::before {
    content: "UNDER CONSTRUCTION";
    position: absolute;
    top: 40px;
    left: -45px;
    width: 200px;
    background: #ffc107;
    color: #000;
    text-align: center;
    font-weight: bold;
    font-size: 0.75rem;
    padding: 4px 0;
    transform: rotate(-45deg);
    box-shadow: 0 0 3px rgba(0, 0, 0, 0.3);
    z-index: 1;
    pointer-events: none;
    }
  </style>
  <div class="container-fluid">
    <div class="row">
    
    <!-- Image and text -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light w-100">
      <a class="navbar-brand" href="/home"><i class="fa fa-home" aria-hidden="true"></i> Home</a>
      {{-- Put this where you want the indicator to appear --}}
      <style>
      <style>

      /* Muted grayscale for inactive state */
      .war-inactive img {
        filter: grayscale(1) opacity(.6);
      }

      /* Red glow for active state */
      .war-active img {
        filter: none;
        box-shadow: 0 0 5px rgba(255, 0, 0, 0.8);
        border-radius: 4px;
      }

      /* Optional pulsing effect when active */
      .war-active img {
        animation: pulseGlow 1.5s ease-in-out infinite;
      }

      @keyframes pulseGlow {

        0%,
        100% {
        box-shadow: 0 0 5px rgba(255, 0, 0, 0.8);
        }

        50% {
        box-shadow: 0 0 10px rgba(255, 0, 0, 1);
        }
      }
      </style>


      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarText"
      aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarText">
      <ul class="navbar-nav mr-auto">
        @can('osmm.admin')
      <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" data-toggle="dropdown"
        aria-haspopup="true" aria-expanded="false">
        OSMM Config
      </a>
      <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
        <a class="dropdown-item" href="{{ route('osmm.config.branding') }}">Branding</a>
      </div>
      </li>
      @endcan
      </ul>
      {{-- War Status Badge --}}
      <span class="badge {{ $inWar ? 'bg-danger war-active' : 'bg-secondary war-inactive' }} mr-sm"
        title="{{ $inWar ? 'Active War' : 'No Active War' }}">
        <img src="https://wiki.eveuniversity.org/images/thumb/3/3d/Wars.png/32px-Wars.png" alt="War status" width="16"
        height="16" class="align-text-top">
        {{ $inWar ? 'At War' : 'Peace' }}
      </span>
      <span class="navbar-text"><span id="cc-time" class="mr-sm"></span> | <sub><span
          id="dt-time"></span></sub></span>


      </div>
    </nav>
    </div>
    <div class="row">
      <nav aria-label="breadcrumb" class="w-100">
        <ol class="breadcrumb">
          <li class="breadcrumb-item active" aria-current="page">Home</li>
        </ol>
      </nav>
    </div>
    
    <div class="row">

    {{-- MAIN --}}
    <div class="col-xl-8">
      {{-- ONLINE / ESI --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">Online Players</span>
          <small class="text-muted" id="onlinePlayers_lastUpdated">—</small>
        </div>
        <div class="card-body p-0">

          <div id="chart_online_players_div" style="width:100%; height:150px;"></div>
        </div>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">ESI Response Times</span>
          <small class="text-muted" id="esi-last-updated">—</small>
        </div>
        <div class="card-body p-0">
          <div id="chart_esi_response_div" style="width:100%; height:150px;"></div>
        </div>
        </div>
      </div>
      </div>

      {{-- MONTHLY KILLMAILS / MINING --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">KillMails</span>
          <small class="text-muted" id="killmails-last-updated">—</small>
        </div>
        <div class="card-body p-0">
          <div id="waterfall_div" style="width:100%; height:150px;"></div>
        </div>
        </div>

      </div>
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">Monthly Mining</span>
          <small class="text-muted" id="mining-last-updated">—</small>
        </div>
        <div class="card-body p-0">
          <div id="chart_mining_div" style="width:100%; height:150px;"></div>
        </div>
        </div>
      </div>
      </div>

      {{-- TOTAL MONTHLY WALLET / WALLET BALANCES --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">Total Wallet (Last 30 Days)</span>
          <small class="text-muted" id="wallet-30d-label"></small>
        </div>
        <div class="card-body p-0">
          <div id="chart_wallet_balance_30d" style="width:100%; height:150px;"></div>
        </div>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">Wallet Balances</span>
          <small class="text-muted">Updated
          {{ \Carbon\Carbon::parse($walletByChar['updated'])->toDayDateTimeString() }} UTC</small>
        </div>
        <div class="card-body p-0">
          <div id="chart_wallet_by_char_div" style="width:100%; height:150px;"></div>
        </div>
        </div>
      </div>
      </div>

      {{-- BOTTOM BIG PANELS: SKILLS / ALLOCATION MAP --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header font-weight-bold">Skills Coverage</div>
        <div class="card-body p-0">
          <canvas id="skills-coverage" style="width:100%; height:420px;"></canvas>
        </div>
        </div>
      </div>
      <div class="col-lg-6 mb-3">
        <div class="card card-ribbon">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="font-weight-bold">Allocation Map</span>
          <small class="text-muted">Updated
          {{ \Carbon\Carbon::parse($allocation['updated'])->toDayDateTimeString() }} UTC</small>
        </div>
        <div class="card-body">
          <div id="chart_allocation_div" style="width:100%; height:420px;"></div>
        </div>
        </div>
      </div>
      </div>
    </div>


    <style>
      :root {
      --rail-gap: 18px;
      }

      /* Make the right rail stick & scroll */
      .right-rail {
      position: sticky;
      top: var(--rail-gap);
      max-height: calc(100vh - (var(--rail-gap) * 2));
      overflow: auto;
      padding-bottom: 6px;
      /* avoid last card cutoff */
      }

      /* Nice card spacing inside a scroll container */
      .right-rail .card {
      border-radius: .5rem;
      }

      /* Keep table headers visible while scrolling the rail */
      .right-rail thead th {
      position: sticky;
      top: 0;
      z-index: 1;
      background: #f8f9fa;
      /* matches .thead-light */
      }

      /* On <1200px, the rail becomes full width — disable sticky */
      @media (max-width: 1199.98px) {
      .right-rail {
        position: static;
        max-height: none;
        overflow: visible;
      }
      }
    </style>
    {{-- RIGHT SIDEBAR --}}
    <aside class="col-xl-4">
      <div class="right-rail">
      {{-- Upcoming Events
      <div class="card mb-3">
        <div class="card-header font-weight-bold">Upcoming Events</div>
        <div class="card-body p-0">
        <table class="table table-sm mb-0" id="upcoming-events">
          <thead class="thead-light">
          <tr>
            <th style="width:40%">Date (UTC)</th>
            <th>Event</th>
          </tr>
          </thead>
          <tbody></tbody>
        </table>
        </div>
      </div> --}}

      {{-- ToDo List --}}
      <div class="card mb-3">
        <div class="card-header font-weight-bold">ToDo List</div>
        <div class="card-body">
        <div class="input-group input-group-sm mb-2">
          <input id="todo-input" class="form-control" placeholder="New task to do">
          <div class="input-group-append">
          <button id="todo-create" class="btn btn-primary" type="button">Create</button>
          </div>
        </div>

        <div id="todo-list" class="mb-0">
          <div class="text-muted small">Loading…</div>
        </div>
        </div>
      </div>

      <script>
        (function () {
        const listEl = document.getElementById('todo-list');
        const input = document.getElementById('todo-input');
        const btn = document.getElementById('todo-create');

        // helpers
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const HEADERS_JSON = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf };
        const ENDPOINTS = {
          index: '{{ route('osmm.todos.index') }}',
          store: '{{ route('osmm.todos.store') }}',
          destroy: id => '{{ url('osmm/todos') }}/' + id,
        };

        function rowTemplate(item) {
          const id = 'todo-' + item.id;
          const txt = (item.text || '').replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
          return `
      <div class="custom-control custom-checkbox mb-1" data-id="${item.id}">
      <input type="checkbox" class="custom-control-input" id="${id}">
      <label class="custom-control-label" for="${id}">${txt}</label>
      </div>`;
        }

        async function loadTodos() {
          listEl.innerHTML = `<div class="text-muted small">Loading…</div>`;
          try {
          const res = await fetch(ENDPOINTS.index, { credentials: 'same-origin' });
          const items = await res.json();
          listEl.innerHTML = items.length
            ? items.map(rowTemplate).join('')
            : `<div class="text-muted small">No tasks yet.</div>`;
          } catch (e) {
          listEl.innerHTML = `<div class="text-danger small">Failed to load tasks.</div>`;
          console.warn(e);
          }
        }

        async function createTodo() {
          const text = (input.value || '').trim();
          if (!text) return;
          btn.disabled = true;
          try {
          const res = await fetch(ENDPOINTS.store, {
            method: 'POST',
            headers: HEADERS_JSON,
            credentials: 'same-origin',
            body: JSON.stringify({ text })
          });
          if (res.ok) {
            input.value = '';
            await loadTodos();
          }
          } catch (e) { console.warn(e); }
          finally { btn.disabled = false; }
        }

        // Event: create on click / Enter key
        btn?.addEventListener('click', createTodo);
        input?.addEventListener('keydown', e => { if (e.key === 'Enter') createTodo(); });

        // Event: delete on checkbox click (event delegation)
        listEl.addEventListener('change', async (e) => {
          const cb = e.target.closest('input[type="checkbox"].custom-control-input');
          if (!cb) return;
          const wrap = cb.closest('[data-id]');
          const id = wrap?.getAttribute('data-id');
          if (!id) return;

          // optional: quick UI feedback
          wrap.style.opacity = '0.6';

          try {
          const res = await fetch(ENDPOINTS.destroy(id), {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin'
          });
          if (res.status === 204) {
            wrap.remove();
            if (!listEl.children.length) {
            listEl.innerHTML = `<div class="text-muted small">No tasks yet.</div>`;
            }
          } else {
            cb.checked = false; // revert
            wrap.style.opacity = '1';
          }
          } catch (err) {
          cb.checked = false;
          wrap.style.opacity = '1';
          console.warn(err);
          }
        });

        // init
        loadTodos();
        })();
      </script>


      {{-- Unread Mail
      <div class="card">
        <div class="card-header font-weight-bold">Unread Mail</div>
        <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light">
          <tr>
            <th style="width:40%">Received</th>
            <th>Subject</th>
          </tr>
          </thead>
          <tbody>
          <tr>
            <td>8/8/25 06:23</td>
            <td>Re: That thing we talked about</td>
          </tr>
          <tr>
            <td>8/8/25 06:23</td>
            <td>Re: That thing we talked about</td>
          </tr>
          <tr>
            <td>8/8/25 06:23</td>
            <td>Re: That thing we talked about</td>
          </tr>
          <tr>
            <td>8/8/25 06:23</td>
            <td>Re: That thing we talked about</td>
          </tr>
          </tbody>
        </table>
        </div>
      </div> --}}
      </div>
    </aside>

    </div>
  </div>



  <!-- ONE loader include only -->
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script>


    (function () {
    function updateUtcTime() {
      const now = new Date();
      const options = {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
      hour12: false, timeZone: 'UTC'
      };
      const formatted = now.toLocaleString('en-US', options).replace(',', '');
      document.getElementById('cc-time').textContent = formatted;
    }
    updateUtcTime();
    setInterval(updateUtcTime, 1000);
    })();

    (function () {
    function updateCountdown() {
      const now = new Date();
      const target = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), 11, 0, 0));
      if (now.getUTCHours() >= 11) target.setUTCDate(target.getUTCDate() + 1);
      const diff = target - now;
      const hours = Math.floor(diff / (1000 * 60 * 60));
      const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((diff % (1000 * 60)) / 1000);
      document.getElementById('dt-time').textContent =
      `DT in T- ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
    })();







    // ---- Load once ----
    google.charts.load('current', { packages: ['corechart', 'line'] });
    google.charts.setOnLoadCallback(initCharts);

    function initCharts() {
    // Draw immediately
    drawOnlinePlayers();
    drawEsiResponse();

    // Refresh every 60s
    setInterval(() => { if (!hovering.online) drawOnlinePlayers(); }, 60000);
    setInterval(() => { if (!hovering.esi) drawEsiResponse(); }, 60000);

    // Handle window resizes (optional)
    window.addEventListener('resize', debounce(() => {
      drawOnlinePlayers();
      drawEsiResponse();
      drawWaterfall();
      drawMining();
      drawWalletBalance30();
      drawWallets();
    }, 200));
    }

    // ---- Hover guards to avoid stuck tooltips on redraw ----
    const hovering = { online: false, esi: false };
    const onlineEl = document.getElementById('chart_online_players_div');
    const esiEl = document.getElementById('chart_esi_response_div');

    if (onlineEl) {
    onlineEl.addEventListener('mouseenter', () => hovering.online = true);
    onlineEl.addEventListener('mouseleave', () => hovering.online = false);
    }
    if (esiEl) {
    esiEl.addEventListener('mouseenter', () => hovering.esi = true);
    esiEl.addEventListener('mouseleave', () => hovering.esi = false);
    }

    // ---- Shared helpers (define once) ----
    function toNum(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
    }
    function toUtcDate(x) {
    if (x == null) return null;
    if (typeof x === 'number') {
      const ms = x > 1e12 ? x : (x < 1e11 ? x * 1000 : x);
      return new Date(ms);
    }
    const iso = ('' + x).match(/[zZ]|[+-]\d{2}:\d{2}$/) ? x : x + 'Z';
    return new Date(iso);
    }
    function isTimestamp(val) {
    return (typeof val === 'number') ||
      (typeof val === 'string' && !isNaN(Date.parse(val + (/[zZ]|[+-]\d{2}:\d{2}$/.test(val) ? '' : 'Z'))));
    }
    function isTimestampSeries(arr) {
    if (!Array.isArray(arr) || !arr.length) return false;
    let checks = 0, hits = 0;
    for (let i = 0; i < arr.length && checks < 5; i++) {
      if (arr[i] != null) { checks++; if (isTimestamp(arr[i])) hits++; }
    }
    return checks > 0 && hits === checks;
    }
    function debounce(fn, wait) {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
    }

    // ---- Common chart options (zero padding, sparkline look) ----
    const baseOptions = {
    legend: 'none',
    chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
    hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    vAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    lineWidth: 1,
    pointSize: 0,
    tooltip: { isHtml: false },
    animation: { duration: 0, startup: false },
    trendlines: { 0: {} }
    };

    // ---- Online Players ----
    function drawOnlinePlayers() {
  $.getJSON("{{ route('seatcore::home.chart.serverstatus') }}", function (payload) {
    const dt = new google.visualization.DataTable();

    // --- helpers ---
    const isTimestamp = v => v != null && !isNaN(Date.parse(v));
    const isTimestampSeries = arr => Array.isArray(arr) && arr.length && arr.every(isTimestamp);
    const toDate = v => new Date(v); // trust payload; it's already UTC
    const toNum = v => (v == null || v === '' ? null : Number(v));

    // Detect payload shape
    const looksLikeLabels = Array.isArray(payload?.labels) && payload?.datasets?.[0]?.data;
    const looksLikePoints = Array.isArray(payload) && payload.length && (payload[0].t !== undefined || payload[0].x !== undefined);

    // Columns
    if (looksLikeLabels && isTimestampSeries(payload.labels)) {
      dt.addColumn('datetime', 'Time (UTC)');
    } else if (looksLikePoints && isTimestampSeries(payload.map(p => p.t ?? p.x))) {
      dt.addColumn('datetime', 'Time (UTC)');
    } else {
      dt.addColumn('number', 'X');
    }
    dt.addColumn('number', 'Concurrent Players');

    // Rows
    let rows = [];
    if (looksLikeLabels) {
      const xs = payload.labels;
      const ys = payload.datasets[0].data;
      const useTime = isTimestampSeries(xs);
      rows = xs.map((x, i) => [useTime ? toDate(x) : i, toNum(ys[i])]);
    } else if (looksLikePoints) {
      rows = payload.map(p => {
        const xVal = p.t ?? p.x;
        const useTime = isTimestamp(xVal);
        return [useTime ? toDate(xVal) : toNum(xVal), toNum(p.y)];
      });
    }
    dt.addRows(rows);

    // Draw chart
    const chart = new google.visualization.LineChart(onlineEl);
    google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
    chart.draw(dt, baseOptions);

    // --- Stats (min, max, current) ---
    let min = Infinity, max = -Infinity;
    let latestTs = null;
    let current = null;

    for (let i = 0, n = dt.getNumberOfRows(); i < n; i++) {
      const y = dt.getValue(i, 1);
      if (Number.isFinite(y)) {
        if (y < min) min = y;
        if (y > max) max = y;
      }
      const x = dt.getValue(i, 0);
      if (x instanceof Date && (!latestTs || x > latestTs)) {
        latestTs = x;
        current = y; // y-value of most recent timestamp
      }
    }

    if (min === Infinity) { min = '—'; max = '—'; }
    if (current === null) current = '—';

    // Footer
    const el = document.getElementById('onlinePlayers_lastUpdated');
    if (el) {
      el.textContent = `Min: ${min} · Max: ${max} · Current: ${current}`;
    }
  });
}





    // ---- ESI Response Times ----
    function drawEsiResponse() {
  $.getJSON("{{ route('seatcore::home.chart.serverresponse') }}", function (payload) {
    const dt = new google.visualization.DataTable();

    const looksLikeLabels = Array.isArray(payload?.labels) && payload?.datasets?.[0]?.data;
    const looksLikePoints = Array.isArray(payload) && payload.length && (payload[0].t !== undefined || payload[0].x !== undefined);

    if (looksLikeLabels && isTimestampSeries(payload.labels)) {
      dt.addColumn('datetime', 'Time (UTC)');
    } else if (looksLikePoints && isTimestampSeries(payload.map(p => p.t ?? p.x))) {
      dt.addColumn('datetime', 'Time (UTC)');
    } else {
      dt.addColumn('number', 'X');
    }
    dt.addColumn('number', 'Response Time (ms)');

    // Build rows
    let rows = [];
    if (looksLikeLabels) {
      const xs = payload.labels;
      const ys = payload.datasets[0].data;
      const useTime = isTimestampSeries(xs);
      rows = xs.map((x, i) => [useTime ? toUtcDate(x) : i, toNum(ys[i])]);
    } else if (looksLikePoints) {
      rows = payload.map(p => {
        const xVal = p.t ?? p.x;
        const useTime = isTimestamp(xVal);
        return [useTime ? toUtcDate(xVal) : toNum(xVal), toNum(p.y)];
      });
    }
    dt.addRows(rows);

    // Draw chart
    const chart = new google.visualization.LineChart(esiEl);
    google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
    chart.draw(dt, baseOptions);

    // --- Stats: min / max / avg (ms) ---
    let min = Infinity, max = -Infinity, sum = 0, count = 0;

    for (let i = 0, n = dt.getNumberOfRows(); i < n; i++) {
      const y = dt.getValue(i, 1);
      if (Number.isFinite(y)) {
        if (y < min) min = y;
        if (y > max) max = y;
        sum += y;
        count++;
      }
    }

    if (count === 0) {
      min = '—'; max = '—';
    }
    const avg = count > 0 ? (sum / count) : null;

    // Footer
    const el = document.getElementById('esi-last-updated');
    if (el) {
      const avgStr = avg == null ? '—' : avg.toFixed(1);
      el.textContent = `Min: ${min} ms · Max: ${max} ms · Avg: ${avgStr} ms`;
    }
  });
}


    google.charts.setOnLoadCallback(drawWaterfall);

    function drawWaterfall() {
  // all data comes from the injected $km
  const KM = @json($km ?? (object)[]);
  const days     = KM.days      || [];
  const cumWins  = KM.cum_wins  || [];
  const cumTotal = KM.cum_total || [];
  const dailyIskRaw = KM.daily_isk ?? null;  // preferred
  const cumIskRaw   = KM.cum_isk   ?? null;  // fallback

  // helpers
  const toNum = v => (v == null || v === '' ? 0 : Number(v));
  const fmtISK = n => (n == null || isNaN(n))
    ? '—'
    : new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(n));

  // ------- per-day deltas for the waterfall (wins - losses) -------
  const delta = days.map((_, i) => {
    const w = toNum(cumWins[i])  - (i > 0 ? toNum(cumWins[i - 1])  : 0);
    const t = toNum(cumTotal[i]) - (i > 0 ? toNum(cumTotal[i - 1]) : 0);
    const l = Math.max(0, t - w);
    return w - l;
  });

  // ------- Waterfall candlestick: [label, low, open, close, high] -------
  const data = new google.visualization.DataTable();
  data.addColumn('string', 'Day');
  data.addColumn('number', 'Low');
  data.addColumn('number', 'Open');
  data.addColumn('number', 'Close');
  data.addColumn('number', 'High');

  let running = 0;
  for (let i = 0; i < days.length; i++) {
    const open = running;
    const close = running + delta[i];
    data.addRow([String(days[i]), Math.min(open, close), open, close, Math.max(open, close)]);
    running = close;
  }
  // Optional "Total" bar at the end
  data.addRow(['Total', Math.min(0, running), 0, running, Math.max(0, running)]);

  const options = {
    legend: 'none',
    chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
    bar: { groupWidth: '85%' },
    hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    vAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    candlestick: {
      hollowIsRising: false,
      fallingColor: { strokeWidth: 0, fill: '#ef4444' },
      risingColor:  { strokeWidth: 0, fill: '#22c55e' }
    }
  };

  const chart = new google.visualization.CandlestickChart(document.getElementById('waterfall_div'));
  google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
  chart.draw(data, options);

  // ============================
  // Avg ISK / Day and MTD ISK
  // ============================
  let perDayIsk = null;
  if (Array.isArray(dailyIskRaw) && dailyIskRaw.length === days.length) {
    perDayIsk = dailyIskRaw.map(toNum);
  } else if (Array.isArray(cumIskRaw) && cumIskRaw.length === days.length) {
    perDayIsk = cumIskRaw.map((v, i, a) => toNum(v) - (i > 0 ? toNum(a[i - 1]) : 0));
  }

  const footerEl = document.getElementById('killmails-last-updated');
  if (!perDayIsk) {
    if (footerEl) footerEl.textContent = `Avg/Day: — ISK · MTD: — ISK`;
    return;
  }

  // Cutoff = last day with any activity (either ISK or non-zero delta)
  let lastIdx = -1;
  for (let i = perDayIsk.length - 1; i >= 0; i--) {
    if (perDayIsk[i] !== 0 || delta[i] !== 0) { lastIdx = i; break; }
  }

  const daysElapsed = lastIdx >= 0 ? (lastIdx + 1) : 0;
  const mtd = lastIdx >= 0
    ? perDayIsk.slice(0, lastIdx + 1).reduce((a, b) => a + toNum(b), 0)
    : 0;
  const avgPerDay = daysElapsed ? (mtd / daysElapsed) : null;

  if (footerEl) {
    footerEl.textContent = `Avg/Day: ${fmtISK(avgPerDay)} ISK · MTD: ${fmtISK(mtd)} ISK`;
  }

  // Optional: quick debug in console
  //  console.log('KM', KM);
}


    google.charts.setOnLoadCallback(drawMining);

    function drawMining() {
  const days     = @json($mining['days']);
  const asteroid = @json($mining['asteroid']);
  const ice      = @json($mining['ice']);
  const moon     = @json($mining['moon']);
  const cumISK   = @json($mining['cum_isk']);

  const data = new google.visualization.DataTable();
  data.addColumn('number', 'Day');
  data.addColumn('number', 'Asteroid');
  data.addColumn('number', 'Ice');
  data.addColumn('number', 'Moon');
  data.addColumn('number', 'Cumulative ISK');

  const rows = days.map((d, i) => [d, asteroid[i], ice[i], moon[i], cumISK[i]]);
  data.addRows(rows);

  // Format ISK (line series)
  new google.visualization.NumberFormat({
    prefix: 'ISK ', groupingSymbol: ',', fractionDigits: 0
  }).format(data, 4);

  const options = {
    legend: { position: 'top' },
    chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
    isStacked: false,                 // grouped bars
    seriesType: 'bars',
    bar: { groupWidth: '75%' },
    hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    vAxes: { 0: { textPosition: 'none' }, 1: { textPosition: 'none' } },
    series: { 3: { type: 'line', targetAxisIndex: 1 } }, // line uses right axis
    trendlines: { 3: {} }
  };

  const el = document.getElementById('chart_mining_div');
  const chart = new google.visualization.ComboChart(el);
  google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
  chart.draw(data, options);

  // -------- Label: Avg/Day ISK · MTD ISK --------
  const toNum = v => (v == null || v === '' ? 0 : Number(v));
  const fmtISK = n => (n == null || isNaN(n))
    ? '—'
    : new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(n));

  // Derive per-day ISK from cumulative line
  const n = Math.min(days.length, Array.isArray(cumISK) ? cumISK.length : 0);
  const perDayIsk = Array.from({ length: n }, (_, i) => {
    const cur  = toNum(cumISK[i]);
    const prev = i > 0 ? toNum(cumISK[i - 1]) : 0;
    return cur - prev;
  });

  // Treat “activity” as either ISK > 0 or mined units > 0
  let lastIdx = -1;
  for (let i = n - 1; i >= 0; i--) {
    const units = toNum(asteroid[i]) + toNum(ice[i]) + toNum(moon[i]);
    if (perDayIsk[i] !== 0 || units !== 0) { lastIdx = i; break; }
  }

  const daysElapsed = lastIdx >= 0 ? (lastIdx + 1) : 0;
  const mtd = lastIdx >= 0
    ? perDayIsk.slice(0, lastIdx + 1).reduce((a, b) => a + toNum(b), 0)
    : 0;
  const avgPerDay = daysElapsed ? (mtd / daysElapsed) : null;

  const footerEl = document.getElementById('mining-last-updated');
  if (footerEl) {
    footerEl.textContent = `Avg/Day: ${fmtISK(avgPerDay)} ISK · MTD: ${fmtISK(mtd)} ISK`;
  }
}


    google.charts.setOnLoadCallback(drawWalletBalance30);

    function drawWalletBalance30() {
  const balances = @json($walletBalance30['balances'] ?? []);
  const dt = new google.visualization.DataTable();
  dt.addColumn('number', 'X');              // simple index for minimal padding
  dt.addColumn('number', 'Total Balance');
  dt.addRows(balances.map((y, i) => [i, y]));

  const opts = {
    legend: 'none',
    chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
    hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    vAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
    lineWidth: 1,
    pointSize: 0,
    trendlines: { 0: {} }
  };

  const el = document.getElementById('chart_wallet_balance_30d');
  const chart = new google.visualization.LineChart(el);
  google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
  chart.draw(dt, opts);

  // ---- Label: Today + 30d projection (linear trend) ----
  const fmtISK = n => (n == null || isNaN(n))
    ? '—'
    : new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(n));

  const n = balances.length;
  if (!n) {
    const lbl = document.getElementById('wallet-30d-label');
    if (lbl) lbl.textContent = 'Today: — ISK · 30d proj: — ISK';
    return;
  }

  const today = Number(balances[n - 1]) || 0;

  // Simple least-squares over the last 30 days (index vs. balance)
  // x: 0..n-1, y: balances[i]
  let sumX = 0, sumY = 0, sumXX = 0, sumXY = 0;
  for (let i = 0; i < n; i++) {
    const x = i;
    const y = Number(balances[i]) || 0;
    sumX  += x;
    sumY  += y;
    sumXX += x * x;
    sumXY += x * y;
  }
  const denom = (n * sumXX - sumX * sumX);
  const slope = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0;   // ISK/day
  const intercept = (sumY - slope * sumX) / n;

  // Project 30 days beyond the last point (today is index n-1)
  const xProj = (n - 1) + 30;
  let projected = intercept + slope * xProj;
  if (!Number.isFinite(projected)) projected = today;
  if (projected < 0) projected = 0; // guard

  const lbl = document.getElementById('wallet-30d-label');
  if (lbl) {
    lbl.textContent = `Today: ${fmtISK(today)} ISK · 30d proj: ${fmtISK(projected)} ISK`;
  }
}


    google.charts.setOnLoadCallback(drawWallets);

    function drawWallets() {
  const rows = @json($walletByChar['rows'] ?? []); // [ [name, balance], ... ]

  const data = new google.visualization.DataTable();
  data.addColumn('string', 'Character');                  // domain (we'll hide axis labels)
  data.addColumn('number', 'Wallet (ISK)');               // value
  data.addColumn({ type: 'string', role: 'annotation' }); // on-bar label
  data.addColumn({ type: 'string', role: 'annotationText' }); // tooltip for annotation

  const short = s => {
    const str = String(s ?? '');
    return str.length > 12 ? str.slice(0, 11) + '…' : str;
  };
  const fmtISK = n =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
      .format(Math.round(Number(n) || 0));
  const abbrISK = n => {
    const x = Math.abs(Number(n) || 0);
    if (x >= 1e12) return (n / 1e12).toFixed(2).replace(/\.00$/, '') + 't';
    if (x >= 1e9)  return (n / 1e9 ).toFixed(2).replace(/\.00$/, '') + 'b';
    if (x >= 1e6)  return (n / 1e6 ).toFixed(2).replace(/\.00$/, '') + 'm';
    if (x >= 1e3)  return (n / 1e3 ).toFixed(2).replace(/\.00$/, '') + 'k';
    return Math.round(n).toString();
  };

  data.addRows(
    rows.map(([name, bal]) => {
      const nm = String(name ?? '');
      const b  = Number(bal) || 0;
      // single on-bar label: "Name · 1.2b"
      const ann = `${short(nm)} · ${abbrISK(b)}`;
      const tip = `${nm}\nISK ${fmtISK(b)}`;
      return [nm, b, ann, tip];
    })
  );

  // Format ISK for bar series
  new google.visualization.NumberFormat({
    prefix: 'ISK ', groupingSymbol: ',', fractionDigits: 0
  }).format(data, 1);

  const options = {
    legend: { position: 'none' },
    bar: { groupWidth: '70%' },
    // no x-axis labels below bars
    chartArea: { left: 0, right: 0, top: 10, bottom: 0, width: '100%', height: '100%' },
    hAxis: { textPosition: 'none' },
    vAxis: { minValue: 0, textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent' },
    annotations: {
      textStyle: { fontSize: 10, color: '#666' },
      // uncomment if labels get clipped inside short bars:
      // alwaysOutside: true
    }
  };

  const chart = new google.visualization.ColumnChart(document.getElementById('chart_wallet_by_char_div'));
  google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
  chart.draw(data, options);
}






    function loadSkillsCoverageChart(chars) {
    if (!chars.length) {
      document.getElementById('skills-coverage').insertAdjacentHTML('beforebegin',
      '<div class="p-3 text-muted">No characters linked.</div>');
      return;
    }

    const PALETTE = [
      { bg: 'rgba(99,164,255,0.20)', border: 'rgba(99,164,255,1)' },
      { bg: 'rgba(34,197,94,0.20)', border: 'rgba(34,197,94,1)' },
      { bg: 'rgba(239,68,68,0.20)', border: 'rgba(239,68,68,1)' },
      { bg: 'rgba(245,158,11,0.20)', border: 'rgba(245,158,11,1)' },
      { bg: 'rgba(168,85,247,0.20)', border: 'rgba(168,85,247,1)' },
    ];

    const ctx = document.getElementById('skills-coverage').getContext('2d');
    let labels = null;
    const datasets = [];

    const requests = chars.map((c, i) => {
      const url = "{{ route('seatcore::character.view.skills.graph.coverage', ['character' => 'CHAR_ID']) }}"
      .replace('CHAR_ID', c.id);

      return $.getJSON(url).then(payload => {
      if (!labels) {
        labels = payload.labels;
      }

      const d0 = payload.datasets[0] || { data: [] };
      const col = PALETTE[i % PALETTE.length];

      datasets.push({
        label: c.name,
        data: d0.data,
        fill: true,
        backgroundColor: col.bg,
        borderColor: col.border,
        borderWidth: 2,
        pointBackgroundColor: col.border,
        pointBorderColor: '#fff',
        pointRadius: 2,
        pointHoverRadius: 3
      });
      });
    });

    Promise.all(requests).then(() => {
      if (!labels) {
      ctx.canvas.insertAdjacentHTML('beforebegin',
        '<div class="p-3 text-muted">No skills data yet. Open a character’s Skills page to sync, then refresh.</div>');
      return;
      }

      new Chart(ctx, {
      type: 'radar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
        legend: { position: 'top', labels: { boxWidth: 12 } },
        tooltip: {
          callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue}%`
          }
        }
        },
        scales: {
        r: {
          beginAtZero: true,
          suggestedMax: 100,
          grid: { color: '#00000022', lineWidth: 1 },
          angleLines: { color: '#00000022', lineWidth: 1 },
          pointLabels: { color: '#000', font: { size: 10 } },
          ticks: {
          stepSize: 20,
          color: '#000',
          backdropColor: 'transparent',
          showLabelBackdrop: false,
          callback: v => v + '%'
          }
        }
        }
      }
      });
    }).catch(err => {
      console.error('Skills coverage fetch failed:', err);
      ctx.canvas.insertAdjacentHTML('beforebegin',
      '<div class="p-3 text-danger">Failed to load skills coverage.</div>');
    });
    }

    // Call on page load
    document.addEventListener('DOMContentLoaded', function () {
    loadSkillsCoverageChart(@json($skillsChars));
    });

    async function loadUpcomingEvents({
    tableSelector = '#upcoming-events',
    endpoint = '{{ route('osmm.calendar.next') }}',
    limit = 5
    } = {}) {
    const table = document.querySelector(tableSelector);
    if (!table) return;
    const tbody = table.tBodies[0] || table.createTBody();
    tbody.innerHTML = '<tr><td colspan="2">Loading…</td></tr>';

    try {
      const res = await fetch(endpoint, { credentials: 'same-origin' });
      const items = (await res.json()).slice(0, limit);

      if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="2" class="text-muted">No upcoming events</td></tr>';
      return;
      }

      // Format: "Aug 08 2025 06:23" (UTC)
      const fmt = new Intl.DateTimeFormat('en-US', {
      month: 'short', day: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'UTC'
      });

      const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      })[c]);

      tbody.innerHTML = items.map(e => {
      const d = new Date(e.date);
      const dateStr = `${fmt.format(d)} ${String(d.getUTCHours()).padStart(2, '0')}:${String(d.getUTCMinutes()).padStart(2, '0')}`;
      const owner = e.owner ? ` <span class="text-muted">(${esc(e.owner)})</span>` : '';
      return `<tr><td>${dateStr}</td><td>${esc(e.title)}${owner}</td></tr>`;
      }).join('');
    } catch (err) {
      console.warn('calendar load failed', err);
      tbody.innerHTML = '<tr><td colspan="2" class="text-danger">Failed to load events</td></tr>';
    }
    }

    // Example: call once on load
    document.addEventListener('DOMContentLoaded', () => {
    loadUpcomingEvents();
    });
  </script>
  <script>
    google.charts.load('current', { packages: ['treemap'] });
    google.charts.setOnLoadCallback(drawAlloc);

    // Abbreviate ISK in JS (server should send raw numbers)
    function abbreviate(n) {
    if (n == null || isNaN(n)) return '';
    const a = Math.abs(n);
    if (a >= 1e12) return (n / 1e12).toFixed(2).replace(/\.00$/, '') + 't';
    if (a >= 1e9) return (n / 1e9).toFixed(2).replace(/\.00$/, '') + 'b';
    if (a >= 1e6) return (n / 1e6).toFixed(2).replace(/\.00$/, '') + 'm';
    if (a >= 1e3) return (n / 1e3).toFixed(2).replace(/\.00$/, '') + 'k';
    return Math.round(n).toString();
    }

    function drawAlloc() {
    /** nodes: [{ id, parent (or null), label, value (number), color? }] */
    const nodes = @json($allocation['nodes'] ?? []);

    // --- Build quick indexes ---
    const byId = new Map();
    const children = new Map();
    let rootId = null;

    for (const n of nodes) {
      const id = String(n.id);
      const parent = n.parent == null ? null : String(n.parent);
      byId.set(id, { ...n, id, parent, value: Number(n.value || 0) });
      if (parent == null) rootId = id;
      if (!children.has(parent)) children.set(parent, []);
      children.get(parent).push(id);
    }

    if (!rootId) {
      // Try to infer a single root if not explicitly marked
      const allIds = new Set([...byId.keys()]);
      for (const n of byId.values()) if (n.parent) allIds.delete(n.parent);
      rootId = allIds.values().next().value || 'root';
      if (!byId.has(rootId)) byId.set(rootId, { id: rootId, parent: null, label: 'Assets', value: 0 });
    }

    // --- Compute aggregate totals for tooltips (leaf values roll up) ---
    const memo = new Map();
    function totalOf(id) {
      if (memo.has(id)) return memo.get(id);
      const kidIds = children.get(id) || [];
      if (kidIds.length === 0) {
      const v = byId.get(id)?.value || 0;
      memo.set(id, v);
      return v;
      }
      let sum = 0;
      for (const k of kidIds) sum += totalOf(k);
      memo.set(id, sum);
      return sum;
    }
    totalOf(rootId); // prime

    // --- DataTable: Id, Parent, Size, Color, Tooltip ---
    const dt = new google.visualization.DataTable();
    dt.addColumn('string', 'Id');                       // v: machine id, f: display label
    dt.addColumn('string', 'Parent');                   // machine id or null
    dt.addColumn('number', 'Size');                     // area size (leaves should have value > 0; others 0)
    dt.addColumn('number', 'Color');                    // numeric color; we use aggregate total for better contrast
    dt.addColumn({ type: 'string', role: 'tooltip' });  // tooltip text

    const rows = [];
    for (const n of byId.values()) {
      const id = n.id;
      const parent = n.parent === null ? null : n.parent;
      const size = Number(n.value || 0);
      const agg = totalOf(id);
      const color = Number(n.color != null ? n.color : agg);
      const label = n.label || id;
      const tip = `${label} — ISK ${abbreviate(agg)}`;
      rows.push([{ v: id, f: label }, parent, size, color, tip]);
    }
    dt.addRows(rows);

    // --- Draw ---
    const el = document.getElementById('chart_allocation_div');
    if (!el) return;
    const tree = new google.visualization.TreeMap(el);
    tree.draw(dt, {
      minColor: '#cfe0fd',
      midColor: '#a7c3fb',
      maxColor: '#7aa4f7',
      showScale: false,
      headerHeight: 18,
      fontColor: '#111',
      generateTooltip: (row) => dt.getValue(row, 4),
      useWeightedAverageForAggregation: true
    });
    }

    // Redraw on resize (debounced)
    (function () {
    let t = null;
    window.addEventListener('resize', () => {
      clearTimeout(t);
      t = setTimeout(drawAlloc, 150);
    });
    })();
  </script>

@endsection