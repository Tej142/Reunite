<?php
/**
 * Unit Test Suite for Matching Engine v2
 */

require_once __DIR__ . '/../Backend/matching_v2.php';

echo "========================================\n";
echo "RUNNING MATCHING ENGINE V2 UNIT TESTS\n";
echo "========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTest($name, $condition, $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "✅ PASS: $name\n";
        $testsPassed++;
    } else {
        echo "❌ FAIL: $name\n";
        if ($details) echo "   Details: $details\n";
        $testsFailed++;
    }
}

// TEST 1: Dell vs Lenovo Hard Veto (The Regression Bug)
$dellLost = [
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
$lenovoFound = [
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

$res1 = evaluateMatchV2($dellLost, $lenovoFound, 98.0);
assertTest("Dell vs Lenovo VETO triggered", $res1['veto_triggered'] !== null && $res1['veto_triggered']['rule'] === 'VETO_BRAND_MISMATCH');
assertTest("Dell vs Lenovo Final Score is 0", $res1['final_score'] === 0.0);
assertTest("Dell vs Lenovo Confidence Tier is DISCARD", $res1['confidence_tier'] === 'DISCARD');
assertTest("Dell vs Lenovo ZERO notifications dispatched", $res1['should_notify'] === false);
assertTest("Dell vs Lenovo ZERO emails dispatched", $res1['should_email'] === false);

// TEST 2: Dell vs Dell Matching Pair
$dellFound = [
    'category_stem' => 'laptop',
    'title' => 'Electronics / Laptop',
    'brand' => 'Dell',
    'model' => 'latitude',
    'color' => ['canonical' => 'Dark Grey', 'family' => 'Grey', 'is_multi' => true],
    'has_skin_or_stickers' => true,
    'unique_identifier' => '',
    'location' => 'cme branch 3rd year room',
    'date' => '2026-10-19',
    'user_id' => 11
];
$res2 = evaluateMatchV2($dellLost, $dellFound, 80.0);
assertTest("Dell vs Dell NO veto", $res2['veto_triggered'] === null);
assertTest("Dell vs Dell Score >= 65%", $res2['final_score'] >= 65.0, "Score: {$res2['final_score']}");
assertTest("Dell vs Dell Confidence Tier is STRONG", $res2['confidence_tier'] === 'STRONG');
assertTest("Dell vs Dell Dispatches notification", $res2['should_notify'] === true);

// TEST 3: Same Brand, but Contradictory Solid Colors (Red vs White) without skin
$dellRed = [
    'category_stem' => 'laptop',
    'title' => 'Dell Inspiron Laptop',
    'brand' => 'Dell',
    'model' => 'inspiron',
    'color' => ['canonical' => 'Red', 'family' => 'Red', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'library',
    'date' => '2026-10-19',
    'user_id' => 1
];
$dellWhite = [
    'category_stem' => 'laptop',
    'title' => 'Dell Inspiron Laptop',
    'brand' => 'Dell',
    'model' => 'inspiron',
    'color' => ['canonical' => 'White', 'family' => 'White', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'library',
    'date' => '2026-10-19',
    'user_id' => 2
];
$res3 = evaluateMatchV2($dellRed, $dellWhite, 75.0);
assertTest("Dell Red vs Dell White VETO or score drop below notification threshold", 
    $res3['should_notify'] === false, 
    "Veto: " . json_encode($res3['veto_triggered']) . ", Score: {$res3['final_score']}");

// TEST 4: Category Incompatibility (Laptop vs Smartphone)
$iphoneFound = [
    'category_stem' => 'smartphone',
    'title' => 'Apple iPhone',
    'brand' => 'Apple',
    'model' => 'iphone 15',
    'color' => ['canonical' => 'Black', 'family' => 'Black', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'cme branch',
    'date' => '2026-10-19',
    'user_id' => 9
];
$res4 = evaluateMatchV2($dellLost, $iphoneFound, 70.0);
assertTest("Laptop vs Smartphone VETO", $res4['veto_triggered'] !== null && $res4['veto_triggered']['rule'] === 'VETO_CATEGORY_INCOMPATIBLE');

// TEST 5: Temporal Impossibility (Found 5 days before lost)
$dellFoundAncient = $dellFound;
$dellFoundAncient['date'] = '2026-10-10'; // 9 days before lost date 2026-10-19
$res5 = evaluateMatchV2($dellLost, $dellFoundAncient, 80.0);
assertTest("Found date earlier than lost date VETO", $res5['veto_triggered'] !== null && $res5['veto_triggered']['rule'] === 'VETO_TEMPORAL_IMPOSSIBILITY');

// TEST 6: Unknown Brand Capped at POSSIBLE
$laptopUnknown = [
    'category_stem' => 'laptop',
    'title' => 'Grey Laptop',
    'brand' => 'unknown',
    'model' => 'unknown',
    'color' => ['canonical' => 'Grey', 'family' => 'Grey', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'cme branch',
    'date' => '2026-10-19',
    'user_id' => 12
];
$res6 = evaluateMatchV2($dellLost, $laptopUnknown, 70.0);
assertTest("Unknown Brand capped at POSSIBLE tier", $res6['confidence_tier'] === 'POSSIBLE');
assertTest("Unknown Brand does NOT notify", $res6['should_notify'] === false);

// TEST 7: Unbranded Contradictory Colors VETO (Red Bottle vs Blue Bottle)
$bottleRed = [
    'category_stem' => 'bottle',
    'title' => 'Water Bottle',
    'brand' => 'unknown',
    'model' => 'unknown',
    'color' => ['canonical' => 'Red', 'family' => 'Red', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'canteen',
    'date' => '2026-10-19',
    'user_id' => 1
];
$bottleBlue = [
    'category_stem' => 'bottle',
    'title' => 'Water Bottle',
    'brand' => 'unknown',
    'model' => 'unknown',
    'color' => ['canonical' => 'Blue', 'family' => 'Blue', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'canteen',
    'date' => '2026-10-19',
    'user_id' => 2
];
$res7 = evaluateMatchV2($bottleRed, $bottleBlue, 80.0);
assertTest("Unbranded Red Bottle vs Blue Bottle VETO", $res7['veto_triggered'] !== null && $res7['veto_triggered']['rule'] === 'VETO_COLOR_CONTRADICTION');
assertTest("Unbranded Red Bottle vs Blue Bottle Discarded", $res7['confidence_tier'] === 'DISCARD');

// TEST 8: Non-tech item matching on present details (Blue bottle vs Blue bottle)
$bottleBlueFound = [
    'category_stem' => 'bottle',
    'title' => 'Water Bottle',
    'brand' => 'unknown',
    'model' => 'unknown',
    'color' => ['canonical' => 'Blue', 'family' => 'Blue', 'is_multi' => false],
    'has_skin_or_stickers' => false,
    'unique_identifier' => '',
    'location' => 'canteen',
    'date' => '2026-10-19',
    'user_id' => 3
];
$res8 = evaluateMatchV2($bottleBlue, $bottleBlueFound, 60.0);
assertTest("Matching Blue Bottle NO veto", $res8['veto_triggered'] === null);
assertTest("Matching Blue Bottle Score >= 65%", $res8['final_score'] >= 65.0);
assertTest("Matching Blue Bottle Reaches STRONG tier", $res8['confidence_tier'] === 'STRONG');
assertTest("Matching Blue Bottle Dispatches notification", $res8['should_notify'] === true);
assertTest("Matching Blue Bottle < 90% does NOT send email", $res8['should_email'] === false);

// TEST 9: Match >= 90% sends Brevo email
$dellLostHigh = $dellLost;
$dellFoundHigh = $dellFound;
$dellFoundHigh['unique_identifier'] = 'SN-DELL-998811';
$dellLostHigh['unique_identifier'] = 'SN-DELL-998811';
$res9 = evaluateMatchV2($dellLostHigh, $dellFoundHigh, 85.0);
assertTest("High confidence verified match >= 90%", $res9['final_score'] >= 90.0, "Score: {$res9['final_score']}");
assertTest("High confidence match dispatches email", $res9['should_email'] === true);

echo "\n========================================\n";
echo "SUMMARY: $testsPassed PASSED, $testsFailed FAILED\n";
echo "========================================\n";

if ($testsFailed > 0) exit(1);
