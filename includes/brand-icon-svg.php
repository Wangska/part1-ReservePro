<?php
/**
 * ReservePro brand icon - now using uploaded image.
 * Usage: set $brand_icon_class = 'logo-icon' for auth pages, or leave unset for 'brand-icon'.
 */
$brand_icon_class = isset($brand_icon_class) ? $brand_icon_class : 'brand-icon';

// Absolute path from web root so it works on root, /host, /admin, etc.
$logo_src = '/part1-ReservePro/background%20image/asd.webp';
?>
<img src="<?php echo htmlspecialchars($logo_src); ?>"
     class="<?php echo htmlspecialchars($brand_icon_class); ?>"
     alt="ReservePro logo">
