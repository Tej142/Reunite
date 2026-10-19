<?php
/**
 * Reunite Matching Engine v2
 * Structured Attribute-Aware Verification, Hard Vetoes, Penalties, Boosts & Confidence Tiers
 */

if (!defined('REUNITE_MATCHING_V2_LOADED')) {
    define('REUNITE_MATCHING_V2_LOADED', true);
}

/**
 * Loads and caches matching configuration rules
 */
function getMatchingRulesV2() {
    static $rules = null;
    if ($rules !== null) return $rules;

    $configPath = __DIR__ . '/config/matching_rules.json';
    if (file_exists($configPath)) {
        $json = file_get_contents($configPath);
        $rules = json_decode($json, true);
    }

    if (!is_array($rules)) {
        // Safe defaults if JSON fails to load
        $rules = [
            'engine_version' => '2.0.0',
            'thresholds' => [
                'notification_threshold' => 65.0,
                'email_threshold' => 90.0,
                'min_possible_score' => 50.0,
                'strong_tier_threshold' => 65.0
            ],
            'temporal' => ['max_prior_tolerance_hours' => 24, 'stale_time_days' => 45],
            'brands' => [],
            'color_families' => [],
            'category_incompatibilities' => []
        ];
    }
    return $rules;
}

/**
 * Extracts canonical category stem (e.g. laptop, phone, mouse, wallet, bag, keys, watch, bottle)
 */
function extractCategoryStem($categoryText, $titleText = '') {
    $combined = strtolower(trim("$categoryText $titleText"));
    $stems = [
        'laptop' => ['laptop', 'notebook', 'macbook', 'chromebook', 'thinkpad', 'latitude'],
        'smartphone' => ['phone', 'smartphone', 'iphone', 'android', 'mobile', 'galaxy s', 'pixel'],
        'tablet' => ['tablet', 'ipad', 'tab'],
        'mouse' => ['mouse', 'pointer'],
        'keyboard' => ['keyboard', 'keypad'],
        'earbuds' => ['earbud', 'earbuds', 'airpod', 'airpods', 'headphone', 'headphones', 'earphone', 'earphones'],
        'smartwatch' => ['smartwatch', 'apple watch', 'galaxy watch', 'smart band', 'fitness band'],
        'watch' => ['watch', 'wristwatch', 'chronograph', 'analog watch'],
        'wallet' => ['wallet', 'purse', 'billfold', 'bifold', 'cardholder', 'card holder', 'money clip'],
        'bag' => ['backpack', 'bag', 'duffel', 'tote', 'handbag', 'rucksack', 'satchel'],
        'keys' => ['key', 'keys', 'keychain', 'fob', 'keyring'],
        'id_card' => ['id card', 'college id', 'student id', 'aadhaar', 'license', 'pan card', 'debit card', 'credit card'],
        'bottle' => ['bottle', 'flask', 'tumbler', 'sipper', 'thermosteel', 'water bottle'],
        'glasses' => ['glasses', 'spectacles', 'sunglasses', 'shades', 'eyewear'],
        'umbrella' => ['umbrella', 'parasol'],
        'book' => ['book', 'notebook', 'textbook', 'diary', 'journal'],
        'charger' => ['charger', 'adapter', 'power bank', 'cable']
    ];

    foreach ($stems as $stem => $keywords) {
        foreach ($keywords as $kw) {
            if (stripos($combined, $kw) !== false) {
                return $stem;
            }
        }
    }

    return 'general';
}

/**
 * Normalizes brand string against brand dictionaries
 */
function normalizeBrandV2($text, $categoryStem = '') {
    $rules = getMatchingRulesV2();
    $brands = $rules['brands'] ?? [];
    $lower = strtolower(trim((string)$text));

    if (empty($lower) || in_array($lower, ['unknown', 'not sure', "i don't know", 'dont know', 'none', 'n/a', 'other', 'unbranded', 'generic', 'generic / unbranded'])) {
        return ['canonical' => 'unknown', 'model_hint' => '', 'confidence' => 0.0];
    }

    foreach ($brands as $bKey => $bData) {
        $canonical = $bData['canonical'];
        $aliases = $bData['aliases'] ?? [$bKey];

        // Check aliases
        foreach ($aliases as $alias) {
            $aliasPattern = '/\b' . preg_quote($alias, '/') . '\b/i';
            if (preg_match($aliasPattern, $lower)) {
                // Check sub-brands for model hint
                $modelHint = '';
                if (!empty($bData['sub_brands'])) {
                    foreach ($bData['sub_brands'] as $subKey => $subName) {
                        if (stripos($lower, $subKey) !== false) {
                            $modelHint = $subName;
                            break;
                        }
                    }
                }
                return ['canonical' => $canonical, 'model_hint' => $modelHint, 'confidence' => 1.0];
            }
        }
    }

    // Capitalize as a raw un-indexed brand
    return ['canonical' => ucwords($lower), 'model_hint' => '', 'confidence' => 0.7];
}

