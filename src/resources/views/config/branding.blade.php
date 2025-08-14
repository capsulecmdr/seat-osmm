{{-- resources/views/vendor/seat-osmm/config/branding.blade.php OR your osmm view path --}}
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
                When enabled, OSMM will render <code>web::layouts.sidebar</code> from your plugin and fall back to SeAT’s default when disabled.
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
                When enabled, OSMM will render <code>web::layouts.footer</code> from your plugin and fall back to SeAT’s default when disabled.
              </small>
            </div>

            <hr>

            {{-- Manifest override (the missing toggle) --}}
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
                When enabled, OSMM will serve your stored manifest JSON from <code>{{ route('osmm.manifest') }}</code>.
              </small>
            </div>

            <hr>

            {{-- Favicon HTML --}}
            <div class="form-group">
              <label for="osmm_branding_sidebar_html" class="mb-1">Sidebar Branding HTML</label>
              <textarea
                id="osmm_branding_sidebar_html"
                name="osmm_branding_sidebar_html"
                class="form-control"
                rows="4"
                placeholder="Custom HTML for your sidebar brand block…"
              >{{ old('osmm_branding_sidebar_html', $osmm_branding_sidebar_html ?? '') }}</textarea>
              <small class="form-text text-muted">
                Rendered where your custom sidebar expects a brand/logo block.
              </small>
            </div>

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
                Must be valid JSON (we validate on save). Pair with the Manifest toggle above.
              </small>
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('osmm.config.branding') }}" class="btn btn-default">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>

      {{-- quick debug helper; remove in prod --}}
      <div class="small text-muted mt-2">
        Current: sidebar={{ (int)($osmm_override_sidebar ?? 0) }},
        footer={{ (int)($osmm_override_footer ?? 0) }},
        manifest={{ (int)($osmm_override_manifest ?? 0) }}
      </div>

    </div>
  </div>
</div>
@endsection
