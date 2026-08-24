<?php
// Distinguished Web Services - Application configuration

declare(strict_types=1);

const APP_NAME = 'Distinguished Web Services';
const APP_TAGLINE = 'Building Digital Solutions That Help Businesses Grow.';
const APP_URL = 'http://localhost/distinguished-web-services';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'distinguished_web_services';
const DB_USER = 'root';
const DB_PASS = '';

const CONTACT_EMAIL = 'hello@distinguishedwebservices.com';
const CONTACT_PHONE = '+234 000 000 0000';
const CONTACT_WHATSAPP = '+234 000 000 0000';

function app_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}
