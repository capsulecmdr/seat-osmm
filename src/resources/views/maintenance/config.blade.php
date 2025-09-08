@extends('web::layouts.app')

@section('title','Maintenance Configuration')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><strong>Maintenance Mode</strong></div>
        <div class="card-body">
          <form method="post" action="{{ route('osmm.maint.toggle') }}">
    @csrf

    {{-- Hidden ensures "enabled" always gets a value --}}
    <input type="hidden" name="enabled" value="0">

    <div class="custom-control custom-switch mb-3">
        <input type="checkbox"
               class="custom-control-input"
               id="maintSwitch"
               name="enabled"
               value="1"
               {{ $settings['maintenance_enabled'] ? 'checked' : '' }}>
        <label class="custom-control-label" for="maintSwitch">
            {{ $settings['maintenance_enabled'] ? 'Enabled' : 'Disabled' }}
        </label>
    </div>

    {{-- Template quick-fill --}}
    <div class="form-group">
      <label for="maintTemplate">Apply template</label>
      <div class="d-flex">
        <select id="maintTemplate" class="form-control mr-2">
          <option value="">— Choose a template —</option>
          @foreach($templates as $tpl)
            <option value="{{ $tpl->id }}"
                    data-reason="{{ e($tpl->reason) }}"
                    data-description="{{ e($tpl->description) }}">
              {{ $tpl->name }}
            </option>
          @endforeach
        </select>
        <button type="button" id="applyTemplateBtn" class="btn btn-outline-secondary">
          Apply
        </button>
      </div>
      <small class="form-text text-muted">Choosing a template will overwrite the Reason & Description fields below (you can still edit them).</small>
    </div>

    <div class="form-group">
        <label for="maintReason">Maintenance reason (short)</label>
        <input
            type="text"
            id="maintReason"
            name="reason"
            class="form-control"
            maxlength="200"
            placeholder="e.g., Database upgrades"
            value="{{ old('reason', $settings['maintenance_reason'] ?? '') }}">
    </div>

    <div class="form-group">
        <label for="maintDesc">Maintenance description (details)</label>
        <textarea
            id="maintDesc"
            name="description"
            class="form-control"
            rows="4"
            placeholder="Optional details, expected timeline, known impacts...">{{ old('description', $settings['maintenance_description'] ?? '') }}</textarea>
    </div>

    <button class="btn btn-primary btn-sm">Save</button>
</form>
<script>
  (function() {
    const selectEl = document.getElementById('maintTemplate');
    const btn = document.getElementById('applyTemplateBtn');
    const reason = document.getElementById('maintReason');
    const desc = document.getElementById('maintDesc');

    if (!selectEl || !btn || !reason || !desc) return;

    btn.addEventListener('click', function () {
      const opt = selectEl.options[selectEl.selectedIndex];
      if (!opt || !opt.value) return;

      const r = opt.getAttribute('data-reason') ?? '';
      const d = opt.getAttribute('data-description') ?? '';

      reason.value = r;
      desc.value = d;
      // Optional: flash the fields to show they changed
      [reason, desc].forEach(el => {
        el.classList.add('border','border-info');
        setTimeout(() => el.classList.remove('border','border-info'), 1200);
      });
    });
  })();
</script>

        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <strong>Announcements</strong>
          <small class="text-muted">Showing non-expired only</small>
        </div>
        <div class="card-body">
          <form method="post" action="{{ route('osmm.maint.announcement.upsert') }}">
            @csrf
            <input type="hidden" name="id" id="ann-id">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Starts At</label>
                <small class="text-muted">Times are local Eve time</small>
                <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', \Carbon\Carbon::now('UTC')->format('Y-m-d\TH:i')) }}">
              </div>
              <div class="form-group col-md-3">
                <label>Ends At</label>
                <small class="text-muted">Times are local Eve time</small>
                <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', \Carbon\Carbon::now('UTC')->endOfDay()->format('Y-m-d\TH:i')) }}">
              </div>
              
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Title</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="form-group col-md-6">
                <label>Content (HTML allowed)</label>
                <textarea name="content" rows="4" class="form-control" required></textarea>
              </div>
            </div>            
            <button class="btn btn-success btn-sm">Save Announcement</button>
          </form>

          <hr>

          <h5 class="mt-3">Existing (non-expired)</h5>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Title</th><th>Status</th><th>Window</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
              @forelse($announcements as $a)
                <tr>
                  <td>{{ $a->title }}</td>
                  <td>{{ ucfirst($a->status) }}</td>
                  <td class="small">
                    @if($a->starts_at) {{ $a->starts_at->toDayDateTimeString() }} UTC @else — @endif
                    &nbsp;→&nbsp;
                    @if($a->ends_at) {{ $a->ends_at->toDayDateTimeString() }} UTC @else — @endif
                  </td>
                  <td>
                    <form method="post" action="{{ route('osmm.maint.announcement.expire',$a) }}" onsubmit="return confirm('Expire this announcement?');" class="d-inline">
                      @csrf
                      <button class="btn btn-outline-danger btn-sm">Expire</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-muted">No announcements.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          {{ $announcements->links() }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
