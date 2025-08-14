<link rel="icon" type="image/png" href="{{ asset('storage/blackanvilsocietyicon2.png') }}">
<link rel="apple-touch-icon" href="{{ asset('storage/blackanvilsocietyicon2.png') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">

@php
  // Use your OSMM settings store, not SeAT's setting()
  $faviconFlag = (int) (osmm_setting('osmm_override_favicon') ?? 0);
  $content = (osmm_setting('osmm_branding_favicon_html') ?? "");
@endphp

@if ($faviconFlag === 1)
  {!! $content !!}
@else
  {{-- Fall back to the original SeAT sidebar --}}
  @include('eveseat_web::includes.footer')
@endif