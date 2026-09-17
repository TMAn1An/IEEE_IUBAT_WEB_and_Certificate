<?php

namespace App\Support\Site;

/**
 * The site's small inline-SVG icon sprite. Direct port of the svg() function
 * from the original reference/legacy-site/includes/components.php — same
 * icon set, same markup, so assets/css/style.css needs no changes.
 */
class Icons
{
    /**
     * @var array<string, array{0: bool, 1: int|float, 2: string, 3: string, 4: string}>
     *                                                                                   [fill, strokeWidth, linecap, linejoin, body]
     */
    private static array $icons = [
        'arrow' => [false, 2.4, 'round', '', '<path d="M5 12h14M13 6l6 6-6 6"/>'],
        'calendar' => [false, 2, 'round', '', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        'pin' => [false, 2, 'round', '', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
        'mail' => [false, 2, 'round', '', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>'],
        'phone' => [false, 2, 'round', '', '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'facebook' => [true, 0, '', '', '<path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/>'],
        'linkedin' => [true, 0, '', '', '<path d="M4.98 3.5A2.5 2.5 0 1 1 0 3.5a2.5 2.5 0 0 1 4.98 0zM.5 8.5h4V24h-4zM8 8.5h3.8v2.1h.06c.53-1 1.83-2.1 3.77-2.1 4.03 0 4.77 2.65 4.77 6.1V24h-4v-6.6c0-1.57-.03-3.6-2.2-3.6-2.2 0-2.53 1.72-2.53 3.5V24H8z"/>'],
        'heart' => [false, 2, 'round', 'round', '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.6 1.1-1a5.5 5.5 0 0 0 0-7.8z"/>'],
        'tech' => [false, 2, 'round', 'round', '<rect x="6" y="6" width="12" height="12" rx="1"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>'],
        'book' => [false, 2, 'round', 'round', '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
        'network' => [false, 2, 'round', 'round', '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>'],
        'user' => [false, 2, 'round', '', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
        'users' => [false, 2, 'round', 'round', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/>'],
        'ticket' => [false, 2, 'round', 'round', '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'],
        'trophy' => [false, 2, 'round', 'round', '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>'],
        'globe' => [false, 2, 'round', 'round', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/>'],
        'monitor' => [false, 2, 'round', 'round', '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
        'info' => [false, 2, 'round', '', '<circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>'],
        'bell' => [false, 2, 'round', '', '<path d="M10.3 3.3a2 2 0 0 1 3.4 0l8 13.4A2 2 0 0 1 20 20H4a2 2 0 0 1-1.7-3.3z"/><path d="M12 9v4M12 17h.01"/>'],
        'caret' => [false, 3, 'round', '', '<path d="m6 9 6 6 6-6"/>'],
        'zoom' => [false, 2, 'round', '', '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'],
        'star' => [true, 0, '', '', '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>'],
    ];

    public static function render(string $name, string $extraAttrs = ''): string
    {
        if (! isset(self::$icons[$name])) {
            return '<!-- missing icon: '.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').' -->';
        }

        [$fill, $stroke, $linecap, $linejoin, $body] = self::$icons[$name];
        $attrs = 'viewBox="0 0 24 24" '.($fill
            ? 'fill="currentColor" '
            : 'fill="none" stroke="currentColor" stroke-width="'.$stroke.'" stroke-linecap="'.$linecap.'"'.($linejoin ? ' stroke-linejoin="'.$linejoin.'"' : '').' '
        ).'aria-hidden="true"'.($extraAttrs ? ' '.$extraAttrs : '');

        return '<svg '.$attrs.'>'.$body.'</svg>';
    }
}
