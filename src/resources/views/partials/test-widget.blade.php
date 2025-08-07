{{-- resources/views/partials/test-widget.blade.php --}}
<div class="card border-primary shadow-sm h-100">
  <div class="card-header bg-primary text-white py-2">
    <h6 class="card-title mb-0">
      {{ $title ?? 'Test Widget' }}
    </h6>
  </div>
  <div class="card-body p-2">
    @isset($html)
      {!! $html !!}
    @else
      <p class="mb-0">{{ $text ?? 'This is a test block from SeAT-OSMM.' }}</p>
    @endisset
  </div>

  @isset($footer)
    <div class="card-footer py-2">
      {!! $footer !!}
    </div>
  @endisset
</div>
