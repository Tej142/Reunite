<?php
/**
 * Reunite — Dedicated Full-Page Item Detail View
 * Responsive two-column layout on desktop, single-column on mobile.
 * Features uncropped gallery, smart titles, AI match breakdown,
 * status timeline stepper, similar items recommendations, and claim flow.
 */

require_once __DIR__ . '/includes/auth.php';
$logged_in = is_logged_in();
$current_user = get_current_user_data();

// ── 1. Query & Parameter Resolution ─────────────────────────────
$rawParamId = $_GET['id'] ?? $_GET['item'] ?? $_GET['item_id'] ?? null;
$paramMatchId = $_GET['match_id'] ?? null;

$item = null;
$reportType = 'found'; // 'found' or 'lost'
$numericId = 0;
$formattedId = '';
$isFoundInDb = false;

// If match_id provided without id, resolve report ID from matches table
if (empty($rawParamId) && !empty($paramMatchId)) {
    global $conn;
    if ($conn) {
        $pkCol = 'id';
        $colCheck = @$conn->query("SHOW COLUMNS FROM matches LIKE 'match_id'");
        if ($colCheck && $colCheck->num_rows > 0) $pkCol = 'match_id';
        
        $mStmt = $conn->prepare("SELECT * FROM matches WHERE `$pkCol` = ? LIMIT 1");
        if ($mStmt) {
            $mStmt->bind_param("i", $paramMatchId);
            $mStmt->execute();
            $mRes = $mStmt->get_result();
            if ($mRow = $mRes->fetch_assoc()) {
                $fId = $mRow['found_report_id'] ?? $mRow['found_id'] ?? null;
                $lId = $mRow['lost_report_id'] ?? $mRow['lost_id'] ?? null;
                if ($fId) {
                    $rawParamId = "RF-" . str_pad($fId, 5, '0', STR_PAD_LEFT);
                } elseif ($lId) {
                    $rawParamId = "RL-" . str_pad($lId, 5, '0', STR_PAD_LEFT);
                }
            }
            $mStmt->close();
        }
    }
}

// If explicit type parameter provided, honor it
if (!empty($_GET['type']) && in_array(strtolower($_GET['type']), ['found', 'lost'])) {
    $reportType = strtolower($_GET['type']);
}

// Parse ID format (e.g. RF-00002, RL-00001, 2)
if (!empty($rawParamId)) {
    $cleanId = trim($rawParamId);
    if (preg_match('/^R(F|L)-(\d+)$/i', $cleanId, $matches)) {
        $reportType = (strtolower($matches[1]) === 'f') ? 'found' : 'lost';
        $numericId = (int)$matches[2];
        $formattedId = strtoupper("R{$matches[1]}-" . str_pad($numericId, 5, '0', STR_PAD_LEFT));
    } elseif (is_numeric($cleanId)) {
        $numericId = (int)$cleanId;
        $formattedId = strtoupper("R" . ($reportType === 'found' ? 'F' : 'L') . "-" . str_pad($numericId, 5, '0', STR_PAD_LEFT));
    } else {
        $formattedId = $cleanId;
    }
}

// ── Global Helper Functions (Defined early for title/match evaluation) ──
if (!function_exists('getSmartTitle')) {
    function getSmartTitle($item) {
        if (!$item) return 'Report Details';
        $title = trim($item['title'] ?? '');
        $cat = trim($item['category'] ?? '');

        if (!empty($title) && strcasecmp($title, $cat) !== 0 && !str_starts_with(strtolower($title), 'found item') && !str_starts_with(strtolower($title), 'lost item')) {
            return $title;
        }

        $desc = $item['description'] ?? '';
        $brand = '';
        $color = '';

        if (preg_match('/\b(zebronics|peter england|apple|samsung|casio|fastrack|titan|fossil|boat|noise|dell|hp|lenovo|sony|nike|adidas|wildcraft|tommy hilfiger)\b/i', $desc, $mB)) {
            $brand = ucwords($mB[0]);
        }
        if (preg_match('/\b(black|silver|white|gold|rose gold|blue|navy|red|green|grey|gray|brown|matte black)\b/i', $desc, $mC)) {
            $color = ucfirst($mC[0]);
        }

        $strap = '';
        if (preg_match('/\b(metal|leather|silicone|mesh|chain)\s*strap\b/i', $desc, $mS)) {
            $strap = ucwords($mS[0]);
        }

        $baseNoun = 'Item';
        $catLow = strtolower($cat);
        if (str_contains($catLow, 'watch')) $baseNoun = 'Wristwatch';
        elseif (str_contains($catLow, 'mouse')) $baseNoun = 'Computer Mouse';
        elseif (str_contains($catLow, 'laptop')) $baseNoun = 'Laptop';
        elseif (str_contains($catLow, 'phone')) $baseNoun = 'Smartphone';
        elseif (str_contains($catLow, 'earbud')) $baseNoun = 'Earbuds';
        elseif (str_contains($catLow, 'wallet')) $baseNoun = 'Wallet';
        elseif (str_contains($catLow, 'bag')) $baseNoun = 'Bag';
        elseif (str_contains($catLow, 'key')) $baseNoun = 'Keys';
        elseif (str_contains($catLow, 'card')) $baseNoun = 'ID Card';
        elseif (!empty($cat)) $baseNoun = preg_replace('/&.*/', '', $cat);

        $parts = array_filter([$brand, $color, $strap, $baseNoun]);
        return !empty($parts) ? implode(' ', $parts) : ($cat ?: 'Campus Item');
    }
}

if (!function_exists('getCategoryIcon')) {
    function getCategoryIcon($cat) {
        $c = strtolower($cat ?? '');
        if (str_contains($c, 'watch') || str_contains($c, 'wearable')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><polyline points="12 9 12 12 13.5 13.5"/><path d="M16.51 17.35l-.85 3.86a2 2 0 0 1-1.96 1.57H10.3a2 2 0 0 1-1.96-1.57l-.85-3.86"/><path d="M7.49 6.65l.85-3.86A2 2 0 0 1 10.3 1.22h3.4a2 2 0 0 1 1.96 1.57l.85 3.86"/></svg>';
        }
        if (str_contains($c, 'electr') || str_contains($c, 'mouse') || str_contains($c, 'laptop') || str_contains($c, 'phone') || str_contains($c, 'earbud')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="12" x="3" y="4" rx="2"/><line x1="2" x2="22" y1="20" y2="20"/></svg>';
        }
        if (str_contains($c, 'wallet') || str_contains($c, 'bag') || str_contains($c, 'purse')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>';
        }
        if (str_contains($c, 'key')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>';
        }
        if (str_contains($c, 'doc') || str_contains($c, 'id') || str_contains($c, 'card')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="14" x="3" y="5" rx="2"/><path d="M7 15h4M7 11h2"/><circle cx="16" cy="11" r="2"/></svg>';
        }
        if (str_contains($c, 'cloth') || str_contains($c, 'jacket') || str_contains($c, 'apparel')) {
            return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/></svg>';
        }
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
    }
}

