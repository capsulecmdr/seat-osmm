


@php
  // Use your OSMM settings store, not SeAT's setting()
  $faviconFlag = (int) (osmm_setting('osmm_override_favicon') ?? 0);
  $content = (osmm_setting('osmm_branding_favicon_html') ?? "");
@endphp

@if ($faviconFlag === 1)
  {!! $content !!}
  <link rel="manifest" href="{{ asset('manifest.json') }}">
@else
  {{-- Fall back to the original SeAT sidebar --}}
  @include('eveseat_web::includes.favicon')
@endif