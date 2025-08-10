{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')

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
        <div id="cc-time" class="d-flex align-items-center justify-content-center bg-light border rounded text-monospace" style="height:86px;">
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
        <div id="dt-time" class="d-flex align-items-center justify-content-center bg-light border rounded text-monospace" style="height:86px;">
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

      {{-- ALERT BANNER --}}
      @if($atWar === false)
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-crosshairs mr-2"></i>
      You are currently At War!
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
      </button>
      </div>
      @endif

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
        <small class="text-muted">Avg/day: ISK {{ number_format((int) round($mining['avg_isk_per_day'] ?? 0)) }}</small>
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
      <div class="card-header font-weight-bold">Wallet Balances</div>
      <div class="card-body">
        <div class="bg-light border rounded w-100" style="height:150px;"></div>
      </div>
      </div>
      </div>
      </div>

      {{-- BOTTOM BIG PANELS: SKILLS / ALLOCATION MAP --}}
      <div class="row">
      <div class="col-lg-6 mb-3">
      <div class="card">
      <div class="card-header font-weight-bold">Skills Coverage</div>
      <div class="card-body">
        <div class="bg-light border rounded w-100" style="height:420px;"></div>
      </div>
      </div>
      </div>
      <div class="col-lg-6 mb-3">
      <div class="card">
      <div class="card-header font-weight-bold">Allocation Map</div>
      <div class="card-body">
        <div class="bg-light border rounded w-100" style="height:420px;"></div>
      </div>
      </div>
      </div>
      </div>
    </div>

    {{-- RIGHT SIDEBAR --}}
    <aside class="col-xl-4">
      {{-- Upcoming Events --}}
      <div class="card mb-3">
      <div class="card-header font-weight-bold">Upcoming Events</div>
      <div class="card-body p-0">
      <table class="table table-sm mb-0">
      <thead class="thead-light">
        <tr><th style="width:40%">Date</th><th>Event</th></tr>
      </thead>
      <tbody>
        <tr><td>8/8/25 06:23</td><td>PVP Fleet</td></tr>
        <tr><td>8/8/25 06:23</td><td>Mining Day</td></tr>
        <tr><td>8/8/25 06:23</td><td>PI Runs</td></tr>
        <tr><td>8/8/25 06:23</td><td>PI Runs</td></tr>
        <tr><td>8/8/25 06:23</td><td>Merc Dens</td></tr>
      </tbody>
      </table>
      </div>
      </div>

      {{-- ToDo List --}}
      <div class="card mb-3">
      <div class="card-header font-weight-bold">ToDo List</div>
      <div class="card-body">
      <div class="input-group input-group-sm mb-2">
      <input class="form-control" placeholder="New task to do">
      <div class="input-group-append">
        <button class="btn btn-primary" type="button">Create</button>
      </div>
      </div>
      <div class="custom-control custom-radio mb-1">
      <input type="radio" id="t1" name="todo" class="custom-control-input">
      <label class="custom-control-label" for="t1">Task #1</label>
      </div>
      <div class="custom-control custom-radio mb-1">
      <input type="radio" id="t2" name="todo" class="custom-control-input">
      <label class="custom-control-label" for="t2">Task #2</label>
      </div>
      <div class="custom-control custom-radio mb-1">
      <input type="radio" id="t3" name="todo" class="custom-control-input">
      <label class="custom-control-label" for="t3">Task #3</label>
      </div>
      <div class="custom-control custom-radio mb-1">
      <input type="radio" id="t4" name="todo" class="custom-control-input">
      <label class="custom-control-label" for="t4">Task #4</label>
      </div>
      </div>
      </div>

      {{-- Unread Mail --}}
      <div class="card">
      <div class="card-header font-weight-bold">Unread Mail</div>
      <div class="card-body p-0">
      <table class="table table-sm mb-0">
      <thead class="thead-light">
        <tr><th style="width:40%">Received</th><th>Subject</th></tr>
      </thead>
      <tbody>
        <tr><td>8/8/25 06:23</td><td>Re: That thing we talked about</td></tr>
        <tr><td>8/8/25 06:23</td><td>Re: That thing we talked about</td></tr>
        <tr><td>8/8/25 06:23</td><td>Re: That thing we talked about</td></tr>
        <tr><td>8/8/25 06:23</td><td>Re: That thing we talked about</td></tr>
      </tbody>
      </table>
      </div>
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
        chartArea: { left:0, top:0, right:0, bottom:0, width:'100%', height:'100%' },
        isStacked: false,            // grouped bars
        seriesType: 'bars',
        bar: { groupWidth: '75%' },
        hAxis: { textPosition:'none', gridlines:{ count:0 }, baselineColor:'transparent', ticks:[] },
        vAxes: { 0: { textPosition:'none' }, 1: { textPosition:'none' } },
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
        chartArea: { left:0, top:0, right:0, bottom:0, width:'100%', height:'100%' },
        hAxis: { textPosition:'none', gridlines:{count:0}, baselineColor:'transparent', ticks:[] },
        vAxis: { textPosition:'none', gridlines:{count:0}, baselineColor:'transparent', ticks:[] },
        lineWidth: 1,
        pointSize: 0,
        trendlines: { 0: {} }
      };

      const el = document.getElementById('chart_wallet_balance_30d');
      const chart = new google.visualization.LineChart(el);
      google.visualization.events.addListener(chart, 'ready', () => chart.setSelection([]));
      chart.draw(dt, opts);
    }

    </script>

@endsection
