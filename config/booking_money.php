<?php
/**
 * Pricing model: guest pays subtotal (nights × rate) + 10% service fee.
 * total_price stored on bookings = subtotal × 1.1
 * Platform commission (service fee) = total_price / 11
 * Host share (subtotal) = total_price − commission
 */
function reservepro_platform_commission_from_total(float $totalPrice): float
{
    return round($totalPrice / 11, 2);
}

function reservepro_host_share_from_total(float $totalPrice): float
{
    return round($totalPrice - ($totalPrice / 11), 2);
}
