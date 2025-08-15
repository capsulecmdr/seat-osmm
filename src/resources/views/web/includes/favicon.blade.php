


@php
  // Use your OSMM settings store, not SeAT's setting()
  $faviconFlag = (int) (osmm_setting('osmm_override_favicon') ?? 0);
  $content = (osmm_setting('osmm_branding_favicon_html') ?? "");

  $manifestFlag  = (int) (osmm_setting('osmm_override_manifest') ?? 0);

  // Decide manifest href
  $manifestHref = $manifestFlag === 1
      ? route('osmm.manifest')                   // your dynamic manifest
      : asset('web/img/favicon/manifest.json');  // vendor static file

@endphp

@if ($faviconFlag === 1)
  {!! $content !!}
  <link rel="manifest" href="{{ $manifestHref }}" crossorigin="use-credentials">
@else
  {{-- Fall back to the original SeAT sidebar --}}
  @php
      // Render vendor include to a string
      $vendorFavicon = view('eveseat_web::includes.favicon')->render();

      // Remove ANY <link rel="manifest" ...> tags (case-insensitive, robust to attribute order)
      $vendorFavicon = preg_replace('/<link\s+[^>]*rel=["\']manifest["\'][^>]*>\s*/i', '', $vendorFavicon);
  @endphp

  {!! $vendorFavicon !!}
  <link rel="manifest" href="{{ $manifestHref }}" crossorigin="use-credentials">
@endif