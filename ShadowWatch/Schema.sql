-- ============================================================
-- ShadowWatch SOC Training Simulator — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS shadowwatch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shadowwatch;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('analyst','senior_analyst','team_lead','admin') DEFAULT 'analyst',
    rank_title VARCHAR(50) DEFAULT 'Recruit',
    xp INT DEFAULT 0,
    level INT DEFAULT 1,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Scenarios ────────────────────────────────────────────────
CREATE TABLE scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    difficulty ENUM('easy','medium','hard','critical') DEFAULT 'medium',
    category VARCHAR(50),
    xp_reward INT DEFAULT 100,
    time_limit_minutes INT DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Alerts ───────────────────────────────────────────────────
CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    category VARCHAR(100),
    source_ip VARCHAR(45),
    dest_ip VARCHAR(45),
    source_port INT DEFAULT NULL,
    dest_port INT DEFAULT NULL,
    protocol VARCHAR(20),
    raw_payload TEXT,
    status ENUM('open','investigating','resolved','false_positive') DEFAULT 'open',
    assigned_to INT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Incidents ────────────────────────────────────────────────
CREATE TABLE incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    status ENUM('open','in_progress','pending','resolved','closed') DEFAULT 'open',
    category VARCHAR(100),
    assigned_to INT DEFAULT NULL,
    created_by INT NOT NULL,
    alert_id INT DEFAULT NULL,
    resolution_notes TEXT,
    closed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL
);

-- ── Incident Comments ─────────────────────────────────────────
CREATE TABLE incident_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incident_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Logs ─────────────────────────────────────────────────────
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT DEFAULT NULL,
    log_type ENUM('syslog','auth','firewall','ids','web','dns','dhcp','endpoint') DEFAULT 'syslog',
    timestamp DATETIME NOT NULL,
    source_host VARCHAR(100),
    source_ip VARCHAR(45),
    dest_ip VARCHAR(45),
    message TEXT NOT NULL,
    severity ENUM('debug','info','notice','warning','error','critical','alert','emergency') DEFAULT 'info',
    raw_log TEXT,
    is_malicious TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL
);

-- ── Threat Intelligence ───────────────────────────────────────
CREATE TABLE threat_intel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ioc_type ENUM('ip','domain','hash','url','email') NOT NULL,
    value VARCHAR(500) NOT NULL,
    threat_type VARCHAR(100),
    confidence ENUM('low','medium','high') DEFAULT 'medium',
    description TEXT,
    tags VARCHAR(500),
    source VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_value (value(255)),
    INDEX idx_ioc_type (ioc_type)
);

-- ── Achievements ─────────────────────────────────────────────
CREATE TABLE achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    xp_reward INT DEFAULT 50,
    condition_type VARCHAR(50),
    condition_value INT DEFAULT 1
);

CREATE TABLE user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

