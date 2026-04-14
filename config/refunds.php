<?php
/**
 * Refund policy + calculations for ReservePro.
 *
 * Notes:
 * - bookings "created_at" is stored as bookings.booking_date in this codebase.
 * - check_in/check_out are DATE (not DATETIME). For "within 24 hours after check-in",
 *   we treat check-in time as 00:00 local server time.
 */

if (!defined('RESERVEPRO_FREE_CANCEL_HOURS')) {
    define('RESERVEPRO_FREE_CANCEL_HOURS', 24);
}

/**
 * @return array{
 *   percent:int,
 *   amount:float,
 *   warning:string,
 *   rule:string
 * }
 */
function reservepro_refund_preview_cancellation(
    string $policy,
    string $bookingDateTime,
    string $checkInDate,
    float $totalAmount,
    ?DateTimeImmutable $now = null
): array {
    $now = $now ?: new DateTimeImmutable('now');

    try {
        $bookedAt = new DateTimeImmutable($bookingDateTime);
    } catch (Exception $e) {
        $bookedAt = $now;
    }

    try {
        $checkIn = new DateTimeImmutable($checkInDate . ' 00:00:00');
    } catch (Exception $e) {
        $checkIn = $now;
    }

    $hoursSinceBooking = (int) floor(max(0, ($now->getTimestamp() - $bookedAt->getTimestamp()) / 3600));
    $daysBeforeCheckin = (int) floor(($checkIn->getTimestamp() - $now->getTimestamp()) / 86400);

    $policy = strtolower(trim($policy));
    $percent = 0;
    $rule = '';

    if ($policy === 'flexible') {
        // Full refund if within free-cancel window OR at least 1 day before check-in.
        if ($hoursSinceBooking <= (int) RESERVEPRO_FREE_CANCEL_HOURS || $daysBeforeCheckin >= 1) {
            $percent = 100;
            $rule = 'flexible_full';
        } else {
            $percent = 50;
            $rule = 'flexible_late_50';
        }
    } elseif ($policy === 'moderate') {
        // Full refund if within free-cancel window; otherwise tiered by timing.
        // This matches the expected UX: "book today, cancel today" => 100%.
        if ($hoursSinceBooking <= (int) RESERVEPRO_FREE_CANCEL_HOURS) {
            $percent = 100;
            $rule = 'moderate_free_cancel_full';
        } elseif ($daysBeforeCheckin >= 7) {
            $percent = 70;
            $rule = 'moderate_early_70';
        } elseif ($daysBeforeCheckin >= 3) {
            $percent = 50;
            $rule = 'moderate_mid_50';
        } elseif ($daysBeforeCheckin >= 1) {
            $percent = 20;
            $rule = 'moderate_late_20';
        } else {
            $percent = 0;
            $rule = 'moderate_last_minute_0';
        }
    } else { // strict (default)
        // Strict: no automatic refund; support/admin can override.
        $percent = 0;
        $rule = 'strict_support_only';
    }

    $amount = round(max(0, $totalAmount) * ($percent / 100), 2);

    $warning = '';
    if ($percent >= 100) {
        $warning = "If you cancel now, you will receive a FULL refund.";
    } elseif ($percent > 0) {
        $warning = "If you cancel now, you will receive a {$percent}% refund.";
    } else {
        $warning = "If you cancel now, you may not be eligible for a refund under this policy.";
    }

    if ($policy === 'strict') {
        $warning = "Strict policy: refunds are not automatic. Support/admin review is required for any refund.";
    }

    return [
        'percent' => (int) $percent,
        'amount' => (float) $amount,
        'warning' => $warning,
        'rule' => $rule,
    ];
}

/**
 * @return array{eligible:bool, deadline:string, rule:string}
 */
function reservepro_issue_eligibility(string $checkInDate, ?DateTimeImmutable $now = null): array
{
    $now = $now ?: new DateTimeImmutable('now');
    try {
        $checkIn = new DateTimeImmutable($checkInDate . ' 00:00:00');
    } catch (Exception $e) {
        return ['eligible' => false, 'deadline' => '', 'rule' => 'bad_checkin_date'];
    }

    $deadline = $checkIn->add(new DateInterval('P1D')); // +24h (interpreted from date-only)
    $eligible = $now <= $deadline;
    return [
        'eligible' => $eligible,
        'deadline' => $deadline->format('Y-m-d H:i:s'),
        'rule' => 'within_24h_of_checkin',
    ];
}

/**
 * @return array{percent:int, rule:string}
 */
function reservepro_issue_refund_percent(string $issueType): array
{
    $t = strtolower(trim($issueType));
    $map = [
        'safety_issue' => 100,
        'wrong_listing' => 70,
        'dirty_room' => 50,
        'missing_amenities' => 50,
        'host_no_show' => 100,
        'other' => 30,
    ];
    $pct = $map[$t] ?? 30;
    return ['percent' => (int) $pct, 'rule' => 'issue_type_map'];
}

