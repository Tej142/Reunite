<?php
/**
 * Remote Live Diagnostic Test for Matching Engine v2 on reunite.site.je
 */
require_once __DIR__ . '/../Backend/config/config.php';
require_once __DIR__ . '/../Backend/functions.php';
require_once __DIR__ . '/../Backend/matching_v2.php';

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if ($token !== 'admin@reunite2024') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$results = [];

// TEST 1: Dell vs Lenovo Evaluation on Live
$dellAttrs = [
    'category_stem' => 'laptop',
    'title' => 'Electronics / Laptop',
    'brand' => 'Dell',
    'model' => 'latitude 5320',
    'color' => ['canonical' => 'Dark Grey', 'family' => 'Grey', 'is_multi' => true],
    'has_skin_or_stickers' => true,
    'unique_identifier' => '',
    'location' => 'cme branch 3rd year room',
    'date' => '2026-10-19',
    'user_id' => 5
];
$lenovoAttrs = [
    'category_stem' => 'laptop',
    'title' => 'Electronics / Laptop',
    'brand' => 'Lenovo',
    'model' => 'unknown',
    'color' => ['canonical' => 'unknown', 'family' => 'unknown', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'lab',
    'date' => '2026-10-19',
    'user_id' => 11
];

$dellVsLenovo = evaluateMatchV2($dellAttrs, $lenovoAttrs, 98.0);
$results['dell_vs_lenovo'] = [
    'veto' => $dellVsLenovo['veto_triggered']['rule'] ?? null,
    'final_score' => $dellVsLenovo['final_score'],
    'confidence_tier' => $dellVsLenovo['confidence_tier'],
    'should_notify' => $dellVsLenovo['should_notify'],
    'should_email' => $dellVsLenovo['should_email'],
    'pass' => ($dellVsLenovo['final_score'] === 0.0 && $dellVsLenovo['confidence_tier'] === 'DISCARD' && !$dellVsLenovo['should_notify'])
];

// TEST 2: Dell vs Dell Evaluation on Live
$dellFoundAttrs = $dellAttrs;
$dellFoundAttrs['user_id'] = 11;
$dellFoundAttrs['model'] = 'latitude';
$dellVsDell = evaluateMatchV2($dellAttrs, $dellFoundAttrs, 80.0);
$results['dell_vs_dell'] = [
    'veto' => $dellVsDell['veto_triggered']['rule'] ?? null,
    'final_score' => $dellVsDell['final_score'],
    'confidence_tier' => $dellVsDell['confidence_tier'],
    'should_notify' => $dellVsDell['should_notify'],
    'should_email' => $dellVsDell['should_email'],
    'pass' => ($dellVsDell['final_score'] >= 65.0 && $dellVsDell['confidence_tier'] === 'STRONG' && $dellVsDell['should_notify'])
];

// TEST 3: Dell Red vs Dell White (Strict Color Veto)
$dellRed = $dellAttrs;
$dellRed['user_id'] = 1;
$dellRed['color'] = ['canonical' => 'Red', 'family' => 'Red', 'is_multi' => false];
$dellRed['has_skin_or_stickers'] = false;

$dellWhite = $dellAttrs;
$dellWhite['user_id'] = 2;
$dellWhite['color'] = ['canonical' => 'White', 'family' => 'White', 'is_multi' => false];
$dellWhite['has_skin_or_stickers'] = false;

$redVsWhite = evaluateMatchV2($dellRed, $dellWhite, 75.0);
$results['red_vs_white'] = [
    'veto' => $redVsWhite['veto_triggered']['rule'] ?? null,
    'final_score' => $redVsWhite['final_score'],
    'confidence_tier' => $redVsWhite['confidence_tier'],
    'should_notify' => $redVsWhite['should_notify'],
    'pass' => ($redVsWhite['veto_triggered']['rule'] === 'VETO_COLOR_CONTRADICTION' && $redVsWhite['final_score'] === 0.0)
];

// TEST 4: Database State Audit
global $conn;
$m18 = $conn->query("SELECT * FROM matches WHERE match_id = 18")->fetch_assoc();
$results['match_18_audit'] = [
    'match_id' => $m18['match_id'] ?? null,
    'score' => $m18['match_percentage'] ?? null,
    'tier' => $m18['confidence_tier'] ?? null,
    'status' => $m18['status'] ?? null,
    'pass' => ($m18 && $m18['confidence_tier'] === 'DISCARD' && $m18['status'] === 'rejected')
];

echo json_encode($results, JSON_PRETTY_PRINT);