/**
 * Normalizes color string into canonical color families
 */
function normalizeColorV2($text) {
    $rules = getMatchingRulesV2();
    $families = $rules['color_families'] ?? [];
    $lower = strtolower(trim((string)$text));

    if (empty($lower) || in_array($lower, ['unknown', 'not sure', "i don't know", 'dont know', 'neutral', 'standard', 'none', 'n/a'])) {
        return ['canonical' => 'unknown', 'family' => 'unknown', 'is_multi' => false, 'confidence' => 0.0];
    }

    $isMulti = (
        strpos($lower, 'multi') !== false ||
        strpos($lower, 'skin') !== false ||
        strpos($lower, 'sticker') !== false ||
        strpos($lower, 'pattern') !== false ||
        strpos($lower, 'patchwork') !== false ||
        strpos($lower, ',') !== false ||
        strpos($lower, '/') !== false ||
        strpos($lower, ' and ') !== false
    );

    // Check specific multi-word families first
    $multiWordColors = ['space grey', 'dark grey', 'dark blue', 'light blue', 'multi-color'];
    foreach ($multiWordColors as $mwc) {
        if (strpos($lower, $mwc) !== false) {
            $fam = $families[$mwc]['canonical'] ?? ucfirst($mwc);
            return ['canonical' => $fam, 'family' => $fam, 'is_multi' => $isMulti || ($fam === 'Multi-Color'), 'confidence' => 1.0];
        }
    }

    foreach ($families as $cKey => $cData) {
        $pattern = '/\b' . preg_quote($cKey, '/') . '\b/i';
        if (preg_match($pattern, $lower)) {
            $fam = $cData['canonical'];
            return ['canonical' => $fam, 'family' => $fam, 'is_multi' => $isMulti || ($fam === 'Multi-Color'), 'confidence' => 1.0];
        }
    }

    return ['canonical' => ucfirst($lower), 'family' => ucfirst($lower), 'is_multi' => $isMulti, 'confidence' => 0.6];
}

/**
 * Evaluates color compatibility between two reports
 */
function evaluateColorCompatibilityV2($colorA, $colorB, $hasSkinOrPattern = false) {
    if ($colorA['canonical'] === 'unknown' || $colorB['canonical'] === 'unknown') {
        return ['status' => 'UNKNOWN', 'delta' => 0.0, 'reason' => 'One or both colors unspecified'];
    }

    if ($colorA['is_multi'] || $colorB['is_multi'] || $hasSkinOrPattern) {
        return ['status' => 'COMPATIBLE_SKIN', 'delta' => 0.0, 'reason' => 'Decorative skin or multi-color pattern detected'];
    }

    $famA = $colorA['family'];
    $famB = $colorB['family'];

    if ($famA === $famB) {
        return ['status' => 'EXACT_MATCH', 'delta' => 10.0, 'reason' => "Matching color family ($famA)"];
    }

    // Check neighbors
    $rules = getMatchingRulesV2();
    $families = $rules['color_families'] ?? [];
    $neighborsA = $families[strtolower($famA)]['neighbors'] ?? [];

    if (in_array($famB, $neighborsA) || in_array('*', $neighborsA)) {
        return ['status' => 'NEIGHBOR_COMPATIBLE', 'delta' => 0.0, 'reason' => "Compatible color lighting variants ($famA ~ $famB)"];
    }

    // Direct contradiction (e.g. Red vs White, Black vs Silver)
    return ['status' => 'CONTRADICTION', 'delta' => -35.0, 'reason' => "Contradictory color families ($famA vs $famB)"];
}

/**
 * Extracts and normalizes attributes from report row and digital_dna JSON
 */
