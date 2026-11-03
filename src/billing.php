<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/http.php';

/**
 * Razorpay annual subscription (₹199 / year) + affiliate coupons.
 * The key secret never reaches the browser. The frontend callback is never
 * trusted on its own — the HMAC signature is verified (or the webhook).
 */

function hh_razorpay_mode(): string
{
    $mode = strtolower(trim((string) hh_cfg('razorpay_mode', 'test')));
    return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
}

function hh_razorpay_key_matches_mode(string $keyId, ?string $mode = null): bool
{
    $mode = $mode ?? hh_razorpay_mode();
    $prefix = $mode === 'live' ? 'rzp_live_' : 'rzp_test_';
    return str_starts_with(trim($keyId), $prefix);
}

/** Safe setup message; never includes or returns a secret. */
function hh_payment_setup_message(): ?string
{
    $keyId = trim((string) hh_cfg('razorpay_key_id', ''));
    $secret = trim((string) hh_cfg('razorpay_key_secret', ''));
    if ($keyId === '' || $secret === '') {
        return 'Payments are not configured. Add Razorpay ' . strtoupper(hh_razorpay_mode()) . ' keys on the server.';
    }
    if (!hh_razorpay_key_matches_mode($keyId)) {
        $prefix = hh_razorpay_mode() === 'live' ? 'rzp_live_' : 'rzp_test_';
        return 'Razorpay mode is ' . strtoupper(hh_razorpay_mode()) . '; the Key ID must start with ' . $prefix . '.';
    }
    return null;
}

function hh_payments_configured(): bool
{
    return hh_payment_setup_message() === null;
}

/** Amount in paise for a coupon code (or for no coupon). */
function hh_price_for(?string $code): array
{
    $base = (int) hh_cfg('price_paise', 19900);
    $code = $code !== null ? strtoupper(trim($code)) : '';
    if ($code === '') {
        return ['base' => $base, 'amount' => $base, 'discount' => 0, 'coupon' => null, 'percent' => 0];
    }

    $stmt = hh_db()->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['base' => $base, 'amount' => $base, 'discount' => 0, 'coupon' => null, 'percent' => 0, 'error' => 'That coupon code is not valid.'];
    }
    if ((int) $coupon['uses_left'] <= 0) {
        return ['base' => $base, 'amount' => $base, 'discount' => 0, 'coupon' => $coupon['code'], 'percent' => 0, 'error' => 'That coupon has been fully used.'];
    }

    $percent = max(0, min(100, (int) $coupon['percent_off']));
    $amount = (int) round($base * (100 - $percent) / 100);
    $amount = max(100, $amount); // never below ₹1

    return [
        'base'     => $base,
        'amount'   => $amount,
        'discount' => $base - $amount,
        'coupon'   => (string) $coupon['code'],
        'percent'  => $percent,
    ];
}

/**
 * Talk to Razorpay. Uses cURL when the host has it, otherwise falls back to
 * stream wrappers, so a shared host with php-curl missing still works.
 */
