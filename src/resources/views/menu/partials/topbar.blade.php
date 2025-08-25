{{-- Horizontal navbar preview for a menu tree --}}
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
</div>
