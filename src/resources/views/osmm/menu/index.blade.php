@extends('web::layouts.app')
@section('page_title', 'OSMM Menu Manager')

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">Native package.sidebar</div>
      <div class="card-body" style="max-height:60vh; overflow:auto">
        @include('osmm.menu.partials.tree', ['menu' => $native])
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">DB Overrides (interpreted)</div>
      <div class="card-body" style="max-height:60vh; overflow:auto">
        @include('osmm.menu.partials.tree', ['menu' => $overrides])
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">Merged (what the app uses)</div>
      <div class="card-body" style="max-height:60vh; overflow:auto">
        @include('osmm.menu.partials.tree', ['menu' => $merged])
      </div>
    </div>
  </div>
</div>

{{-- Quick-create forms (minimal; expand as needed) --}}
<div class="card mt-3">
  <div class="card-header">Create / Update Overrides</div>
  <div class="card-body">
    <form method="post" action="{{ route('osmm.menu.parent.upsert') }}" class="mb-3">
      @csrf
      <h6 class="text-muted">Parent</h6>
      <div class="form-row">
        <div class="col"><input name="name" class="form-control" placeholder="Name"></div>
        <div class="col"><input name="icon" class="form-control" placeholder="Icon (e.g., fas fa-home)"></div>
        <div class="col"><input name="route_segment" class="form-control" placeholder="route_segment"></div>
      </div>
      <div class="form-row mt-2">
        <div class="col"><input name="route" class="form-control" placeholder="route (optional)"></div>
        <div class="col"><input name="permission" class="form-control" placeholder="permission (optional)"></div>
        <div class="col"><input name="order" type="number" class="form-control" placeholder="order"></div>
      </div>
      <button class="btn btn-primary btn-sm mt-2">Save Parent</button>
    </form>

    <form method="post" action="{{ route('osmm.menu.child.upsert') }}" class="mb-3">
      @csrf
      <h6 class="text-muted">Child</h6>
      <div class="form-row">
        <div class="col">
          <select name="parent_id" class="form-control">
            <option value="">Select Parent</option>
            @foreach($parentOptions as $p)
              <option value="{{ DB::table('osmm_menu_items')->where('route_segment',$p['seg'])->whereNull('parent')->value('id') }}">
                {{ $p['label'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col"><input name="name" class="form-control" placeholder="Name"></div>
        <div class="col"><input name="icon" class="form-control" placeholder="Icon"></div>
      </div>
      <div class="form-row mt-2">
        <div class="col"><input name="route" class="form-control" placeholder="route"></div>
        <div class="col"><input name="permission" class="form-control" placeholder="permission"></div>
        <div class="col"><input name="order" type="number" class="form-control" placeholder="order"></div>
      </div>
      <button class="btn btn-primary btn-sm mt-2">Save Child</button>
    </form>

    <form method="post" action="{{ route('osmm.menu.reset') }}" onsubmit="return confirm('Clear ALL overrides?')">
      @csrf
      <button class="btn btn-danger btn-sm">Reset All Overrides</button>
    </form>
  </div>
</div>
@endsection
