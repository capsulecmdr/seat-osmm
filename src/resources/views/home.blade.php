{{-- resources/views/home.blade.php --}}
@extends('web::layouts.app')

@section('page_title', 'Home')

@section('content')
<style>
  /* Layout */
  :root {
    --sidebar-w: 340px;
    --gutter: 18px;
    --panel-h: 150px;           /* small chart panels */
    --panel-h-tall: 420px;      /* large bottom panels */
  }
  .home-wrap {
    position: relative;
  }
  .home-main {
    /* leave room for fixed right rail */
    width: calc(100% - var(--sidebar-w) - var(--gutter));
    margin-right: calc(var(--sidebar-w) + var(--gutter));
  }
  .home-rail {
    position: absolute;
    top: 0;
    right: 0;
    width: var(--sidebar-w);
  }

  /* Card chrome to match screenshot */
  .cc-card { border: 1px solid #d8d8d8; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
  .cc-head {
    background: #106cd6;           /* bright Eve-blue-ish */
    color: #fff;
    font-weight: 700;
    font-size: 1.05rem;
    padding: .5rem .75rem;
    border-bottom: 1px solid #0f5fbf;
  }
  .cc-body { background: #fff; padding: .75rem; }

  /* “tile” look in top time boxes */
  .cc-time {
    display:flex; align-items:center; justify-content:center;
    height: 86px; border:1px solid #e6e6e6; border-radius:6px; background:#f7f7f7;
    font-size: 1.05rem;
  }

  /* Placeholders for charts/treemaps/etc */
  .ph {
    height: var(--panel-h);
    background: repeating-linear-gradient(
      -45deg, #f5f5f5, #f5f5f5 10px, #f0f0f0 10px, #f0f0f0 20px
    );
    border:1px solid #e6e6e6;
    border-radius:4px;
  }
  .ph.tall { height: var(--panel-h-tall); }
  .mb-18 { margin-bottom: var(--gutter) !important; }

  /* Small tables */
  .table-sm th, .table-sm td { padding: .25rem .5rem; }
  .rail-card .cc-body { padding: 0; }
  .rail-card .table { margin-bottom: 0; }

  /* Tiny “divider” like screenshot */
  .thin-divider { border-top: 2px solid #dcdcdc; margin: .75rem 0 1rem; }
  @media (max-width: 1199.98px) {
    .home-main { width: 100%; margin-right: 0; }
    .home-rail { position: static; width: 100%; }
  }
</style>

<div class="home-wrap">

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

  <div class="home-main">
    {{-- CURRENT TIME / DOWNTIME --}}
    <div class="row mb-18">
      <div class="col-lg-6 mb-3 mb-lg-0">
        <div class="cc-card">
          <div class="cc-head">Current Eve Time</div>
          <div class="cc-body">
            <div class="cc-time">Aug 8 2025 14:25:53</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="cc-card">
          <div class="cc-head">Downtime</div>
          <div class="cc-body">
            <div class="cc-time">T minus 12:35:04</div>
          </div>
        </div>
      </div>
    </div>

    <div class="thin-divider"></div>

    {{-- ONLINE / ESI --}}
    <div class="row mb-18">
      <div class="col-lg-6 mb-3 mb-lg-0">
        <div class="cc-card">
          <div class="cc-head">Online Players</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="cc-card">
          <div class="cc-head">ESI Response Times</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
    </div>

    {{-- MONTHLY KILLMAILS / MINING --}}
    <div class="row mb-18">
      <div class="col-lg-6 mb-3 mb-lg-0">
        <div class="cc-card">
          <div class="cc-head">Monthly Killmails</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="cc-card">
          <div class="cc-head">Monthly Mining</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
    </div>

    {{-- TOTAL MONTHLY WALLET / WALLET BALANCES --}}
    <div class="row mb-18">
      <div class="col-lg-6 mb-3 mb-lg-0">
        <div class="cc-card">
          <div class="cc-head">Total Monthly Wallet</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="cc-card">
          <div class="cc-head">Wallet Balances</div>
          <div class="cc-body"><div class="ph"></div></div>
        </div>
      </div>
    </div>

    {{-- BOTTOM BIG PANELS: SKILLS / ALLOCATION MAP --}}
    <div class="row mb-3">
      <div class="col-lg-6 mb-3 mb-lg-0">
        <div class="cc-card">
          <div class="cc-head">Skills Coverage</div>
          <div class="cc-body"><div class="ph tall"></div></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="cc-card">
          <div class="cc-head">Allocation Map</div>
          <div class="cc-body"><div class="ph tall"></div></div>
        </div>
      </div>
    </div>
  </div>

  {{-- RIGHT SIDEBAR --}}
  <aside class="home-rail">
    {{-- Upcoming Events --}}
    <div class="cc-card rail-card mb-18">
      <div class="cc-head">Upcoming Events</div>
      <div class="cc-body">
        <table class="table table-sm">
          <thead>
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
    <div class="cc-card mb-18">
      <div class="cc-head">ToDo List</div>
      <div class="cc-body">
        <div class="p-2">
          <div class="input-group input-group-sm mb-2">
            <input class="form-control" placeholder="New task to do">
            <div class="input-group-append">
              <button class="btn btn-primary">Create</button>
            </div>
          </div>
          <div class="custom-control custom-radio mb-1">
            <input type="radio" id="t1" name="todo" class="custom-control-input">
            <label class="custom-control-label" for="t1">Task #1</label>
          </div>
          <div class="custom-control custom-radio mb-1">
            <input type="radio" id="t2" name="todo" class="custom-control-input">
            <label class="custom-control-label" for="t2">Task #1</label>
          </div>
          <div class="custom-control custom-radio mb-1">
            <input type="radio" id="t3" name="todo" class="custom-control-input">
            <label class="custom-control-label" for="t3">Task #1</label>
          </div>
          <div class="custom-control custom-radio mb-1">
            <input type="radio" id="t4" name="todo" class="custom-control-input">
            <label class="custom-control-label" for="t4">Task #1</label>
          </div>
        </div>
      </div>
    </div>

    {{-- Unread Mail --}}
    <div class="cc-card rail-card">
      <div class="cc-head">Unread Mail</div>
      <div class="cc-body">
        <table class="table table-sm">
          <thead>
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
@endsection
