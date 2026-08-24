<?php
declare(strict_types=1);
function send_lead_notification(array $lead): bool
{
    $to = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '';
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $subject = 'New Website Enquiry - ' . (string)($lead['service'] ?? 'Project Enquiry');
    $safe = static fn($v): string => trim(strip_tags((string)($v ?? '')));
    $body = "A new project enquiry was submitted on Distinguished Web Services.\n\n";
    $body .= "Name: {$safe($lead['name'] ?? '')}\nEmail: {$safe($lead['email'] ?? '')}\nPhone: {$safe($lead['phone'] ?? '')}\nCompany: {$safe($lead['company'] ?? '')}\nService: {$safe($lead['service'] ?? '')}\nBudget: {$safe($lead['budget'] ?? '')}\nTimeline: {$safe($lead['timeline'] ?? '')}\n\nMessage:\n{$safe($lead['message'] ?? '')}\n\nView leads: " . app_url('admin/leads.php');
    $headers = "From: Distinguished Web Services <{$to}>\r\n";
    if (!empty($lead['email']) && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) $headers .= 'Reply-To: ' . $safe($lead['email']) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}
function send_lead_confirmation(array $lead): bool
{
    $email = (string)($lead['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $name = htmlspecialchars((string)($lead['name'] ?? 'there'), ENT_QUOTES, 'UTF-8');
    $site = defined('APP_NAME') ? APP_NAME : 'Distinguished Web Services';
    $subject = 'We received your project enquiry';
    $body = "Hi {$name},\n\nThank you for contacting {$site}. We have received your project enquiry and will review it shortly.\n\nIf you need to add information, simply reply to this email.\n\nRegards,\n{$site}";
    $from = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : '';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    $headers = "From: {$site} <{$from}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($email, $subject, $body, $headers);
}