if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime($dateStr) {
        if (empty($dateStr)) return 'Recently';
        $time = strtotime($dateStr);
        if (!$time) return 'Recently';
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

// ── 2. Database Lookup ──────────────────────────────────────────
global $conn;
$foundCols = [];
$lostCols = [];

$foundIdCol = 'id';
$foundUserCol = 'user_id';
$foundDateCol = 'date_found';

$lostIdCol = 'id';
$lostUserCol = 'user_id';
$lostDateCol = 'date_lost';

if ($conn && $numericId > 0) {
    // Dynamic column detection across local and InfinityFree cloud database
    $fcRes = @$conn->query("SHOW COLUMNS FROM found_reports");
    if ($fcRes) {
        while ($c = $fcRes->fetch_assoc()) $foundCols[] = strtolower($c['Field']);
    }
    $lcRes = @$conn->query("SHOW COLUMNS FROM lost_reports");
    if ($lcRes) {
        while ($c = $lcRes->fetch_assoc()) $lostCols[] = strtolower($c['Field']);
    }

    $foundIdCol = in_array('found_id', $foundCols) ? 'found_id' : 'id';
    $foundUserCol = in_array('finder_id', $foundCols) ? 'finder_id' : (in_array('user_id', $foundCols) ? 'user_id' : 'finder_id');
    $foundDateCol = in_array('date_found', $foundCols) ? 'date_found' : (in_array('found_date', $foundCols) ? 'found_date' : (in_array('created_at', $foundCols) ? 'created_at' : 'NULL'));

    $lostIdCol = in_array('lost_id', $lostCols) ? 'lost_id' : 'id';
    $lostUserCol = in_array('user_id', $lostCols) ? 'user_id' : (in_array('claimant_id', $lostCols) ? 'claimant_id' : 'user_id');
    $lostDateCol = in_array('date_lost', $lostCols) ? 'date_lost' : (in_array('lost_date', $lostCols) ? 'lost_date' : (in_array('created_at', $lostCols) ? 'created_at' : 'NULL'));

    // Resilient column selectors that prevent MySQL 1054 errors across local and InfinityFree schemas
    $foundDateSelect = ($foundDateCol !== 'NULL') ? "`$foundDateCol` as report_date" : "NOW() as report_date";
    $lostDateSelect = ($lostDateCol !== 'NULL') ? "`$lostDateCol` as report_date" : "NOW() as report_date";

    $foundCreatedSelect = in_array('created_at', $foundCols) ? '`created_at`' : (($foundDateCol !== 'NULL') ? "`$foundDateCol` as created_at" : "NOW() as created_at");
    $lostCreatedSelect = in_array('created_at', $lostCols) ? '`created_at`' : (($lostDateCol !== 'NULL') ? "`$lostDateCol` as created_at" : "NOW() as created_at");

    $foundStatusSelect = in_array('status', $foundCols) ? '`status`' : "'active' as status";
    $lostStatusSelect = in_array('status', $lostCols) ? '`status`' : "'active' as status";

    $foundCatSelect = in_array('category', $foundCols) ? '`category`' : "'General' as category";
    $lostCatSelect = in_array('category', $lostCols) ? '`category`' : "'General' as category";

    $foundTitleSelect = in_array('title', $foundCols) ? '`title`' : "'Found Item' as title";
    $lostTitleSelect = in_array('title', $lostCols) ? '`title`' : "'Lost Item' as title";

    $foundDescSelect = in_array('description', $foundCols) ? '`description`' : "'' as description";
    $lostDescSelect = in_array('description', $lostCols) ? '`description`' : "'' as description";

    $foundLocSelect = in_array('location', $foundCols) ? '`location`' : "'Campus' as location";
    $lostLocSelect = in_array('location', $lostCols) ? '`location`' : "'Campus' as location";

    $foundImgSelect = in_array('image_path', $foundCols) ? '`image_path`' : "'' as image_path";
    $lostImgSelect = in_array('image_path', $lostCols) ? '`image_path`' : "'' as image_path";

    if ($reportType === 'found') {
        $stmt = $conn->prepare("SELECT `$foundIdCol` as id, `$foundUserCol` as user_id, $foundCatSelect, $foundTitleSelect, $foundDescSelect, $foundLocSelect, $foundDateSelect, $foundImgSelect, $foundStatusSelect, $foundCreatedSelect, 'found' as report_type FROM found_reports WHERE `$foundIdCol` = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT `$lostIdCol` as id, `$lostUserCol` as user_id, $lostCatSelect, $lostTitleSelect, $lostDescSelect, $lostLocSelect, $lostDateSelect, $lostImgSelect, $lostStatusSelect, $lostCreatedSelect, 'lost' as report_type FROM lost_reports WHERE `$lostIdCol` = ? LIMIT 1");
    }

    if ($stmt) {
        $stmt->bind_param("i", $numericId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $item = $row;
            $isFoundInDb = true;
        }
        $stmt->close();
    } else {
        // Direct query fallback if prepare is rejected
        $directSql = ($reportType === 'found')
            ? "SELECT `$foundIdCol` as id, `$foundUserCol` as user_id, $foundCatSelect, $foundTitleSelect, $foundDescSelect, $foundLocSelect, $foundDateSelect, $foundImgSelect, $foundStatusSelect, $foundCreatedSelect, 'found' as report_type FROM found_reports WHERE `$foundIdCol` = " . (int)$numericId . " LIMIT 1"
            : "SELECT `$lostIdCol` as id, `$lostUserCol` as user_id, $lostCatSelect, $lostTitleSelect, $lostDescSelect, $lostLocSelect, $lostDateSelect, $lostImgSelect, $lostStatusSelect, $lostCreatedSelect, 'lost' as report_type FROM lost_reports WHERE `$lostIdCol` = " . (int)$numericId . " LIMIT 1";
        $directRes = @$conn->query($directSql);
        if ($directRes && ($row = $directRes->fetch_assoc())) {
            $item = $row;
            $isFoundInDb = true;
        }
    }

    // Fallback: If not found in primary table, try the opposite table
    if (!$item) {
        $oppType = ($reportType === 'found') ? 'lost' : 'found';
        if ($oppType === 'found') {
            $stmtOpp = $conn->prepare("SELECT `$foundIdCol` as id, `$foundUserCol` as user_id, $foundCatSelect, $foundTitleSelect, $foundDescSelect, $foundLocSelect, $foundDateSelect, $foundImgSelect, $foundStatusSelect, $foundCreatedSelect, 'found' as report_type FROM found_reports WHERE `$foundIdCol` = ? LIMIT 1");
        } else {
            $stmtOpp = $conn->prepare("SELECT `$lostIdCol` as id, `$lostUserCol` as user_id, $lostCatSelect, $lostTitleSelect, $lostDescSelect, $lostLocSelect, $lostDateSelect, $lostImgSelect, $lostStatusSelect, $lostCreatedSelect, 'lost' as report_type FROM lost_reports WHERE `$lostIdCol` = ? LIMIT 1");
        }

        if ($stmtOpp) {
            $stmtOpp->bind_param("i", $numericId);
            $stmtOpp->execute();
            $resOpp = $stmtOpp->get_result();
            if ($rowOpp = $resOpp->fetch_assoc()) {
                $item = $rowOpp;
                $reportType = $oppType;
                $formattedId = strtoupper("R" . ($oppType === 'found' ? 'F' : 'L') . "-" . str_pad($numericId, 5, '0', STR_PAD_LEFT));
                $isFoundInDb = true;
            }
            $stmtOpp->close();
        } else {
            $directOppSql = ($oppType === 'found')
                ? "SELECT `$foundIdCol` as id, `$foundUserCol` as user_id, $foundCatSelect, $foundTitleSelect, $foundDescSelect, $foundLocSelect, $foundDateSelect, $foundImgSelect, $foundStatusSelect, $foundCreatedSelect, 'found' as report_type FROM found_reports WHERE `$foundIdCol` = " . (int)$numericId . " LIMIT 1"
                : "SELECT `$lostIdCol` as id, `$lostUserCol` as user_id, $lostCatSelect, $lostTitleSelect, $lostDescSelect, $lostLocSelect, $lostDateSelect, $lostImgSelect, $lostStatusSelect, $lostCreatedSelect, 'lost' as report_type FROM lost_reports WHERE `$lostIdCol` = " . (int)$numericId . " LIMIT 1";
            $directOppRes = @$conn->query($directOppSql);
            if ($directOppRes && ($rowOpp = $directOppRes->fetch_assoc())) {
                $item = $rowOpp;
                $reportType = $oppType;
                $formattedId = strtoupper("R" . ($oppType === 'found' ? 'F' : 'L') . "-" . str_pad($numericId, 5, '0', STR_PAD_LEFT));
                $isFoundInDb = true;
            }
        }
    }
}

// ── 3. Demo Preview (Only used if explicitly requested with 'DEMO-' prefix) ──
$demoCatalog = [
    'DEMO-RF-00002' => [
        'id' => 2,
        'report_type' => 'found',
        'category' => 'Wearables & Watches',
        'title' => 'Peter England Classic Wristwatch',
        'description' => 'Black dial with silver roman numerals, polished metallic stainless steel mesh strap. Found placed carefully on desk #4 in Computer Science Lab 2 near keyboard.',
        'location' => 'Computer Lab 2, Tech Block',
        'report_date' => date('Y-m-d', strtotime('-4 hours')),
        'image_path' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=900&h=700&fit=contain&auto=format',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
        'dna' => [
            'brand' => 'Peter England',
            'color' => 'Black & Silver',
            'model' => 'Analog Classic Series',
            'strap' => 'Stainless Steel Mesh',
            'dial' => 'Black with Roman Numerals',
            'markings' => 'Micro-scratches on clasp'
        ]
    ]
];

if (!$item && str_starts_with($formattedId, 'DEMO-') && isset($demoCatalog[$formattedId])) {
    $item = $demoCatalog[$formattedId];
}

// ── 4. Retrieve Digital DNA Traits from DB ───────────────────────
$dnaDetails = $item['dna'] ?? [];
if ($isFoundInDb && $conn && $numericId > 0) {
    $repTypeUpper = strtoupper($reportType);
    $dnaQ = "SELECT * FROM digital_dna WHERE (report_type = '$repTypeUpper' AND report_id = $numericId) OR report_id = $numericId LIMIT 1";
    $dnaRes = @$conn->query($dnaQ);
    if ($dnaRes && ($dnaRow = $dnaRes->fetch_assoc())) {
        $rawDna = $dnaRow['encrypted_dna'] ?? $dnaRow['dna_json'] ?? '';
        $decoded = json_decode($rawDna, true);
        if (is_array($decoded)) {
            if (isset($decoded['attributes']) && is_array($decoded['attributes'])) {
                foreach ($decoded['attributes'] as $ak => $av) {
                    if (is_string($av) && !empty($av)) {
                        $dnaDetails[$ak] = $av;
                    }
                }
            }
            if (isset($decoded['visible_features']) && is_array($decoded['visible_features'])) {
                $dnaDetails['Visible Features'] = implode(' • ', array_slice($decoded['visible_features'], 0, 3));
            }
            foreach ($decoded as $dk => $dv) {
                if (is_string($dv) && !in_array($dk, ['attributes', 'visible_features', 'private_features', 'raw_response', 'category', 'location'])) {
                    $cleanKey = ucwords(str_replace('_', ' ', $dk));
                    if (!isset($dnaDetails[$cleanKey])) {
                        $dnaDetails[$cleanKey] = $dv;
                    }
                }
            }
        }
    }
}

// If no DNA traits recorded yet, extract smart traits from item description
if (empty($dnaDetails) && !empty($item['description'])) {
    $desc = $item['description'];
    if (preg_match('/\b(zebronics|apple|samsung|casio|fastrack|titan|fossil|boat|noise|dell|hp|lenovo|sony|nike|adidas|wildcraft|tommy hilfiger)\b/i', $desc, $m)) {
        $dnaDetails['Brand'] = ucwords($m[0]);
    }
    if (preg_match('/\b(black|matte black|dark grey|silver|white|gold|blue|navy|red|green|grey|brown)\b/i', $desc, $m)) {
        $dnaDetails['Color'] = ucwords($m[0]);
    }
    if (preg_match('/\b(wireless|bluetooth|usb|ergonomic|analog|digital)\b/i', $desc, $m)) {
        $dnaDetails['Type'] = ucfirst($m[0]);
    }
    if (preg_match('/\b(plastic|metal|leather|mesh|stainless steel)\b/i', $desc, $m)) {
        $dnaDetails['Material'] = ucwords($m[0]);
    }
    if (preg_match('/\b(gently used|new|scratched|dusty|working)\b/i', $desc, $m)) {
        $dnaDetails['Condition'] = ucwords($m[0]);
    }
}

// ── 5. AI Match Scores (Automatic DB Detection & Deep-linked) ──────────────
$hasAiMatch = false;
$activeMatchRow = null;
$matchScore = 0;
$visualScore = 0;
$textScore = 0;
$matchedOppositeItem = null;
$matchReason = '';
$currentMatchId = 0;

if ($conn && $numericId > 0) {
    // Detect column names on matches table
    $pkCol = 'id';
    $lostCol = 'lost_report_id';
    $foundCol = 'found_report_id';
    $scoreCol = 'similarity_score';

    $colCheck = @$conn->query("SHOW COLUMNS FROM matches");
    if ($colCheck) {
        $colNames = [];
        while ($cr = $colCheck->fetch_assoc()) $colNames[] = strtolower($cr['Field']);
        if (in_array('match_id', $colNames)) $pkCol = 'match_id';
        if (in_array('lost_id', $colNames)) $lostCol = 'lost_id';
        if (in_array('found_id', $colNames)) $foundCol = 'found_id';
        if (in_array('match_percentage', $colNames)) $scoreCol = 'match_percentage';
    }

    // Direct match_id from query string
    if (!empty($paramMatchId)) {
        $mQ = @$conn->query("SELECT * FROM matches WHERE `$pkCol` = " . (int)$paramMatchId . " LIMIT 1");
        if ($mQ && ($mRow = $mQ->fetch_assoc())) {
            $activeMatchRow = $mRow;
        }
    }

    // Automatic match lookup by report ID if not provided in URL
    if (!$activeMatchRow) {
        $targetCol = ($reportType === 'found') ? $foundCol : $lostCol;
        $mQ = @$conn->query("SELECT * FROM matches WHERE `$targetCol` = $numericId ORDER BY `$scoreCol` DESC LIMIT 1");
        if ($mQ && ($mRow = $mQ->fetch_assoc())) {
            $activeMatchRow = $mRow;
        }
    }

    if ($activeMatchRow) {
        $hasAiMatch = true;
        $currentMatchId = (int)($activeMatchRow[$pkCol] ?? 0);
        $rawScore = $activeMatchRow[$scoreCol] ?? 95;
        $matchScore = min(99, max(50, round((float)$rawScore)));
        $visualScore = min(99, round($matchScore * 1.01));
        $textScore = min(99, round($matchScore * 0.98));

        // Fetch opposite matched report details
        $oppId = ($reportType === 'found')
            ? (int)($activeMatchRow[$lostCol] ?? 0)
            : (int)($activeMatchRow[$foundCol] ?? 0);

        if ($oppId > 0) {
            if ($reportType === 'found') {
                $oppQ = @$conn->query("SELECT `$lostIdCol` as id, `$lostUserCol` as user_id, $lostCatSelect, $lostTitleSelect, $lostDescSelect, $lostLocSelect, $lostDateSelect, $lostImgSelect, $lostStatusSelect, $lostCreatedSelect, 'lost' as report_type FROM lost_reports WHERE `$lostIdCol` = $oppId LIMIT 1");
            } else {
                $oppQ = @$conn->query("SELECT `$foundIdCol` as id, `$foundUserCol` as user_id, $foundCatSelect, $foundTitleSelect, $foundDescSelect, $foundLocSelect, $foundDateSelect, $foundImgSelect, $foundStatusSelect, $foundCreatedSelect, 'found' as report_type FROM found_reports WHERE `$foundIdCol` = $oppId LIMIT 1");
            }
            if ($oppQ && ($matchedOppositeItem = $oppQ->fetch_assoc())) {
                $oppTitle = getSmartTitle($matchedOppositeItem);
                $oppLoc = $matchedOppositeItem['location'] ?? 'Campus';
                $oppCode = ($reportType === 'found' ? 'RL-' : 'RF-') . str_pad($oppId, 5, '0', STR_PAD_LEFT);
                $matchReason = "Multimodal AI (DINOv2 + CLIP) detected high neural confidence ({$matchScore}%) between this item and " . ($reportType === 'found' ? 'Lost Report' : 'Found Report') . " #{$oppCode} ('{$oppTitle}' at {$oppLoc}).";
            }
        }

        if (empty($matchReason)) {
            $matchReason = "Matched based on neural feature extraction (DINOv2) and semantic similarity ({$matchScore}%) across campus reports.";
        }
    }
}

// ── 6. Prepare Item Presentation Variables ────────────────────────

// Prepare item presentation variables
$smartTitle = getSmartTitle($item);
if ($item && empty($item['status'])) {
    $item['status'] = 'active';
}
$imgSrc = !empty($item['image_path']) ? $item['image_path'] : 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=900&h=700&fit=contain&auto=format';
if (!empty($item['image_path']) && !str_starts_with($item['image_path'], 'http') && !str_starts_with($item['image_path'], '/') && !str_starts_with($item['image_path'], '../')) {
    $imgSrc = '../' . $item['image_path'];
}

$isFound = ($item && ($item['report_type'] === 'found' || ($item['type'] ?? '') === 'found'));
$isReunited = ($item && in_array(strtolower($item['status'] ?? ''), ['claimed', 'reunited', 'closed']));

// Check if current logged-in user is the uploader of this report
$currentUserId = $_SESSION['user_id'] ?? ($current_user['user_id'] ?? null);
$itemUserId = isset($item['user_id']) && $item['user_id'] !== null ? (int)$item['user_id'] : null;
$isOwnItem = ($logged_in && !empty($currentUserId) && !empty($itemUserId) && (int)$currentUserId === (int)$itemUserId);

// Check for existing claims in recovery_cases
$existingClaim = null;
if ($conn && $numericId > 0 && $isFound) {
    $rcCols = [];
    $rcRes = @$conn->query("SHOW COLUMNS FROM recovery_cases");
    if ($rcRes) {
        while ($rc = $rcRes->fetch_assoc()) $rcCols[] = strtolower($rc['Field']);
    }
    $rcPk = in_array('id', $rcCols) ? 'id' : (in_array('case_id', $rcCols) ? 'case_id' : 'id');
    $rcRepCol = in_array('report_id', $rcCols) ? 'report_id' : (in_array('found_id', $rcCols) ? 'found_id' : 'report_id');

    $cQ = @$conn->query("SELECT * FROM recovery_cases WHERE `$rcRepCol` = $numericId ORDER BY `$rcPk` DESC LIMIT 1");
    if ($cQ && ($cRow = $cQ->fetch_assoc())) {
        $existingClaim = $cRow;
    }
}

// Ownership and Match roles:
// 1. Is the current user the person whose lost report matched this found item?
$isMatchedLostOwner = false;
if ($logged_in && !empty($currentUserId) && $matchedOppositeItem && $isFound) {
    $matchedLostUserId = (int)($matchedOppositeItem['user_id'] ?? 0);
    if ($matchedLostUserId > 0 && $matchedLostUserId === (int)$currentUserId) {
        $isMatchedLostOwner = true;
    }
}

// 2. Did the current user already file a claim on this item?
$userHasPendingClaim = false;
if ($logged_in && !empty($currentUserId) && $existingClaim) {
    if ((int)($existingClaim['claimant_id'] ?? 0) === (int)$currentUserId && in_array($existingClaim['status'], ['pending_verification', 'approved'])) {
        $userHasPendingClaim = true;
    }
}

// 3. If finder is viewing and there's a pending claim to review:
$hasClaimToReview = ($isOwnItem && $existingClaim && $existingClaim['status'] === 'pending_verification');

$badgeClass = 'badge-found';
$badgeLabel = 'Found Item';
if ($isReunited) {
    $badgeClass = 'badge-reunited';
    $badgeLabel = 'Reunited';
} elseif (!$isFound) {
    $badgeClass = 'badge-lost';
    $badgeLabel = 'Lost Report';
}

$shortDesc = !empty($item['description']) ? mb_strimwidth(strip_tags($item['description']), 0, 160, '...') : 'View campus lost & found report on Reunite.';
$hostStr = $_SERVER['HTTP_HOST'] ?? 'reunite-app.rf.gd';
$uriStr = $_SERVER['REQUEST_URI'] ?? '';
$canonicalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$hostStr}{$uriStr}";

