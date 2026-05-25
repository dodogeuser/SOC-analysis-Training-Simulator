<?php
$pageTitle = 'Threat Intelligence';
$currentPage = 'scenarios';
require_once __DIR__ . '/includes/header.php';

$tab = $_GET['tab'] ?? 'intel';

$iocs = db()->fetchAll("SELECT * FROM threat_intel WHERE is_active = 1 ORDER BY confidence DESC, created_at DESC");
$scenarios = db()->fetchAll("SELECT s.* FROM scenarios s 
    WHERE s.is_active = 1 ORDER BY s.difficulty");

$searchQuery = '';
$searchResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_indicator'])) {
    $searchQuery = trim($_POST['indicator'] ?? '');
    if ($searchQuery) {
        $searchResult = db()->fetchOne("SELECT * FROM threat_intel WHERE indicator = ?", [$searchQuery]);
    }
}
?>

<div class="page-header">
    <div>
        <div class="page-title">Threat Intelligence</div>
        <div class="page-subtitle">INDICATORS · SCENARIOS · LOOKUP</div>
    </div>
</div>

<!-- TABS -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:0;">
    <?php foreach (['intel' => '⬡ IOC Database', 'lookup' => '◎ IOC Lookup', 'scenarios' => '⚡ Attack Scenarios'] as $t => $label): ?>
    <a href="?tab=<?= $t ?>" 
       style="padding:10px 20px;font-family:var(--font-mono);font-size:12px;letter-spacing:1px;text-decoration:none;border-bottom:2px solid <?= $tab === $t ? 'var(--accent-cyan)' : 'transparent' ?>;color:<?= $tab === $t ? 'var(--accent-cyan)' : 'var(--text-secondary)' ?>;transition:all 0.2s">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'intel'): ?>
