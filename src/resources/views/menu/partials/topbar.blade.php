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


    <!-- War badge + clocks -->
    <span class="badge bg-secondary war-inactive mr-sm" title="No Active War">
      <img src="https://wiki.eveuniversity.org/images/thumb/3/3d/Wars.png/32px-Wars.png"
           alt="War status" width="16" height="16" class="align-text-top">
      Peace
    </span>
    <span class="navbar-text">
      <span id="cc-time" class="mr-sm">Aug 22 2025, 21:46:47</span> |
      <sub><span id="dt-time">DT in T- 13:13:12</span></sub>
    </span>
  </div>

  <!-- Search -->
  <form action="https://anvil.capsulecmdr.com/support/search" method="get" class="form-inline ml-3">
    <div class="input-group input-group-sm">
      <input type="text" name="q" class="form-control form-control-navbar" placeholder="Search...">
      <div class="input-group-append">
        <button type="submit" id="search-btn" class="btn btn-navbar">
          <i class="fas fa-search"></i>
        </button>
      </div>
    </div>
  </form>

  <!-- Right: User -->
  <ul class="navbar-nav ml-auto">
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
        <img src="//images.evetech.net/characters/2117189532/portrait?size=64"
             class="img-circle elevation-2" alt="User Image" style="height:25px">
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <a href="https://anvil.capsulecmdr.com/profile/settings" class="dropdown-item">
          <i class="fas fa-id-card"></i> Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item" data-toggle="modal" data-target="#characterSwitchModal">
          <i class="fas fa-exchange-alt"></i> Change Main Character
        </a>
        <div class="dropdown-divider"></div>
        <a href="https://anvil.capsulecmdr.com/auth/eve" class="dropdown-item">
          <i class="fas fa-link"></i> Link Character
        </a>
        <div class="dropdown-divider"></div>
        <form action="https://anvil.capsulecmdr.com/auth/logout" method="post" class="px-3">
          <input type="hidden" name="_token" value="TIDZ6Ynn9gnqEKfLsRowkNGV5ncjDWTXhmmZiZqg" autocomplete="off">
          <button type="submit" class="btn btn-link dropdown-item p-0">
            <i class="fas fa-sign-out-alt"></i> Sign Out
          </button>
        </form>
      </div>
    </li>
  </ul>
</nav>
