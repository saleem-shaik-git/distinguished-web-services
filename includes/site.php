<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

function site_settings(): array {
    static $settings = null;
    if ($settings !== null) return $settings;
    $settings = [];
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) { return $settings; }
    return $settings;
}
function setting(string $key, string $default = ''): string { $v = site_settings()[$key] ?? ''; return $v !== '' ? $v : $default; }
function public_services(): array {
    try { return db()->query('SELECT * FROM services WHERE is_active=1 ORDER BY sort_order ASC, id ASC')->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function public_projects(): array {
    try { return db()->query('SELECT * FROM projects WHERE is_published=1 ORDER BY is_featured DESC, created_at DESC')->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function public_testimonials(): array {
    try { return db()->query('SELECT * FROM testimonials WHERE is_published=1 ORDER BY created_at DESC')->fetchAll(); }
    catch (Throwable $e) { return []; }
}
