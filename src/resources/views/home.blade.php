{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')
<script>
    console.log(@json($publicInfo));
    console.log(@json($blueprints));
</script>
  <div class="container-fluid">
    <div class="row">
    {{-- MAIN --}}
    <div class="col-xl-8">
      {{-- CURRENT TIME / DOWNTIME --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header font-weight-bold">Current Eve Time</div>
        <div class="card-body">
          <div id="cc-time"
          class="d-flex align-items-center justify-content-center bg-light border rounded text-monospace"
          style="height:86px;">
          Aug 8 2025 14:25:53
          </div>
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
          </script>
        </div>
        </div>
      </div>

      <div class="col-lg-6 mb-3">
        <div class="card">
        <div class="card-header font-weight-bold">Downtime</div>
        <div class="card-body">
          <div id="dt-time"
          class="d-flex align-items-center justify-content-center bg-light border rounded text-monospace"
          style="height:86px;">
          T minus 12:35:04
          </div>
          <script>
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
              `T minus ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
          })();
          </script>
        </div>
        </div>
      </div>
      </div>

      {{-- Put this where you want the indicator to appear --}}
      <div class="war-indicator" title="{{ $inWar ? 'Active War' : 'No Active War' }}" aria-label="War Status">
        <img
          src="https://wiki.eveuniversity.org/images/thumb/3/3d/Wars.png/32px-Wars.png"
          alt="War status icon"
          class="war-icon {{ $inWar ? 'war-active' : 'war-inactive' }}"
          width="24" height="24"
        />
        <span class="war-label {{ $inWar ? 'text-danger' : 'text-muted' }}">
          {{ $inWar ? 'At War' : 'Peace' }}
        </span>
      </div>

      @push('styles')
      <style>
        .war-indicator {
          display: inline-flex;
          align-items: center;
          gap: .5rem;
        }
        .war-icon {
          display: inline-block;
          border-radius: 6px;
          transition: filter .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        /* Muted state */
        .war-inactive {
          filter: grayscale(1) opacity(.45);
        }
        /* “Illuminated” red state */
        .war-active {
          filter: none;
          box-shadow:
            0 0 0 2px rgba(220, 53, 69, .25),
            0 0 10px rgba(220, 53, 69, .85),
            0 0 18px rgba(220, 53, 69, .55);
          transform: translateZ(0); /* crisp glow */
        }
        /* Optional subtle pulse when at war */
        .war-active {
          animation: warPulse 1.6s ease-in-out infinite;
        }
        @keyframes warPulse {
          0%, 100% { box-shadow:
            0 0 0 2px rgba(220, 53, 69, .25),
            0 0 10px rgba(220, 53, 69, .85),
            0 0 18px rgba(220, 53, 69, .55); }
          50% { box-shadow:
            0 0 0 2px rgba(220, 53, 69, .35),
            0 0 14px rgba(220, 53, 69, 1),
            0 0 26px rgba(220, 53, 69, .65); }
        }
        .war-label { font-weight: 600; font-size: .9rem; }
      </style>
      @endpush


      <hr class="my-4">

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
          <small class="text-muted">month to date</small>
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
          <small class="text-muted">Avg/day: ISK
          {{ number_format((int) round($mining['avg_isk_per_day'] ?? 0)) }}</small>
          <small class="text-muted">MTD: ISK {{ number_format((int) round($mining['mtd_isk'] ?? 0)) }}</small>
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
          <small class="text-muted">
          Today: ISK {{ number_format((int) round($walletBalance30['today'] ?? 0)) }}
          </small>
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
        <div class="card">
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
      (function(){
        const listEl = document.getElementById('todo-list');
        const input  = document.getElementById('todo-input');
        const btn    = document.getElementById('todo-create');

        // helpers
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const HEADERS_JSON = {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf};
        const ENDPOINTS = {
          index:  '{{ route('osmm.todos.index') }}',
          store:  '{{ route('osmm.todos.store') }}',
          destroy: id => '{{ url('osmm/todos') }}/' + id,
        };

        function rowTemplate(item){
          const id  = 'todo-' + item.id;
          const txt = (item.text || '').replace(/[&<>"]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]));
          return `
            <div class="custom-control custom-checkbox mb-1" data-id="${item.id}">
              <input type="checkbox" class="custom-control-input" id="${id}">
              <label class="custom-control-label" for="${id}">${txt}</label>
            </div>`;
        }

        async function loadTodos(){
          listEl.innerHTML = `<div class="text-muted small">Loading…</div>`;
          try{
            const res = await fetch(ENDPOINTS.index, {credentials:'same-origin'});
            const items = await res.json();
            listEl.innerHTML = items.length
              ? items.map(rowTemplate).join('')
              : `<div class="text-muted small">No tasks yet.</div>`;
          }catch(e){
            listEl.innerHTML = `<div class="text-danger small">Failed to load tasks.</div>`;
            console.warn(e);
          }
        }

        async function createTodo(){
          const text = (input.value || '').trim();
          if(!text) return;
          btn.disabled = true;
          try{
            const res = await fetch(ENDPOINTS.store, {
              method: 'POST',
              headers: HEADERS_JSON,
              credentials:'same-origin',
              body: JSON.stringify({ text })
            });
            if(res.ok){
              input.value = '';
              await loadTodos();
            }
          }catch(e){ console.warn(e); }
          finally{ btn.disabled = false; }
        }

        // Event: create on click / Enter key
        btn?.addEventListener('click', createTodo);
        input?.addEventListener('keydown', e => { if(e.key === 'Enter') createTodo(); });

        // Event: delete on checkbox click (event delegation)
        listEl.addEventListener('change', async (e) => {
          const cb = e.target.closest('input[type="checkbox"].custom-control-input');
          if(!cb) return;
          const wrap = cb.closest('[data-id]');
          const id = wrap?.getAttribute('data-id');
          if(!id) return;

          // optional: quick UI feedback
          wrap.style.opacity = '0.6';

          try{
            const res = await fetch(ENDPOINTS.destroy(id), {
              method: 'DELETE',
              headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf},
              credentials:'same-origin'
            });
            if(res.status === 204){
              wrap.remove();
              if(!listEl.children.length){
                listEl.innerHTML = `<div class="text-muted small">No tasks yet.</div>`;
              }
            }else{
              cb.checked = false; // revert
              wrap.style.opacity = '1';
            }
          }catch(err){
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

      const looksLikeLabels = Array.isArray(payload?.labels) && payload?.datasets?.[0]?.data;
      const looksLikePoints = Array.isArray(payload) && payload.length && (payload[0].t !== undefined || payload[0].x !== undefined);

      if (looksLikeLabels && isTimestampSeries(payload.labels)) {
      dt.addColumn('datetime', 'Time (UTC)');
      } else if (looksLikePoints && isTimestampSeries(payload.map(p => p.t ?? p.x))) {
      dt.addColumn('datetime', 'Time (UTC)');
      } else {
      dt.addColumn('number', 'X');
      }
      dt.addColumn('number', 'Concurrent Players');

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

      const chart = new google.visualization.LineChart(onlineEl);
      google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
      chart.draw(dt, baseOptions);

      // Update last updated (UTC hh:mm:ss)
      const now = new Date();
      document.getElementById('onlinePlayers_lastUpdated').textContent =
      'Updated ' + now.toUTCString();
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

      const chart = new google.visualization.LineChart(esiEl);
      google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
      chart.draw(dt, baseOptions);

      const now = new Date();
      document.getElementById('esi-last-updated').textContent =
      'Updated ' + now.toUTCString();
    });


    }

    google.charts.setOnLoadCallback(drawWaterfall);

    function drawWaterfall() {
    const days = @json($km['days']);       // [1..EOM]
    const cumWins = @json($km['cum_wins']);   // cumulative wins by day index
    const cumTotal = @json($km['cum_total']);  // cumulative (wins + losses)

    // Derive per-day wins, totals, losses, and net delta (wins - losses)
    const perWins = [];
    const perTotal = [];
    const perLoss = [];
    const delta = [];

    for (let i = 0; i < days.length; i++) {
      const w = cumWins[i] - (i > 0 ? cumWins[i - 1] : 0);
      const t = cumTotal[i] - (i > 0 ? cumTotal[i - 1] : 0);
      const l = Math.max(0, t - w);         // guard against negatives
      const d = w - l;                      // net for the day

      perWins.push(w);
      perTotal.push(t);
      perLoss.push(l);
      delta.push(d);
    }

    // Waterfall candlestick: [label, low, open, close, high]
    // where open = running total before the day, close = after applying delta
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
      const low = Math.min(open, close);
      const high = Math.max(open, close);

      data.addRow([String(days[i]), low, open, close, high]);

      running = close; // advance
    }

    // Optional: add a final "Total" bar
    data.addRow(['Total', Math.min(0, running), 0, running, Math.max(0, running)]);

    const options = {
      legend: 'none',
      chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
      bar: { groupWidth: '85%' },
      hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
      vAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
      candlestick: {
      hollowIsRising: false,               // solid up bars
      fallingColor: { strokeWidth: 0, fill: '#ef4444' }, // red for net losses
      risingColor: { strokeWidth: 0, fill: '#22c55e' }  // green for net wins
      }
    };

    const chart = new google.visualization.CandlestickChart(document.getElementById('waterfall_div'));
    google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
    chart.draw(data, options);
    }

    google.charts.setOnLoadCallback(drawMining);

    function drawMining() {
    const days = @json($mining['days']);
    const asteroid = @json($mining['asteroid']);
    const ice = @json($mining['ice']);
    const moon = @json($mining['moon']);
    const cumISK = @json($mining['cum_isk']);

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
      isStacked: false,            // grouped bars
      seriesType: 'bars',
      bar: { groupWidth: '75%' },
      hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
      vAxes: { 0: { textPosition: 'none' }, 1: { textPosition: 'none' } },
      series: {
      3: { type: 'line', targetAxisIndex: 1 } // line uses right axis
      },
      trendlines: { 3: {} }
    };

    const el = document.getElementById('chart_mining_div');
    const chart = new google.visualization.ComboChart(el);
    google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
    chart.draw(data, options);
    }

    google.charts.setOnLoadCallback(drawWalletBalance30);

    function drawWalletBalance30() {
    const balances = @json($walletBalance30['balances']); // absolute totals
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
    }

    google.charts.setOnLoadCallback(drawWallets);

    function drawWallets() {
    const rows = @json($walletByChar['rows']); // [ [name, balance], ... ]

    const data = new google.visualization.DataTable();
    data.addColumn('string', 'Character');
    data.addColumn('number', 'Wallet (ISK)');
    data.addRows(rows);

    // Format ISK with commas, no decimals
    new google.visualization.NumberFormat({
      prefix: 'ISK ',
      groupingSymbol: ',',
      fractionDigits: 0
    }).format(data, 1);

    const options = {
      legend: { position: 'none' },
      bar: { groupWidth: '70%' },
      chartArea: { left: 0, right: 0, top: 10, bottom: 0, width: '100%', height: '100%' },
      hAxis: { textStyle: { fontSize: 10 } },   // show names
      vAxis: { minValue: 0, textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent' }
    };

    const chart = new google.visualization.ColumnChart(document.getElementById('chart_wallet_by_char_div'));
    google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
    chart.draw(data, options);
    }

    google.charts.load('current', { packages: ['treemap'] });
    google.charts.setOnLoadCallback(drawAlloc);

    function abbreviate(n) {
    if (n === null || n === undefined) return '';
    const abs = Math.abs(n);
    if (abs >= 1e12) return (n / 1e12).toFixed(1).replace(/\.0$/, '') + 't';
    if (abs >= 1e9) return (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'b';
    if (abs >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'm';
    if (abs >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
    return Math.round(n).toString();
    }

    function drawAlloc() {
    const leaves = @json($allocation['leaves']); // [{label, loc, isk}, ...]

    // 1) Totals per location
    const totals = {};
    for (const { loc, isk } of leaves) {
      totals[loc] = (totals[loc] || 0) + (isk || 0);
    }

    // 2) Final parent labels with abbreviated totals
    const locLabel = {};
    Object.keys(totals).forEach(loc => {
      locLabel[loc] = `${loc} — ISK ${abbreviate(totals[loc])}`;
    });

    // 3) Build DataTable
    const dt = new google.visualization.DataTable();
    dt.addColumn('string', 'Node');
    dt.addColumn('string', 'Parent');
    dt.addColumn('number', 'Total ISK (size)');
    dt.addColumn('number', 'Color');

    const rows = [];
    rows.push(['Assets', null, 0, 0]); // root

    Object.keys(totals).forEach(loc => {
      rows.push([locLabel[loc], 'Assets', 0, 0]); // container nodes
    });

    leaves.forEach(({ label, loc, isk }) => {
      rows.push([`${label} — ISK ${abbreviate(isk)}`, locLabel[loc], Number(isk) || 0, 0]);
    });

    dt.addRows(rows);

    const tree = new google.visualization.TreeMap(document.getElementById('chart_allocation_div'));
    tree.draw(dt, {
      minColor: '#63a4ff',
      midColor: '#63a4ff',
      maxColor: '#63a4ff',
      showScale: false,
      headerHeight: 18,
      fontColor: '#111',
      generateTooltip: (row, size, value) => {
      // Custom tooltip with full numbers and location nesting
      const node = dt.getValue(row, 0);
      const parent = dt.getValue(row, 1);
      const val = dt.getValue(row, 2);
      return `<div style="padding:6px 8px;font-size:12px">
            <div><strong>${node}</strong></div>
            <div>Value: ISK ${val.toLocaleString()}</div>
            </div>`;
      }
    });
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

@endsection