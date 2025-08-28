{{-- @extends('web::layouts.app')

@section('title', 'Maintenance')

@section('content') --}}
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow">
        <div class="card-body text-center">
          <h1 class="mb-3">We’ll be right back.</h1>
          <p class="text-muted">The server is currently in maintenance mode.</p>
          @if(isset($announcement) && $announcement)
            <hr>
            <h5 class="mb-2">{{ $announcement->title }}</h5>
            <div class="text-left">{!! $announcement->content !!}</div>
            @if($announcement->starts_at || $announcement->ends_at)
              <p class="mt-3 small text-muted">
                @if($announcement->starts_at) Starts: {{ $announcement->starts_at->toDayDateTimeString() }} UTC @endif
                @if($announcement->starts_at && $announcement->ends_at) • @endif
                @if($announcement->ends_at) Ends: {{ $announcement->ends_at->toDayDateTimeString() }} UTC @endif
              </p>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
{{-- @endsection --}}
