<?php
function ic($n, $c = '')
{
  static $p = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-7h6v7"/>',
    'jobs' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 13h18"/>',
    'cal' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>',
    'cap' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'bell' => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
    'back' => '<path d="M19 12H5M11 5l-7 7 7 7"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'pin' => '<path d="M12 21s7-6 7-12a7 7 0 0 0-14 0c0 6 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
    'check' => '<path d="M5 12l5 5 9-10"/>',
    'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
    'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
    'book' => '<path d="M12 6c-2-2-6-2-9-1v14c3-1 7-1 9 1 2-2 6-2 9-1V5c-3-1-7-1-9 1zM12 6v14"/>',
    'up' => '<path d="M12 16V4M6 10l6-6 6 6M4 20h16"/>',
    'bank' => '<path d="M3 9l9-6 9 6zM5 11v7M10 11v7M14 11v7M19 11v7M3 21h18"/>',
    'doc' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-5-5"/>',
    'star' => '<path d="M12 3l2.8 5.8 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.7l6.2-.9z"/>',
    'heart' => '<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.5A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
    'sliders' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
    'store' => '<path d="M4 9l1-5h14l1 5M5 9v11h14V9M3 9c0 2.5 3 2.5 4.5 0 1.5 2.5 3 2.5 4.5 0 1.500 2.5 3 2.5 4.5 0 1.500 2.5 4.500 2.500 4.500 0"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/>',
    'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    'trend' => '<path d="M3 17l6-6 4 4 8-8M15 7h6v6"/>',
    'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
    'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>',
  ];
  return '<svg class="ic ' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
}

// Show/hide password button. Both icons are in the markup; CSS shows the right one from aria-pressed
// (eye = "click to show", eye-off = "click to hide"), and assets/js/app.js flips the state.
function pw_toggle()
{
  return '<button type="button" class="pw-toggle" data-pw aria-label="Show password" aria-pressed="false">'
    . ic('eye', 'ic-eye') . ic('eye-off', 'ic-off') . '</button>';
}
