{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

<div class="container-fluid">
  <div class="row">
    {{-- MAIN --}}
    <div class="col-xl-8">

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
                <script>
                    // Load Google Charts
                    google.charts.load('current', { packages: ['corechart', 'line'] });
                    google.charts.setOnLoadCallback(initChart);

                    function initChart() {

                        const now = new Date();
                        const options = {
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                        hour12: false, timeZone: 'UTC'
                        };
                        const formatted = now.toLocaleString('en-US', options).replace(',', '');
                        document.getElementById('onlinePlayers_lastUpdated').textContent = "last updated: " + formatted;

                        // Draw immediately
                        fetchAndDraw();

                        // Refresh every 60 seconds
                        setInterval(fetchAndDraw, 60000);
                        }

                    function fetchAndDraw() {
                        $.getJSON("{{ route('seatcore::home.chart.serverstatus') }}", function (payload) {
                        const dataTable = new google.visualization.DataTable();

                        // Detect x type: time or index
                        const looksLikeLabels = Array.isArray(payload?.labels) && payload?.datasets?.[0]?.data;
                        const looksLikePoints  = Array.isArray(payload) && payload.length && (payload[0].t !== undefined || payload[0].x !== undefined);

                        // Choose X column type (UTC time if timestamps present, else numeric index)
                        if (looksLikeLabels && isTimestampSeries(payload.labels)) {
                            dataTable.addColumn('datetime', 'Time (UTC)');
                        } else if (looksLikePoints && isTimestampSeries(payload.map(p => p.t ?? p.x))) {
                            dataTable.addColumn('datetime', 'Time (UTC)');
                        } else {
                            dataTable.addColumn('number', 'X');
                        }

                        dataTable.addColumn('number', 'Concurrent Players');

                        // Build rows
                        let rows = [];

                        if (looksLikeLabels) {
                            const xs = payload.labels;
                            const ys = payload.datasets[0].data;
                            const useTime = isTimestampSeries(xs);

                            rows = xs.map((x, i) => [
                            useTime ? toUtcDate(x) : i,
                            toNum(ys[i])
                            ]);
                        } else if (looksLikePoints) {
                            // Supports Chart.js points as {t: ISO/epoch, y: value} or {x, y}
                            rows = payload.map(p => {
                            const xVal = p.t ?? p.x;
                            const useTime = isTimestamp(xVal);
                            return [
                                useTime ? toUtcDate(xVal) : toNum(xVal),
                                toNum(p.y)
                            ];
                            });
                        } else {
                            console.warn('Unexpected payload shape for serverstatus route:', payload);
                        }

                        dataTable.addRows(rows);

                        const options = {
                            legend: 'none',
                            chartArea: {
                                left: 0,
                                top: 0,
                                right: 0,
                                bottom: 0,
                                width: '100%',
                                height: '100%'
                            },
                            hAxis: {
                                textPosition: 'none',   // hide horizontal axis labels
                                gridlines: { count: 0 },
                                baselineColor: 'transparent'
                            },
                            vAxis: {
                                textPosition: 'none',   // hide vertical axis labels
                                gridlines: { count: 0 },
                                baselineColor: 'transparent'
                            },
                            trendlines: { 0: {} }
                            };

                        const chart = new google.visualization.LineChart(document.getElementById('chart_online_players_div'));
                        chart.draw(dataTable, options);
                        });
                    }

                    // --- helpers ---
                    function toNum(v) {
                        const n = Number(v);
                        return Number.isFinite(n) ? n : null;
                    }

                    // Accepts ISO string, epoch ms, or epoch s
                    function toUtcDate(x) {
                        if (x == null) return null;
                        if (typeof x === 'number') {
                        // heuristic: treat 13-digit as ms, 10-digit as seconds
                        const ms = x > 1e12 ? x : (x < 1e11 ? x * 1000 : x);
                        return new Date(ms);
                        }
                        // If backend sends ISO without timezone, force UTC by appending 'Z'
                        const iso = ('' + x).match(/[zZ]|[+-]\d{2}:\d{2}$/) ? x : x + 'Z';
                        return new Date(iso);
                    }

                    function isTimestamp(val) {
                        return (typeof val === 'number') || (typeof val === 'string' && !isNaN(Date.parse(val + (/[zZ]|[+-]\d{2}:\d{2}$/.test(val) ? '' : 'Z'))));
                    }

                    function isTimestampSeries(arr) {
                        if (!Array.isArray(arr) || !arr.length) return false;
                        let checks = 0, hits = 0;
                        for (let i = 0; i < arr.length && checks < 5; i++) {
                        if (arr[i] !== undefined && arr[i] !== null) {
                            checks++;
                            if (isTimestamp(arr[i])) hits++;
                        }
                        }
                        return checks > 0 && hits === checks;
                    }
                    </script>
              
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
                    <script>
                        google.charts.load('current', { packages: ['corechart', 'line'] });
                        google.charts.setOnLoadCallback(initEsiChart);

                        function initEsiChart() {
                        // Draw immediately
                        fetchAndDrawEsi();
                        // Refresh every 60 seconds
                        setInterval(fetchAndDrawEsi, 60000);
                        }

                        function fetchAndDrawEsi() {
                        $.getJSON("{{ route('seatcore::home.chart.serverresponse') }}", function (payload) {
                            const dataTable = new google.visualization.DataTable();

                            // Detect x type: time or index
                            const looksLikeLabels = Array.isArray(payload?.labels) && payload?.datasets?.[0]?.data;
                            const looksLikePoints = Array.isArray(payload) && payload.length && (payload[0].t !== undefined || payload[0].x !== undefined);

                            if (looksLikeLabels && isTimestampSeries(payload.labels)) {
                            dataTable.addColumn('datetime', 'Time (UTC)');
                            } else if (looksLikePoints && isTimestampSeries(payload.map(p => p.t ?? p.x))) {
                            dataTable.addColumn('datetime', 'Time (UTC)');
                            } else {
                            dataTable.addColumn('number', 'X');
                            }

                            dataTable.addColumn('number', 'Response Time (ms)');

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

                            dataTable.addRows(rows);

                            const options = {
                            legend: 'none',
                            chartArea: { left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%' },
                            hAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
                            vAxis: { textPosition: 'none', gridlines: { count: 0 }, baselineColor: 'transparent', ticks: [] },
                            lineWidth: 1,
                            pointSize: 0,                            
                            trendlines: { 0: {} }
                            };

                            new google.visualization.LineChart(document.getElementById('chart_esi_response_div'))
                            .draw(dataTable, options);

                            // Update "last updated" text
                            const now = new Date();
                            document.getElementById('esi-last-updated').textContent = `Updated ${now.toUTCString()}`;
                        });
                        }

                        // --- helpers ---
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
                        return (typeof val === 'number') || (typeof val === 'string' && !isNaN(Date.parse(val + (/[zZ]|[+-]\d{2}:\d{2}$/.test(val) ? '' : 'Z'))));
                        }

                        function isTimestampSeries(arr) {
                        if (!Array.isArray(arr) || !arr.length) return false;
                        let checks = 0, hits = 0;
                        for (let i = 0; i < arr.length && checks < 5; i++) {
                            if (arr[i] !== undefined && arr[i] !== null) {
                            checks++;
                            if (isTimestamp(arr[i])) hits++;
                            }
                        }
                        return checks > 0 && hits === checks;
                        }

                    </script>
                </div>
            </div>
        </div>
    </div>

      {{-- MONTHLY KILLMAILS / MINING --}}
      <div class="row">
        <div class="col-lg-6 mb-3">
          <div class="card">
            <div class="card-header font-weight-bold">Monthly Killmails</div>
            <div class="card-body">
              <div class="bg-light border rounded w-100" style="height:150px;"></div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-3">
          <div class="card">
            <div class="card-header font-weight-bold">Monthly Mining</div>
            <div class="card-body">
              <div class="bg-light border rounded w-100" style="height:150px;"></div>
            </div>
          </div>
        </div>
      </div>

      {{-- TOTAL MONTHLY WALLET / WALLET BALANCES --}}
      <div class="row">
        <div class="col-lg-6 mb-3">
          <div class="card">
            <div class="card-header font-weight-bold">Total Monthly Wallet</div>
            <div class="card-body">
              <div class="bg-light border rounded w-100" style="height:150px;"></div>
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

@endsection
