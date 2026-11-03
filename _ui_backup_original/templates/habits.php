<?php
/** Sheet parity: editable habit list. Archiving preserves every prior log. */
declare(strict_types=1);
$weekdayNames = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
$activeHabitCount = count($habitRows ?? []);
?>
<header class="page-intro">
  <div class="eyebrow"><span class="eyebrow-dot"></span> YOUR HABIT LIST <span class="eyebrow-divider">/</span> MANAGE</div>
  <h1>Habits</h1>
  <p>Add or adjust the habits shown in your tracker. Up to 20 can be active; archiving never deletes past logs or XP.</p>
</header>

<section class="habit-editor card" aria-labelledby="habit-form-title">
  <div class="editor-heading">
    <div><span class="eyebrow-small">HABIT SETTINGS</span><h2 id="habit-form-title">Add a habit</h2></div>
    <span class="badge"><?= $activeHabitCount ?>/20 active</span>
  </div>
  <form id="habit-form" autocomplete="off">
    <input type="hidden" id="habit-id" name="id" value="">
    <div class="habit-form-grid">
      <div class="field field-wide">
        <label for="habit-name">Habit name</label>
        <input type="text" id="habit-name" name="name" maxlength="191" required placeholder="e.g. Read for 20 minutes">
      </div>
      <div class="field">
        <label for="habit-xp">XP per completion</label>
        <input type="number" id="habit-xp" name="xp" min="1" max="100" value="5" required>
      </div>
      <div class="field">
        <label for="habit-freq">Schedule</label>
        <select id="habit-freq" name="freq">
          <option value="every_day">Every day</option>
          <option value="days_of_week">Selected weekdays</option>
          <option value="every_x_days">Every X days</option>
        </select>
      </div>
      <div class="field" id="interval-field" hidden>
        <label for="habit-interval">Repeat every (days)</label>
        <input type="number" id="habit-interval" name="interval_days" min="1" max="365" value="2">
      </div>
      <div class="field">
        <label for="habit-start">Schedule starts</label>
        <input type="date" id="habit-start" name="start_date" value="<?= hh_e(hh_today()) ?>" required>
      </div>
    </div>
    <fieldset class="weekday-fieldset" id="weekday-fields" hidden>
      <legend>Days this habit is due</legend>
      <div class="weekday-options">
        <?php foreach ($weekdayNames as $key => $label): ?>
          <label class="weekday-option"><input type="checkbox" name="<?= hh_e($key) ?>" value="1"><span><?= hh_e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="editor-actions">
      <button type="submit" class="btn-primary" id="habit-save">Add habit</button>
      <button type="button" class="btn-ghost" id="habit-cancel-edit" hidden>Cancel edit</button>
      <span class="small muted" id="habit-msg" role="status" aria-live="polite"></span>
    </div>
  </form>
  <p class="small faint" style="margin:.8rem 0 0">Changing a schedule affects which future dates are due. Existing completions stay in your logbook and lifetime XP.</p>
</section>

<div class="section-head"><h2>Active habits</h2><span class="hint">Edit the name, reward, or due schedule.</span></div>
<div class="habit-manager-list">
  <?php foreach (($habitRows ?? []) as $habit): ?>
    <?php
      $schedule = hh_freq_label($habit);
      if ($habit['freq'] === 'days_of_week') {
          $selectedDays = [];
          foreach ($weekdayNames as $key => $dayName) if ((int) $habit[$key] === 1) $selectedDays[] = $dayName;
          $schedule = $selectedDays ? implode(', ', $selectedDays) : 'No weekdays selected';
      }
    ?>
    <article class="habit-manager-row">
      <div class="habit-manager-copy">
        <strong><?= hh_e((string) $habit['name']) ?></strong>
        <span class="small muted"><?= hh_e($schedule) ?> · <?= (int) $habit['xp'] ?> XP · starts <?= hh_e(hh_date((string) $habit['start_date'])->format('d M Y')) ?></span>
      </div>
      <div class="habit-manager-actions">
        <button type="button" class="btn-sm btn-ghost habit-edit"
          data-id="<?= (int) $habit['id'] ?>" data-name="<?= hh_e((string) $habit['name']) ?>"
          data-xp="<?= (int) $habit['xp'] ?>" data-freq="<?= hh_e((string) $habit['freq']) ?>"
          data-start="<?= hh_e((string) $habit['start_date']) ?>" data-interval="<?= (int) ($habit['interval_days'] ?? 0) ?>"
          <?php foreach ($weekdayNames as $key => $dayName): ?>data-<?= hh_e($key) ?>="<?= (int) $habit[$key] ?>" <?php endforeach; ?>
          aria-label="Edit <?= hh_e((string) $habit['name']) ?>">Edit</button>
        <button type="button" class="btn-sm btn-ghost habit-archive" data-id="<?= (int) $habit['id'] ?>" data-name="<?= hh_e((string) $habit['name']) ?>">Archive</button>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$activeHabitCount): ?><div class="empty">No active habits. Add one above or restore an archived habit.</div><?php endif; ?>
</div>

<?php if (!empty($archivedHabitRows)): ?>
  <div class="section-head" style="margin-top:1.8rem"><h2>Archived habits</h2><span class="hint">Past logs and earned XP remain available.</span></div>
  <div class="habit-manager-list">
    <?php foreach ($archivedHabitRows as $habit): ?>
      <article class="habit-manager-row is-archived">
        <div class="habit-manager-copy">
          <strong><?= hh_e((string) $habit['name']) ?></strong>
          <span class="small muted">Archived <?= hh_e(hh_date((string) $habit['archived_at'])->format('d M Y')) ?> · <?= (int) $habit['xp'] ?> XP reward</span>
        </div>
        <button type="button" class="btn-sm btn-ghost habit-restore" data-id="<?= (int) $habit['id'] ?>">Restore</button>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
