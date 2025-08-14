@extends('web::layouts.app')

@section('page_title', 'OSMM Config — Branding')

@section('content')
<div class="container-fluid">
  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('osmm.config.branding.save') }}">
    @csrf

    {{-- Sidebar Branding --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Sidebar Branding</h3>
      </div>
      <div class="card-body">
        @php
          $sidebarEnabled = old('osmm_override_sidebar', ($osmm_override_sidebar ?? false)) ? true : false;
        @endphp
        <div class="form-check mb-3">
          <input type="checkbox"
                 class="form-check-input"
                 id="osmm_override_sidebar"
                 name="osmm_override_sidebar"
                 value="1"
                 {{ $sidebarEnabled ? 'checked' : '' }}>
          <label class="form-check-label" for="osmm_override_sidebar">
            Enable sidebar branding override
          </label>
        </div>

        <label for="osmm_branding_sidebar_html" class="form-label">Sidebar HTML</label>
        <textarea class="form-control"
                  id="osmm_branding_sidebar_html"
                  name="osmm_branding_sidebar_html"
                  rows="6"
                  placeholder="&lt;div class=&quot;brand-link&quot;&gt;Your Brand&lt;/div&gt;"
                  {{ $sidebarEnabled ? '' : 'disabled' }}
        >{{ old('osmm_branding_sidebar_html', $osmm_branding_sidebar_html ?? '') }}</textarea>
        <small class="form-text text-muted">
          Rendered unescaped in the sidebar brand area. Admin-only; ensure your HTML is trusted.
        </small>
      </div>
    </div>

    {{-- Footer Branding --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Footer Branding</h3>
      </div>
      <div class="card-body">
        @php
          $footerEnabled = old('osmm_override_footer', ($osmm_override_footer ?? false)) ? true : false;
        @endphp
        <div class="form-check mb-3">
          <input type="checkbox"
                 class="form-check-input"
                 id="osmm_override_footer"
                 name="osmm_override_footer"
                 value="1"
                 {{ $footerEnabled ? 'checked' : '' }}>
          <label class="form-check-label" for="osmm_override_footer">
            Enable footer branding override
          </label>
        </div>

        <label for="osmm_branding_footer_html" class="form-label">Footer HTML</label>
        <textarea class="form-control"
                  id="osmm_branding_footer_html"
                  name="osmm_branding_footer_html"
                  rows="6"
                  placeholder="&lt;div class=&quot;text-muted&quot;&gt;© 2025 Your Corp&lt;/div&gt;"
                  {{ $footerEnabled ? '' : 'disabled' }}
        >{{ old('osmm_branding_footer_html', $osmm_branding_footer_html ?? '') }}</textarea>
        <small class="form-text text-muted">
          Rendered unescaped in the footer include. Keep it concise.
        </small>
      </div>
    </div>

    {{-- Manifest Override --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title mb-0">Web App Manifest</h3>
      </div>
      <div class="card-body">
        @php
          $manifestEnabled = old('osmm_override_manifest', ($osmm_override_manifest ?? false)) ? true : false;
        @endphp
        <div class="form-check mb-3">
          <input type="checkbox"
                 class="form-check-input"
                 id="osmm_override_manifest"
                 name="osmm_override_manifest"
                 value="1"
                 {{ $manifestEnabled ? 'checked' : '' }}>
          <label class="form-check-label" for="osmm_override_manifest">
            Enable manifest override
          </label>
          <div class="small text-muted">
            Served from <code>{{ route('seat-osmm.manifest') }}</code>. Your favicon include should reference this.
          </div>
        </div>

        <label for="osmm_branding_manifest_json" class="form-label">Manifest JSON</label>
        <textarea class="form-control font-monospace"
                  id="osmm_branding_manifest_json"
                  name="osmm_branding_manifest_json"
                  rows="10"
                  placeholder='{"name":"SeAT","short_name":"SeAT"}'
                  {{ $manifestEnabled ? '' : 'disabled' }}
        >{{ old('osmm_branding_manifest_json', $osmm_branding_manifest_json ?? '') }}</textarea>
        <small class="form-text text-muted">
          Must be valid JSON. Tip: include <code>name</code>, <code>short_name</code>, <code>icons</code>, and <code>theme_color</code>.
        </small>
      </div>
    </div>

    <div class="card">
      <div class="card-footer">
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>
</div>

{{-- Simple enable/disable toggling --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  function bindToggle(checkboxId, textareaId) {
    const cb = document.getElementById(checkboxId);
    const ta = document.getElementById(textareaId);
    if (!cb || !ta) return;
    const apply = () => { ta.disabled = !cb.checked; };
    cb.addEventListener('change', apply);
    apply();
  }
  bindToggle('osmm_override_sidebar',  'osmm_branding_sidebar_html');
  bindToggle('osmm_override_footer',   'osmm_branding_footer_html');
  bindToggle('osmm_override_manifest', 'osmm_branding_manifest_json');
});
</script>
@endsection