function extractNormalizedAttributesV2($reportRow, $dnaRow = null) {
    $title = $reportRow['title'] ?? '';
    $category = $reportRow['category'] ?? '';
    $description = $reportRow['description'] ?? '';
    $location = $reportRow['location'] ?? ($reportRow['found_location'] ?? ($reportRow['lost_location'] ?? ''));
    $date = $reportRow['lost_date'] ?? ($reportRow['found_date'] ?? ($reportRow['date_lost'] ?? ($reportRow['date_found'] ?? '')));

    $rawDnaAttrs = [];
    if (!empty($dnaRow['encrypted_dna'])) {
        $decoded = json_decode($dnaRow['encrypted_dna'], true);
        if (is_array($decoded) && isset($decoded['attributes'])) {
            $rawDnaAttrs = $decoded['attributes'];
        }
    }

    // Fallback: extract key-values from description if appended in parentheses: (Brand: Dell, ...)
    if (empty($rawDnaAttrs) && preg_match('/\((.*?)\)$/s', $description, $m)) {
        $parts = explode(',', $m[1]);
        foreach ($parts as $p) {
            $kv = explode(':', $p, 2);
            if (count($kv) === 2) {
                $rawDnaAttrs[trim($kv[0])] = trim($kv[1]);
            }
        }
    }

    $categoryStem = extractCategoryStem($category, $title);

    // Extract Brand
    $rawBrand = $rawDnaAttrs['Brand'] ?? ($reportRow['brand'] ?? '');
    if (empty($rawBrand)) {
        $bNorm = normalizeBrandV2("$title $description", $categoryStem);
    } else {
        $bNorm = normalizeBrandV2($rawBrand, $categoryStem);
    }

    // Extract Model
    $rawModel = $rawDnaAttrs['Model'] ?? ($reportRow['model'] ?? '');
    $modelNorm = strtolower(trim((string)$rawModel));
    if (in_array($modelNorm, ['standard edition', 'unknown', 'unidentifiable', 'neutral', 'none', 'n/a', ''])) {
        $modelNorm = $bNorm['model_hint'] ? strtolower($bNorm['model_hint']) : 'unknown';
    }

    // Extract Color
    $rawColor = $rawDnaAttrs['Color'] ?? ($reportRow['color'] ?? '');
    if (empty($rawColor)) {
        $cNorm = normalizeColorV2("$title $description");
    } else {
        $cNorm = normalizeColorV2($rawColor);
    }

    // Detect skin, stickers, or distinctive marks
    $marksText = strtolower(($rawDnaAttrs['Distinguishing Marks'] ?? '') . ' ' . ($rawDnaAttrs['Unique Mark'] ?? '') . ' ' . $description);
    $hasSkinOrStickers = (
        strpos($marksText, 'skin') !== false ||
        strpos($marksText, 'sticker') !== false ||
        strpos($marksText, 'scratch') !== false ||
        strpos($marksText, 'nautical') !== false ||
        strpos($marksText, 'decal') !== false ||
        strpos($marksText, 'engraving') !== false ||
        strpos($marksText, 'pattern') !== false
    );

    // Private Unique Identifiers (Serial, IMEI, Card ID, Name on Card)
    $serial = trim($rawDnaAttrs['Serial Number'] ?? ($rawDnaAttrs['serial'] ?? ''));
    if (strtolower($serial) === 'none' || strtolower($serial) === 'unknown') $serial = '';

    return [
        'category_stem' => $categoryStem,
        'category_raw' => $category,
        'title' => $title,
        'brand' => $bNorm['canonical'],
        'brand_confidence' => $bNorm['confidence'],
        'model' => $modelNorm,
        'color' => $cNorm,
        'has_skin_or_stickers' => $hasSkinOrStickers,
        'marks_text' => $marksText,
        'unique_identifier' => $serial,
        'location' => strtolower(trim((string)$location)),
        'date' => $date ? date('Y-m-d', strtotime($date)) : null,
        'user_id' => (int)($reportRow['user_id'] ?? ($reportRow['finder_id'] ?? 0))
    ];
}

/**
 * Checks category family incompatibilities (e.g. laptop vs phone)
 */
function areCategoriesIncompatible($stemA, $stemB) {
    if ($stemA === 'general' || $stemB === 'general') return false;
    if ($stemA === $stemB) return false;

    // Both are electronics, but incompatible sub-devices
    $rules = getMatchingRulesV2();
    $incompatPairs = $rules['category_incompatibilities'] ?? [];

    foreach ($incompatPairs as $pair) {
        if (($stemA === $pair[0] && $stemB === $pair[1]) || ($stemA === $pair[1] && $stemB === $pair[0])) {
            return true;
        }
    }

    return false;
}

