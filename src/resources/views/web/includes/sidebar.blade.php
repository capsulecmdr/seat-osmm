@php
  // Use your OSMM settings store, not SeAT's setting()
  $sidebarFlag = (int) (osmm_setting('osmm_override_sidebar') ?? 0);
  $content = (osmm_setting('osmm_branding_sidebar_html') ?? "");
@endphp

@if ($sidebarFlag === 1)
    
@else
    {{-- Fall back to the original SeAT sidebar --}}
    {{-- @include('eveseat_web::includes.sidebar') --}}
@endif