function hh_razorpay_request(string $method, string $url, ?array $payload = null): array
{
    $key = (string) hh_cfg('razorpay_key_id', '');
    $secret = (string) hh_cfg('razorpay_key_secret', '');
    $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $key . ':' . $secret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => "Content-Type: application/json\r\n"
                    . 'Authorization: Basic ' . base64_encode($key . ':' . $secret) . "\r\n",
                'content'       => (string) $json,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ((array) ($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        $err = $body === false ? 'stream request failed (allow_url_fopen?)' : '';
    }

    if ($body === false) {
        return ['ok' => false, 'error' => 'Could not reach Razorpay: ' . $err, 'status' => 0];
    }
    $data = json_decode((string) $body, true);
    if ($status >= 200 && $status < 300 && is_array($data)) {
        return ['ok' => true, 'data' => $data, 'status' => $status];
    }
    $message = is_array($data) ? (string) ($data['error']['description'] ?? 'Razorpay rejected the request.') : 'Razorpay rejected the request.';
    return ['ok' => false, 'error' => $message, 'status' => $status, 'data' => $data];
}

/** Create the order + the local payments row (status created). */
function hh_create_order(int $user_id, ?string $coupon): array
{
    if (!hh_payments_configured()) {
        return ['ok' => false, 'error' => (string) hh_payment_setup_message()];
    }
    $price = hh_price_for($coupon);
    if (!empty($price['error'])) {
        return ['ok' => false, 'error' => $price['error']];
    }

    $receipt = 'hh_' . $user_id . '_' . time();
    $res = hh_razorpay_request('POST', 'https://api.razorpay.com/v1/orders', [
        'amount'          => $price['amount'],
        'currency'        => 'INR',
        'receipt'         => $receipt,
        'notes'           => [
            'product'  => 'Hector Habit — annual',
            'username' => (string) ($_SESSION['username'] ?? ''),
            'coupon'   => (string) ($price['coupon'] ?? ''),
        ],
    ]);

    if (!$res['ok']) {
        return ['ok' => false, 'error' => $res['error']];
    }

    $orderId = (string) $res['data']['id'];
    $stmt = hh_db()->prepare(
        'INSERT INTO payments (user_id, razorpay_order_id, amount_paise, coupon_code, status) VALUES (?, ?, ?, ?, "created")'
    );
    $stmt->execute([$user_id, $orderId, $price['amount'], $price['coupon']]);

    return [
        'ok'      => true,
        'order'   => $orderId,
        'amount'  => $price['amount'],
        'key'     => (string) hh_cfg('razorpay_key_id', ''),
        'coupon'  => $price['coupon'],
    ];
}

/** Razorpay Checkout handler signature check. */
function hh_verify_checkout_signature(string $orderId, string $paymentId, string $signature): bool
{
    $secret = (string) hh_cfg('razorpay_key_secret', '');
    if ($secret === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Mark a payment paid and extend the subscription by one year.
 * Idempotent: paying twice never stacks the term twice, because the row must
 * still be in 'created' state for the term to move.
 */
function hh_fulfil_payment(string $orderId, ?string $paymentId, ?string $via = 'checkout'): array
{
    $pdo = hh_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM payments WHERE razorpay_order_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Unknown order.'];
        }
        if ($payment['status'] === 'paid') {
            $pdo->commit();
            return ['ok' => true, 'already' => true];
        }

        $pdo->prepare(
            'UPDATE payments SET status = "paid", razorpay_payment_id = ?, paid_at = NOW() WHERE id = ?'
        )->execute([$paymentId, (int) $payment['id']]);

        $pdo->prepare(
            'UPDATE users
                SET paid_until = DATE_ADD(GREATEST(IFNULL(paid_until, CURDATE()), CURDATE()), INTERVAL 1 YEAR)
              WHERE id = ?'
        )->execute([(int) $payment['user_id']]);

        if (!empty($payment['coupon_code'])) {
            // conditional decrement — never below zero
            $pdo->prepare('UPDATE coupons SET uses_left = uses_left - 1 WHERE code = ? AND uses_left > 0')
                ->execute([(string) $payment['coupon_code']]);
        }

        $pdo->commit();
        return ['ok' => true, 'via' => $via, 'user_id' => (int) $payment['user_id']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Could not record the payment.'];
    }
}

/** Webhook: the authority. */
function hh_verify_webhook(string $rawBody, ?string $signature): bool
{
    $secret = trim((string) hh_cfg('razorpay_webhook_secret', ''));
    if ($secret === '' || !$signature) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}

/** Latest payments for the billing page. */
function hh_recent_payments(int $user_id, int $limit = 5): array
{
    $stmt = hh_db()->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function hh_rupees(int $paise): string
{
    $whole = intdiv($paise, 100);
    $rest = $paise % 100;
    return $rest === 0 ? '₹' . number_format($whole) : '₹' . number_format($whole) . '.' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
}
