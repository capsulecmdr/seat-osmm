@extends('web::layouts.app')
@section('page_title', 'OSMM Menu Manager')

@section('content')
{{-- Top previews: Native vs Merged as a navbar --}}
<div class="row">
  <div class="col-md-12">
    <div class="card mb-3">
      <div class="card-header">
        <strong>Native Navbar (preview)</strong>
        <small class="text-muted ml-2">base-native (consolidated)</small>
      </div>
      <div class="card-body p-0">
        @include('seat-osmm::menu.partials.topbar', ['menu' => $native, 'can' => $can ?? null])
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="card mb-3">
      <div class="card-header">
        <strong>Merged Navbar (preview)</strong>
        <small class="text-muted ml-2">DB overrides applied</small>
      </div>
      <div class="card-body p-0">
        @include('seat-osmm::menu.partials.topbar', ['menu' => $merged, 'can' => $can ?? null])
      </div>
    </div>
  </div>
</div>

<div class="row">
  {{-- LEFT: Native sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Native Sidebar</strong>
        <small class="text-muted ml-2">base-native (consolidated)</small>
      </div>
      <div id="native-sidebar"
           class="card-body p-0"
           style="max-height:60vh; overflow:auto"
           data-menu='@json($native)'>
        @include('seat-osmm::partials.sidebar', [
          'menu' => $native,
          'can'  => $can ?? null
        ])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Native</strong></div>
      <div id="details-native" class="card-body small">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>

  {{-- CENTER: DB Overrides list + details + edit/update --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Overrides in Database</strong>
        <small class="text-muted ml-2">osmm_menu_items</small>
      </div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        @include('seat-osmm::menu.partials.overrides_list', ['rows' => $dbRows])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Override</strong></div>
      <div id="details-overrides" class="card-body small">
        <em>Select an override row…</em>
      </div>

      <div class="card-footer">
        {{-- EDIT / UPDATE FORM (pre-filled on click in center list) --}}
        <form id="override-edit-form" method="post" action="{{ route('osmm.menu.parent.upsert') }}">
          @csrf
          <input type="hidden" name="id" id="edit-id">
          <input type="hidden" name="parent_id" id="edit-parent_id">

          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label class="mb-0">Name override</label>
              <input type="text" class="form-control form-control-sm"
                     name="name_override" id="edit-name_override"
                     placeholder="Leave blank to keep native">
              <small class="form-text text-muted">Shown for section title or child label.</small>
            </div>
            <div class="form-group col-md-6 mb-2">
              <label class="mb-0">Label override (translation key)</label>
              <input type="text" class="form-control form-control-sm"
                     name="label_override" id="edit-label_override"
                     placeholder="e.g., web::seat.home">
              <small class="form-text text-muted">Optional: replace the translation key used for the label.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-5 mb-2">
              <label class="mb-0">Visibility override</label>
              <select class="form-control form-control-sm" name="visible" id="edit-visible">
                <option value="">(no override)</option>
                <option value="1">Show (force visible)</option>
                <option value="0">Hide (force hidden)</option>
              </select>
              <small class="form-text text-muted">Show/hide regardless of permission.</small>
            </div>
            <div class="form-group col-md-7 mb-2">
              <label class="mb-0">Route (optional)</label>
              <input type="text" class="form-control form-control-sm"
                     name="route" id="edit-route" placeholder="e.g., seatcore::tools.moons.index">
              <small class="form-text text-muted">Named route. If missing, link will be disabled.</small>
            </div>
          </div>

          <div class="form-row">
            <div id="wrap-route-segment" class="form-group col-md-5 mb-2">
              <label class="mb-0">Route Segment (parents only)</label>
              <input type="text" class="form-control form-control-sm"
                     name="route_segment" id="edit-route_segment"
                     list="route-segment-options"
                     placeholder="e.g., configuration">
              <small class="form-text text-muted">Must match a section to override it.</small>
            </div>
            <div class="form-group col-md-3 mb-2">
              <label class="mb-0">Order</label>
              <input type="number" min="1" class="form-control form-control-sm"
                     name="order" id="edit-order" placeholder="1">
              <small class="form-text text-muted">1-based position (blank = native).</small>
            </div>
            <div class="form-group col-md-4 mb-2">
              <label class="mb-0">Permission</label>
              <select class="form-control form-control-sm" name="permission" id="edit-permission">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $opt)
                  @php
                    $val = is_array($opt) ? $opt['value'] : $opt;
                    $lab = is_array($opt) ? $opt['label'] : $opt;
                  @endphp
                  <option value="{{ $val }}">{{ $lab }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Users must have this permission (unless visibility=Show).</small>
            </div>
          </div>

          <div class="form-row">
            <div id="wrap-parent-select" class="form-group col-md-8 mb-2" style="display:none;">
              <label class="mb-0">Parent (for child items)</label>
              <select class="form-control form-control-sm" id="edit-parent-select">
                @foreach(($parentOptions ?? []) as $p)
                  <option value="{{ $p['parent_id'] ?? '' }}">{{ $p['label'] }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Top-level section this child belongs to.</small>
            </div>
            <div class="form-group col-md-4 mb-2">
              <label class="mb-0">Icon</label>
              <input type="text" class="form-control form-control-sm"
                     name="icon" id="edit-icon" placeholder="e.g., fas fa-user">
              <small class="form-text text-muted">Font Awesome class.</small>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <div>
              <button type="submit" class="btn btn-primary btn-sm" id="edit-submit">Save Changes</button>
              <button type="button" class="btn btn-outline-danger btn-sm" id="edit-delete">Delete</button>
            </div>
            <small id="edit-mode-help" class="text-muted"></small>
          </div>
        </form>

        {{-- Hidden delete form --}}
        <form id="override-delete-form" method="post" action="{{ route('osmm.menu.delete') }}" style="display:none;">
          @csrf
          @method('DELETE')
          <input type="hidden" name="id" id="delete-id">
        </form>
      </div>
    </div>
  </div>

  {{-- RIGHT: Merged sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Merged Sidebar</strong>
        <small class="text-muted ml-2">DB overrides applied</small>
      </div>
      <div id="merged-sidebar"
           class="card-body p-0"
           style="max-height:60vh; overflow:auto"
           data-menu='@json($merged)'>
        @include('seat-osmm::partials.osmm.sidebar', [
          'menu' => $merged,
          'can'  => $can ?? null
        ])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Merged</strong></div>
      <div id="details-merged" class="card-body small">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>
</div>

{{-- OVERRIDE-ONLY FORMS --}}
<div class="row mt-4">
  {{-- PARENT OVERRIDE --}}
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Override: Parent (Top-level section)</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('osmm.menu.parent.upsert') }}" id="form-parent-override">
          @csrf
          <input type="hidden" name="id" id="parent-id">
          <input type="hidden" name="route_segment" id="parent-seg-hidden">
          <input type="hidden" name="parent_id" id="parent-db-id">

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Section (route_segment)</label>
              <select class="form-control form-control-sm" id="parent-seg-select">
                @foreach(($routeSegments ?? []) as $opt)
                  <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Select the existing top-level section to override.</small>
            </div>

            <div class="col-md-6 mb-2">
              <label class="mb-0">Item to override</label>
              <select class="form-control form-control-sm" id="parent-item-select" disabled>
                <option value="">(the section itself)</option>
              </select>
              <small class="form-text text-muted">Parent overrides always target the section itself.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Name override</label>
              <input name="name_override" id="parent-name-override"
                     class="form-control form-control-sm"
                     placeholder="Leave blank to keep native name">
              <small class="form-text text-muted">Replaces the section title in the sidebar.</small>
            </div>
            <div class="col-md-6 mb-2">
              <label class="mb-0">Label override (translation key)</label>
              <input name="label_override" id="parent-label-override"
                     class="form-control form-control-sm"
                     placeholder="e.g., web::seat.home">
              <small class="form-text text-muted">Optional translation key to use for the label.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-4 mb-2">
              <label class="mb-0">Visibility override</label>
              <select class="form-control form-control-sm" name="visible" id="parent-visible">
                <option value="">(no override)</option>
                <option value="1">Show (force visible)</option>
                <option value="0">Hide (force hidden)</option>
              </select>
              <small class="form-text text-muted">Show/hide regardless of permission.</small>
            </div>
            <div class="col-md-5 mb-2">
              <label class="mb-0">Route (optional)</label>
              <select class="form-control form-control-sm" name="route" id="parent-route-select">
                <option value="">(no route)</option>
              </select>
              <small class="form-text text-muted">Use the section’s route or one of its children’s routes.</small>
            </div>
            <div class="col-md-3 mb-2">
              <label class="mb-0">Order</label>
              <input name="order" type="number" min="1" id="parent-order" class="form-control form-control-sm" placeholder="1">
              <small class="form-text text-muted">1-based position among sections (blank = native).</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Permission</label>
              <select name="permission" class="form-control form-control-sm" id="parent-permission-select">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $opt)
                  @php
                    $val = is_array($opt) ? $opt['value'] : $opt;
                    $lab = is_array($opt) ? $opt['label'] : $opt;
                  @endphp
                  <option value="{{ $val }}">{{ $lab }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Users must have this permission (unless visibility=Show).</small>
            </div>
            <div class="col-md-6 mb-2">
              <label class="mb-0">Icon</label>
              <input name="icon" id="parent-icon" class="form-control form-control-sm" placeholder="fas fa-wrench">
              <small class="form-text text-muted">Font Awesome class.</small>
            </div>
          </div>

          <button class="btn btn-primary btn-sm">Save Parent Override</button>
        </form>
      </div>
    </div>
  </div>

  {{-- CHILD OVERRIDE --}}
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Override: Child (Menu item)</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('osmm.menu.child.upsert') }}" id="form-child-override">
          @csrf
          <input type="hidden" name="id" id="child-id">
          <input type="hidden" name="parent_id" id="child-parent-id">
          <input type="hidden" name="route_segment" id="child-seg-hidden">
          <input type="hidden" name="target_route" id="child-target-route">
          <input type="hidden" name="target_name"  id="child-target-name">

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Parent section</label>
              <select class="form-control form-control-sm" id="child-seg-select" required>
                @foreach(($routeSegments ?? []) as $opt)
                  <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Section containing the child to override.</small>
            </div>

            <div class="col-md-6 mb-2">
              <label class="mb-0">Item to override</label>
              <select class="form-control form-control-sm" id="child-item-select" required>
                <option value="">Select an item…</option>
              </select>
              <small class="form-text text-muted">Populated from the selected section’s children.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Name override</label>
              <input name="name_override" id="child-name-override"
                     class="form-control form-control-sm"
                     placeholder="Leave blank to keep native name">
              <small class="form-text text-muted">Replaces the child label in the submenu.</small>
            </div>
            <div class="col-md-6 mb-2">
              <label class="mb-0">Label override (translation key)</label>
              <input name="label_override" id="child-label-override"
                     class="form-control form-control-sm"
                     placeholder="e.g., web::seat.moons_reporter">
              <small class="form-text text-muted">Optional translation key to use for the label.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-4 mb-2">
              <label class="mb-0">Visibility override</label>
              <select class="form-control form-control-sm" name="visible" id="child-visible">
                <option value="">(no override)</option>
                <option value="1">Show (force visible)</option>
                <option value="0">Hide (force hidden)</option>
              </select>
              <small class="form-text text-muted">Show/hide regardless of permission.</small>
            </div>
            <div class="col-md-5 mb-2">
              <label class="mb-0">Route</label>
              <select class="form-control form-control-sm" name="route" id="child-route-select">
                <option value="">(no change)</option>
              </select>
              <small class="form-text text-muted">Filtered to routes within the selected section.</small>
            </div>
            <div class="col-md-3 mb-2">
              <label class="mb-0">Order</label>
              <input name="order" id="child-order" type="number" min="1"
                     class="form-control form-control-sm" placeholder="1">
              <small class="form-text text-muted">1-based position within section (blank = native).</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Permission</label>
              <select name="permission" class="form-control form-control-sm" id="child-permission-select">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $opt)
                  @php
                    $val = is_array($opt) ? $opt['value'] : $opt;
                    $lab = is_array($opt) ? $opt['label'] : $opt;
                  @endphp
                  <option value="{{ $val }}">{{ $lab }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Restrict visibility for this item (unless visibility=Show).</small>
            </div>
            <div class="col-md-6 mb-2">
              <label class="mb-0">Icon</label>
              <input name="icon" id="child-icon" class="form-control form-control-sm" placeholder="fas fa-moon">
              <small class="form-text text-muted">Font Awesome class.</small>
            </div>
          </div>

          <button class="btn btn-primary btn-sm">Save Child Override</button>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Shared datalist for route segments --}}
<datalist id="route-segment-options">
  @foreach(($routeSegments ?? []) as $opt)
    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
  @endforeach
</datalist>

{{-- Styling --}}
<style>
  .osmm-sidebar a.osmm-link { cursor: pointer; }
  .osmm-sidebar .osmm-link.active,
  .osmm-overrides .list-group-item.active {
    background: rgba(0,123,255,.08);
    border-left: 3px solid #007bff;
  }
  .osmm-details .table td, .osmm-details .table th { vertical-align: middle; }
  .osmm-details .w-35 { width: 35%; }
</style>

{{-- Script: details tables, edit form, dependent dropdowns --}}
@push('javascript')
<script>
(function(){
  // ---------- helpers ----------
  const esc = (s) => (s === null || s === undefined) ? '' :
    String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
             .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

  function badge(txt, type='info') {
    return `<span class="badge badge-${type}">${esc(txt)}</span>`;
  }
  function iconTag(cls) {
    return cls ? `<i class="${esc(cls)}"></i> <code class="ml-1">${esc(cls)}</code>` : '—';
  }
  function routeCell(obj) {
    const route = obj.route || null;
    const url   = obj.url || '#';
    if (!route) return '—';
    const link  = url && url !== '#' ? `<a href="${esc(url)}">${esc(route)}</a>` : `<span>${esc(route)}</span>`;
    return `<div>${link}</div>`;
  }
  function keyRow(label, valueHtml) {
    return `<tr><th class="w-35">${esc(label)}</th><td>${valueHtml}</td></tr>`;
  }
  function renderTableHTML(data) {
    const isDb     = !!data.db;
    const isParent = data.type === 'parent';
    const order = isDb
      ? (isParent
          ? ['id','type','name','name_override','label_override','label','icon','route_segment','route','permission','visible','order','created_at','updated_at']
          : ['id','type','parent_id','parent_name','name','name_override','label_override','label','icon','route','permission','visible','order','created_at','updated_at'])
      : (isParent
          ? ['type','source','key','name','label','icon','route_segment','route','permission','plural']
          : ['type','source','parent_key','key','name','label','icon','route','permission','plural']);

    const labels = {
      id:'DB ID', type:'Type', source:'Source', key:'Key',
      parent_key:'Parent Key', parent_id:'Parent ID', parent_name:'Parent Name',
      name:'Name', name_override:'Name Override', label_override:'Label Override',
      label:'Label', icon:'Icon', route_segment:'Route Segment',
      route:'Route', url:'URL', permission:'Permission', plural:'Plural',
      visible:'Visible', order:'Order', created_at:'Created', updated_at:'Updated'
    };

    const rows = [];
    order.forEach(k => {
      if (!(k in data)) return;
      let html;
      switch(k) {
        case 'icon':       html = iconTag(data.icon); break;
        case 'route':      html = routeCell(data);    break;
        case 'permission': html = data.permission ? badge(data.permission, 'primary') : '—'; break;
        case 'visible':
          html = (data.visible === 1 || data.visible === '1') ? badge('true','success') :
                 (data.visible === 0 || data.visible === '0') ? badge('false','dark') : '—';
          break;
        case 'type':       html = badge(data.type, 'secondary'); break;
        case 'source':     html = data.source ? badge(data.source, 'light') : '—'; break;
        case 'plural':     html = (data.plural === true) ? badge('true','success') :
                                   (data.plural === false) ? badge('false','dark') : '—'; break;
        default:           html = (data[k] !== null && data[k] !== undefined && String(data[k]).length)
                                  ? `<code>${esc(data[k])}</code>` : '—';
      }
      rows.push(keyRow(labels[k] || k, html));
    });

    return `<div class="osmm-details">
      <table class="table table-sm table-striped mb-0"><tbody>${rows.join('')}</tbody></table>
    </div>`;
  }
  function renderDetailsTable(targetId, data){
    const el = document.getElementById(targetId);
    if (!el) return;
    el.innerHTML = renderTableHTML(data);
  }

  // ---------- build “click → details” without data attrs ----------
  function getLiIndex(li) {
    const parentUl = li.parentElement;
    if (!parentUl) return -1;
    const lis = Array.from(parentUl.children).filter(n => n.tagName === 'LI');
    return lis.indexOf(li);
  }
  function calcPathFromAnchor(aEl, rootContainerId) {
    // returns an array of indexes: [parentIdx] or [parentIdx, childIdx] or deeper
    const li = aEl.closest('li');
    const path = [];
    let curLi = li;
    while (curLi) {
      const idx = getLiIndex(curLi);
      if (idx >= 0) path.unshift(idx);
      const parentCardBody = curLi.closest('#'+rootContainerId);
      if (parentCardBody) break;
      curLi = curLi.parentElement.closest('li');
    }
    return path; // [] if not under root
  }
  function lookupFromPath(menuObj, path, isNative) {
    // menuObj is an object (top-level associative). Preserve key for parent.
    const keys = Object.keys(menuObj); // insertion order preserved
    let node, parentKey = null, parentNode = null;

    if (path.length === 0) return null;

    // parent level
    const pIdx = path[0];
    const pKey = keys[pIdx];
    if (!pKey) return null;
    parentKey = pKey;
    node = menuObj[pKey];
    parentNode = node;

    // descend children
    for (let i = 1; i < path.length; i++) {
      const idx = path[i];
      const arr = Array.isArray(node.entries) ? node.entries : [];
      node = arr[idx];
      if (!node) return null;
    }

    const type = (path.length === 1) ? 'parent' : 'child';
    const payload = {
      type,
      source: isNative ? 'native' : 'merged',
      key: type === 'parent' ? parentKey : (node.route || node.name || ''),
      parent_key: parentKey,
      name: node.name || '',
      label: node.label || node.name || '',
      icon: node.icon || '',
      route: node.route || '',
      permission: node.permission || '',
      route_segment: (type === 'parent') ? (parentNode.route_segment || parentKey) : undefined,
      plural: node.plural
    };

    // if child, add parent_name
    if (type === 'child') {
      payload.parent_name = parentNode.name || parentKey;
    }

    return payload;
  }
  function wireSidebar(containerId, targetDetailsId, isNative) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const menuObj = (() => { try { return JSON.parse(container.dataset.menu || '{}'); } catch(e){ return {}; } })();

    container.addEventListener('click', function(ev){
      const a = ev.target.closest('a.nav-link');
      if (!a) return;
      // don’t hijack real navigation in preview
      ev.preventDefault();

      // highlight
      container.querySelectorAll('a.nav-link.osmm-link').forEach(el => el.classList.remove('active'));
      a.classList.add('osmm-link','active');

      const path = calcPathFromAnchor(a, containerId);
      const data = lookupFromPath(menuObj, path, isNative);
      if (data) renderDetailsTable(targetDetailsId, data);
    });
  }

  wireSidebar('native-sidebar', 'details-native', true);
  wireSidebar('merged-sidebar', 'details-merged', false);

  // ---------- Overrides list → details + edit form ----------
  function fillSelectPermission(value){
    const sel = document.getElementById('edit-permission');
    if (!sel) return;
    if (value && ![...sel.options].some(o => o.value === value)) {
      const opt = new Option(value, value, true, true);
      sel.add(opt);
    } else {
      sel.value = value || '';
    }
  }
  function setEditMode(data){
    const form = document.getElementById('override-edit-form');
    if (!form) return;

    const isParent = (data.type === 'parent');
    form.action = isParent ? @json(route('osmm.menu.parent.upsert')) : @json(route('osmm.menu.child.upsert'));

    document.getElementById('edit-id').value = data.id || '';
    document.getElementById('edit-name_override').value  = data.name_override  || '';
    document.getElementById('edit-label_override').value = data.label_override || '';
    document.getElementById('edit-icon').value  = data.icon || '';
    document.getElementById('edit-route').value = data.route || '';
    document.getElementById('edit-order').value = data.order || '';
    fillSelectPermission(data.permission);

    // visible (fix payload->data bug)
    document.getElementById('edit-visible').value =
      (data.visible === 0 || data.visible === '0') ? '0' :
      (data.visible === 1 || data.visible === '1') ? '1' : '';

    const wrapRouteSeg   = document.getElementById('wrap-route-segment');
    const routeSegInput  = document.getElementById('edit-route_segment');
    const wrapParent     = document.getElementById('wrap-parent-select');
    const parentIdInput  = document.getElementById('edit-parent_id');
    const parentSelect   = document.getElementById('edit-parent-select');

    if (isParent) {
      wrapRouteSeg.style.display = '';
      routeSegInput.value = data.route_segment || '';
      wrapParent.style.display = 'none';
      parentIdInput.value = '';
    } else {
      wrapRouteSeg.style.display = 'none';
      routeSegInput.value = '';
      wrapParent.style.display = '';
      if (parentSelect) {
        if (data.parent_id) parentSelect.value = data.parent_id;
        parentIdInput.value = parentSelect.value || data.parent_id || '';
        parentSelect.addEventListener('change', () => {
          parentIdInput.value = parentSelect.value;
        }, { once: true });
      }
    }

    const help = document.getElementById('edit-mode-help');
    if (help) help.textContent = isParent ? 'Editing parent override' : 'Editing child override';

    const delBtn = document.getElementById('edit-delete');
    const delFm  = document.getElementById('override-delete-form');
    if (delBtn && delFm) {
      delBtn.onclick = function () {
        if (!data.id) return;
        if (confirm('Delete this override?')) {
          document.getElementById('delete-id').value = data.id;
          delFm.submit();
        }
      };
    }
  }

  // center list clicks (list renders rows with data-osmm-override-row + data-item JSON)
  document.addEventListener('click', function(ev){
    const row = ev.target.closest('[data-osmm-override-row]');
    if (!row) return;
    try {
      const payload = JSON.parse(row.dataset.item || '{}');
      renderDetailsTable('details-overrides', payload);
      setEditMode(payload);
    } catch(e){}
  });

  // ---------- Dependent dropdowns for bottom forms ----------
  const CATALOG = @json($menuCatalog ?? []);
  const PARENT_IDS = {};
  Object.keys(CATALOG).forEach(seg => { PARENT_IDS[seg] = CATALOG[seg].parent.db_parent_id || ''; });

  // Parent override form
  const pSegSel  = document.getElementById('parent-seg-select');
  const pSegHid  = document.getElementById('parent-seg-hidden');
  const pDbIdHid = document.getElementById('parent-db-id');
  const pItemSel = document.getElementById('parent-item-select');
  const pRouteSel= document.getElementById('parent-route-select');

  function rebuildParentRoutes(seg) {
    pRouteSel.innerHTML = '<option value="">(no route)</option>';
    if (!seg || !CATALOG[seg]) return;
    (CATALOG[seg].routes || []).forEach(r => {
      pRouteSel.add(new Option(r, r));
    });
  }
  function onParentSegChange() {
    const seg = pSegSel.value || '';
    pSegHid.value  = seg;
    pDbIdHid.value = PARENT_IDS[seg] || '';
    pItemSel.innerHTML = '';
    pItemSel.add(new Option(CATALOG[seg]?.parent?.label || seg, seg, true, true));
    rebuildParentRoutes(seg);
  }
  if (pSegSel) { onParentSegChange(); pSegSel.addEventListener('change', onParentSegChange); }

  // Child override form
  const cSegSel   = document.getElementById('child-seg-select');
  const cSegHid   = document.getElementById('child-seg-hidden');
  const cParentId = document.getElementById('child-parent-id');
  const cItemSel  = document.getElementById('child-item-select');
  const cRouteSel = document.getElementById('child-route-select');
  const cTargetRoute = document.getElementById('child-target-route');
  const cTargetName  = document.getElementById('child-target-name');

  function rebuildChildItems(seg) {
    cItemSel.innerHTML = '<option value="">Select an item…</option>';
    if (!seg || !CATALOG[seg]) return;
    (CATALOG[seg].children || []).forEach(c => {
      const text = (c.label || c.name || '(no label)') + (c.route ? ` [${c.route}]` : '');
      const val  = JSON.stringify({ route: c.route || '', name: c.name || '' });
      cItemSel.add(new Option(text, val));
    });
  }
  function rebuildChildRoutes(seg) {
    cRouteSel.innerHTML = '<option value="">(no change)</option>';
    if (!seg || !CATALOG[seg]) return;
    (CATALOG[seg].routes || []).forEach(r => cRouteSel.add(new Option(r, r)));
  }
  function onChildSegChange() {
    const seg = cSegSel.value || '';
    cSegHid.value  = seg;
    cParentId.value= PARENT_IDS[seg] || '';
    rebuildChildItems(seg);
    rebuildChildRoutes(seg);
    cTargetRoute.value = '';
    cTargetName.value  = '';
  }
  function onChildItemChange() {
    const val = cItemSel.value;
    try {
      const obj = JSON.parse(val || '{}');
      cTargetRoute.value = obj.route || '';
      cTargetName.value  = obj.name  || '';
      if (obj.route) cRouteSel.value = obj.route;
    } catch(e) {
      cTargetRoute.value = '';
      cTargetName.value  = '';
    }
  }
  if (cSegSel) { onChildSegChange(); cSegSel.addEventListener('change', onChildSegChange); }
  if (cItemSel) cItemSel.addEventListener('change', onChildItemChange);
})();
</script>
@endpush
@endsection