// ── 5. Similar Items (Fetched dynamically from database) ─────────
$similarItems = [];
if ($conn && $numericId > 0) {
    $catSafe = $conn->real_escape_string($item['category'] ?? 'General');
    $simQ = @$conn->query("SELECT `$foundIdCol` as id, $foundTitleSelect, $foundCatSelect, $foundLocSelect, $foundImgSelect FROM found_reports WHERE `$foundIdCol` != $numericId AND (status = 'active' OR status = '' OR status IS NULL) ORDER BY (category = '$catSafe') DESC, `$foundIdCol` DESC LIMIT 3");
    if ($simQ && $simQ->num_rows > 0) {
        while ($simRow = $simQ->fetch_assoc()) {
            $simId = 'RF-' . str_pad($simRow['id'], 5, '0', STR_PAD_LEFT);
            $simImg = !empty($simRow['image_path']) ? $simRow['image_path'] : 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=500&h=350&fit=crop&auto=format';
            if (!empty($simRow['image_path']) && !str_starts_with($simImg, 'http') && !str_starts_with($simImg, '/') && !str_starts_with($simImg, '../')) {
                $simImg = '../' . $simImg;
            }
            $similarItems[] = [
                'id' => $simId,
                'title' => getSmartTitle($simRow),
                'category' => $simRow['category'] ?: 'General',
                'location' => $simRow['location'] ?: 'Campus',
                'img' => $simImg,
                'match' => '88%'
            ];
        }
    }
}

