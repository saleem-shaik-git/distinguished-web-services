CREATE DATABASE IF NOT EXISTS distinguished_web_services CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE distinguished_web_services;

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NULL,
    service VARCHAR(120) NULL,
    budget VARCHAR(80) NULL,
    message TEXT NOT NULL,
    status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_messages_status (status),
    INDEX idx_messages_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    short_description VARCHAR(500) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    category VARCHAR(120) NOT NULL,
    short_description VARCHAR(500) NOT NULL,
    description TEXT NULL,
    challenge TEXT NULL,
    solution TEXT NULL,
    technologies VARCHAR(500) NULL,
    featured_image VARCHAR(255) NULL,
    live_url VARCHAR(500) NULL,
    github_url VARCHAR(500) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_projects_featured (is_featured),
    INDEX idx_projects_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(150) NOT NULL,
    company VARCHAR(180) NULL,
    position VARCHAR(150) NULL,
    testimonial TEXT NOT NULL,
    photo VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'Distinguished Web Services'),
('tagline', 'Building Digital Solutions That Help Businesses Grow.'),
('seo_title', 'Distinguished Web Services | Web Development & Digital Growth'),
('seo_description', 'Professional web development, custom applications, e-commerce, digital marketing and business automation solutions.'),
('contact_email', 'hello@distinguishedwebservices.com');

INSERT IGNORE INTO services (title, slug, short_description, icon, sort_order) VALUES
('Web Development','web-development','Professional responsive websites built around your brand and business objectives.','bi-window-stack',1),
('Custom Web Applications','custom-web-applications','Database-driven portals, dashboards, CRM systems and business applications.','bi-grid-1x2',2),
('E-Commerce','e-commerce','Online stores with products, payments, orders, inventory and customer management.','bi-cart3',3),
('Digital Marketing','digital-marketing','SEO, Google Ads, Meta advertising, lead generation and conversion optimization.','bi-graph-up-arrow',4),
('AI & Automation','ai-automation','Practical AI and automated workflows that reduce repetitive work.','bi-robot',5),
('API Integration','api-integration','Connect payments, messaging, CRM, analytics, AI and third-party services.','bi-plug',6);
