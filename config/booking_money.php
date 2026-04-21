<?php
/**
 * Money split model (based on stored bookings.total_price):
 * - Host share: 90% of total_price
 * - Admin/platform share: 9% of total_price
 *
 * Note: if your checkout still adds a 10% fee on top of subtotal, the remaining ~1%
 * is simply not attributed by these helpers (e.g. payment processing / rounding).
 */
function reservepro_platform_commission_from_total(float $totalPrice): float
{
    return round($totalPrice * 0.09, 2);
}

function reservepro_host_share_from_total(float $totalPrice): float
{
    return round($totalPrice * 0.90, 2);
}