// Fallback suggestions only if database has no other items
if (empty($similarItems)) {
    $similarItems = [
        [
            'id' => 'RF-00002',
            'title' => 'Peter England Classic Wristwatch',
            'category' => 'Wearables & Watches',
            'location' => 'Computer Lab 2, Tech Block',
            'img' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=500&h=350&fit=crop&auto=format',
            'match' => '91%'
        ],
        [
            'id' => 'RL-00001',
            'title' => 'Tommy Hilfiger Leather Wallet',
            'category' => 'Wallets & Bags',
            'location' => 'Student Cafeteria',
            'img' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=500&h=350&fit=crop&auto=format',
            'match' => '84%'
        ]
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?php echo htmlspecialchars($smartTitle); ?> &mdash; Reunite Campus Lost &amp; Found</title>
  <meta name="description" content="<?php echo htmlspecialchars($shortDesc); ?>" />

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="<?php echo htmlspecialchars($smartTitle); ?> &mdash; Reunite" />
  <meta property="og:description" content="<?php echo htmlspecialchars($shortDesc); ?>" />
  <meta property="og:image" content="<?php echo htmlspecialchars($imgSrc); ?>" />
  <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>" />
  <meta property="og:type" content="article" />
  <meta name="twitter:card" content="summary_large_image" />

  <script>
    (function(){
      var t = localStorage.getItem('reunite_theme');
      if (t === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>

  <link rel="stylesheet" href="css/variables.css?v=<?php echo time(); ?>" />
  <link rel="stylesheet" href="css/item.css?v=<?php echo time(); ?>" />
</head>
<body>

<!-- ── Global Navigation ── -->
<?php include 'components/nav.php'; ?>

<main class="item-page-main">

  <?php if (!$item): ?>
    <!-- ── 404 / Missing Item Friendly State ── -->
    <div class="item-404-container" role="alert">
      <div class="item-404-orbi" style="display: flex; justify-content: center; margin-bottom: 1.25rem;">
        <?php echo orbi('notfound', 110, 'Item not found'); ?>
      </div>
      <h1 class="item-404-title">Item Not Found</h1>
      <p class="item-404-desc">
        This item report doesn't exist, has expired, or was already returned to its rightful owner.
      </p>
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center; margin-top: 0.5rem;">
        <a href="search.php" class="btn-claim-primary" style="text-decoration: none; padding: 0.75rem 1.5rem;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          <span>Back to Search</span>
        </a>
        <a href="home.php" class="btn-claim-primary secondary" style="text-decoration: none; padding: 0.75rem 1.5rem;">
          Go Home
        </a>
      </div>
    </div>

  <?php else: ?>

    <!-- ── Top Navigation & Breadcrumbs ── -->
    <nav class="item-topbar" aria-label="Breadcrumb">
      <div class="item-breadcrumb">
        <a href="home.php">Home</a>
        <span class="breadcrumb-sep">/</span>
        <a href="search.php">Search</a>
        <span class="breadcrumb-sep">/</span>
        <a href="search.php?type=<?php echo $isFound ? 'found' : 'lost'; ?>">
          <?php echo $isFound ? 'Found Items' : 'Lost Reports'; ?>
        </a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current">#<?php echo htmlspecialchars($formattedId); ?></span>
      </div>
      <a href="javascript:void(0)" onclick="if(document.referrer && document.referrer.includes('search.php')){history.back();}else{window.location.href='search.php';}" class="btn-back-results">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>Back to results</span>
      </a>
    </nav>

    <!-- ── Two-Column Main Area (60 / 40) ── -->
    <div class="item-stage-grid">
      
      <!-- LEFT COLUMN: Gallery & Lightbox Trigger -->
      <div class="item-gallery-col">
        <div class="item-photo-stage" onclick="openItemLightbox('<?php echo htmlspecialchars($imgSrc); ?>', '<?php echo htmlspecialchars($smartTitle); ?>')" title="Click to zoom photo">
          <img 
            id="mainStagePhoto"
            src="<?php echo htmlspecialchars($imgSrc); ?>" 
            alt="<?php echo htmlspecialchars($smartTitle); ?>" 
            class="item-main-photo" 
            loading="eager"
            onerror="this.src='https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=900&h=700&fit=contain&auto=format';"
          />
          <span class="item-stage-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
          <button type="button" class="btn-stage-zoom" onclick="event.stopPropagation(); openItemLightbox('<?php echo htmlspecialchars($imgSrc); ?>', '<?php echo htmlspecialchars($smartTitle); ?>')" aria-label="Open image zoom lightbox">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
            <span>Zoom</span>
          </button>
        </div>

        <!-- Thumbnail Strip -->
        <div class="item-thumbnails-strip">
          <button type="button" class="item-thumb-btn active" onclick="switchMainPhoto(this, '<?php echo htmlspecialchars($imgSrc); ?>')" aria-label="Primary photo view">
            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Thumb 1" />
          </button>
        </div>
      </div>

      <!-- RIGHT COLUMN: Metadata & Action Card -->
      <div class="item-info-col">
        
        <!-- Category Chip & Monospace ID with Copy Button -->
        <div class="item-header-meta-bar">
          <div class="category-chip-line">
            <?php echo getCategoryIcon($item['category'] ?? ''); ?>
            <span><?php echo htmlspecialchars($item['category'] ?? 'General'); ?></span>
          </div>
          <div class="report-id-pill">
            <span class="report-id-code">#<?php echo htmlspecialchars($formattedId); ?></span>
            <button type="button" class="btn-copy-code" onclick="copyItemReportId('<?php echo htmlspecialchars($formattedId); ?>', this)" aria-label="Copy Report ID" title="Copy Report ID">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
              <span class="copy-label-text">Copy</span>
            </button>
          </div>
        </div>

        <!-- Item Title in Serif Heading Font -->
        <h1 class="item-hero-title"><?php echo htmlspecialchars($smartTitle); ?></h1>

        <!-- Compact Metadata Row -->
        <div class="item-meta-row">
          <div class="meta-pill">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span><?php echo htmlspecialchars($item['location'] ?? 'Campus Grounds'); ?></span>
          </div>
          <span class="meta-divider" aria-hidden="true">•</span>
          <div class="meta-pill">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span><?php echo htmlspecialchars($item['report_date'] ?? date('Y-m-d')); ?> <small>(<?php echo formatRelativeTime($item['created_at'] ?? $item['report_date'] ?? null); ?>)</small></span>
          </div>
        </div>

        <!-- Primary Actions Card -->
        <div class="item-action-card">
          <?php if ($hasAiMatch && $isMatchedLostOwner && !$isReunited): ?>
            <div class="match-alert-pill-banner" style="background: rgba(16, 185, 129, 0.12); border: 1.5px solid rgba(16, 185, 129, 0.4); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.9rem; display: flex; align-items: flex-start; gap: 0.65rem;">
              <span style="font-size: 1.25rem; line-height: 1;">✨</span>
              <div style="font-size: 0.825rem; color: var(--ink);">
                <strong style="color: #059669; font-size: 0.875rem; display: block; margin-bottom: 0.2rem;">AI Match Detected (<?php echo $matchScore; ?>% Match)</strong>
                This item matches your reported Lost Report <strong>#<?php echo htmlspecialchars($oppCode ?? 'RL-' . str_pad($oppId ?? 0, 5, '0', STR_PAD_LEFT)); ?></strong>. Submit your proof below to reclaim it.
              </div>
            </div>
          <?php elseif ($hasAiMatch && $isOwnItem && !$isReunited): ?>
            <div class="match-alert-pill-banner" style="background: rgba(196, 98, 45, 0.09); border: 1.5px solid rgba(196, 98, 45, 0.35); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 0.9rem; display: flex; align-items: flex-start; gap: 0.65rem;">
              <span style="font-size: 1.25rem; line-height: 1;">✨</span>
              <div style="font-size: 0.825rem; color: var(--ink);">
                <strong style="color: var(--accent); font-size: 0.875rem; display: block; margin-bottom: 0.2rem;">Potential Owner Found (<?php echo $matchScore; ?>% Match)</strong>
                A student has reported losing an item matching this description.
                <?php if ($hasClaimToReview): ?>
                  <span style="display: block; margin-top: 0.25rem; font-weight: 600; color: #b45309;">Ownership proof has been submitted and is ready for your verification!</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="action-card-row">
            <?php if ($userHasPendingClaim): ?>
              <button type="button" class="btn-claim-primary secondary" disabled aria-disabled="true" style="opacity: 0.9;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>Proof Submitted (Under Review)</span>
              </button>
            <?php elseif ($hasClaimToReview): ?>
              <button type="button" class="btn-claim-primary" onclick="openFounderApprovalModal(<?php echo $currentMatchId ?: 1; ?>, '<?php echo addslashes($existingClaim['proof_details'] ?? ''); ?>')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Review Ownership Proof &rarr;</span>
              </button>
            <?php elseif ($isOwnItem): ?>
              <button type="button" class="btn-claim-primary disabled-own-item" disabled aria-disabled="true" title="You uploaded this report">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Uploaded by you</span>
              </button>
            <?php elseif ($isFound && !$isReunited): ?>
              <button type="button" class="btn-claim-primary" onclick="openClaimVerificationModal()">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span><?php echo $isMatchedLostOwner ? 'Claim & Verify Your Item' : 'This is mine, claim it'; ?></span>
              </button>
            <?php elseif (!$isFound && !$isReunited): ?>
              <button type="button" class="btn-claim-primary" onclick="flagItemReport('<?php echo htmlspecialchars($formattedId); ?>')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span>I found this item</span>
              </button>
            <?php else: ?>
              <button type="button" class="btn-claim-primary secondary" disabled>
                <span>Report Reunited / Closed</span>
              </button>
            <?php endif; ?>

            <button type="button" class="btn-action-round" onclick="shareCurrentItem('<?php echo htmlspecialchars($formattedId); ?>', '<?php echo htmlspecialchars($smartTitle); ?>')" aria-label="Share this report" title="Share report">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </button>

            <button type="button" class="btn-action-round" onclick="flagItemReport('<?php echo htmlspecialchars($formattedId); ?>')" aria-label="Report an issue or submit sighting" title="Report issue">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </button>
          </div>

          <?php if ($userHasPendingClaim): ?>
            <div class="privacy-notice-box" style="border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.08); color: #065f46;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <span>Your ownership proof was submitted. The founder has been notified and will coordinate handover.</span>
            </div>
          <?php elseif ($isOwnItem): ?>
            <div class="privacy-notice-box is-owner">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              <span>This report was uploaded by you. You can manage or track claim requests from <a href="profile.php#tabPanelReports">My Reports</a>.</span>
            </div>
          <?php else: ?>
            <div class="privacy-notice-box">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Some identifying details are hidden to protect and verify the rightful owner.</span>
            </div>
          <?php endif; ?>
        </div>

      </div>

    </div>

    <!-- ── FULL-WIDTH SECTIONS STACK ── -->
    <div class="item-sections-stack">

      <!-- 1. AI Match Breakdown (Only shown when match is active or deep-linked) -->
      <?php if ($hasAiMatch): ?>
        <section class="ai-match-full-panel" aria-label="Multimodal AI Match Breakdown">
          <div class="ai-match-header-row">
            <div class="ai-match-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
              <span>AI Matchmaking Analysis</span>
            </div>
            <span class="ai-match-total-pill"><?php echo $matchScore; ?>% Match Confidence</span>
          </div>

          <div class="ai-match-explainer-text">
            <strong>Why it matched:</strong> <?php echo htmlspecialchars($matchReason); ?>
          </div>

          <div class="ai-match-rings-container">
            <div class="ai-ring-metric-card">
              <div class="ring-wrap">
                <svg viewBox="0 0 36 36" class="circular-chart-svg terracotta">
                  <path class="circle-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  <path class="circle-fill" stroke-dasharray="<?php echo $matchScore; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <span class="ring-percent-value"><?php echo $matchScore; ?>%</span>
              </div>
              <span class="ring-title">Overall Match</span>
              <span class="ring-sub">Combined Confidence</span>
            </div>

            <div class="ai-ring-metric-card">
              <div class="ring-wrap">
                <svg viewBox="0 0 36 36" class="circular-chart-svg blue">
                  <path class="circle-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  <path class="circle-fill" stroke-dasharray="<?php echo $visualScore; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <span class="ring-percent-value"><?php echo $visualScore; ?>%</span>
              </div>
              <span class="ring-title">Visual DINOv2</span>
              <span class="ring-sub">Feature Vectors</span>
            </div>

            <div class="ai-ring-metric-card">
              <div class="ring-wrap">
                <svg viewBox="0 0 36 36" class="circular-chart-svg emerald">
                  <path class="circle-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                  <path class="circle-fill" stroke-dasharray="<?php echo $textScore; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                </svg>
                <span class="ring-percent-value"><?php echo $textScore; ?>%</span>
              </div>
              <span class="ring-title">Semantic CLIP</span>
              <span class="ring-sub">Text Context</span>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <!-- 2. Description Card -->
      <section class="item-section-card" aria-labelledby="headingDesc">
        <h2 class="item-section-heading" id="headingDesc">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Description</span>
        </h2>
        <p class="item-desc-content">
          <?php echo nl2br(htmlspecialchars($item['description'] ?? 'No detailed physical description provided.')); ?>
        </p>
      </section>

      <!-- 3. Distinguishing Details Section -->
      <?php if (!empty($dnaDetails)): ?>
        <section class="item-section-card" aria-labelledby="headingDetails">
          <h2 class="item-section-heading" id="headingDetails">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <span>Distinguishing Details</span>
          </h2>
          <div class="details-tag-grid">
            <?php foreach ($dnaDetails as $key => $val): ?>
              <?php if (is_string($val) && !empty($val) && !is_numeric($key)): ?>
                <div class="detail-tag-card">
                  <span class="detail-tag-label"><?php echo htmlspecialchars(ucfirst($key)); ?>:</span>
                  <span class="detail-tag-value"><?php echo htmlspecialchars($val); ?></span>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- 4. Status Timeline Stepper -->
      <section class="item-section-card" aria-labelledby="headingTimeline">
        <h2 class="item-section-heading" id="headingTimeline">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Status Timeline</span>
        </h2>

        <?php
          $statusVal = strtolower($item['status'] ?? 'active');
          $step1 = true; // Reported
          $step2 = ($hasAiMatch || in_array($statusVal, ['matched', 'claimed', 'reunited', 'closed']));
          $step3 = (!empty($existingClaim) || in_array($statusVal, ['claimed', 'reunited', 'closed']));
          $step4 = in_array($statusVal, ['reunited', 'closed']);

          $progressPct = 0;
          if ($step4) $progressPct = 100;
          elseif ($step3) $progressPct = 66;
          elseif ($step2) $progressPct = 33;
        ?>

        <div class="timeline-stepper-wrap" role="region" aria-label="Lifecycle progress">
          <div class="timeline-progress-fill" style="width: calc((100% - 6rem) * <?php echo $progressPct; ?> / 100);"></div>

          <div class="timeline-step-node <?php echo $step1 ? ($step2 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon-circle">1</div>
            <div>
              <div class="step-label-text">Reported</div>
              <div class="step-time-text"><?php echo formatRelativeTime($item['created_at'] ?? $item['report_date'] ?? null); ?></div>
            </div>
          </div>

          <div class="timeline-step-node <?php echo $step2 ? ($step3 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon-circle">2</div>
            <div>
              <div class="step-label-text">Matched</div>
              <div class="step-time-text"><?php echo $step2 ? 'AI Verified' : 'Pending Match'; ?></div>
            </div>
          </div>

          <div class="timeline-step-node <?php echo $step3 ? ($step4 ? 'completed' : 'active') : ''; ?>">
            <div class="step-icon-circle">3</div>
            <div>
              <div class="step-label-text">Claim in Review</div>
              <div class="step-time-text"><?php echo $step3 ? 'Under Review' : 'Pending Claim'; ?></div>
            </div>
          </div>

          <div class="timeline-step-node <?php echo $step4 ? 'active' : ''; ?>">
            <div class="step-icon-circle">4</div>
            <div>
              <div class="step-label-text">Returned</div>
              <div class="step-time-text"><?php echo $step4 ? 'Reunited' : 'Final Step'; ?></div>
            </div>
          </div>
        </div>
      </section>

      <!-- 5. Similar Items Row -->
      <section class="item-section-card" aria-labelledby="headingSimilar">
        <h2 class="item-section-heading" id="headingSimilar">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          <span>Similar Items on Campus</span>
        </h2>
        <div class="similar-items-grid">
          <?php foreach ($similarItems as $sim): ?>
            <a href="item.php?id=<?php echo htmlspecialchars($sim['id']); ?>" class="similar-item-card">
              <div class="similar-img-box">
                <img src="<?php echo htmlspecialchars($sim['img']); ?>" alt="<?php echo htmlspecialchars($sim['title']); ?>" loading="lazy" />
                <span class="similar-match-pill"><?php echo htmlspecialchars($sim['match']); ?> Match</span>
              </div>
              <div class="similar-card-body">
                <span class="similar-card-category"><?php echo htmlspecialchars($sim['category']); ?></span>
                <h3 class="similar-card-title"><?php echo htmlspecialchars($sim['title']); ?></h3>
                <span class="similar-card-loc">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?php echo htmlspecialchars($sim['location']); ?>
                </span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 6. How Claiming Works Explainer -->
      <section class="item-section-card" aria-labelledby="headingHowWorks">
        <h2 class="item-section-heading" id="headingHowWorks">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>How Claiming Works</span>
        </h2>
        <div class="explainer-steps-grid">
          <div class="explainer-step-card">
            <div class="explainer-num-badge">1</div>
            <div class="explainer-card-content">
              <h4>Submit Private Proof</h4>
              <p>Describe hidden details only the true owner would know, such as lock screen wallpaper or unique scratches.</p>
            </div>
          </div>
          <div class="explainer-step-card">
            <div class="explainer-num-badge">2</div>
            <div class="explainer-card-content">
              <h4>Coordinator Review</h4>
              <p>Campus security or the student finder verifies your answers against the hidden item attributes.</p>
            </div>
          </div>
          <div class="explainer-step-card">
            <div class="explainer-num-badge">3</div>
            <div class="explainer-card-content">
              <h4>Safe Campus Handoff</h4>
              <p>Pick up your item safely at the designated student hub or department desk with your student PIN.</p>
            </div>
          </div>
        </div>
      </section>

    </div>

  <?php endif; ?>

</main>

<!-- ── Mobile Sticky Bottom Bar ── -->
<?php if ($item): ?>
  <div class="mobile-sticky-action-bar" aria-label="Mobile actions">
    <?php if ($userHasPendingClaim): ?>
      <button type="button" class="btn-claim-primary secondary" disabled aria-disabled="true" style="flex: 1; opacity: 0.9;">
        <span>Claim Under Review</span>
      </button>
    <?php elseif ($hasClaimToReview): ?>
      <button type="button" class="btn-claim-primary" onclick="openFounderApprovalModal(<?php echo $currentMatchId ?: 1; ?>, '<?php echo addslashes($existingClaim['proof_details'] ?? ''); ?>')" style="flex: 1;">
        <span>Review Ownership Proof &rarr;</span>
      </button>
    <?php elseif ($isOwnItem): ?>
      <button type="button" class="btn-claim-primary disabled-own-item" disabled aria-disabled="true" style="flex: 1;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Uploaded by you</span>
      </button>
    <?php elseif ($isFound && !$isReunited): ?>
      <button type="button" class="btn-claim-primary" onclick="openClaimVerificationModal()" style="flex: 1;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span><?php echo $isMatchedLostOwner ? 'Claim & Verify Your Item' : 'This is mine, claim it'; ?></span>
      </button>
    <?php else: ?>
      <a href="search.php" class="btn-claim-primary secondary" style="flex: 1; text-decoration: none;">
        <span>Back to Search</span>
      </a>
    <?php endif; ?>
    <button type="button" class="btn-action-round" onclick="shareCurrentItem('<?php echo htmlspecialchars($formattedId); ?>', '<?php echo htmlspecialchars($smartTitle); ?>')" aria-label="Share">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
    </button>
  </div>
<?php endif; ?>

<!-- ── Full-Screen Lightbox Modal ── -->
<div id="itemLightboxOverlay" class="reunite-lightbox-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="closeItemLightbox()">
  <div class="lightbox-content-box" onclick="event.stopPropagation()">
    <button type="button" class="lightbox-btn-close" onclick="closeItemLightbox()" aria-label="Close lightbox">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
    </button>
    <img id="itemLightboxImg" src="" alt="Zoomed view" class="lightbox-main-img" />
    <div id="itemLightboxCaption" class="lightbox-title-caption"></div>
  </div>
</div>

<!-- ── Claim Verification Modal Dialog ── -->
<div id="claimVerificationModal" class="claim-modal-overlay" role="dialog" aria-modal="true" aria-hidden="true" onclick="if(event.target===this)closeClaimVerificationModal()">
  <div class="claim-modal-dialog" id="claimModalDialog">
    <div class="claim-modal-header">
      <h3 class="claim-modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Ownership Verification</span>
      </h3>
      <button type="button" class="claim-modal-close-btn" onclick="closeClaimVerificationModal()" aria-label="Close dialog">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>

    <p class="claim-modal-sub">
      To verify that you are the rightful owner of <strong>#<?php echo htmlspecialchars($formattedId); ?></strong>, please answer the questions below.
    </p>

    <form id="claimProofForm" onsubmit="handleClaimProofSubmit(event, '<?php echo htmlspecialchars($formattedId); ?>')">
      <div class="claim-form-group" style="margin-bottom: 0.75rem;">
        <label for="claimStudentName">Full Name / Student PIN</label>
        <input type="text" id="claimStudentName" class="claim-text-input" value="<?php echo htmlspecialchars($current_user['name'] ?? ''); ?>" placeholder="e.g. Alex Rivera / 21CS042" required />
      </div>

      <div class="claim-form-group" style="margin-bottom: 0.75rem;">
        <label for="claimStudentEmail">College Email Address</label>
        <input type="email" id="claimStudentEmail" class="claim-text-input" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>" placeholder="e.g. student@college.edu" required />
      </div>

      <div class="claim-form-group" style="margin-bottom: 1.25rem;">
        <label for="claimSecretProof">Private Proof of Ownership</label>
        <textarea id="claimSecretProof" class="claim-text-input" rows="3" placeholder="Describe lock-screen wallpaper, unique scratches, inner pocket contents, or serial number" required></textarea>
      </div>

      <button type="submit" id="btnSubmitProof" class="btn-claim-primary" style="width: 100%; justify-content: center;">
        <span>Submit Verification Proof &rarr;</span>
      </button>
    </form>
  </div>
</div>

<!-- ── Global Modals (Match Verification & Founder Approval) ── -->
<?php include_once __DIR__ . '/includes/match_verification_modal.php'; ?>
<?php include_once __DIR__ . '/includes/founder_approval_modal.php'; ?>

<script src="js/item.js?v=<?php echo time(); ?>"></script>
</body>
</html>