<!-- IOC DATABASE -->
<div class="card" style="padding:0;margin-bottom:20px">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
        <span class="card-title" style="margin:0">⬡ Known Threat Indicators (<?= count($iocs) ?>)</span>
        <input type="text" class="form-control" id="ioc-search" placeholder="Search IOCs..." style="max-width:220px;margin-left:auto" oninput="filterTable('ioc-search','ioc-table')">
    </div>
    <div class="table-wrapper" style="border:none">
        <table id="ioc-table">
            <thead>
                <tr>
                    <th>Indicator</th>
                    <th>Type</th>
                    <th>Threat Type</th>
                    <th>Confidence</th>
                    <th>Tags</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($iocs as $ioc): 
                    $confColors = ['high' => 'var(--accent-red)', 'medium' => 'var(--accent-yellow)', 'low' => 'var(--accent-green)'];
                    $tags = json_decode($ioc['tags'] ?? '[]', true);
                ?>
                <tr>
                    <td>
                        <div class="td-mono" style="color:var(--accent-cyan);cursor:pointer" onclick="copyToClipboard('<?= htmlspecialchars($ioc['indicator']) ?>')" title="Click to copy">
                            <?= htmlspecialchars($ioc['indicator']) ?>
                        </div>
                    </td>
                    <td><span class="ioc-tag"><?= strtoupper($ioc['indicator_type']) ?></span></td>
                    <td style="color:var(--text-primary);font-size:13px"><?= htmlspecialchars($ioc['threat_type']) ?></td>
                    <td>
                        <span style="font-family:var(--font-mono);font-size:10px;color:<?= $confColors[$ioc['confidence']] ?? 'var(--text-secondary)' ?>">
                            ● <?= strtoupper($ioc['confidence']) ?>
                        </span>
                    </td>
                    <td>
                        <?php foreach ($tags as $tag): ?>
                        <span class="ioc-tag"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="td-mono" style="font-size:11px;color:var(--text-dim)"><?= timeAgo($ioc['last_seen']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stats by type -->
<div class="grid-3">
    <?php
    $byType = db()->fetchAll("SELECT indicator_type, COUNT(*) as cnt FROM threat_intel GROUP BY indicator_type");
    $byConf = db()->fetchAll("SELECT confidence, COUNT(*) as cnt FROM threat_intel GROUP BY confidence");
    $byThreat = db()->fetchAll("SELECT threat_type, COUNT(*) as cnt FROM threat_intel GROUP BY threat_type ORDER BY cnt DESC LIMIT 5");
    ?>
    <div class="card">
        <div class="card-title">By Indicator Type</div>
        <?php foreach ($byType as $t): ?>
        <div class="flex justify-between" style="padding:6px 0;border-bottom:1px solid rgba(26,58,82,0.3);font-family:var(--font-mono);font-size:12px;">
            <span class="ioc-tag"><?= strtoupper($t['indicator_type']) ?></span>
            <span style="color:var(--accent-cyan)"><?= $t['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <div class="card-title">By Confidence Level</div>
        <?php $confColors2 = ['high' => 'var(--accent-red)', 'medium' => 'var(--accent-yellow)', 'low' => 'var(--accent-green)']; ?>
        <?php foreach ($byConf as $c): ?>
        <div class="flex justify-between" style="padding:6px 0;border-bottom:1px solid rgba(26,58,82,0.3);font-family:var(--font-mono);font-size:12px;">
            <span style="color:<?= $confColors2[$c['confidence']] ?>">● <?= strtoupper($c['confidence']) ?></span>
            <span style="color:var(--accent-cyan)"><?= $c['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <div class="card-title">Top Threat Types</div>
        <?php foreach ($byThreat as $t): ?>
        <div class="flex justify-between" style="padding:6px 0;border-bottom:1px solid rgba(26,58,82,0.3);font-size:12px;">
            <span><?= htmlspecialchars($t['threat_type']) ?></span>
            <span style="font-family:var(--font-mono);color:var(--accent-cyan)"><?= $t['cnt'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($tab === 'lookup'): ?>
<!-- IOC LOOKUP -->
<div class="grid-2" style="gap:24px">
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-title">◎ IOC Lookup Tool</div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:20px">
                Check any IP address, domain name, file hash, or URL against our threat intelligence database.
            </p>
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= generateCsrfToken() ?>">
                <div class="form-group">
                    <label class="form-label">Indicator</label>
                    <input type="text" name="indicator" class="form-control" 
                           placeholder="IP, domain, hash, or URL"
                           value="<?= htmlspecialchars($searchQuery) ?>"
                           style="font-family:var(--font-mono)">
                </div>
                <div class="flex gap-8">
                    <button type="submit" name="lookup_indicator" class="btn btn-primary">⬡ Lookup</button>
                    <a href="?tab=lookup" class="btn btn-ghost">Clear</a>
                </div>
            </form>

            <?php if ($searchQuery): ?>
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
                <?php if ($searchResult): ?>
                <div class="alert-bar error" style="margin-bottom:12px">⚠ THREAT INDICATOR FOUND IN DATABASE</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <?php $fields = ['Indicator' => $searchResult['indicator'], 'Type' => strtoupper($searchResult['indicator_type']), 'Threat Type' => $searchResult['threat_type'], 'Confidence' => strtoupper($searchResult['confidence']), 'First Seen' => $searchResult['first_seen'], 'Last Seen' => $searchResult['last_seen']]; ?>
                    <?php foreach ($fields as $label => $val): ?>
                    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:10px">
                        <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim);letter-spacing:2px"><?= $label ?></div>
                        <div style="font-family:var(--font-mono);font-size:13px;color:var(--accent-red);margin-top:4px"><?= htmlspecialchars($val) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:12px;background:var(--bg-panel);border:1px solid var(--border);border-radius:4px;padding:12px">
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim);letter-spacing:2px;margin-bottom:6px">DESCRIPTION</div>
                    <div style="font-size:13px;color:var(--text-secondary)"><?= htmlspecialchars($searchResult['description']) ?></div>
                </div>
                <?php $tags = json_decode($searchResult['tags'] ?? '[]', true); ?>
                <?php if ($tags): ?>
                <div style="margin-top:12px">
                    <div style="font-family:var(--font-mono);font-size:9px;color:var(--text-dim);letter-spacing:2px;margin-bottom:6px">TAGS</div>
                    <?php foreach ($tags as $tag): ?><span class="ioc-tag"><?= htmlspecialchars($tag) ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="alert-bar success">✓ NOT FOUND — "<?= htmlspecialchars($searchQuery) ?>" is not in our threat database</div>
                <div style="font-family:var(--font-mono);font-size:12px;color:var(--text-secondary);margin-top:8px">
                    This indicator appears to be clean based on our current threat intelligence.
                    Note: Absence of data does not guarantee safety.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bulk quick check examples -->
        <div class="card">
            <div class="card-title">⬡ Quick Test Lookups</div>
            <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);margin-bottom:12px">Click to test these known IOCs:</div>
            <?php foreach ($iocs as $ioc): ?>
            <span class="ioc-tag" style="cursor:pointer;margin:3px" onclick="document.querySelector('[name=indicator]').value='<?= htmlspecialchars($ioc['indicator']) ?>';document.querySelector('[name=lookup_indicator]').click()">
                <?= htmlspecialchars($ioc['indicator']) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-title">⬡ Threat Feed Summary</div>
        <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-secondary);line-height:2">
            <div>Total IOCs: <span style="color:var(--accent-cyan)"><?= count($iocs) ?></span></div>
            <div>Last Updated: <span style="color:var(--accent-cyan)"><?= date('Y-m-d H:i') ?> UTC</span></div>
            <div>Feed Status: <span style="color:var(--accent-green)">● ACTIVE</span></div>
        </div>
        <div style="margin-top:20px;padding:12px;background:var(--bg-void);border:1px solid var(--border);border-radius:4px;font-family:var(--font-mono);font-size:11px;color:var(--text-dim)">
            <div style="color:var(--accent-cyan);margin-bottom:8px">// THREAT FEED OUTPUT</div>
            <?php foreach ($iocs as $ioc): ?>
            <div style="margin-bottom:4px;">
                <span style="color:<?= $ioc['confidence'] === 'high' ? 'var(--accent-red)' : ($ioc['confidence'] === 'medium' ? 'var(--accent-yellow)' : 'var(--accent-green)') ?>">
                    [<?= strtoupper($ioc['confidence'][0]) ?>]
                </span>
                <span style="color:var(--text-secondary)"> <?= htmlspecialchars($ioc['indicator']) ?></span>
                <span style="color:var(--text-dim)"> — <?= htmlspecialchars($ioc['threat_type']) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="terminal-cursor"></div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ATTACK SCENARIOS -->
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">
    <?php foreach (['beginner','intermediate','advanced','expert'] as $diff): ?>
    <span style="font-family:var(--font-mono);font-size:11px;padding:4px 12px;border-radius:3px;border:1px solid var(--border);color:var(--text-secondary)"><?= ucfirst($diff) ?>: <?= count(array_filter($scenarios, fn($s) => $s['difficulty'] === $diff)) ?></span>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <?php foreach ($scenarios as $scenario): 
        $diffColors = ['beginner' => 'var(--accent-green)', 'intermediate' => 'var(--accent-yellow)', 'advanced' => 'var(--accent-orange)', 'expert' => 'var(--accent-red)'];
        $catIcons = ['malware' => '🦠', 'phishing' => '🎣', 'ddos' => '💥', 'intrusion' => '🚪', 'data_exfiltration' => '📤', 'ransomware' => '🔒', 'insider_threat' => '👤', 'brute_force' => '🔨', 'sql_injection' => '💉', 'xss' => '⚡'];
    ?>
    <div class="card" style="border-left:3px solid <?= ['low' => 'var(--severity-low)', 'medium' => 'var(--severity-medium)', 'high' => 'var(--severity-high)', 'critical' => 'var(--severity-critical)'][$scenario['severity']] ?>">
        <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px">
            <div style="font-size:24px"><?= $catIcons[$scenario['category']] ?? '⚠' ?></div>
            <div>
                <div style="font-weight:700;font-size:13px;color:var(--text-bright)"><?= htmlspecialchars($scenario['title']) ?></div>
                <div style="display:flex;gap:6px;margin-top:4px">
                    <?= severityBadge($scenario['severity']) ?>
                    <span style="font-family:var(--font-mono);font-size:10px;color:<?= $diffColors[$scenario['difficulty']] ?>">
                        <?= strtoupper($scenario['difficulty']) ?>
                    </span>
                </div>
            </div>
        </div>
        <p style="font-size:12px;color:var(--text-secondary);line-height:1.6;margin-bottom:12px"><?= htmlspecialchars($scenario['description']) ?></p>
        <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--font-mono);font-size:11px;">
            <span style="color:var(--text-dim)">Category: <span style="color:var(--text-secondary)"><?= htmlspecialchars($scenario['category']) ?></span></span>
            <span style="color:var(--accent-cyan)">+<?= $scenario['points'] ?> pts</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$extraScripts = "<script>filterTable('ioc-search','ioc-table');</script>";
require_once __DIR__ . '/includes/footer.php';
?>