<?php
/**
 * Plan / Billing — the only page an expired account can reach.
 * Expects: $user, $access, $recent, and optionally $state (only when access is
 * active, so the shell can show the month picker).
 */
declare(strict_types=1);

$price = hh_price_for(isset($_GET['coupon']) ? (string) $_GET['coupon'] : null);
$paymentMode = hh_razorpay_mode();
$paymentSetupMessage = hh_payment_setup_message();
$configured = hh_payments_configured();

$activeTab = 'plan';
$title = 'Plan · Hector Habit';

require __DIR__ . '/_shell_top.php';
?>

<header class="page-intro">
  <div class="eyebrow"><span class="eyebrow-dot"></span> MEMBERSHIP <span class="eyebrow-divider">/</span> PLAN</div>
  <h1>Your plan</h1>
  <p>One private habit grid, billed once a year. Secure checkout by Razorpay.</p>
</header>

<div class="section-head">
  <h2>Annual subscription</h2>
  <span class="hint">One plan. No tiers, no seats, no "pro".</span>
</div>

<div class="payment-mode-note">
  <span class="badge <?= $paymentMode === 'test' ? 'warn' : 'ok' ?>">Razorpay <?= hh_e(strtoupper($paymentMode)) ?> MODE</span>
  <span class="small muted"><?= $paymentMode === 'test' ? 'Sandbox payments only; no real money is charged.' : 'Live payments are enabled for this server.' ?></span>
</div>

<div class="card price-card">
  <?php if (!$access['active']): ?>
    <div class="alert alert-error">
      Trial ended <?= hh_e($access['until']->format('d M Y')) ?>. Pay <strong><?= hh_e(hh_rupees((int) $price['amount'])) ?></strong> to unlock the dashboard for a year.
    </div>
  <?php elseif ($access['reason'] === 'trial'): ?>
    <div class="alert"><strong>Trial running.</strong> Ends <?= hh_e($access['until']->format('d M Y')) ?> — <?= (int) $access['days_left'] ?> day(s) left. No card was needed to start it.</div>
  <?php else: ?>
    <div class="alert alert-ok"><strong>Paid.</strong> Active until <?= hh_e($access['until']->format('d M Y')) ?>.</div>
  <?php endif; ?>

  <div class="price">
    <?= $price['coupon'] && !isset($price['error']) ? hh_e(hh_rupees((int) $price['amount'])) : '₹199' ?>
    <small>/ year</small>
  </div>
  <p class="muted small">3-day trial, then <?= hh_e(hh_rupees((int) $price['base'])) ?> billed once a year. Affiliate coupons apply at checkout.</p>

  <?php if (isset($price['error'])): ?>
    <div class="alert alert-error"><?= hh_e((string) $price['error']) ?></div>
  <?php endif; ?>

  <form id="coupon-form" style="margin:1rem 0">
    <label for="coupon">Coupon code</label>
    <div class="row">
      <input type="text" id="coupon" name="coupon" placeholder="HECTOR20" style="flex:1 1 160px; text-transform:uppercase" autocapitalize="characters" spellcheck="false"
             value="<?= hh_e((string) ($price['coupon'] ?? '')) ?>">
      <button type="submit" class="btn-ghost">Apply</button>
    </div>
    <p class="small faint" style="margin:.5rem 0 0">
      <?php if ($price['coupon'] && !isset($price['error'])): ?>
        <?= hh_e((string) $price['coupon']) ?> applied — <?= (int) $price['percent'] ?>% off.
        You save <?= hh_e(hh_rupees((int) $price['discount'])) ?>. Amount charged: <span class="mono"><?= hh_e(hh_rupees((int) $price['amount'])) ?></span>.
      <?php else: ?>
        A coupon reduces the price and records which affiliate it belongs to.
      <?php endif; ?>
    </p>
  </form>

  <?php if (!$configured): ?>
    <button class="btn-primary btn-block" disabled>Payments are not configured</button>
    <p class="small faint" style="margin:.6rem 0 0">
      <?= hh_e((string) $paymentSetupMessage) ?> Nothing is faked while setup is incomplete.
    </p>
  <?php elseif ($access['reason'] === 'paid'): ?>
    <button class="btn-block" disabled>Paid until <?= hh_e($access['until']->format('d M Y')) ?></button>
    <p class="small faint" style="margin:.6rem 0 0">Renewal opens again after <?= hh_e($access['until']->modify('-30 days')->format('d M Y')) ?>. Paying early extends from the current paid date, it never throws days away.</p>
  <?php else: ?>
    <?php if ($access['reason'] === 'trial'): ?>
      <button class="btn-block" type="button" disabled>
        Trial ends <?= hh_e($access['until']->format('d M Y')) ?>
      </button>
      <button id="pay-btn" class="btn-primary btn-block" type="button" style="margin-top:.5rem">
        Pay <?= hh_e(hh_rupees((int) $price['amount'])) ?> now
      </button>
    <?php else: ?>
      <button id="pay-btn" class="btn-primary btn-block" type="button">
        Pay <?= hh_e(hh_rupees((int) $price['amount'])) ?>
      </button>
    <?php endif; ?>
    <p class="small faint" style="margin:.6rem 0 0">
      Razorpay handles the payment. The signature is verified on the server before anything unlocks.
    </p>
  <?php endif; ?>
</div>

<?php if (!empty($recent)): ?>
  <div class="section-head" style="margin-top:2rem"><h2>Payments</h2></div>
  <div class="card table-wrap" style="max-width:760px">
    <table class="mini">
      <thead>
        <tr><th>When</th><th>Order</th><th>Payment</th><th>Amount</th><th>Coupon</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $p): ?>
          <tr>
            <td class="mono"><?= hh_e($p['paid_at'] ? date('d M Y H:i', strtotime((string) $p['paid_at'])) : '—') ?></td>
            <td class="mono small"><?= hh_e((string) $p['razorpay_order_id']) ?></td>
            <td class="mono small"><?= hh_e((string) ($p['razorpay_payment_id'] ?? '—')) ?></td>
            <td class="mono"><?= hh_e(hh_rupees((int) $p['amount_paise'])) ?></td>
            <td><?= hh_e((string) ($p['coupon_code'] ?? '—')) ?></td>
            <td><span class="badge <?= $p['status'] === 'paid' ? 'ok' : '' ?>"><?= hh_e((string) $p['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
$razorpayScript = $configured;
require __DIR__ . '/_shell_bottom.php';
