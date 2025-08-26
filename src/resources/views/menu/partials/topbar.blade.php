{{-- Horizontal navbar preview for a menu tree
<div class="bg-light border-bottom">
  <nav class="navbar navbar-expand navbar-light px-2 py-0">
    <ul class="navbar-nav">
      @foreach($menu as $key => $item)
        @include('seat-osmm::menu.partials.osmm.topbar-node', [
          'item' => $item,
          'key'  => $key,
          'can'  => $can ?? null,
        ])
      @endforeach
    </ul>
  </nav>
</div> --}}

<nav class="main-header navbar navbar-expand navbar-dark navbar-gray" style="margin-left:0px !important">
  <!-- Brand -->
  <a class="navbar-brand d-flex align-items-center" href="/home">
    <img class="brand-image" src="https://anvil.capsulecmdr.com/storage/blackanvilsocietyicon2.png"
         alt="Black Anvil Society" style="height:25px">
  </a>
  <span class="navbar-text mr-3">
    <b style="color:red">B</b>lack <b style="color:red">Anvil</b> <b style="color:red">S</b>ociety
  </span>

  <!-- Toggler -->
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#osmmNavbar"
          aria-controls="osmmNavbar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Left: Dynamic Menu -->
  <div class="collapse navbar-collapse" id="osmmNavbar">
    <ul class="navbar-nav">
      @foreach($menu as $key => $item)
        @include('seat-osmm::menu.partials.osmm.topbar-node', [
          'item' => $item,
          'key'  => $key,
          'can'  => $can ?? null,
        ])
      @endforeach
    </ul>

  <!-- Right: User -->
  <ul class="navbar-nav ml-auto">
    <li>
      <!-- War badge + clocks -->
    <span class="badge bg-secondary war-inactive mr-sm" title="No Active War">
      <img src="https://wiki.eveuniversity.org/images/thumb/3/3d/Wars.png/32px-Wars.png"
           alt="War status" width="16" height="16" class="align-text-top">
      Peace
    </span>
    </li>

    <li>
<!-- Search -->
  <form action="{{ route('seatcore::support.search') }}" method="get" class="form-inline ml-3">
    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-navbar" placeholder="{{ trans('web::seat.search') }}...">
      <div class="input-group-append">
        <button type="submit" id="search-btn" class="btn btn-navbar">
          <i class="fas fa-search"></i>
        </button>
      </div>
    </div>
  </form>
    </li>
    <!-- Impersonation information -->
    @if(session('impersonation_origin', false))

        <li class="nav-item dropdown">
          <a href="{{ route('seatcore::configuration.users.impersonate.stop') }}"
            class="nav-link" data-widget="dropdown" data-placement="bottom"
            title="{{ trans('web::seat.stop_impersonation') }}">
            <i class="fa fa-user-secret"></i>
          </a>
        </li>
    @endif

      <!-- Queue information -->
      @can('global.queue_manager')
        <li class="nav-item dropdown">
          <a href="{{ route('horizon.index') }}" class="nav-link" data-widget="dropdown" data-placement="bottom"
            title="{{ trans('web::seat.queued') }}" target="_blank">
            <i class="fas fa-truck"></i>
            <span class="badge badge-success navbar-badge" id="queue_count">0</span>
          </a>
        </li>
        <li class="nav-item dropdown">
          <a href="{{ route('horizon.index') }}" class="nav-link" data-widget="dropdown" data-placement="bottom"
            title="{{ trans('web::seat.error') }}" target="_blank">
            <i class="fas fa-exclamation"></i>
            <span class="badge badge-danger navbar-badge" id="error_count">0</span>
          </a>
        </li>
      @endcan

    <li class="nav-item dropdown">
      <a class="nav-link" id="characterMenu" data-toggle="dropdown" href="#" aria-expanded="false">
        {!! img('characters', 'portrait', auth()->user()->main_character_id, 64, ['class' => 'img-circle elevation-2', 'alt' => 'User Image'], false) !!}
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        @if(auth()->user()->name != 'admin')
          <a href="{{ route('seatcore::profile.view') }}" class="dropdown-item">
            <i class="fas fa-id-card"></i> {{ trans('web::seat.profile') }}
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item" data-toggle="modal" data-target="#characterSwitchModal">
            <i class="fas fa-exchange-alt"></i> {{ trans('web::seat.switch_character') }}
          </a>
          <div class="dropdown-divider"></div>
          <a href="{{ route('seatcore::auth.eve') }}" class="dropdown-item">
            <i class="fas fa-link"></i> {{ trans('web::seat.link_character') }}
          </a>
          <div class="dropdown-divider"></div>
        @endif
        <form action="{{ route('seatcore::auth.logout') }}" method="post">
          {{ csrf_field() }}
          <button type="submit" class="btn btn-link dropdown-item">
            <i class="fas fa-sign-out-alt"></i>
            {{ trans('web::seat.sign_out') }}
          </button>
        </form>
      </div>
    </li>
  </ul>
</nav>
<style>
  #characterMenu img{
    height:auto;
    width: 2.1rem;
  }
</style>