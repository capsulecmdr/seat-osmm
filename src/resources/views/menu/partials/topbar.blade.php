@php
  $content = (osmm_setting('osmm_branding_sidebar_html') ?? "");
@endphp

<nav class="main-header navbar navbar-expand-lg navbar-dark navbar-gray" style="margin-left:0!important">
  <!-- Brand -->
  <a class="navbar-brand brand-link d-flex align-items-center mr-2" href="/home">
    {!! $content !!}
  </a>

  <!-- Hamburger -->
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#osmmNavbar"
          aria-controls="osmmNavbar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Left: Dynamic Menu (collapses on < lg) -->
  <div class="collapse navbar-collapse" id="osmmNavbar">
    <ul class="navbar-nav navbar-nav-scroll flex-nowrap">
      @foreach($menu as $key => $item)
        @include('seat-osmm::menu.partials.osmm.topbar-node', [
          'item' => $item,
          'key'  => $key,
          'can'  => $can ?? null,
        ])
      @endforeach
    </ul>

    <!-- On mobile, push right-tools below menu; on lg+ keep to the far right -->
    <ul class="navbar-nav ml-lg-auto mt-2 mt-lg-0 align-items-center">
      <li class="nav-item mr-2">
        <span class="badge bg-secondary war-inactive" title="No Active War">
          <img src="https://wiki.eveuniversity.org/images/thumb/3/3d/Wars.png/32px-Wars.png"
               alt="War status" width="16" height="16" class="align-text-top">
          Peace
        </span>
      </li>

      <li class="nav-item d-none d-md-block">
        <form action="{{ route('seatcore::support.search') }}" method="get" class="form-inline ml-2">
          <div class="input-group input-group-sm">
            <input type="text" name="q" class="form-control form-control-navbar"
                   placeholder="{{ trans('web::seat.search') }}...">
            <div class="input-group-append">
              <button type="submit" id="search-btn" class="btn btn-navbar">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </div>
        </form>
      </li>

      @if(session('impersonation_origin', false))
        <li class="nav-item">
          <a href="{{ route('seatcore::configuration.users.impersonate.stop') }}"
             class="nav-link" title="{{ trans('web::seat.stop_impersonation') }}">
            <i class="fa fa-user-secret"></i>
          </a>
        </li>
      @endif

      @can('global.queue_manager')
        <li class="nav-item">
          <a href="{{ route('horizon.index') }}" class="nav-link" title="{{ trans('web::seat.queued') }}" target="_blank">
            <i class="fas fa-truck"></i>
            <span class="badge badge-success navbar-badge" id="queue_count">0</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ route('horizon.index') }}" class="nav-link" title="{{ trans('web::seat.error') }}" target="_blank">
            <i class="fas fa-exclamation"></i>
            <span class="badge badge-danger navbar-badge" id="error_count">0</span>
          </a>
        </li>
      @endcan

      <li class="nav-item dropdown">
        <a class="nav-link" id="characterMenu" data-toggle="dropdown" href="#" aria-expanded="false">
          {!! img('characters', 'portrait', auth()->user()->main_character_id, 64, ['class' => 'img-circle elevation-2', 'alt' => 'User Image'], false) !!}
        </a>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg">
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
              <i class="fas fa-sign-out-alt"></i> {{ trans('web::seat.sign_out') }}
            </button>
          </form>
        </div>
      </li>
    </ul>
  </div>
</nav>

<style>
  /* brand content shouldn’t stretch the bar */
  .navbar .brand-link img, .navbar .brand-link svg { max-height: 28px; }
  .navbar .brand-link { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  /* prevent long menus from wrapping; allow swipe/scroll */
  .navbar-nav-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .navbar-nav-scroll > .nav-item { white-space: nowrap; }
  /* make dropdowns usable inside a scroll container */
  .navbar-nav-scroll .dropdown-menu { position: absolute; }

  /* avatar size */
  #characterMenu img { width: 2.1rem; height: auto; }
</style>
