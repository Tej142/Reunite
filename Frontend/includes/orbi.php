<?php
/**
 * Orbi Mascot Helper for Reunite
 * Single source of truth is Frontend/components/orbi.svg.php
 */

/**
 * Output Orbi SVG with specified state, size, and accessible label.
 * Caches raw SVG template so it is read from disk only once per request.
 *
 * @param string $state 'idle'|'loading'|'thinking'|'happy'|'notfound'
 * @param int $size Rendered width in pixels (default 120)
 * @param string|null $label Accessible label. If provided, sets role="img" and aria-label. Otherwise aria-hidden="true".
 * @return string The inline SVG string
 */
function orbi($state = 'idle', $size = 120, $label = null) {
    static $cachedSvg = null;

    if ($cachedSvg === null) {
        $svgPath = __DIR__ . '/../components/orbi.svg.php';
        if (!file_exists($svgPath)) {
            $svgPath = dirname(__DIR__) . '/components/orbi.svg.php';
        }
        if (file_exists($svgPath)) {
            $cachedSvg = trim(file_get_contents($svgPath));
        } else {
            $cachedSvg = '';
        }
    }

    if (empty($cachedSvg)) {
        return '';
    }

    $validStates = ['idle', 'loading', 'thinking', 'happy', 'notfound'];
    $state = in_array(strtolower((string)$state), $validStates, true) ? strtolower((string)$state) : 'idle';
    $size = is_numeric($size) ? max(16, (int)$size) : 120;

    $svg = $cachedSvg;

    // Update data-state attribute
    $svg = preg_replace('/data-state="[^"]*"/', 'data-state="' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '"', $svg, 1);

    // Update width and inject dynamic --orbi-size style property
    $svg = preg_replace(
        '/width="\d+"/',
        'width="' . $size . '" style="--orbi-size: ' . $size . 'px;"',
        $svg,
        1
    );

    // Update Accessibility attributes
    if (!empty($label)) {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        // Replace aria-hidden with aria-label + role="img"
        if (str_contains($svg, 'aria-hidden="true"')) {
            $svg = str_replace('aria-hidden="true"', 'role="img" aria-label="' . $escapedLabel . '"', $svg);
        } else {
            $svg = preg_replace('/<svg /', '<svg role="img" aria-label="' . $escapedLabel . '" ', $svg, 1);
        }
    } else {
        // Decorative by default
        if (str_contains($svg, 'role="img"')) {
            $svg = preg_replace('/\s*role="img"\s*/', ' ', $svg);
            $svg = preg_replace('/\s*aria-label="[^"]*"\s*/', ' ', $svg);
        }
        if (!str_contains($svg, 'aria-hidden="true"')) {
            $svg = preg_replace('/<svg /', '<svg aria-hidden="true" ', $svg, 1);
        }
    }

    return $svg;
}
