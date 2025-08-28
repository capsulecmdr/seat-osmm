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
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="maintSwitch" name="enabled" value="1" {{ $settings['maintenance_enabled'] ? 'checked' : '' }}>
              <label class="custom-control-label" for="maintSwitch">
                {{ $settings['maintenance_enabled'] ? 'Enabled' : 'Disabled' }}
              </label>
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
                value="{{ old('reason', $settings['maintenance_reason'] ?? 'SeAT Maintenance Advisory') }}">
            </div>

            <div class="form-group">
              <label for="maintDesc">Maintenance description (details)</label>
              <textarea
                id="maintDesc"
                name="description"
                class="form-control"
                rows="4"
                placeholder="Optional details, expected timeline, known impacts...">{{ old('description', $settings['maintenance_description'] ?? 'Server entering reinforced mode. Secure assets and enjoy a Quafe while engineering refits some rigs.') }}</textarea>
            </div>

            <button class="btn btn-primary btn-sm">Save</button>
          </form>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header"><strong>Discord Webhook</strong></div>
        <div class="card-body">
          <form method="post" action="{{ route('osmm.maint.webhook') }}">
            @csrf
            <div class="form-group form-check">
              <input type="checkbox" class="form-check-input" id="whEnabled" name="enabled" value="1" {{ $settings['webhook_enabled'] ? 'checked' : '' }}>
              <label for="whEnabled" class="form-check-label">Enable Webhook</label>
            </div>
            <div class="form-group">
              <label>Webhook URL</label>
              <input type="url" class="form-control" name="url" value="{{ $settings['webhook_url'] }}">
            </div>
            <div class="form-group">
              <label>Username (optional)</label>
              <input type="text" class="form-control" name="username" value="{{ $settings['webhook_username'] }}">
            </div>
            <div class="form-group">
              <label>Avatar URL (optional)</label>
              <input type="url" class="form-control" name="avatar" value="{{ $settings['webhook_avatar'] }}">
            </div>
            <button class="btn btn-primary btn-sm">Save</button>
          </form>
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
              <div class="form-group col-md-8">
                <label>Title</label>
                <input type="text" name="title" class="form-control" required>
              </div>
              <div class="form-group col-md-4">
                <label>Status</label>
                <select name="status" class="form-control">
                  <option value="new">New</option>
                  <option value="active">Active</option>
                  <option value="expired">Expired</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Content (HTML allowed)</label>
              <textarea name="content" rows="4" class="form-control" required></textarea>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Starts At (UTC)</label>
                <input type="datetime-local" name="starts_at" class="form-control">
              </div>
              <div class="form-group col-md-6">
                <label>Ends At (UTC)</label>
                <input type="datetime-local" name="ends_at" class="form-control">
              </div>
            </div>
            <div class="form-row"><input type="hidden" name="show_banner" value="0">
              <input type="hidden" name="send_to_discord" value="0">
              <div class="form-group form-check">
                <input
                  type="checkbox"
                  class="form-check-input"
                  id="send_to_discord"
                  name="send_to_discord"
                  value="1"
                  {{ old('send_to_discord', 0) ? 'checked' : '' }}>
                <label class="form-check-label" for="send_to_discord">Send to Discord</label>
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
                  <th>Title</th><th>Status</th><th>Window</th><th>Banner</th><th>Actions</th>
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
                  <td>{{ $a->show_banner ? 'Yes' : 'No' }}</td>
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
