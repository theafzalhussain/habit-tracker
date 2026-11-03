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
    safe(bindMonthSelect);
    safe(bindTheme);
    safe(bindPassword);
    safe(bindCoupon);
    safe(bindHabitManager);
    safe(bindPay);
    safe(scrollGridToToday);
  });

  // Exposed for debugging from the console; harmless.
  window.Hector = { refresh: refresh, get state() { return state; } };
})();
