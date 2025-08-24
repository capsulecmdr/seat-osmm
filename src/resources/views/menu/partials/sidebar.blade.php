@php
/**
 * Props:
 * - $menu : array (your consolidated "base-native" or merged menu)
 * - $can  : callable permission checker: fn(string $perm|null): bool
 *
 * Usage:
 * @include('seat-osmm::menu.partials.sidebar', ['menu' => $native, 'can' => $can])
 * @include('seat-osmm::menu.partials.sidebar', ['menu' => $merged, 'can' => $can])
 */
@endphp

<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
  @foreach($menu as $key => $item)
    @include('seat-osmm::menu.partials.sidebar-node', ['item' => $item, 'can' => $can, 'level' => 0])
  @endforeach
</ul>
