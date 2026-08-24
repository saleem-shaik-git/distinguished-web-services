<?php
require_once __DIR__ . '/../config/config.php';

$services = [
    ['icon' => 'bi-window-stack', 'number' => '01', 'title' => 'Web Development', 'text' => 'Professional, responsive websites designed around your brand, audience and business objectives.'],
    ['icon' => 'bi-grid-1x2', 'number' => '02', 'title' => 'Custom Web Applications', 'text' => 'Database-driven portals, dashboards, CRM systems and business applications built around your workflows.'],
    ['icon' => 'bi-cart3', 'number' => '03', 'title' => 'E-Commerce', 'text' => 'Online stores with product management, payments, orders, inventory and customer management.'],
    ['icon' => 'bi-graph-up-arrow', 'number' => '04', 'title' => 'Digital Marketing', 'text' => 'SEO, Google Ads, Meta advertising, lead generation and conversion-focused digital campaigns.'],
    ['icon' => 'bi-robot', 'number' => '05', 'title' => 'AI & Automation', 'text' => 'Practical automation and AI workflows that reduce repetitive work and improve productivity.'],
    ['icon' => 'bi-plug', 'number' => '06', 'title' => 'API Integration', 'text' => 'Connect payments, messaging, CRM, analytics, AI and other third-party services into one ecosystem.'],
];

