<?php
// Deterministic demo seed for the 14.x ops suite. Dates are computed relative
// to "today" so overdue / low-margin / over-budget / SLA-risk states always
// demonstrate themselves. Running Seed::run() twice refreshes the same dataset.
declare(strict_types=1);

final class Seed
{
    public static function run(PDO $pdo, string $adminEmail = 'admin@distinguishedwebservices.com', string $adminPassword = 'DwsAdmin#2026'): array
    {
        $t = static fn (string $mod): string => date('Y-m-d', strtotime($mod));
        $ts = static fn (string $mod): string => date('Y-m-d H:i:s', strtotime($mod));

        foreach (['ops_alert_events', 'ops_alerts', 'ops_automation_runs', 'ops_automation_rules',
                  'ops_report_snapshots', 'ops_cost_entries', 'ops_tasks', 'ops_invoices',
                  'ops_tickets', 'ops_leads', 'ops_projects', 'ops_team', 'ops_clients', 'ops_admins'] as $tbl) {
            $pdo->exec("DELETE FROM $tbl");
        }

        // ---- admins ----
        $ins = $pdo->prepare('INSERT INTO ops_admins (name, email, password_hash, role, is_active) VALUES (?,?,?,?,1)');
        $ins->execute(['Lead Administrator', $adminEmail, password_hash($adminPassword, PASSWORD_BCRYPT), 'superadmin']);
        $ins->execute(['Ops Analyst', 'analyst@distinguishedwebservices.com', password_hash('DwsAnalyst#2026', PASSWORD_BCRYPT), 'analyst']);

        // ---- clients ----
        $ins = $pdo->prepare('INSERT INTO ops_clients (name, contact_name, email, phone) VALUES (?,?,?,?)');
        $clients = [
            ['Rivers Microfinance Bank', 'Adaeze Okonkwo', 'adaeze@riversmfb.ng', '+234 803 111 0001'],
            ['PortHaul Logistics Ltd', 'Emeka Wachuku', 'emeka@porthaul.ng', '+234 803 111 0002'],
            ['BrightPath Recruitment', 'Funke Adeyemi', 'funke@brightpath.ng', '+234 803 111 0003'],
            ['NaijaKart Marketplace', 'Sadiq Bello', 'sadiq@naijakart.ng', '+234 803 111 0004'],
            ['GreenFields Agro', 'Chidi Nwosu', 'chidi@greenfields.ng', '+234 803 111 0005'],
        ];
        foreach ($clients as $c) { $ins->execute($c); }

        // ---- team ----
        $ins = $pdo->prepare('INSERT INTO ops_team (name, role, hourly_cost, is_active) VALUES (?,?,?,?)');
        $team = [
            ['Saleem Shaik', 'Lead Engineer', 12000, 1],
            ['Ngozi Eze', 'Full-stack Developer', 8000, 1],
            ['Tunde Bakare', 'Designer', 6500, 1],
            ['Aisha Mohammed', 'Digital Marketer', 5500, 1],
            ['Obinna Ike', 'QA / Support', 4500, 1],
        ];
        foreach ($team as $m) { $ins->execute($m); }

        // ---- projects (mixed health states) ----
        // id via insert order: 1..6
        $ins = $pdo->prepare('INSERT INTO ops_projects (client_id,name,status,billing_type,budget_amount,hourly_rate,start_date,due_date,completed_at) VALUES (?,?,?,?,?,?,?,?,?)');
        $projects = [
            [1, 'Digital Banking Platform Rebuild',   'active',    'fixed',  18500000, 0,     $t('-150 days'), $t('+25 days'),  null],        // healthy margin
            [2, 'Logistics Operations Portal',        'active',    'fixed',  9500000,  0,     $t('-120 days'), $t('-5 days'),   null],        // OVERDUE + tight
            [3, 'Talent Management SaaS MVP',         'active',    'fixed',  6800000,  0,     $t('-90 days'),  $t('+40 days'),  null],        // LOW MARGIN
            [4, 'E-Commerce Store & Payments',        'active',    'hourly', 5200000,  9500,  $t('-75 days'),  $t('+15 days'),  null],        // OVER BUDGET
            [5, 'Agro Trade Dashboard',               'completed', 'fixed',  4100000,  0,     $t('-200 days'), $t('-60 days'),  $ts('-60 days')], // completed, profitable
            [1, 'Marketing Site Refresh',             'completed', 'fixed',  1800000,  0,     $t('-240 days'), $t('-150 days'), $ts('-150 days')], // completed, LOW MARGIN
        ];
        foreach ($projects as $p) { $ins->execute($p); }

        // ---- cost ledger (14.6.1 data) ----
        $ins = $pdo->prepare('INSERT INTO ops_cost_entries (project_id,category,description,amount,cost_date,team_id) VALUES (?,?,?,?,?,?)');
        $costs = [
            // P1: costs ~12.4M vs 18.5M budget -> ~33% margin
            [1, 'labor',         'Sprint 1-4 engineering',        4800000, $t('-140 days'), 1],
            [1, 'labor',         'Sprint 5-8 engineering',        4200000, $t('-60 days'),  2],
            [1, 'software',      'Cloud & licences Q1-Q2',         950000, $t('-45 days'),  null],
            [1, 'subcontractor', 'Security audit (external)',     1200000, $t('-30 days'),  null],
            [1, 'labor',         'UI design tracks',              1250000, $t('-90 days'),  3],
            // P2: costs ~9.9M vs 9.5M -> OVER BUDGET slightly, overdue project
            [2, 'labor',         'Portal development',            6200000, $t('-110 days'), 2],
            [2, 'software',      'Maps & SMS APIs',                700000, $t('-50 days'),  null],
            [2, 'subcontractor', 'Fleet hardware integration',    1500000, $t('-20 days'),  null],
            [2, 'labor',         'QA hardening',                  1500000, $t('-10 days'),  5],
            // P3: costs ~6.35M vs 6.8M -> ~7% margin = LOW MARGIN
            [3, 'labor',         'MVP build',                     4100000, $t('-80 days'),  2],
            [3, 'labor',         'AI matching module',            1450000, $t('-35 days'),  1],
            [3, 'software',      'LLM API usage',                  500000, $t('-15 days'),  null],
            [3, 'materials',     'Candidate assessment licences',  300000, $t('-25 days'),  null],
            // P4: costs ~5.9M vs 5.2M -> OVER BUDGET (hourly project)
            [4, 'labor',         'Storefront engineering',        3100000, $t('-70 days'),  2],
            [4, 'labor',         'Payments integration',          1600000, $t('-40 days'),  1],
            [4, 'software',      'Hosting & PCI tooling',          450000, $t('-30 days'),  null],
            [4, 'other',         'Client training & content',      750000, $t('-12 days'),  null],
            // P5 completed: costs ~2.6M vs 4.1M -> ~36% margin
            [5, 'labor',         'Dashboard build',               1850000, $t('-190 days'), 2],
            [5, 'software',      'Data pipeline services',         350000, $t('-170 days'), null],
            [5, 'labor',         'Analytics & handover',           400000, $t('-100 days'), 1],
            // P6 completed: costs ~1.72M vs 1.8M -> ~4% margin = LOW MARGIN (completed)
            [6, 'labor',         'Site refresh',                   950000, $t('-235 days'), 3],
            [6, 'labor',         'SEO migration',                  520000, $t('-220 days'), 4],
            [6, 'other',         'Stock imagery & copy',            250000, $t('-210 days'), null],
        ];
        foreach ($costs as $c) { $ins->execute($c); }

        // ---- invoices ----
        $ins = $pdo->prepare('INSERT INTO ops_invoices (invoice_number,client_id,project_id,status,issue_date,due_date,subtotal,tax,total,paid_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $invoices = [
            ['INV-2026-001', 1, 1, 'paid',    $t('-120 days'), $t('-90 days'),  9000000, 360000,  9360000, $ts('-95 days')],
            ['INV-2026-002', 2, 2, 'paid',    $t('-90 days'),  $t('-60 days'),  4750000, 190000,  4940000, $ts('-62 days')],
            ['INV-2026-003', 3, 3, 'sent',    $t('-55 days'),  $t('-25 days'),  3400000, 136000,  3536000, null],          // OVERDUE 25 days
            ['INV-2026-004', 4, 4, 'sent',    $t('-20 days'),  $t('+10 days'),  2600000, 104000,  2704000, null],          // not yet due
            ['INV-2026-005', 5, 5, 'paid',    $t('-150 days'), $t('-120 days'), 4100000, 164000,  4264000, $ts('-118 days')],
            ['INV-2026-006', 1, 1, 'sent',    $t('-40 days'),  $t('-10 days'),  4500000, 180000,  4680000, null],          // OVERDUE 10 days
            ['INV-2026-007', 2, 2, 'draft',   $t('-5 days'),   $t('+25 days'),  2000000, 80000,   2080000, null],
            ['INV-2026-008', 5, null, 'void', $t('-200 days'), $t('-170 days'), 500000,  20000,    520000,  null],
        ];
        foreach ($invoices as $i) { $ins->execute($i); }

        // ---- tasks ----
        $ins = $pdo->prepare('INSERT INTO ops_tasks (project_id,title,status,team_id,due_date,completed_at,estimate_hours) VALUES (?,?,?,?,?,?,?)');
        $tasks = [
            [1, 'Core banking ledger API',        'done',        1, $t('-100 days'), $ts('-102 days'), 80],
            [1, 'Transaction notifications',      'done',        2, $t('-40 days'),  $ts('-41 days'),  45],
            [1, 'Admin back-office reports',      'in_progress', 2, $t('+7 days'),   null,            60],
            [1, 'Penetration test remediation',   'todo',        1, $t('+18 days'),  null,            30],
            [2, 'Fleet tracking module',          'done',        2, $t('-60 days'),  $ts('-58 days'),  70],
            [2, 'Route optimization engine',      'in_progress', 1, $t('-3 days'),   null,            55],   // OVERDUE
            [2, 'Client UAT sign-off',            'todo',        5, $t('-1 days'),   null,            8],    // OVERDUE
            [3, 'Candidate profile matcher',      'in_progress', 1, $t('+5 days'),   null,            40],
            [3, 'Employer portal screens',        'todo',        3, $t('+12 days'),  null,            24],
            [4, 'Checkout & Paystack flow',       'done',        2, $t('-30 days'),  $ts('-31 days'),  50],
            [4, 'Inventory sync service',         'blocked',     2, $t('+2 days'),   null,            35],
            [4, 'Marketing launch assets',        'todo',        4, $t('-2 days'),   null,            16],   // OVERDUE
            [5, 'Executive dashboard handover',   'done',        1, $t('-65 days'),  $ts('-66 days'),  20],
        ];
        foreach ($tasks as $tk) { $ins->execute($tk); }

        // ---- CRM leads ----
        $ins = $pdo->prepare('INSERT INTO ops_leads (name,company,email,phone,stage,value_estimate,last_contacted_at,next_followup_at) VALUES (?,?,?,?,?,?,?,?)');
        $leads = [
            ['Bisi Ogun',   'Lagos Health Plus',    'bisi@lhplus.ng', '+234 802 555 0001', 'new',       6500000,  null,           $ts('-1 day')],      // follow-up OVERDUE
            ['Kene Umeh',   'Enugu Auto Parts',     'kene@eap.ng',    '+234 802 555 0002', 'contacted', 4200000,  $ts('-6 days'), $ts('-2 days')],     // follow-up OVERDUE
            ['Mira Etim',   'Calabar Foods',        'mira@cfoods.ng', '+234 802 555 0003', 'qualified', 9800000,  $ts('-3 days'), $ts('+2 days')],     // upcoming
            ['Dapo Lawal',  'Ibadan EdTech',        'dapo@ied.ng',    '+234 802 555 0004', 'proposal', 12500000,  $ts('-2 days'), $ts('+1 day')],      // upcoming
            ['Zara Sani',   'Kano Textiles',        'zara@ktex.ng',   '+234 802 555 0005', 'won',       7500000,  $ts('-10 days'), null],
            ['Femi Ode',    'Abeokuta Farms',       'femi@af.ng',     '+234 802 555 0006', 'lost',      2000000,  $ts('-20 days'), null],
        ];
        foreach ($leads as $l) { $ins->execute($l); }

        // ---- support tickets (SLA) ----
        $ins = $pdo->prepare('INSERT INTO ops_tickets (client_id,subject,priority,status,sla_due_at,first_response_at,resolved_at,created_at) VALUES (?,?,?,?,?,?,?,?)');
        $tickets = [
            [1, 'Cannot reconcile end-of-day report',   'urgent', 'open',     $ts('-4 hours'),  null,            null,           $ts('-6 hours')], // SLA BREACHED (no response)
            [2, 'Driver app GPS ping failures',         'high',   'open',     $ts('+2 hours'),  null,            null,           $ts('-1 hour')],   // SLA AT RISK
            [3, 'Bulk CV upload failing',               'normal', 'pending',  $ts('-1 day'),    $ts('-2 days'),  null,           $ts('-3 days')],   // responded in time
            [4, 'Payment webhook retries',              'high',   'open',     $ts('+6 hours'),  $ts('-1 hour'),  null,           $ts('-7 hours')],  // responded, resolution pending
            [5, 'Dashboard export formatting',          'low',    'resolved', $ts('-3 days'),   $ts('-4 days'),  $ts('-3 days'), $ts('-5 days')],
            [1, 'Branch user lockouts',                 'normal', 'resolved', $ts('-5 days'),   $ts('-6 days'),  $ts('-5 days'), $ts('-7 days')],
            [2, 'Invoice mismatch on batch #4471',      'normal', 'resolved', $ts('-8 days'),   $ts('-9 days'),  $ts('-7 days'), $ts('-10 days')],
        ];
        foreach ($tickets as $ti) { $ins->execute($ti); }

        // ---- automation rules (14.9) ----
        $ins = $pdo->prepare('INSERT INTO ops_automation_rules (name,trigger_type,conditions_json,action_type,action_config_json,schedule,is_active,dedup_minutes) VALUES (?,?,?,?,?,?,?,?)');
        $rules = [
            ['Daily risk scan',                'daily_check', null,                              'alert_scan',      null,                                    'daily',   1, 60],
            ['Daily operations snapshot',      'daily_check', null,                              'generate_report', '{"report_type":"daily_operations_snapshot"}', 'daily', 1, 600],
            ['Weekly sales report',            'daily_check', '{"day_of_week":"Monday"}',         'generate_report', '{"report_type":"weekly_sales"}',        'weekly',  1, 600],
            ['Monthly executive report',       'daily_check', '{"day_of_month":"1"}',            'generate_report', '{"report_type":"monthly_executive"}',   'monthly', 1, 600],
            ['Critical alert escalation',      'alert_raised', '{"severity":"critical"}',        'log_notification', '{"template":"ESCALATE: {title} ({severity})"}', 'event', 1, 60],
            ['Overdue invoice nudge log',      'alert_raised', '{"type":"overdue_invoice"}',     'log_notification', '{"template":"Nudge client for {title}"}',  'event', 1, 120],
        ];
        foreach ($rules as $r) { $ins->execute($r); }

        return [
            'admins' => 2, 'clients' => count($clients), 'team' => count($team), 'projects' => count($projects),
            'cost_entries' => count($costs), 'invoices' => count($invoices), 'tasks' => count($tasks),
            'leads' => count($leads), 'tickets' => count($tickets), 'automation_rules' => count($rules),
            'admin_email' => $adminEmail, 'admin_password' => $adminPassword,
        ];
    }
}
