@extends('web::layouts.app')
@section('title', 'OSMM Menu Manager')

@section('content')
@php($native_raw = config('package.sidebar') ?? [])

<div class="row">
  {{-- LEFT: Native sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Native Sidebar</strong>
        <small class="text-muted ml-2">native raw menu</small>
      </div>
      <div id="native-sidebar"
           class="card-body p-0"
           style="max-height:60vh; overflow:auto"
           data-menu='@json($native_raw)'>
        @include('seat-osmm::menu.partials.sidebar', [
          'menu' => $native_raw,
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
      <div class="card-header">
        <strong>Menu Master Override</strong>
        <small class="text-muted ml-2">Use the native SeAT menu or OSMM menu options.</small>
      </div>

      <div class="card-body">
        <form id="form-osmm-settings" action="{{ route('osmm.menu.save-mode') }}" method="post" novalidate>
          @csrf

          @php
            $modeNum = (int)($osmmMenuMode ?? 0);
            $modeStrMap = [0 => 'off', 1 => 'off', 2 => 'sidebar', 3 => 'topbar'];
            $modeStr = $modeStrMap[$modeNum] ?? 'off';
          @endphp

          <input type="hidden" name="osmm_menu_mode" id="osmm-menu-mode" value="{{ $modeNum }}">

          <div class="form-group mb-3">
            <label class="mb-2 d-flex justify-content-between align-items-center">
              <span>Menu Override Level</span>
              <span class="badge badge-pill badge-secondary" id="osmm-menu-mode-label">
                {{$modeStr}}
              </span>
            </label>

            <input
              type="range"
              class="custom-range"
              id="osmm-menu-slider"
              min="1" max="3" step="1"
              value="{{ $modeNum }}"
              data-initial="{{ $modeNum }}"
              aria-describedby="osmm-menu-help">

            <div class="d-flex justify-content-between small text-muted mt-1 px-1">
              <span>Off</span>
              <span>Sidebar</span>
              <span>Topbar</span>
            </div>

            <small id="osmm-menu-help" class="form-text text-muted">
              Choose where to surface the <em>OSMM Menu</em>.
            </small>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card md-3">
      <div class="card-header"><strong>Override Item</strong>
        <small class="text-muted ml-2">Select any native item (parent or child) by its path</small>
      </div>
      <div class="card-body">
        <form method="post" action="{{ route('osmm.menu.override.upsert') }}" id="form-override">
          @csrf

          <div class="form-row">
            <div class="col-md-6 mb-3">
              <label class="mb-0">Item</label>
              <select name="item_key" id="ov-item-key" class="form-control form-control-sm" required>
                {{-- populated by JS from CATALOG_FLAT --}}
              </select>
              <small class="form-text text-muted">This uses the stable item_key; override follows the item even if it moves.</small>
            </div>
            <div class="col-md-3 mb-3">
              <label class="mb-0">Visibility</label>
              <select name="visible" id="ov-visible" class="form-control form-control-sm">
                <option value="">(no change)</option>
                <option value="1">Show (force)</option>
                <option value="0">Hide</option>
              </select>
              <small class="form-text text-muted">Force show clears permission at render time.</small>
            </div>
            <div class="col-md-3 mb-3">
              <label class="mb-0">Order</label>
              <input type="number" min="1" name="order_override" id="ov-order" class="form-control form-control-sm" placeholder="1">
              <small class="form-text text-muted">1-based within the item’s current parent.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-4 mb-3">
              <label class="mb-0">Label override</label>
              <input name="label_override" id="ov-label" class="form-control form-control-sm" placeholder="Text or translation key">
              <small class="form-text text-muted">Set a literal label or i18n key.</small>
            </div>
            <div class="col-md-4 mb-3">
              <label class="mb-0">Permission override</label>
              <select name="permission_override" id="ov-permission" class="form-control form-control-sm">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $opt)
                  @php $val = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt; @endphp
                  <option value="{{ $val }}">{{ $val }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Ignored if Visibility = Show.</small>
            </div>
            <div class="col-md-4 mb-3">
              <label class="mb-0">Icon override</label>
              <input name="icon_override" id="ov-icon" class="form-control form-control-sm" placeholder="fas fa-…">
              <small class="form-text text-muted">Optional Font Awesome class.</small>
            </div>
          </div>

          <div class="form-row">
            <div class="col-md-6 d-flex align-items-end justify-content-end">
              <button class="btn btn-primary btn-sm">Save Override</button>
              <button type="button" class="btn btn-outline-danger btn-sm ml-2" id="ov-delete">Delete</button>
            </div>
          </div>
        </form>

        {{-- Hidden delete form --}}
        <form id="form-override-delete" action="{{ route('osmm.menu.override.delete') }}" method="post" style="display:none;">
          @csrf
          @method('DELETE')
          <input type="hidden" name="item_key" id="ov-del-item-key">
          {{-- Optional legacy fallback --}}
          {{-- <input type="hidden" name="id" id="ov-del-id"> --}}
        </form>

      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Overrides in Database</strong>
        <small class="text-muted ml-2">osmm_menu_overrides (item_key)</small>
      </div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        {{-- New: list overrides from the item_key table --}}
        <div class="list-group list-group-flush osmm-overrides" id="osmm-override-list">
          @forelse(($overrides ?? []) as $key => $ov)
            <button
              type="button"
              class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
              data-osmm-override-row
              data-item='@json(array_merge($ov, ["item_key"=>$key]))'>
              <span class="text-monospace">{{ $key }}</span>
              <small class="text-muted">
                @if(!empty($ov['label_override'])) lbl
                @endif
                @if(array_key_exists('visible',$ov) && $ov['visible'] !== null) vis
                @endif
                @if(!empty($ov['order_override'])) ord
                @endif
                @if(!empty($ov['permission_override'])) perm
                @endif
              </small>
            </button>
          @empty
            <div class="p-3 text-muted">No overrides saved yet.</div>
          @endforelse
        </div>
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
        @include('seat-osmm::menu.partials.sidebar', [
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
<hr/>

{{-- Top previews: Native vs Merged as a navbar --}}
<div class="row">
  <div class="col-md-12">
    <div class="card mb-3">
      <div class="card-header">
        <strong>Native Navbar</strong>
        <small class="text-muted ml-2">native raw menu</small>
      </div>
      <div class="card-body p-0">
        @include('seat-osmm::menu.partials.topbar', ['menu' => $native_raw, 'can' => $can ?? null])
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

<hr/>

{{-- OVERRIDE-ONLY FORMS --}}
<div class="row mt-4">
  {{-- LEFT: PARENT OVERRIDE --}}
  <div class="col-md-4">
    
  </div>

  {{-- CENTER: SETTINGS --}}
  <div class="col-md-4">
    
  </div>


  {{-- RIGHT: CHILD OVERRIDE --}}
  <div class="col-md-4">
    
  </div>
</div>
<div class="row mt-4">
  
</div>


{{-- Shared datalist for route segments --}}
<datalist id="route-segment-options">
  @foreach(($routeSegments ?? []) as $opt)
    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
  @endforeach
</datalist>

{{-- Styling --}}
{{-- <style>
  .osmm-sidebar a.osmm-link { cursor: pointer; }
  .osmm-sidebar .osmm-link.active,
  .osmm-overrides .list-group-item.active {
    background: rgba(0,123,255,.08);
    border-left: 3px solid #007bff;
  }
  .osmm-details .table td, .osmm-details .table th { vertical-align: middle; }
  .osmm-details .w-35 { width: 35%; }
</style> --}}

{{-- Script: details tables, edit form, dependent dropdowns --}}
@push('javascript')
<script>
(function(){
  const slider = document.getElementById('osmm-menu-slider');
  const label  = document.getElementById('osmm-menu-mode-label');
  const hidden = document.getElementById('osmm-menu-mode');
  const form   = document.getElementById('form-osmm-settings');

  if (!slider || !label || !hidden || !form) return;

  // Accept 0 (treated as Off) so first render from server works even if not set yet
  const map = {0:'off', 1:'off', 2:'sidebar', 3:'topbar'};
  const title = (s) => s.charAt(0).toUpperCase() + s.slice(1);

  // Initialize from server-provided data-initial (or current value)
  const init = parseInt(slider.getAttribute('data-initial') || slider.value || '0', 10);
  slider.value = isNaN(init) ? 0 : init;

  function sync() {
    const v = parseInt(slider.value, 10);
    const mode = (v in map) ? map[v] : 'off';
    label.textContent = title(mode);
    hidden.value = mode;
  }

  slider.addEventListener('input',  sync);
  slider.addEventListener('change', sync);

  sync(); // initial
})();


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
    const delFm  = document.getElementById('form-override-delete');

    if (delBtn && delFm) {
      delBtn.onclick = function () {
        // payload from your overrides list detail panel:
        // make sure it includes item_key or _osmm_item_key
        const key = (window.osmmCurrentOverride?.item_key)
                || (window.osmmCurrentOverride?._osmm_item_key)
                || (typeof payload !== 'undefined' ? (payload.item_key || payload._osmm_item_key) : '');

        if (!key) { alert('Missing item_key for this override.'); return; }

        if (confirm('Delete this override?')) {
          document.getElementById('ov-del-item-key').value = key;
          // Optional legacy id support:
          // document.getElementById('ov-del-id').value = window.osmmCurrentOverride?.id || '';
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
      window.osmmCurrentOverride = payload;
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



(function(){
  // ===== Flat catalog for the new selector =====
  // Provide this from controller: $catalogFlat = [['item_key'=>'...','path'=>'Home'], ...]
  const CATALOG_FLAT = @json($catalogFlat ?? []);

  // Populate the override-item select
  const sel = document.getElementById('ov-item-key');
  if (sel) {
    sel.innerHTML = '';
    CATALOG_FLAT.forEach(i => {
      sel.add(new Option(i.path + '  —  ' + i.item_key, i.item_key));
    });
  }

  // Pre-fill form when clicking an override row
  document.addEventListener('click', function(ev){
    const row = ev.target.closest('[data-osmm-override-row]');
    if (!row) return;
    let payload = {};
    try { payload = JSON.parse(row.dataset.item || '{}'); } catch(e){}
    const f = document.getElementById('form-override');
    if (!f) return;

    // Select the item
    if (payload.item_key && sel && ![...sel.options].some(o => o.value === payload.item_key)) {
      sel.add(new Option(payload.item_key, payload.item_key, true, true));
    }
    if (sel) sel.value = payload.item_key || '';

    // Fill fields (normalize blank → '')
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = (v ?? '') === null ? '' : (v ?? ''); };
    set('ov-label',        payload.label_override);
    set('ov-order',        payload.order_override);
    set('ov-permission',   payload.permission_override);
    set('ov-icon',         payload.icon_override);
    set('ov-route',        payload.route_override);

    // Visible: accept 0/1 or blank
    const vis = document.getElementById('ov-visible');
    if (vis) {
      if (payload.visible === 0 || payload.visible === '0') vis.value = '0';
      else if (payload.visible === 1 || payload.visible === '1') vis.value = '1';
      else vis.value = '';
    }
  });

  // Delete current override
  const delBtn = document.getElementById('ov-delete');
  if (delBtn) delBtn.addEventListener('click', function(){
    const k = (document.getElementById('ov-item-key') || {}).value;
    if (!k) return;
    if (!confirm('Delete override for ' + k + '?')) return;
    const fm = document.getElementById('form-override-delete');
    document.getElementById('ov-del-item-key').value = k;
    fm.submit();
  });
})();

</script>
@endpush
@endsection
