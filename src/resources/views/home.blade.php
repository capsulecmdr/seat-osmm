{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')

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
            <div class="card-header font-weight-bold">Online Players</div>
            <div class="card-body">
              <div class="bg-light border rounded w-100" style="height:150px;"></div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-3">
          <div class="card">
            <div class="card-header font-weight-bold">ESI Response Times</div>
            <div class="card-body">
              <div class="bg-light border rounded w-100" style="height:150px;"></div>
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