/**
 * Executes full Matching Engine v2 evaluation on a candidate pair
 */
function evaluateMatchV2($lostAttrs, $foundAttrs, $vectorSimilarity = null) {
    $rules = getMatchingRulesV2();
    $thresholds = $rules['thresholds'] ?? [
        'notification_threshold' => 65.0,
        'email_threshold' => 90.0,
        'min_possible_score' => 50.0,
        'strong_tier_threshold' => 75.0
    ];

    $ruleHits = [];
    $penalties = [];
    $boosts = [];
    $vetoTriggered = null;

    // ==========================================
    // 1. HARD VETO CHECKS
    // ==========================================

    // VETO 1: Self-Match (Same User)
    if ($lostAttrs['user_id'] > 0 && $foundAttrs['user_id'] > 0 && $lostAttrs['user_id'] === $foundAttrs['user_id']) {
        $vetoTriggered = [
            'rule' => 'VETO_SAME_USER',
            'reason' => 'User cannot match against their own submitted report.'
        ];
    }

    // VETO 2: Category Incompatibility (e.g. Laptop vs Smartphone / Mouse)
    if (!$vetoTriggered && areCategoriesIncompatible($lostAttrs['category_stem'], $foundAttrs['category_stem'])) {
        $vetoTriggered = [
            'rule' => 'VETO_CATEGORY_INCOMPATIBLE',
            'reason' => "Incompatible item categories ({$lostAttrs['category_stem']} vs {$foundAttrs['category_stem']})."
        ];
    }

    // VETO 3: Brand Mismatch (e.g. Dell vs Lenovo)
    $brandA = $lostAttrs['brand'];
    $brandB = $foundAttrs['brand'];
    $bothBrandsKnown = ($brandA !== 'unknown' && $brandB !== 'unknown');

    if (!$vetoTriggered && $bothBrandsKnown && (strtolower($brandA) !== strtolower($brandB))) {
        $vetoTriggered = [
            'rule' => 'VETO_BRAND_MISMATCH',
            'reason' => "Confirmed brand conflict ({$brandA} vs {$brandB})."
        ];
    }

    // VETO 4: Private Unique Identifier Mismatch
    $idA = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($lostAttrs['unique_identifier']));
    $idB = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($foundAttrs['unique_identifier']));
    if (!$vetoTriggered && !empty($idA) && !empty($idB) && $idA !== $idB) {
        $vetoTriggered = [
            'rule' => 'VETO_IDENTIFIER_MISMATCH',
            'reason' => 'Non-matching unique private identifiers (serial/IMEI/tag).'
        ];
    }

    // VETO 5: Temporal Impossibility (Found Date > 24 hours BEFORE Lost Date)
    $maxTolerance = ($rules['temporal']['max_prior_tolerance_hours'] ?? 24) * 3600;
    if (!$vetoTriggered && !empty($lostAttrs['date']) && !empty($foundAttrs['date'])) {
        $lostTs = strtotime($lostAttrs['date']);
        $foundTs = strtotime($foundAttrs['date']);
        if ($lostTs > 0 && $foundTs > 0 && ($lostTs - $foundTs) > $maxTolerance) {
            $daysDiff = round(($lostTs - $foundTs) / 86400, 1);
            $vetoTriggered = [
                'rule' => 'VETO_TEMPORAL_IMPOSSIBILITY',
                'reason' => "Found date is {$daysDiff} days prior to reported lost date."
            ];
        }
    }

    // VETO 6: Strict Color Veto for Solid Items (Strongest Color Verification)
    // If both colors are specified solid colors, and are directly contradictory with NO skin/pattern/stickers
    $colorEval = evaluateColorCompatibilityV2(
        $lostAttrs['color'],
        $foundAttrs['color'],
        $lostAttrs['has_skin_or_stickers'] || $foundAttrs['has_skin_or_stickers']
    );

    if (!$vetoTriggered && $lostAttrs['color']['canonical'] !== 'unknown' && $foundAttrs['color']['canonical'] !== 'unknown') {
        if ($colorEval['status'] === 'CONTRADICTION' && !$lostAttrs['has_skin_or_stickers'] && !$foundAttrs['has_skin_or_stickers']) {
            $brandContext = $bothBrandsKnown ? "Same brand ({$brandA}) but strictly" : "Strictly";
            $vetoTriggered = [
                'rule' => 'VETO_COLOR_CONTRADICTION',
                'reason' => "{$brandContext} incompatible solid colors ({$lostAttrs['color']['canonical']} vs {$foundAttrs['color']['canonical']})."
            ];
        }
    }

    // ==========================================
    // 2. SCORING & ADJUSTMENTS (If not vetoed)
    // ==========================================
    if ($vetoTriggered !== null) {
        $finalScore = 0.0;
        $tier = 'DISCARD';
        $ruleHits[] = $vetoTriggered;
        $whySummary = $vetoTriggered['reason'];
    } else {
        // Compute Base Similarity
        if ($vectorSimilarity !== null && is_numeric($vectorSimilarity)) {
            $baseScore = (float)$vectorSimilarity;
        } else {
            // Heuristic Base: Category + Title match
            $baseScore = 0.0;
            if ($lostAttrs['category_stem'] === $foundAttrs['category_stem'] && $lostAttrs['category_stem'] !== 'general') {
                $baseScore += 30.0;
            }
            if (strtolower(trim($lostAttrs['title'])) === strtolower(trim($foundAttrs['title']))) {
                $baseScore += 25.0;
            } else {
                similar_text(strtolower($lostAttrs['title']), strtolower($foundAttrs['title']), $sim);
                if ($sim >= 60) $baseScore += ($sim * 0.25);
            }
        }

        $adjustments = 0.0;

        // Boost 1: Brand Match
        if ($bothBrandsKnown && strtolower($brandA) === strtolower($brandB)) {
            $boosts[] = ['rule' => 'BOOST_BRAND_MATCH', 'delta' => 25.0, 'reason' => "Verified matching brand ({$brandA})"];
            $adjustments += 25.0;

            // Boost 2: Model / Series Match
            if ($lostAttrs['model'] !== 'unknown' && $foundAttrs['model'] !== 'unknown') {
                $modA = strtolower(trim($lostAttrs['model']));
                $modB = strtolower(trim($foundAttrs['model']));
                if ($modA === $modB) {
                    $boosts[] = ['rule' => 'BOOST_MODEL_MATCH', 'delta' => 20.0, 'reason' => "Matching model/series ({$lostAttrs['model']})"];
                    $adjustments += 20.0;
                } elseif (strpos($modA, $modB) !== false || strpos($modB, $modA) !== false) {
                    // Compatible sub-model or series match (e.g. Latitude 5320 and Latitude)
                    $boosts[] = ['rule' => 'BOOST_MODEL_SERIES_MATCH', 'delta' => 15.0, 'reason' => "Compatible model series ({$modA} ~ {$modB})"];
                    $adjustments += 15.0;
                } else {
                    // Penalty: Same brand, but contradictory models (e.g. Latitude vs XPS)
                    $penalties[] = ['rule' => 'PENALTY_MODEL_MISMATCH', 'delta' => -35.0, 'reason' => "Different models of {$brandA} ({$lostAttrs['model']} vs {$foundAttrs['model']})"];
                    $adjustments -= 35.0;
                }
            }
        }

        // Color Adjustment
        if ($colorEval['delta'] > 0) {
            $boosts[] = ['rule' => 'BOOST_COLOR_MATCH', 'delta' => $colorEval['delta'], 'reason' => $colorEval['reason']];
            $adjustments += $colorEval['delta'];
        } elseif ($colorEval['delta'] < 0) {
            $penalties[] = ['rule' => 'PENALTY_COLOR_CONFLICT', 'delta' => $colorEval['delta'], 'reason' => $colorEval['reason']];
            $adjustments += $colorEval['delta'];
        }

        // Boost 3: Distinctive Marks / Skin Token Overlap
        if ($lostAttrs['has_skin_or_stickers'] && $foundAttrs['has_skin_or_stickers']) {
            $boosts[] = ['rule' => 'BOOST_DISTINCTIVE_MARKS', 'delta' => 15.0, 'reason' => 'Both items report decorative skin/stickers/marks'];
            $adjustments += 15.0;
        }

        // Boost 4: Private Unique Identifier Verified Match (Near-Certain)
        if (!empty($idA) && !empty($idB) && $idA === $idB) {
            $boosts[] = ['rule' => 'BOOST_IDENTIFIER_MATCH', 'delta' => 45.0, 'reason' => 'Private unique serial/identifier verified'];
            $adjustments += 45.0;
        }

        // Location Proximity
        if (!empty($lostAttrs['location']) && !empty($foundAttrs['location'])) {
            if ($lostAttrs['location'] === $foundAttrs['location'] || stripos($lostAttrs['location'], $foundAttrs['location']) !== false || stripos($foundAttrs['location'], $lostAttrs['location']) !== false) {
                $boosts[] = ['rule' => 'BOOST_LOCATION_MATCH', 'delta' => 10.0, 'reason' => "Matching campus location ({$lostAttrs['location']})"];
                $adjustments += 10.0;
            } else {
                $penalties[] = ['rule' => 'PENALTY_LOCATION_DIVERGENCE', 'delta' => -15.0, 'reason' => 'Differing reported locations'];
                $adjustments -= 15.0;
            }
        }

        $rawScore = $baseScore + $adjustments;
        $finalScore = round(max(0.0, min(99.0, $rawScore)), 2);

        // ==========================================
        // 3. CONFIDENCE TIER ASSIGNMENT
        // ==========================================
        // Tier Capping:
        // For tech categories (laptop, phone, etc.), brand match (or unique identifier) is required to reach STRONG tier.
        // For non-tech items (bottles, keys, umbrellas, books, wallets), matching present details (color, location, title) allows STRONG tier.
        $techCategories = ['laptop', 'smartphone', 'tablet', 'smartwatch', 'earbuds'];
        $isTechCategory = in_array($lostAttrs['category_stem'], $techCategories);

        $brandConfirmed = $bothBrandsKnown && (strtolower($brandA) === strtolower($brandB));
        $identifierConfirmed = (!empty($idA) && !empty($idB) && $idA === $idB);

        $brandRequirementMet = $brandConfirmed || (!$isTechCategory && ($brandA === 'unknown' || $brandB === 'unknown')) || $identifierConfirmed;

        $hasFatalPenalty = false;
        foreach ($penalties as $p) {
            if ($p['delta'] <= -30.0) {
                $hasFatalPenalty = true;
                break;
            }
        }

        if ($finalScore >= $thresholds['strong_tier_threshold'] && $brandRequirementMet && !$hasFatalPenalty) {
            $tier = 'STRONG';
        } elseif ($finalScore >= $thresholds['min_possible_score']) {
            $tier = 'POSSIBLE';
        } else {
            $tier = 'DISCARD';
        }

        $ruleHits = array_merge($boosts, $penalties);

        // Construct Short Why Matched Summary
        $reasons = [];
        if ($bothBrandsKnown) $reasons[] = "Brand: {$brandA}";
        if ($colorEval['status'] === 'EXACT_MATCH') $reasons[] = "Color: {$lostAttrs['color']['canonical']}";
        if ($lostAttrs['has_skin_or_stickers'] && $foundAttrs['has_skin_or_stickers']) $reasons[] = "Distinctive decorative marks";
        if (empty($reasons)) $reasons[] = "Category: {$lostAttrs['category_stem']}";
        $whySummary = implode(', ', $reasons);
    }

    // Policy Decisions
    $shouldNotify = ($tier === 'STRONG' && $finalScore >= $thresholds['notification_threshold']);
    $shouldEmail = ($tier === 'STRONG' && $finalScore >= $thresholds['email_threshold']);

    return [
        'engine_version' => $rules['engine_version'] ?? '2.0.0',
        'final_score' => $finalScore,
        'confidence_tier' => $tier,
        'should_notify' => $shouldNotify,
        'should_email' => $shouldEmail,
        'veto_triggered' => $vetoTriggered,
        'why_matched_summary' => $whySummary,
        'match_reasons' => [
            'engine_version' => '2.0.0',
            'tier' => $tier,
            'final_score' => $finalScore,
            'should_notify' => $shouldNotify,
            'should_email' => $shouldEmail,
            'verified_attributes' => [
                'category' => ['lost' => $lostAttrs['category_stem'], 'found' => $foundAttrs['category_stem'], 'match' => ($lostAttrs['category_stem'] === $foundAttrs['category_stem'])],
                'brand' => ['lost' => $brandA, 'found' => $brandB, 'match' => ($brandA === $brandB && $brandA !== 'unknown')],
                'color' => ['lost' => $lostAttrs['color']['canonical'], 'found' => $foundAttrs['color']['canonical'], 'status' => $colorEval['status']]
            ],
            'rules_hit' => $ruleHits,
            'why_matched_summary' => $whySummary
        ]
    ];
}