-- ── Activity Log ──────────────────────────────────────────────
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Settings ──────────────────────────────────────────────────
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default users (password for all: Admin@1234)
INSERT INTO users (username, email, password_hash, role, rank_title, xp, level) VALUES
('admin',     'admin@shadowwatch.local',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSFCN5PKK', 'admin',          'Commander',      9999, 10),
('analyst01', 'analyst01@shadowwatch.local','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSFCN5PKK', 'analyst',         'Recruit',         250,  2),
('soc_lead',  'lead@shadowwatch.local',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSFCN5PKK', 'team_lead',       'Sergeant',       4800,  7);

-- Scenarios
INSERT INTO scenarios (name, description, difficulty, category, xp_reward, time_limit_minutes, created_by) VALUES
('Brute Force Attack',         'Multiple failed SSH login attempts detected from external IP. Investigate and respond.',              'easy',     'Authentication',    150, 20, 1),
('SQL Injection Campaign',     'Web application firewall triggered multiple SQLi signatures. Identify scope and remediate.',         'medium',   'Web Attack',        300, 30, 1),
('Ransomware Outbreak',        'Endpoint detection alerts on mass file encryption. Contain and remediate immediately.',              'critical', 'Malware',           800, 45, 1),
('Lateral Movement via PsExec','Internal host communicating with multiple servers using admin shares.',                              'hard',     'Lateral Movement',  500, 35, 1),
('Phishing Campaign',          'Employees receiving emails with malicious attachments. Triage and respond.',                        'medium',   'Social Engineering',250, 25, 1),
('DNS Tunneling',              'Unusual DNS query patterns suggest data exfiltration via DNS tunneling.',                           'hard',     'Exfiltration',      600, 40, 1);

-- Achievements
INSERT INTO achievements (name, description, icon, xp_reward, condition_type, condition_value) VALUES
('First Blood',       'Resolve your first alert',             'target',    50,   'alerts_resolved', 1),
('Quick Draw',        'Resolve an alert in under 5 minutes',  'zap',       100,  'quick_resolve',   1),
('Centurion',         'Resolve 100 alerts total',             'shield',    500,  'alerts_resolved', 100),
('Incident Commander','Create 10 incident tickets',           'file-text', 150,  'incidents_created',10),
('Log Whisperer',     'Analyze 50 log entries',               'activity',  200,  'logs_analyzed',   50),
('Threat Hunter',     'Look up 25 IOCs in threat intel',      'search',    250,  'ioc_lookups',     25),
('Seasoned Analyst',  'Reach level 5',                        'star',      300,  'level_reached',   5),
('Elite Operator',    'Reach level 10',                       'award',     1000, 'level_reached',   10);

-- Threat Intel
INSERT INTO threat_intel (ioc_type, value, threat_type, confidence, description, tags, source) VALUES
('ip',     '185.220.101.47',                      'Tor Exit Node',         'high',   'Known Tor exit node used in credential stuffing attacks',         'tor,proxy,anonymizer',         'ThreatFox'),
('ip',     '192.168.100.254',                     'Internal Scanner',      'medium', 'Internal IP conducting unauthorized port scanning',               'scanner,internal',             'IDS Alert'),
('domain', 'evil-c2-server.ru',                   'C2 Infrastructure',     'high',   'Confirmed C2 server for Emotet botnet',                           'emotet,c2,malware',            'MISP'),
('domain', 'phish-login.microsoft-secure.xyz',    'Phishing',              'high',   'Phishing domain impersonating Microsoft login portal',            'phishing,microsoft',           'PhishTank'),
('ip',     '45.33.32.156',                        'Scanner',               'medium', 'Mass scanning host, part of Shodan scanner network',             'scanner,shodan',               'AbuseIPDB'),
('hash',   '44d88612fea8a8f36de82e1278abb02f',   'Malware',               'high',   'EICAR test file MD5 — used for AV detection testing',            'test,eicar',                   'Internal'),
('ip',     '10.0.0.99',                           'Compromised Host',      'high',   'Internal host exhibiting ransomware behavior',                   'ransomware,internal',          'EDR Alert'),
('domain', 'download-update.info',                'Malware Distribution',  'high',   'Domain serving malicious payloads disguised as software updates', 'malware,dropper',              'VirusTotal'),
('ip',     '203.0.113.55',                        'Brute Force',           'high',   'IP conducting SSH brute force attacks across multiple targets',   'bruteforce,ssh',               'Fail2ban'),
('url',    'http://malware-hosting.biz/payload.exe','Malware Distribution','high',   'URL serving ransomware payload',                                  'ransomware,payload',           'VirusTotal');

-- Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name',               'ShadowWatch',  'Application name'),
('alert_auto_generate',     '1',            'Auto-generate alerts from active scenarios'),
('alert_interval_minutes',  '5',            'Interval for auto-generating alerts'),
('max_login_attempts',      '5',            'Maximum failed login attempts before lockout'),
('lockout_duration_minutes','15',           'Account lockout duration in minutes'),
('xp_per_alert',            '25',           'XP awarded per resolved alert'),
('xp_per_incident',         '50',           'XP awarded per closed incident'),
('session_timeout_hours',   '8',            'Session timeout in hours');