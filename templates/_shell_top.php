<?php
/**
 * Shared app shell: <head>, header, tabs.
 * Expects: $user, $access, $state (when a month is loaded), $activeTab.
 */
declare(strict_types=1);

$theme = in_array((string) $user['theme'], HH_THEMES, true) ? (string) $user['theme'] : 'ember';
$activeTab = $activeTab ?? 'today';
$title = $title ?? 'Hector Habit';

$monthSuffix = isset($state['month_key']) ? '&m=' . rawurlencode((string) $state['month_key']) : '';
$selectedYear = (int) ($state['year'] ?? (new DateTimeImmutable('today'))->format('Y'));
$selectedMonth = (int) ($state['month'] ?? (new DateTimeImmutable('today'))->format('n'));
$yearOptions = isset($state) ? hh_year_options(hh_db(), (int) $user['id'], $selectedYear) : [];
$monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
// The picker marks today's year/month; the month is only "current" inside the current year.
$nowYear = (int) (new DateTimeImmutable('today'))->format('Y');
$nowMonth = $selectedYear === $nowYear ? (int) (new DateTimeImmutable('today'))->format('n') : 0;

$tabs = [
  'today'   => ['label' => 'Today',   'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="7"/><path d="M9 5.5v4l2.5 2"/></svg>', 'href' => '/app?v=today'],
  'week'    => ['label' => 'Week',    'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h12M3 9h12M3 12h7"/></svg>', 'href' => '/app?v=week' . $monthSuffix],
  'month'   => ['label' => 'Month',   'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3.5" width="14" height="12" rx="2"/><path d="M6 2v3M12 2v3M2 7.5h14"/></svg>', 'href' => '/app?v=month' . $monthSuffix],
  'habits'  => ['label' => 'Habits',  'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 4 4 8-8"/></svg>', 'href' => '/app?v=habits'],
  'profile' => ['label' => 'Profile', 'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="6" r="3"/><path d="M3 16c0-3 2.7-5 6-5s6 2 6 5"/></svg>', 'href' => '/app?v=profile'],
  'logs'    => ['label' => 'Logs',    'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h10M4 8h10M4 12h6"/></svg>', 'href' => '/app?v=logs&m=all'],
  'plan'    => ['label' => 'Plan',    'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2v5l3 3M4.5 14a7 7 0 1 0 0-10"/></svg>', 'href' => '/billing'],
  'look'    => ['label' => 'Look',    'icon' => '<svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="3"/><path d="M2 9a7 7 0 0 1 14 0 7 7 0 0 1-14 0"/></svg>', 'href' => '/app?v=look'],
];
?>
<!doctype html>
<html lang="en" class="theme-<?= hh_e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="<?= $theme === 'paper' ? 'light' : 'dark' ?>">
<meta name="theme-color" content="<?= $theme === 'paper' ? '#f4efe4' : '#08090b' ?>">
<title><?= hh_e($title) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%230b0c0f'/><path d='M9 22V10h3v4.4h5V10h3v12h-3v-4.6h-5V22z' fill='%23e3a857'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400;1,9..144,500&display=swap">
<link rel="stylesheet" href="<?= hh_e(hh_url('/assets/app.css')) ?>">
</head>
<body data-view="<?= hh_e($activeTab) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="app-header">
  <div class="inner">
    <div class="who">
      <div class="brand-group">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 28 28" role="presentation">
            <rect x="1.5" y="1.5" width="25" height="25" rx="8" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <rect x="6" y="6" width="6" height="6" rx="2" fill="currentColor"/>
            <rect x="16" y="6" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
            <rect x="6" y="16" width="6" height="6" rx="2" fill="currentColor" opacity=".5"/>
            <rect x="16" y="16" width="6" height="6" rx="2" fill="currentColor"/>
          </svg>
        </span>
        <div>
          <div class="wordmark">Hector <span>Habit</span></div>
          <div class="sub" id="header-sub"><?= hh_e($state['month_label'] ?? '') ?><?= isset($profileData) ? hh_e(' · Lv ' . $profileData['level'] . ' ' . $profileData['rank']['emoji'] . ' ' . $profileData['rank']['label']) : '' ?></div>
        </div>
      </div>
    </div>
    <div class="controls">
      <?php if (!empty($access['active']) && isset($state)): ?>
        <?php /* Native selects stay as the no-JS fallback; app.js upgrades each .hh-select to the styled picker. */ ?>
        <div class="hh-select hh-select--year" data-hh-select data-title="Select year" data-current="<?= $nowYear ?>">
          <label class="sr-only" for="year-select" id="year-select-label">Year</label>
          <select id="year-select" aria-label="Year">
            <?php foreach ($yearOptions as $yearOption): ?>
              <option value="<?= (int) $yearOption ?>" <?= (int) $yearOption === $selectedYear ? 'selected' : '' ?>><?= (int) $yearOption ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="hh-select hh-select--month" data-hh-select data-title="Select month" data-current="<?= $nowMonth ?: '' ?>">
          <label class="sr-only" for="month-select" id="month-select-label">Month</label>
          <select id="month-select" aria-label="Month">
            <?php foreach ($monthNames as $monthNumber => $monthName): ?>
              <option value="<?= (int) $monthNumber ?>" data-short="<?= hh_e(substr($monthName, 0, 3)) ?>" <?= (int) $monthNumber === $selectedMonth ? 'selected' : '' ?>><?= hh_e($monthName) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <a class="btn btn-sm btn-ghost" href="<?= hh_e(hh_url('/logout')) ?>">Sign out</a>
    </div>
  </div>
  <nav class="tabs" aria-label="Primary navigation">
    <?php foreach ($tabs as $key => $tab): ?>
      <a class="tab <?= $activeTab === $key ? 'is-active' : '' ?>" href="<?= hh_e(hh_url($tab['href'])) ?>"<?= $activeTab === $key ? ' aria-current="page"' : '' ?>>
        <span class="tab-icon" aria-hidden="true"><?= $tab['icon'] ?></span>
        <?= hh_e($tab['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
</header>

<main class="wrap" id="main">
