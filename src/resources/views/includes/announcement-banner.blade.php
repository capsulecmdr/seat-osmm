@php
  $banner = \CapsuleCmdr\SeatOsmm\Models\OsmmAnnouncement::bannerable()->get()
              ->tap(fn($c) => $c->each->refreshComputedStatus())
              ->first(fn($a) => $a->is_visible);
@endphp

@if ($banner)
  <div class="alert alert-warning mb-0 rounded-0 dont-autohide" role="alert" style="position:sticky; top:0; z-index: 1050;">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="mr-3">
        <strong>{{ $banner->title }}</strong>
        <span class="ml-2">{!! $banner->content !!}</span>
      </div>
      @can('osmm.maint_manage')
        <a class="btn btn-sm btn-outline-dark" href="{{ route('osmm.maint.config') }}">Manage</a>
      @endcan
    </div>
  </div>
@endif
