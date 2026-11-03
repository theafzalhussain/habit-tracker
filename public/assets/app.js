/* ==========================================================================
   Hector Habit — vanilla JS. No framework, no build step, no secrets.
   The server is the source of truth: every write is followed by a re-fetch of
   /state, so a stale tab can never show a phantom checkmark.
   ========================================================================== */
(function () {
  'use strict';

  var BOOT = window.HECTOR || {};
  var state = BOOT.state || null;
  var BASE = BOOT.base || '';
  var CSRF = BOOT.csrf || '';
  var VIEW = (document.body && document.body.dataset.view) || 'today';
  var WD = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null && text !== '') { n.textContent = text; }
    return n;
  }

  /* ------------------------------------------------------------- toast ---- */
  var toastTimer = null;
  function toast(msg, isError) {
    var box = $('#toast');
    if (!box) { return; }
    box.textContent = msg;
    box.className = 'show' + (isError ? ' err' : '');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { box.className = ''; }, 2800);
  }

  /* --------------------------------------------------------------- api ---- */
  function api(path, data) {
    var body = new URLSearchParams();
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    body.append('csrf', CSRF);

    return fetch(BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-CSRF-Token': CSRF
      },
      body: body.toString()
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, error: 'Server returned an unreadable response.' };
      });
    });
  }

  /* ------------------------------------------------------------ render ---- */
  function setHTML(id, html) {
    var node = document.getElementById(id);
    if (node) { node.innerHTML = html; }
  }

  // Not every view prints every stat (Look and Plan have no stat strip at all),
  // so each one is optional. A missing element must never abort the render.
  function renderStats() {
    var s = state.stats;
    var done = document.getElementById('stat-done');
    if (done) { done.textContent = String(s.done_today); }
    setHTML('stat-due', s.due_today + ' <small>scheduled today</small>');
    setHTML('stat-xp', s.xp_month + ' <small>XP this month</small>');
    setHTML('stat-month', s.month_pct + '% <small>' + s.month_hit + '/' + s.month_scheduled + ' scheduled</small>');

    var meter = document.getElementById('today-progress');
    var fill = document.getElementById('today-progress-fill');
    var copy = document.getElementById('today-progress-copy');
    if (meter && fill && copy) {
      var due = Number(s.due_today) || 0;
      var completed = Number(s.done_today) || 0;
      var pct = due > 0 ? Math.round(completed * 100 / due) : 0;
      fill.style.width = pct + '%';
      meter.setAttribute('aria-valuemax', String(Math.max(1, due)));
      meter.setAttribute('aria-valuenow', String(completed));
      copy.textContent = due > 0 ? completed + ' of ' + due + ' completed' : 'No habits scheduled today';
    }

    var badge = s.today_achievement || null;
    var badgeMark = document.getElementById('today-achievement-mark');
    var badgeTitle = document.getElementById('today-achievement-title');
    var badgeCopy = document.getElementById('today-achievement-copy');
    var badgeBox = document.getElementById('today-achievement');
    if (badgeMark && badgeTitle && badgeCopy) {
      badgeMark.textContent = badge ? badge.emoji : '—';
      badgeTitle.textContent = badge ? badge.name + ' · ' + s.success_pct_today + '%' : 'No scheduled habits';
      badgeCopy.textContent = badge ? badge.copy : 'Nothing is due today.';
      if (badgeBox) { badgeBox.dataset.tier = badge ? badge.tier : 'none'; }
    }
  }

  function cellButton(h, cell, opts) {
    opts = opts || {};
    var btn = el('button', 'day-btn');
    btn.type = 'button';
    btn.dataset.habit = String(h.id);
    btn.dataset.date = cell.iso;

    if (opts.weekdayLabel) { btn.appendChild(el('span', 'wd', opts.weekdayLabel)); }
    if (opts.text) {
      btn.appendChild(el('span', null, opts.text));
      btn.classList.add('today-action');
    } else {
      btn.appendChild(el('span', null, String(cell.day)));
    }
    if (cell.achievement) {
      btn.appendChild(el('span', 'day-achievement', cell.achievement.emoji));
    }

    btn.setAttribute('aria-pressed', cell.checked ? 'true' : 'false');
    var badgeLabel = cell.achievement ? ' · ' + cell.achievement.name + ' daily achievement' : '';
    btn.setAttribute('aria-label', h.name + ' — ' + (cell.checked ? 'completed' : 'not completed') + ' on ' + cell.iso + badgeLabel);
    if (cell.checked) { btn.classList.add('is-on'); }
    if (cell.today) { btn.classList.add('is-today'); }
    if (cell.in_month === false) { btn.classList.add('is-out-month'); }
    if (!cell.due) {
      btn.disabled = true;
      btn.title = 'Not scheduled on this day';
    } else {
      btn.title = cell.iso + ' — ' + (cell.checked ? 'checked, click to uncheck' : 'click to check');
    }
    if (cell.achievement) { btn.title += ' · ' + cell.achievement.name + ' achievement'; }
    return btn;
  }

  function habitCard(h) {
    var card = el('article', 'habit');
    if (h.scheduled === 0) { card.classList.add('is-idle'); }
    if (VIEW === 'today') { card.classList.add('habit--today'); }
    if (VIEW === 'today' && h.today_cell && h.today_cell.checked) { card.classList.add('is-complete'); }
    card.dataset.habit = String(h.id);

    var head = el('div', 'habit-copy');
    head.appendChild(el('div', 'name', h.name));
    var hit = VIEW === 'week' ? Number(h.week_hit || 0) : Number(h.hit || 0);
    var scheduled = VIEW === 'week' ? Number(h.week_scheduled || 0) : Number(h.scheduled || 0);
    var period = VIEW === 'week' ? 'this week' : 'this month';
    var detail = scheduled > 0 ? hit + '/' + scheduled + ' ' + period : 'No scheduled days ' + period;
    head.appendChild(el('div', 'meta', detail + ' · ' + h.freq_label + ' · ' + h.xp + ' XP'));

    var streak = el('div', 'streak' + (h.streak > 0 ? ' hot' : ''), '🔥 ' + h.streak + 'd');
    streak.title = h.streak > 0 ? h.streak + ' consecutive due-days checked' : 'No active streak';
    streak.setAttribute('aria-label', h.streak + ' day streak');

    var bar = el('div', 'bar');
    var fill = el('i');
    fill.style.width = (scheduled > 0 ? Math.round(hit * 100 / scheduled) : 0) + '%';
    bar.appendChild(fill);

    var days = el('div', 'days' + (VIEW === 'month' ? ' scroll' : ''));

    if (VIEW === 'today') {
      var tcell = h.today_cell || null;
      if (tcell && tcell.due) {
        var big = cellButton(h, tcell, { text: tcell.checked ? 'Completed today ✓' : 'Mark today' });
        days.appendChild(big);
      }
    } else if (VIEW === 'week') {
      (h.week_cells || []).forEach(function (cell) {
        var wdLabel = WD[cell.weekday - 1];
        days.appendChild(cellButton(h, cell, { weekdayLabel: wdLabel }));
      });
    } else {
      h.cells.forEach(function (c) { days.appendChild(cellButton(h, c, {})); });
    }

    card.appendChild(head);
    card.appendChild(streak);
    card.appendChild(bar);
    card.appendChild(days);
    return card;
  }

  function padCells(tr, count) {
    for (var i = 0; i < count; i++) { tr.appendChild(el('td', 'cell')); }
  }

  function renderList() {
    var list = $('#habit-list');
    if (!list) { return; }
    list.innerHTML = '';
    var visible = state.habits;
    if (VIEW === 'today') {
      visible = state.habits.filter(function (h) { return !!(h.today_cell && h.today_cell.due); });
    }
    if (!visible.length) {
      var message = VIEW === 'today'
        ? 'Nothing is scheduled for today. A zero-due day is not a failure; there is no scheduled work to mark.'
        : 'No habits on this account yet.';
      list.appendChild(el('div', 'empty', message));
      return;
    }
    visible.forEach(function (h) { list.appendChild(habitCard(h)); });
  }

  function renderGrid() {
    var body = $('#grid-body');
    if (!body) { return; }
    body.innerHTML = '';

    state.habits.forEach(function (h) {
      var tr = el('tr');
      var th = el('td', 'col-habit');
      var box = el('div');
      box.appendChild(el('div', 'name', h.name));
      box.appendChild(el('div', 'meta small', h.hit + '/' + h.scheduled + ' · ' + h.xp + ' XP · 🔥 ' + h.streak + 'd'));
      th.appendChild(box);
      tr.appendChild(th);

      h.cells.forEach(function (c) {
        var td = el('td', 'cell');
        var b = el('button');
        b.type = 'button';
        b.dataset.habit = String(h.id);
        b.dataset.date = c.iso;
        if (c.checked) { b.classList.add('is-on'); }
        if (c.today) { b.classList.add('is-today'); }
        b.setAttribute('aria-pressed', c.checked ? 'true' : 'false');
        b.setAttribute('aria-label', h.name + ' — ' + (c.checked ? 'completed' : 'not completed') + ' on ' + c.iso);
        b.disabled = !c.due;
        b.title = c.iso + (c.due ? (c.checked ? ' — checked' : ' — click to check') : ' — not scheduled');
        td.appendChild(b);
        tr.appendChild(td);
      });
      padCells(tr, 31 - h.cells.length);
      body.appendChild(tr);
    });

    var pctRow = $('#grid-pct');
    if (pctRow) {
      pctRow.innerHTML = '';
      pctRow.appendChild(el('td', 'col-stat', 'Daily success'));
      state.daily.forEach(function (d) {
        var td = el('td', 'pct-cell');
        if (d.pct !== null) {
          if (d.achievement) {
            var mark = el('span', 'pct-achievement', d.achievement.emoji);
            mark.title = d.achievement.name + ' — ' + d.achievement.copy;
            td.appendChild(mark);
          }
          td.appendChild(el('span', null, d.pct + '%'));
        }
        if (d.today) { td.style.color = 'var(--accent)'; }
        pctRow.appendChild(td);
      });
      padCells(pctRow, 31 - state.daily.length);
    }
  }

  function bindToggles(root) {
    $$('.day-btn, table.grid .cell button', root || document).forEach(function (btn) {
      if (btn.dataset.bound === '1' || btn.disabled) { return; }
      btn.dataset.bound = '1';
      btn.addEventListener('click', onToggle);
    });
  }

  function render() {
    if (!state) { return; }
    renderStats();
    renderList();
    renderGrid();
    bindToggles();

    var monthLabel = $('#month-label');
    var sub = $('#header-sub');
    if (sub) {
      var dateText = state.is_current_month
        ? state.month_label + ' · day ' + state.today_day
        : state.month_label + ' · viewing another month';
      var profile = BOOT.profile || null;
      var levelText = profile ? ' · Level ' + profile.level + ' ' + profile.rank_emoji + ' ' + profile.rank_label : '';
      sub.textContent = dateText + levelText;
    }
  }

  /* ------------------------------------------------------------ toggling -- */
  function onToggle(ev) {
    var btn = ev.currentTarget;
    var habitId = btn.dataset.habit;
    var date = btn.dataset.date;
    if (!habitId || !date) { return; }

    var wasOn = btn.classList.contains('is-on');
    var willBeOn = !wasOn;

    btn.classList.toggle('is-on', willBeOn);
    btn.classList.add('is-saving');
    btn.setAttribute('aria-pressed', willBeOn ? 'true' : 'false');
    btn.setAttribute('aria-busy', 'true');
    btn.disabled = true;

    api('/toggle', { habit_id: habitId, date: date, checked: willBeOn ? 1 : 0 })
      .then(function (json) {
        if (!json.ok) {
          btn.classList.toggle('is-on', wasOn);
          btn.setAttribute('aria-pressed', wasOn ? 'true' : 'false');
          btn.classList.remove('is-saving');
          btn.removeAttribute('aria-busy');
          btn.disabled = false;
          toast(json.error || 'Could not save that.', true);
          return;
        }
        return refresh().then(function () {
          if (btn.isConnected) {
            btn.classList.remove('is-saving');
            btn.removeAttribute('aria-busy');
            btn.disabled = false;
          }
        });
      })
      .catch(function () {
        btn.classList.toggle('is-on', wasOn);
        btn.setAttribute('aria-pressed', wasOn ? 'true' : 'false');
        btn.classList.remove('is-saving');
        btn.removeAttribute('aria-busy');
        btn.disabled = false;
        toast('Network error — nothing was saved.', true);
      });
  }

  function refresh() {
    if (!state) { return Promise.resolve(); }
    return fetch(BASE + '/state?m=' + encodeURIComponent(state.month_key), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json && json.habits) { state = json; render(); }
      })
      .catch(function () { toast('Could not refresh. Reload the page.', true); });
  }

  /* ---------------------------------------------------------------- misc -- */
  function bindMonthSelect() {
    var monthSel = $('#month-select');
    var yearSel = $('#year-select');
    if (!monthSel || !yearSel) { return; }
    function changeMonth() {
      var year = yearSel.value;
      var month = String(monthSel.value).padStart(2, '0');
      // The Sheet has separate year/month selectors. Keep Week/Month and Logs
      // views in place; other tabs open the month grid when time changes.
      var nextView = (VIEW === 'week' || VIEW === 'month' || VIEW === 'logs') ? VIEW : 'month';
      window.location.href = BASE + '/app?v=' + nextView + '&m=' + encodeURIComponent(year + '-' + month);
    }
    monthSel.addEventListener('change', changeMonth);
    yearSel.addEventListener('change', changeMonth);
  }

  /* ----------------------------------------------------- premium select -- */
  // Upgrades <div class="hh-select" data-hh-select><select>…</select></div>
  // into a styled combobox + listbox (WAI-ARIA "select-only combobox").
  // The native <select> stays in the DOM as the source of truth: choosing an
  // option sets its value and fires `change`, so bindMonthSelect is unchanged.
  // Wide screens get a popover anchored to the trigger; phones a bottom sheet.
  // The panel lives in <body> because the header's backdrop-filter would
  // otherwise clip and re-anchor anything fixed inside it.
  var SHEET_MEDIA = '(max-width: 560px)';
  var CHEVRON_SVG = '<svg class="hh-select-chevron" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="m3 4.5 3 3 3-3"/></svg>';
  var CLOSE_SVG = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8"/></svg>';
  var pickers = [];
  var openPicker = null;
  var pickerBackdrop = null;

  function isSheetMode() {
    return !!(window.matchMedia && window.matchMedia(SHEET_MEDIA).matches);
  }

  function getBackdrop() {
    if (!pickerBackdrop) {
      pickerBackdrop = el('div', 'hh-select-backdrop');
      pickerBackdrop.hidden = true;
      // `click` (not pointerdown) so the tap that closes the sheet can never
      // fall through to a link underneath it.
      pickerBackdrop.addEventListener('click', function () {
        if (openPicker) { openPicker.close(true); }
      });
      document.body.appendChild(pickerBackdrop);
    }
    return pickerBackdrop;
  }

  function enhanceSelect(wrap) {
    var select = $('select', wrap);
    if (!select || !select.options.length || wrap.dataset.enhanced === '1') { return; }
    wrap.dataset.enhanced = '1';

    var uid = select.id || ('hh-select-' + pickers.length);
    var labelNode = $('label', wrap);
    var title = wrap.dataset.title || select.getAttribute('aria-label') || 'Choose';
    var current = wrap.dataset.current || '';
    var count = select.options.length;
    var cols = Math.max(1, Math.min(3, count));

    // Trigger: looks like the old pill, behaves like a select.
    var trigger = el('button', 'hh-select-trigger');
    trigger.type = 'button';
    trigger.id = uid + '-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', uid + '-listbox');
    if (labelNode && labelNode.id) {
      trigger.setAttribute('aria-labelledby', labelNode.id);
    } else {
      trigger.setAttribute('aria-label', select.getAttribute('aria-label') || title);
    }
    var valueNode = el('span', 'hh-select-value');
    var fullNode = el('span', 'hh-select-full');
    var shortNode = el('span', 'hh-select-short');
    valueNode.appendChild(fullNode);
    valueNode.appendChild(shortNode);
    trigger.appendChild(valueNode);
    trigger.insertAdjacentHTML('beforeend', CHEVRON_SVG);

    // Panel: title row (+ close button in sheet mode) and the option grid.
    var panel = el('div', 'hh-select-panel');
    panel.id = uid + '-panel';
    panel.hidden = true;
    panel.style.setProperty('--hh-cols', String(cols));
    var head = el('div', 'hh-select-head');
    head.appendChild(el('span', 'hh-select-title', title));
    var closeBtn = el('button', 'hh-select-close');
    closeBtn.type = 'button';
    closeBtn.tabIndex = -1;
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.innerHTML = CLOSE_SVG;
    head.appendChild(closeBtn);

    var list = el('ul', 'hh-select-list');
    list.id = uid + '-listbox';
    list.setAttribute('role', 'listbox');
    if (labelNode && labelNode.id) {
      list.setAttribute('aria-labelledby', labelNode.id);
    } else {
      list.setAttribute('aria-label', title);
    }

    var items = [];
    for (var i = 0; i < count; i++) {
      var opt = select.options[i];
      var full = (opt.textContent || '').trim();
      var shown = cols > 1 ? (opt.dataset.short || full) : full;
      var isCurrent = current !== '' && opt.value === current;
      var li = el('li', 'hh-option');
      li.id = uid + '-opt-' + i;
      li.setAttribute('role', 'option');
      li.dataset.index = String(i);
      li.appendChild(el('span', 'hh-option-label', shown));
      var spoken = full + (isCurrent ? ', current' : '');
      if (spoken !== shown) { li.setAttribute('aria-label', spoken); }
      if (isCurrent) {
        li.classList.add('is-current');
        li.title = 'Current';
      }
      if (opt.disabled) {
        li.classList.add('is-disabled');
        li.setAttribute('aria-disabled', 'true');
      }
      list.appendChild(li);
      items.push(li);
    }
    panel.appendChild(head);
    panel.appendChild(list);
    document.body.appendChild(panel);

    wrap.insertBefore(trigger, select);
    wrap.classList.add('is-enhanced');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    var active = -1;
    var sheet = false;
    var keyHandledAt = 0;
    var typed = '';
    var typedTimer = null;
    var hideTimer = null;
    var placeFrame = 0;
    var picker = null;

    function labelOf(index) { return (select.options[index].textContent || '').trim(); }

    function sync() {
      var idx = select.selectedIndex;
      var opt = idx >= 0 ? select.options[idx] : null;
      fullNode.textContent = opt ? labelOf(idx) : '';
      shortNode.textContent = opt ? (opt.dataset.short || labelOf(idx)) : '';
      items.forEach(function (item, index) {
        var on = index === idx;
        item.classList.toggle('is-selected', on);
        item.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }

    // Browsers may restore a stale form value on reload/back; the server's
    // `selected` option is the month actually rendered on the page.
    function resetToServerValue() {
      for (var n = 0; n < select.options.length; n++) {
        if (select.options[n].defaultSelected) { select.selectedIndex = n; break; }
      }
      wrap.classList.remove('is-busy');
      sync();
    }

    function scrollIntoList(item) {
      var top = item.offsetTop;
      var bottom = top + item.offsetHeight;
      if (top < list.scrollTop) {
        list.scrollTop = top - 4;
      } else if (bottom > list.scrollTop + list.clientHeight) {
        list.scrollTop = bottom - list.clientHeight + 4;
      }
    }

    function setActive(index, scroll) {
      if (!items.length) { return; }
      index = Math.max(0, Math.min(items.length - 1, index));
      if (active >= 0 && items[active]) { items[active].classList.remove('is-active'); }
      active = index;
      items[index].classList.add('is-active');
      trigger.setAttribute('aria-activedescendant', items[index].id);
      if (scroll) { scrollIntoList(items[index]); }
    }

    // Grid navigation: left/right step one, up/down step one row and stop at the edge.
    function move(delta) {
      var next = active + delta;
      if (next < 0 || next >= items.length) {
        if (Math.abs(delta) > 1) { return; }
        next = next < 0 ? 0 : items.length - 1;
      }
      setActive(next, true);
    }

    function typeahead(ch) {
      clearTimeout(typedTimer);
      typedTimer = setTimeout(function () { typed = ''; }, 700);
      typed += ch.toLowerCase();
      var repeat = typed.length > 1 && typed.split('').every(function (c) { return c === typed[0]; });
      var needle = repeat ? typed[0] : typed;
      var from = (typed.length === 1 || repeat) ? active + 1 : Math.max(active, 0);
      for (var n = 0; n < items.length; n++) {
        var index = (from + n) % items.length;
        if (labelOf(index).toLowerCase().indexOf(needle) === 0) { return index; }
      }
      return -1;
    }

    function place() {
      if (sheet) {
        panel.style.top = '';
        panel.style.left = '';
        panel.style.maxHeight = '';
        panel.style.transformOrigin = '';
        panel.classList.remove('is-up');
        return;
      }
      var r = trigger.getBoundingClientRect();
      var vw = document.documentElement.clientWidth;
      var vh = window.innerHeight;
      var gap = 8;
      var margin = 12;
      panel.style.maxHeight = '';
      var w = panel.offsetWidth;
      var h = panel.offsetHeight;
      var below = vh - r.bottom - gap - margin;
      var above = r.top - gap - margin;
      var up = h > below && above > below;
      var room = Math.max(140, up ? above : below);
      if (h > room) {
        panel.style.maxHeight = room + 'px';
        h = room;
      }
      var left = r.left;
      if (left + w > vw - margin) { left = r.right - w; }
      left = Math.max(margin, Math.min(left, vw - w - margin));
      var top = up ? r.top - gap - h : r.bottom + gap;
      panel.style.left = Math.round(left) + 'px';
      panel.style.top = Math.round(top) + 'px';
      panel.style.transformOrigin = Math.round(r.left + r.width / 2 - left) + 'px ' + (up ? '100%' : '0');
      panel.classList.toggle('is-up', up);
    }

    function open() {
      if (openPicker && openPicker !== picker) { openPicker.close(false); }
      clearTimeout(hideTimer);
      sheet = isSheetMode();
      panel.classList.toggle('is-sheet', sheet);
      panel.classList.remove('is-open');
      panel.hidden = false;
      place();
      setActive(select.selectedIndex >= 0 ? select.selectedIndex : 0, true);
      trigger.setAttribute('aria-expanded', 'true');
      wrap.classList.add('is-open');
      openPicker = picker;

      var backdrop = sheet ? getBackdrop() : null;
      if (backdrop) {
        backdrop.hidden = false;
        document.body.classList.add('hh-sheet-open');
      }
      void panel.offsetHeight; // commit the closed pose so the entrance animates
      panel.classList.add('is-open');
      if (backdrop) { backdrop.classList.add('is-open'); }

      document.addEventListener('pointerdown', onOutside, true);
      document.addEventListener('keydown', onDocKey, true);
      window.addEventListener('resize', onViewport);
      window.addEventListener('scroll', onScroll, true);
    }

    function close(refocus) {
      if (openPicker !== picker) { return; }
      openPicker = null;
      panel.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.removeAttribute('aria-activedescendant');
      wrap.classList.remove('is-open');
      if (sheet && pickerBackdrop) {
        pickerBackdrop.classList.remove('is-open');
        document.body.classList.remove('hh-sheet-open');
      }
      document.removeEventListener('pointerdown', onOutside, true);
      document.removeEventListener('keydown', onDocKey, true);
      window.removeEventListener('resize', onViewport);
      window.removeEventListener('scroll', onScroll, true);
      cancelAnimationFrame(placeFrame);
      clearTimeout(hideTimer);
      hideTimer = setTimeout(function () {
        if (openPicker === picker) { return; }
        panel.hidden = true;
        if (pickerBackdrop && !pickerBackdrop.classList.contains('is-open')) { pickerBackdrop.hidden = true; }
      }, 360);
      if (refocus) {
        try { trigger.focus({ preventScroll: true }); } catch (err) { trigger.focus(); }
      }
    }

    function choose(index) {
      if (index < 0 || index >= items.length || select.options[index].disabled) { return; }
      var changed = select.selectedIndex !== index;
      select.selectedIndex = index;
      sync();
      close(true);
      if (changed) {
        wrap.classList.add('is-busy'); // bindMonthSelect navigates; show it is working
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }

    function onOutside(ev) {
      if (sheet) { return; } // the backdrop owns outside taps in sheet mode
      if (panel.contains(ev.target) || trigger.contains(ev.target)) { return; }
      close(false);
    }

    function onDocKey(ev) {
      // Focus normally stays on the trigger; this catches Escape if it moved.
      if (ev.key === 'Escape' && document.activeElement !== trigger) {
        ev.preventDefault();
        close(true);
      }
    }

    function onViewport() {
      if (isSheetMode() !== sheet) { close(false); return; }
      cancelAnimationFrame(placeFrame);
      placeFrame = requestAnimationFrame(place);
    }

    function onScroll(ev) {
      if (sheet || (ev.target && ev.target.nodeType === 1 && panel.contains(ev.target))) { return; }
      var r = trigger.getBoundingClientRect();
      if (r.bottom < 0 || r.top > window.innerHeight) { close(false); return; }
      cancelAnimationFrame(placeFrame);
      placeFrame = requestAnimationFrame(place);
    }

    function isPrintable(ev) {
      return ev.key.length === 1 && ev.key !== ' ' && !ev.ctrlKey && !ev.metaKey && !ev.altKey;
    }

    trigger.addEventListener('click', function (ev) {
      // Enter/Space were already handled on keydown; ignore the synthetic click.
      if (ev.detail === 0 && Date.now() - keyHandledAt < 500) { return; }
      if (openPicker === picker) { close(true); } else { open(); }
    });

    trigger.addEventListener('keydown', function (ev) {
      var key = ev.key;
      var isOpen = openPicker === picker;
      var match;

      if (ev.altKey && (key === 'ArrowDown' || key === 'ArrowUp')) {
        ev.preventDefault();
        if (isOpen) { choose(active); } else { open(); }
        return;
      }
      if (!isOpen) {
        if (key === 'ArrowDown' || key === 'ArrowUp' || key === 'Enter' || key === ' ' || key === 'Home' || key === 'End') {
          ev.preventDefault();
          keyHandledAt = Date.now();
          open();
          if (key === 'Home') { setActive(0, true); }
          if (key === 'End') { setActive(items.length - 1, true); }
        } else if (isPrintable(ev)) {
          ev.preventDefault();
          open();
          match = typeahead(key);
          if (match >= 0) { setActive(match, true); }
        }
        return;
      }

      switch (key) {
        case 'ArrowRight': move(1); break;
        case 'ArrowLeft': move(-1); break;
        case 'ArrowDown': move(cols); break;
        case 'ArrowUp': move(-cols); break;
        case 'Home': setActive(0, true); break;
        case 'End': setActive(items.length - 1, true); break;
        case 'PageDown': setActive(active + cols * 3, true); break;
        case 'PageUp': setActive(active - cols * 3, true); break;
        case 'Enter':
        case ' ':
          keyHandledAt = Date.now();
          choose(active);
          break;
        case 'Escape': close(true); break;
        case 'Tab': close(false); return; // let focus move on
        default:
          if (!isPrintable(ev)) { return; }
          match = typeahead(key);
          if (match >= 0) { setActive(match, true); }
      }
      ev.preventDefault();
    });

    // Keep focus on the trigger while pointing at options (keyboard keeps working).
    panel.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    list.addEventListener('click', function (ev) {
      var item = ev.target.closest ? ev.target.closest('.hh-option') : null;
      if (item && list.contains(item)) { choose(Number(item.dataset.index)); }
    });
    list.addEventListener('pointermove', function (ev) {
      if (ev.pointerType !== 'mouse') { return; }
      var item = ev.target.closest ? ev.target.closest('.hh-option') : null;
      if (item && Number(item.dataset.index) !== active) { setActive(Number(item.dataset.index), false); }
    });
    closeBtn.addEventListener('click', function () { close(true); });

    picker = { open: open, close: close, reset: resetToServerValue };
    pickers.push(picker);
    resetToServerValue();
  }

  function bindSelects() {
    $$('[data-hh-select]').forEach(function (wrap) { safe(function () { enhanceSelect(wrap); }); });
  }

  // Back/forward cache: the page comes back exactly as it was left, i.e. with
  // the picker showing the month that was being navigated to. Restore it.
  window.addEventListener('pageshow', function (ev) {
    if (!ev.persisted) { return; }
    if (openPicker) { openPicker.close(false); }
    pickers.forEach(function (p) { p.reset(); });
  });

  function bindTheme() {
    $$('.swatch').forEach(function (s) {
      s.addEventListener('click', function () {
        var theme = s.dataset.theme;
        api('/theme', { theme: theme }).then(function (json) {
          if (!json.ok) { toast(json.error || 'Theme not saved.', true); return; }
          document.documentElement.className = 'theme-' + theme;
          $$('.swatch').forEach(function (o) { o.classList.toggle('is-active', o === s); });
          toast('Theme saved: ' + theme);
        }).catch(function () { toast('Network error — theme not saved.', true); });
      });
    });
  }

  function bindPassword() {
    var form = $('#password-form');
    if (!form) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var out = $('#password-msg');
      out.textContent = 'Saving…';
      api('/password', { current: form.elements.current.value, new: form.elements.newpw.value })
        .then(function (json) {
          out.textContent = json.message || (json.ok ? 'Password updated.' : 'Could not update the password.');
          if (json.ok) { form.reset(); }
        })
        .catch(function () { out.textContent = 'Network error — password not changed.'; });
    });
  }

  // Look → Recovery key: needs the current password; the key is shown once.
  function bindRecovery() {
    var form = $('#recovery-form');
    if (!form) { return; }
    var submit = $('#recovery-submit');
    var out = $('#recovery-msg');
    var reveal = $('#recovery-reveal');
    var keyBox = $('#recovery-key');
    var badge = $('#recovery-badge');
    var since = $('#recovery-since');

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      out.textContent = 'Creating your key…';
      submit.disabled = true;
      api('/recovery', { current: form.elements.current.value })
        .then(function (json) {
          submit.disabled = false;
          if (!json.ok) {
            out.textContent = json.message || json.error || 'Could not create a recovery key.';
            return;
          }
          form.reset();
          keyBox.textContent = json.key;
          reveal.hidden = false;
          out.textContent = json.message || 'Recovery key created.';
          if (badge) { badge.className = 'badge ok'; badge.textContent = 'ACTIVE'; }
          if (since) { since.textContent = 'Created ' + (json.created || 'today'); }
          submit.textContent = 'Create a new key';
        })
        .catch(function () {
          submit.disabled = false;
          out.textContent = 'Network error — no key was created.';
        });
    });
  }

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // Plain-http hosts have no async clipboard API.
    return new Promise(function (resolve, reject) {
      var area = document.createElement('textarea');
      area.value = text;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      area.style.pointerEvents = 'none';
      document.body.appendChild(area);
      area.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
      document.body.removeChild(area);
      if (ok) { resolve(); } else { reject(new Error('copy failed')); }
    });
  }

  function bindCopy() {
    $$('[data-copy-target]').forEach(function (btn) {
      var original = btn.textContent;
      var timer = null;
      btn.hidden = false;
      btn.addEventListener('click', function () {
        var source = document.getElementById(btn.dataset.copyTarget);
        var text = source ? source.textContent.trim() : '';
        if (!text) { return; }
        copyText(text).then(function () {
          btn.textContent = 'Copied ✓';
          btn.classList.add('is-copied');
        }, function () {
          // Last resort: select it so the user can copy by hand.
          var range = document.createRange();
          range.selectNodeContents(source);
          var selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(range);
          btn.textContent = 'Selected — copy it';
        }).then(function () {
          clearTimeout(timer);
          timer = setTimeout(function () {
            btn.textContent = original;
            btn.classList.remove('is-copied');
          }, 2400);
        });
      });
    });
  }

  // /forgot: instant "passwords match" feedback and no double submit.
  function bindForgot() {
    var form = $('#forgot-form');
    if (!form) { return; }
    var pw = form.elements.new_password;
    var confirm = form.elements.confirm_password;
    var submit = $('button[type="submit"]', form);

    function checkMatch() {
      confirm.setCustomValidity(confirm.value !== '' && confirm.value !== pw.value ? "The two new passwords don't match." : '');
    }
    if (pw) { pw.addEventListener('input', checkMatch); }
    if (confirm) { confirm.addEventListener('input', checkMatch); }

    form.addEventListener('submit', function (ev) {
      if (form.dataset.busy === '1') { ev.preventDefault(); return; }
      form.dataset.busy = '1';
      if (submit) { submit.setAttribute('aria-busy', 'true'); submit.classList.add('is-busy'); }
    });
    window.addEventListener('pageshow', function () {
      form.dataset.busy = '';
      if (submit) { submit.removeAttribute('aria-busy'); submit.classList.remove('is-busy'); }
    });
  }

  // /signup: instant password validation & matching feedback, plus no double submit.
  function bindSignup() {
    var form = $('#signup-form');
    if (!form) { return; }
    var pw = form.elements.password;
    var confirm = form.elements.confirm_password;
    var submit = $('button[type="submit"]', form);

    function validate() {
      if (pw) {
        var val = pw.value || '';
        if (val !== '') {
          if (val.length < 8) {
            pw.setCustomValidity('Password must be at least 8 characters.');
          } else if (!/[A-Z]/.test(val)) {
            pw.setCustomValidity('Password must contain at least one uppercase letter (A-Z).');
          } else if (!/[^a-zA-Z0-9]/.test(val)) {
            pw.setCustomValidity('Password must contain at least one special character (!@#$% etc.).');
          } else {
            pw.setCustomValidity('');
          }
        } else {
          pw.setCustomValidity('');
        }
      }

      if (confirm && confirm.value !== '') {
        confirm.setCustomValidity(pw && confirm.value !== pw.value ? 'Passwords do not match.' : '');
      }
    }

    if (pw) { pw.addEventListener('input', validate); }
    if (confirm) { confirm.addEventListener('input', validate); }

    form.addEventListener('submit', function (ev) {
      validate();
      if (!form.checkValidity()) {
        form.reportValidity();
        ev.preventDefault();
        return;
      }
      if (form.dataset.busy === '1') { ev.preventDefault(); return; }
      form.dataset.busy = '1';
      if (submit) { submit.setAttribute('aria-busy', 'true'); submit.classList.add('is-busy'); }
    });
    window.addEventListener('pageshow', function () {
      form.dataset.busy = '';
      if (submit) { submit.removeAttribute('aria-busy'); submit.classList.remove('is-busy'); }
    });
  }

  function bindCoupon() {
    var form = $('#coupon-form');
    if (!form) { return; }
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var url = new URL(window.location.href);
      var code = form.elements.coupon.value.trim();
      if (code) { url.searchParams.set('coupon', code); } else { url.searchParams.delete('coupon'); }
      window.location.href = url.toString();
    });
  }

  function bindPay() {
    var btn = $('#pay-btn');
    if (!btn || btn.disabled) { return; }
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var coupon = (new URL(window.location.href)).searchParams.get('coupon') || '';

      api('/billing/order', { coupon: coupon }).then(function (json) {
        if (!json.ok) {
          toast(json.error || 'Could not start the order.', true);
          btn.disabled = false;
          return;
        }
        if (!window.Razorpay) {
          toast('Razorpay Checkout did not load. Refresh and try again.', true);
          btn.disabled = false;
          return;
        }
        var rz = new window.Razorpay({
          key: json.key,
          amount: json.amount,
          currency: 'INR',
          name: 'Hector Habit',
          description: 'Annual subscription',
          order_id: json.order,
          theme: { color: '#e8a54b' },
          handler: function (response) {
            api('/billing/verify', {
              razorpay_order_id: response.razorpay_order_id,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature
            }).then(function (res) {
              if (res.ok) {
                toast('Payment recorded. Reloading…');
                setTimeout(function () { window.location.reload(); }, 800);
              } else {
                toast(res.error || 'Verification failed. Do not pay twice — contact the owner first.', true);
                btn.disabled = false;
              }
            });
          },
          modal: { ondismiss: function () { btn.disabled = false; toast('Checkout closed — nothing was charged.'); } }
        });
        rz.on('payment.failed', function () { toast('Payment failed.', true); btn.disabled = false; });
        rz.open();
      }).catch(function () {
        toast('Network error — no order was created.', true);
        btn.disabled = false;
      });
    });
  }

  function bindHabitManager() {
    var form = $('#habit-form');
    if (!form) { return; }
    var freq = form.elements.freq;
    var weekdays = $('#weekday-fields');
    var interval = $('#interval-field');
    var heading = $('#habit-form-title');
    var submit = $('#habit-save');
    var cancel = $('#habit-cancel-edit');
    var message = $('#habit-msg');
    var defaultStart = form.elements.start_date.value;

    function updateScheduleFields() {
      if (weekdays) { weekdays.hidden = freq.value !== 'days_of_week'; }
      if (interval) { interval.hidden = freq.value !== 'every_x_days'; }
    }
    function resetForm() {
      form.reset();
      form.elements.id.value = '';
      form.elements.start_date.value = defaultStart;
      form.elements.xp.value = '5';
      heading.textContent = 'Add a habit';
      submit.textContent = 'Add habit';
      cancel.hidden = true;
      message.textContent = '';
      updateScheduleFields();
    }
    freq.addEventListener('change', updateScheduleFields);
    updateScheduleFields();

    $$('.habit-edit').forEach(function (button) {
      button.addEventListener('click', function () {
        form.elements.id.value = button.dataset.id || '';
        form.elements.name.value = button.dataset.name || '';
        form.elements.xp.value = button.dataset.xp || '5';
        freq.value = button.dataset.freq || 'every_day';
        form.elements.start_date.value = button.dataset.start || defaultStart;
        form.elements.interval_days.value = button.dataset.interval || '2';
        ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].forEach(function (day) {
          form.elements[day].checked = button.dataset[day] === '1';
        });
        heading.textContent = 'Edit habit';
        submit.textContent = 'Save changes';
        cancel.hidden = false;
        message.textContent = 'Past logs and earned XP will remain unchanged.';
        updateScheduleFields();
        form.elements.name.focus();
        window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 100, behavior: 'smooth' });
      });
    });
    if (cancel) { cancel.addEventListener('click', resetForm); }

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      message.textContent = 'Saving…';
      var data = {
        id: form.elements.id.value,
        name: form.elements.name.value.trim(),
        xp: form.elements.xp.value,
        freq: freq.value,
        start_date: form.elements.start_date.value,
        interval_days: form.elements.interval_days.value
      };
      ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'].forEach(function (day) {
        data[day] = form.elements[day].checked ? 1 : 0;
      });
      api('/habit/save', data).then(function (json) {
        if (!json.ok) { message.textContent = json.error || 'Habit could not be saved.'; return; }
        toast(json.message || 'Habit saved.');
        window.location.reload();
      }).catch(function () { message.textContent = 'Network error — habit was not saved.'; });
    });

    $$('.habit-archive').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!window.confirm('Archive “' + (button.dataset.name || 'this habit') + '”? Past logs and XP will be kept.')) { return; }
        api('/habit/archive', { habit_id: button.dataset.id }).then(function (json) {
          if (!json.ok) { toast(json.message || 'Could not archive that habit.', true); return; }
          toast(json.message);
          window.location.reload();
        }).catch(function () { toast('Network error — habit was not archived.', true); });
      });
    });

    $$('.habit-restore').forEach(function (button) {
      button.addEventListener('click', function () {
        api('/habit/restore', { habit_id: button.dataset.id }).then(function (json) {
          if (!json.ok) { toast(json.message || 'Could not restore that habit.', true); return; }
          toast(json.message);
          window.location.reload();
        }).catch(function () { toast('Network error — habit was not restored.', true); });
      });
    });
  }

  function scrollGridToToday() {
    var wrap = $('.grid-wrap');
    var today = $('table.grid .cell button.is-today');
    if (!wrap || !today) { return; }
    var offset = today.getBoundingClientRect().left - wrap.getBoundingClientRect().left;
    wrap.scrollLeft = Math.max(0, wrap.scrollLeft + offset - wrap.clientWidth / 2 + 14);
  }

  function safe(fn) {
    try { fn(); } catch (err) { console.error('Hector Habit:', err); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (state) {
      document.documentElement.className = 'theme-' + (BOOT.theme || 'ember');
      safe(render);
    }
    safe(bindSelects);
    safe(bindMonthSelect);
    safe(bindTheme);
    safe(bindPassword);
    safe(bindRecovery);
    safe(bindCopy);
    safe(bindForgot);
    safe(bindSignup);
    safe(bindCoupon);
    safe(bindHabitManager);
    safe(bindPay);
    safe(scrollGridToToday);
  });

  // Exposed for debugging from the console; harmless.
  window.Hector = { refresh: refresh, get state() { return state; } };
})();
