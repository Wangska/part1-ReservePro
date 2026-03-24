<?php
/**
 * ReservePro brand icon - now using uploaded image.
 * Usage: set $brand_icon_class = 'logo-icon' for auth pages, or leave unset for 'brand-icon'.
 */
$brand_icon_class = isset($brand_icon_class) ? $brand_icon_class : 'brand-icon';

// Absolute path from web root so it works on root, /host, /admin, etc.
$logo_src = '/part1-ReservePro/background%20image/asd.webp';

// Styles: logo-icon (auth) gets full style; brand-icon gets border
if ($brand_icon_class === 'logo-icon') {
    $logo_style = 'width:48px;height:48px;object-fit:contain;border:2px solid rgba(212,165,116,0.9);border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.2);box-sizing:border-box;';
} else {
    $logo_style = 'border:2px solid rgba(212,165,116,0.6);border-radius:10px;box-sizing:border-box;';
}
?>
<img src="<?php echo htmlspecialchars($logo_src); ?>"
     class="<?php echo htmlspecialchars($brand_icon_class); ?>"
     style="<?php echo $logo_style; ?>"
     alt="ReservePro logo">
