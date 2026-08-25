<?php declare(strict_types=1);
function paystack_secret_key(): string { return trim((string)(getenv('PAYSTACK_SECRET_KEY') ?: '')); }
function payment_callback_url(string $token): string { require_once __DIR__.'/config.php'; return app_url('portal/pay-invoice.php?token='.rawurlencode($token).'&callback=1'); }