$projects = [
    ['category' => 'FinTech / Web Application', 'title' => 'Digital Banking Platform', 'text' => 'A database-driven banking experience covering authentication, accounts, transactions, notifications and administration.', 'tech' => 'PHP · MySQL · Bootstrap · JavaScript'],
    ['category' => 'Logistics / Business Platform', 'title' => 'Logistics Management Platform', 'text' => 'A digital operations platform designed to organize customers, logistics workflows, deliveries and reporting.', 'tech' => 'Web Application · Database · APIs'],
    ['category' => 'Recruitment / AI', 'title' => 'Talent Management Platform', 'text' => 'A recruitment workflow platform for jobs, candidate profiles, applications and intelligent hiring processes.', 'tech' => 'Web Application · AI · Database · APIs'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Distinguished Web Services builds professional websites, custom web applications, e-commerce platforms, digital marketing campaigns and business automation solutions.">
    <meta name="theme-color" content="#07111f">
    <title><?= APP_NAME ?> | Web Development & Digital Growth</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="site-shell">
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top site-nav">
        <div class="container py-2">
            <a class="navbar-brand brand-mark" href="#home"><span>DW</span> Distinguished <strong>Web Services</strong></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainMenu"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse d-none d-lg-flex" id="desktopMenu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#projects">Projects</a></li>
                    <li class="nav-item"><a class="nav-link" href="#marketing">Marketing</a></li>
                    <li class="nav-item"><a class="nav-link" href="#process">Process</a></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm px-4" href="#contact">Start a Project <i class="bi bi-arrow-up-right"></i></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="mainMenu">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Distinguished Web Services</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
        <div class="offcanvas-body"><div class="navbar-nav gap-2"><a class="nav-link" href="#about" data-bs-dismiss="offcanvas">About</a><a class="nav-link" href="#services" data-bs-dismiss="offcanvas">Services</a><a class="nav-link" href="#projects" data-bs-dismiss="offcanvas">Projects</a><a class="nav-link" href="#marketing" data-bs-dismiss="offcanvas">Marketing</a><a class="nav-link" href="#process" data-bs-dismiss="offcanvas">Process</a><a class="btn btn-brand mt-3" href="#contact" data-bs-dismiss="offcanvas">Start a Project</a></div></div>
    </div>

    <main id="home">
        <section class="hero-section position-relative overflow-hidden">
            <div class="hero-grid"></div><div class="hero-glow hero-glow-one"></div><div class="hero-glow hero-glow-two"></div>
            <div class="container position-relative">
                <div class="row align-items-center min-vh-100 py-5">
                    <div class="col-lg-7 pt-5">
                        <div class="eyebrow reveal"><span></span> WEB DEVELOPMENT · DIGITAL GROWTH · AUTOMATION</div>
                        <h1 class="display-title reveal">Digital Solutions.<br><span>Built for Growth.</span></h1>
                        <p class="hero-copy reveal">We build professional websites, custom web applications, e-commerce platforms and digital marketing systems that help businesses attract customers, improve operations and grow.</p>
                        <div class="d-flex flex-wrap gap-3 reveal"><a href="#contact" class="btn btn-brand btn-lg">Start a Project <i class="bi bi-arrow-up-right"></i></a><a href="#projects" class="btn btn-outline-light btn-lg">Explore Our Work</a></div>
                        <div class="hero-trust reveal"><span>PHP</span><span>MySQL</span><span>Bootstrap</span><span>JavaScript</span><span>APIs</span></div>
                    </div>
                    <div class="col-lg-5 d-none d-lg-block">
                        <div class="growth-visual reveal">
                            <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
                            <div class="core-card"><div class="core-icon"><i class="bi bi-code-slash"></i></div><strong>Digital Growth</strong><small>Technology + Strategy</small></div>
                            <div class="float-card card-a"><i class="bi bi-window"></i><span>Web</span></div><div class="float-card card-b"><i class="bi bi-graph-up-arrow"></i><span>Leads</span></div><div class="float-card card-c"><i class="bi bi-lightning-charge"></i><span>Automation</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding bg-light" id="about">
            <div class="container"><div class="row g-5 align-items-center"><div class="col-lg-5"><div class="section-kicker">01 — ABOUT US</div><h2 class="section-title dark">Technology meets business strategy.</h2></div><div class="col-lg-7"><p class="lead dark-copy">Distinguished Web Services helps businesses use technology to solve problems, reach customers and create better ways of working.</p><p class="muted-copy">We combine web development, digital marketing and business automation to create practical solutions around each client's objectives. Our focus is not simply to make technology look good, but to make it useful, measurable and ready to grow.</p><div class="mini-stat-row"><div><strong>01</strong><span>Business-first thinking</span></div><div><strong>02</strong><span>Practical technology</span></div><div><strong>03</strong><span>Long-term growth</span></div></div></div></div></div>
        </section>

        <section class="section-padding dark-section" id="services"><div class="container"><div class="section-heading-row"><div><div class="section-kicker">02 — WHAT WE DO</div><h2 class="section-title">Digital solutions around your business.</h2></div><p>From a high-converting website to a connected business platform, we build digital infrastructure around your goals.</p></div><div class="row g-3 mt-4"><?php foreach ($services as $service): ?><div class="col-md-6 col-xl-4"><article class="service-card h-100"><div class="service-number"><?= htmlspecialchars($service['number']) ?></div><div class="service-icon"><i class="bi <?= htmlspecialchars($service['icon']) ?>"></i></div><h3><?= htmlspecialchars($service['title']) ?></h3><p><?= htmlspecialchars($service['text']) ?></p><a href="#contact">Explore service <i class="bi bi-arrow-right"></i></a></article></div><?php endforeach; ?></div></div></section>

        <section class="section-padding project-section" id="projects"><div class="container"><div class="section-heading-row"><div><div class="section-kicker">03 — SELECTED WORK</div><h2 class="section-title dark">Built to solve real problems.</h2></div><p class="dark-copy">A growing collection of web applications, business platforms and digital products.</p></div><div class="row g-4 mt-3"><?php foreach ($projects as $project): ?><div class="col-lg-4"><article class="project-card h-100"><div class="project-visual"><div class="mock-window"><div></div><span></span><span></span><span></span></div></div><div class="p-4"><small class="project-category"><?= htmlspecialchars($project['category']) ?></small><h3><?= htmlspecialchars($project['title']) ?></h3><p><?= htmlspecialchars($project['text']) ?></p><div class="project-tech"><?= htmlspecialchars($project['tech']) ?></div><a href="#contact" class="project-link">View case study <i class="bi bi-arrow-up-right"></i></a></div></article></div><?php endforeach; ?></div></div></section>

        <section class="section-padding marketing-section" id="marketing"><div class="container"><div class="row align-items-center g-5"><div class="col-lg-5"><div class="section-kicker">04 — DIGITAL MARKETING</div><h2 class="section-title">Don't just build your website. Make sure people find it.</h2><p class="muted-copy">We connect technology with acquisition strategy so your digital presence can attract, engage and convert the right audience.</p><a href="#contact" class="btn btn-brand mt-3">Grow My Business <i class="bi bi-arrow-up-right"></i></a></div><div class="col-lg-7"><div class="funnel"><div class="funnel-step"><span>01</span><strong>Awareness</strong><small>SEO · Paid Ads · Content</small></div><div class="funnel-step"><span>02</span><strong>Traffic</strong><small>Search · Social · Campaigns</small></div><div class="funnel-step"><span>03</span><strong>Engagement</strong><small>Landing Pages · Content</small></div><div class="funnel-step"><span>04</span><strong>Lead</strong><small>Forms · WhatsApp · Calls</small></div><div class="funnel-step active"><span>05</span><strong>Growth</strong><small>Conversion · Analytics · Scale</small></div></div></div></div></div></section>

        <section class="section-padding dark-section" id="process"><div class="container"><div class="section-kicker">05 — OUR PROCESS</div><h2 class="section-title">From strategy to launch.</h2><div class="row g-4 mt-4"><div class="col-md-4"><div class="process-item"><span>01</span><h3>Discover</h3><p>Understand your business, customers, objectives and technical requirements.</p></div></div><div class="col-md-4"><div class="process-item"><span>02</span><h3>Build</h3><p>Design, develop, integrate and test the solution around the agreed scope.</p></div></div><div class="col-md-4"><div class="process-item"><span>03</span><h3>Grow</h3><p>Launch, measure performance and continuously improve the digital experience.</p></div></div></div></div></section>

        <section class="section-padding bg-light" id="contact"><div class="container"><div class="contact-box"><div class="row g-5 align-items-center"><div class="col-lg-5"><div class="section-kicker">06 — START A PROJECT</div><h2 class="section-title dark">Have an idea, business or project?</h2><p class="muted-copy">Tell us what you're trying to build, improve or grow. We'll help you identify the right digital solution.</p><div class="contact-details"><div><i class="bi bi-envelope"></i><span><?= CONTACT_EMAIL ?></span></div><div><i class="bi bi-whatsapp"></i><span><?= CONTACT_WHATSAPP ?></span></div></div></div><div class="col-lg-7"><form class="project-form" action="contact.php" method="post"><div class="row g-3"><div class="col-md-6"><label>Full Name</label><input type="text" name="name" class="form-control" required></div><div class="col-md-6"><label>Email Address</label><input type="email" name="email" class="form-control" required></div><div class="col-md-6"><label>Phone / WhatsApp</label><input type="text" name="phone" class="form-control"></div><div class="col-md-6"><label>Service Required</label><select name="service" class="form-select"><option>Web Development</option><option>Custom Web Application</option><option>E-Commerce</option><option>Digital Marketing</option><option>AI & Automation</option><option>API Integration</option></select></div><div class="col-12"><label>Tell us about your project</label><textarea name="message" rows="5" class="form-control" required></textarea></div><div class="col-12"><button class="btn btn-brand btn-lg" type="submit">Send Project Enquiry <i class="bi bi-arrow-up-right"></i></button></div></div></form></div></div></div></div></section>
    </main>

    <footer class="site-footer"><div class="container py-5"><div class="row g-4"><div class="col-lg-5"><div class="brand-mark footer-brand"><span>DW</span> Distinguished <strong>Web Services</strong></div><p>Digital solutions for businesses ready to grow.</p></div><div class="col-6 col-lg-2"><h6>Company</h6><a href="#about">About</a><a href="#projects">Projects</a><a href="#process">Process</a></div><div class="col-6 col-lg-2"><h6>Services</h6><a href="#services">Web Development</a><a href="#services">Web Applications</a><a href="#marketing">Digital Marketing</a></div><div class="col-lg-3"><h6>Let's build something remarkable.</h6><a class="footer-cta" href="#contact">Start a Project <i class="bi bi-arrow-up-right"></i></a></div></div><hr><div class="d-flex flex-column flex-md-row justify-content-between gap-2 small"><span>© <?= date('Y') ?> Distinguished Web Services. All Rights Reserved.</span><span>Web Development · Digital Marketing · Automation</span></div></div></footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="assets/js/app.js"></script>
</body></html>
