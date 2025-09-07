@extends('web::layouts.app')

@section('page_title', 'OSMM — Branding')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-md-10 col-lg-9">

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Branding & Layout Overrides</h3>
        </div>

        <form method="POST" action="{{ route('osmm.config.branding.update') }}">
          @csrf
          @method('PUT')

          <div class="card-body">

            {{-- Enhanced Home Page --}}
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="hidden" name="osmm_use_enhanced_home" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_use_enhanced_home"
                  name="osmm_use_enhanced_home"
                  value="1"
                  @checked(old('osmm_use_enhanced_home', (int)($osmm_use_enhanced_home ?? 0)) == 1)
                >
                <label class="custom-control-label" for="osmm_use_enhanced_home">
                  Use <strong>Enhanced Home Page</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                When enabled, OSMM serves the enhanced dashboard/home experience.
              </small>
            </div>

            <hr>


            {{-- Sidebar override --}}
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="hidden" name="osmm_override_sidebar" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_sidebar"
                  name="osmm_override_sidebar"
                  value="1"
                  @checked(old('osmm_override_sidebar', (int)($osmm_override_sidebar ?? 0)) == 1)
                >
                <label class="custom-control-label" for="osmm_override_sidebar">
                  Use OSMM custom <strong>Sidebar</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                When enabled, OSMM renders <code>web::layouts.sidebar</code> from your plugin; otherwise SeAT’s default.
              </small>
            </div>

            <hr>

            {{-- Footer override --}}
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="hidden" name="osmm_override_footer" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_footer"
                  name="osmm_override_footer"
                  value="1"
                  @checked(old('osmm_override_footer', (int)($osmm_override_footer ?? 0)) == 1)
                >
                <label class="custom-control-label" for="osmm_override_footer">
                  Use OSMM custom <strong>Footer</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                When enabled, OSMM renders <code>web::layouts.footer</code> from your plugin; otherwise SeAT’s default.
              </small>
            </div>

            <hr>

            {{-- Manifest override --}}
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="hidden" name="osmm_override_manifest" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_manifest"
                  name="osmm_override_manifest"
                  value="1"
                  @checked(old('osmm_override_manifest', (int)($osmm_override_manifest ?? 0)) == 1)
                >
                <label class="custom-control-label" for="osmm_override_manifest">
                  Use OSMM custom <strong>manifest.json</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                Served from <code>{{ route('osmm.manifest') }}</code> when enabled.
              </small>
            </div>

            <hr>

            {{-- NEW: Favicon override --}}
            <div class="form-group">
              <div class="custom-control custom-switch">
                <input type="hidden" name="osmm_override_favicon" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_favicon"
                  name="osmm_override_favicon"
                  value="1"
                  @checked(old('osmm_override_favicon', (int)($osmm_override_favicon ?? 0)) == 1)
                >
                <label class="custom-control-label" for="osmm_override_favicon">
                  Use OSMM custom <strong>Favicon</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                Inject your own <code>&lt;link rel="icon" ...&gt;</code>/<code>&lt;link rel="apple-touch-icon" ...&gt;</code> tags below.
              </small>
            </div>

            <hr>

            {{-- Sidebar Branding HTML --}}
            <div class="form-group">
              <label for="osmm_branding_sidebar_html" class="mb-1">Sidebar Branding HTML</label>
              <textarea
                id="osmm_branding_sidebar_html"
                name="osmm_branding_sidebar_html"
                class="form-control"
                rows="4"
                placeholder="Custom HTML for your sidebar brand/logo…"
              >{{ old('osmm_branding_sidebar_html', $osmm_branding_sidebar_html ?? '') }}</textarea>
            </div>

            {{-- Footer Branding HTML --}}
            <div class="form-group">
              <label for="osmm_branding_footer_html" class="mb-1">Footer Branding HTML</label>
              <textarea
                id="osmm_branding_footer_html"
                name="osmm_branding_footer_html"
                class="form-control"
                rows="4"
                placeholder="Custom HTML for the footer…"
              >{{ old('osmm_branding_footer_html', $osmm_branding_footer_html ?? '') }}</textarea>
            </div>

            {{-- NEW: Favicon HTML --}}
            <div class="form-group">
              <label for="osmm_branding_favicon_html" class="mb-1">Favicon HTML (head)</label>
              <textarea
                id="osmm_branding_favicon_html"
                name="osmm_branding_favicon_html"
                class="form-control"
                rows="5"
                placeholder='<link rel="icon" href="{{ asset('storage/favicon-32x32.png') }}" sizes="32x32"> …'
              >{{ old('osmm_branding_favicon_html', $osmm_branding_favicon_html ?? '') }}</textarea>
              <small class="form-text text-muted">
                Put one or more <code>&lt;link&gt;</code> tags. Render this only when <em>Favicon override</em> is enabled.
              </small>
            </div>

            {{-- Manifest JSON --}}
            <div class="form-group">
              <label for="osmm_branding_manifest_json" class="mb-1">manifest.json</label>
              <textarea
                id="osmm_branding_manifest_json"
                name="osmm_branding_manifest_json"
                class="form-control"
                rows="10"
                placeholder='{"name":"SeAT","short_name":"SeAT"}'
              >{{ old('osmm_branding_manifest_json', $osmm_branding_manifest_json ?? '') }}</textarea>
              <small class="form-text text-muted">
                Must be valid JSON (validated on save).
              </small>
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('osmm.config.branding') }}" class="btn btn-default">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>

      {{-- Debug helper; remove in prod --}}
      <div class="small text-muted mt-2">
        Current: sidebar={{ (int)($osmm_override_sidebar ?? 0) }},
        footer={{ (int)($osmm_override_footer ?? 0) }},
        manifest={{ (int)($osmm_override_manifest ?? 0) }},
        favicon={{ (int)($osmm_override_favicon ?? 0) }}
      </div>

    </div>
  </div>
</div>
@endsection
