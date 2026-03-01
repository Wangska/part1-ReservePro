<?php
/**
 * ReservePro brand icon - fancy key-in-badge logo.
 * Usage: set $brand_icon_class = 'logo-icon' for auth pages, or leave unset for 'brand-icon'.
 */
$brand_icon_class = isset($brand_icon_class) ? $brand_icon_class : 'brand-icon';
?>
<svg class="<?php echo htmlspecialchars($brand_icon_class); ?>" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <defs>
    <linearGradient id="rpLogoBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#E6C89C"/>
      <stop offset="50%" stop-color="#D4A574"/>
      <stop offset="100%" stop-color="#8B6F47"/>
    </linearGradient>
    <filter id="rpLogoShadow" x="-30%" y="-30%" width="160%" height="160%">
      <feDropShadow dx="0" dy="2" stdDeviation="1.2" flood-color="#000" flood-opacity="0.15"/>
    </filter>
  </defs>
  <circle cx="16" cy="16" r="14" fill="url(#rpLogoBg)" filter="url(#rpLogoShadow)"/>
  <g fill="#FFFFFF">
    <!-- Key bow -->
    <circle cx="16" cy="11" r="4"/>
    <circle cx="16" cy="11" r="1.5" fill="url(#rpLogoBg)"/>
    <!-- Key shaft & tooth -->
    <path d="M14 15h4v9h-4V15zm4 6h4v2.5h-4V21z"/>
  </g>
</svg>
