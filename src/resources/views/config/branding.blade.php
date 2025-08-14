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

  <div class="card">
    <div class="card-header"><h3 class="card-title">Branding Overrides</h3></div>
    <form method="POST" action="{{ route('osmm.config.branding.save') }}">
      @csrf
      <div class="card-body">

        <div class="form-group">
          <label>Favicon Override (raw HTML)</label>
          <textarea name="favicon_override_html" rows="6" class="form-control" placeholder="e.g. &lt;link rel=&quot;icon&quot; href=&quot;/path/favicon.ico&quot;&gt;">{{ old('favicon_override_html', $favicon_override_html) }}</textarea>
          <small class="form-text text-muted">This is injected into the head include. Use full <code>&lt;link&gt;</code> tags as needed.</small>
        </div>

        <div class="form-group">
          <label>Sidebar Branding Override (raw HTML)</label>
          <textarea name="sidebar_branding_override" rows="6" class="form-control" placeholder="Custom sidebar brand markup">{{ old('sidebar_branding_override', $sidebar_branding_override) }}</textarea>
          <small class="form-text text-muted">Rendered unescaped in the sidebar brand area.</small>
        </div>

        <div class="form-group">
          <label>Footer Branding Override (raw HTML)</label>
          <textarea name="footer_branding_override" rows="6" class="form-control" placeholder="Custom footer markup">{{ old('footer_branding_override', $footer_branding_override) }}</textarea>
          <small class="form-text text-muted">Rendered unescaped in the footer include.</small>
        </div>

        <div class="form-group">
          <label>Manifest Override (JSON)</label>
          <textarea name="manifest_override" rows="10" class="form-control" placeholder='{"name":"SeAT","short_name":"SeAT"}'>{{ old('manifest_override', $manifest_override) }}</textarea>
          <small class="form-text text-muted">Must be valid JSON. Served from <code>{{ route('osmm.manifest') }}</code>.</small>
        </div>

      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection
