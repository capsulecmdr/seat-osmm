@php
/**
 * Props:
 * - $menu : array (same structure as sidebar)
 * - $can  : callable permission checker
 *
 * Usage:
 * @include('seat-osmm::menu.partials.topbar', ['menu' => $native, 'can' => $can])
 * @include('seat-osmm::menu.partials.topbar', ['menu' => $merged, 'can' => $can])
 */
@endphp

<style>
/* nested dropdown support */
.dropdown-submenu { position: relative; }
.dropdown-submenu > .dropdown-menu {
  top: 0; left: 100%;
  margin-top: -1px; margin-left: .1rem;
}
</style>

<ul class="navbar-nav">
  @foreach($menu as $key => $item)
    @include('seat-osmm::menu.partials.osmm.topbar-node', ['item' => $item, 'can' => $can, 'level' => 0])
  @endforeach
</ul>
