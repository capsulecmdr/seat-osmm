@extends('web::layouts.app')

@section('page_title', 'OSMM — Branding')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-md-8">

      {{-- Status flash --}}
      @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
          {{ session('status') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      @endif

      {{-- Validation errors --}}
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Branding & Layout Overrides</h3>
        </div>
        <form method="POST" action="{{ route('osmm.config.branding.update') }}">
          @csrf
          @method('PUT')

          <div class="card-body">

            {{-- Sidebar override --}}
            @php $sidebarOn = (int) setting('osmm_override_sidebar', 0); @endphp
            <div class="form-group">
              <div class="custom-control custom-switch">
                {{-- Hidden default (ensures 0 is submitted when unchecked) --}}
                <input type="hidden" name="osmm_override_sidebar" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_sidebar"
                  name="osmm_override_sidebar"
                  value="1"
                  @checked(old('osmm_override_sidebar', $sidebarOn) == 1)
                >
                <label class="custom-control-label" for="osmm_override_sidebar">
                  Use OSMM custom <strong>Sidebar</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                When enabled, OSMM will render <code>web::layouts.sidebar</code> from your plugin and
                fall back to SeAT’s default when disabled.
              </small>
            </div>

            <hr>

            {{-- Footer override --}}
            @php $footerOn = (int) setting('osmm_override_footer', 0); @endphp
            <div class="form-group">
              <div class="custom-control custom-switch">
                {{-- Hidden default (ensures 0 is submitted when unchecked) --}}
                <input type="hidden" name="osmm_override_footer" value="0">
                <input
                  type="checkbox"
                  class="custom-control-input"
                  id="osmm_override_footer"
                  name="osmm_override_footer"
                  value="1"
                  @checked(old('osmm_override_footer', $footerOn) == 1)
                >
                <label class="custom-control-label" for="osmm_override_footer">
                  Use OSMM custom <strong>Footer</strong>
                </label>
              </div>
              <small class="form-text text-muted">
                When enabled, OSMM will render <code>web::layouts.footer</code> from your plugin and
                fall back to SeAT’s default when disabled.
              </small>
            </div>

          </div>

          <div class="card-footer d-flex justify-content-between">
            <a href="{{ route('osmm.config.branding') }}" class="btn btn-default">Cancel</a>
            <button type="submit" class="btn btn-primary">
              Save Changes
            </button>
          </div>
        </form>
      </div>

      {{-- Live state helper (optional, remove in prod) --}}
      <div class="small text-muted mt-2">
        Current: sidebar={{ (int) setting('osmm_override_sidebar', 0) }},
        footer={{ (int) setting('osmm_override_footer', 0) }}
      </div>
    </div>
  </div>
</div>
@endsection
