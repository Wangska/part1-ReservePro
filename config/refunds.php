<?php
/**
 * Refund policy + calculations for ReservePro.
 *
 * Cancellation (flexible + moderate): refund is based on hours since the
 * booking was placed (bookings.booking_date), not days before check-in.
 *
 * - 100% if cancel within 12 hours of booking.
 * - 70% if cancel after 12 hours through 24 hours after booking.
 * - 50% if cancel after 24 hours through 48 hours (2 days) after booking.
 * - 0% if cancel after more than 72 hours (3 days) after booking.
 * - 0% for cancellations between 48 and 72 hours (no tier specified).
 *
 * Strict: no automatic refund; support/admin can override.
 *
 * Issue-based refunds use reservepro_issue_* helpers below.
 */

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

    $hoursSinceBooking = (int) floor(max(0, ($now->getTimestamp() - $bookedAt->getTimestamp()) / 3600));

    $policy = strtolower(trim($policy));
    $percent = 0;
    $rule = '';

    if ($policy === 'strict') {
        $percent = 0;
        $rule = 'strict_support_only';
    } else {
        // flexible + moderate share the same schedule
        if ($hoursSinceBooking <= 12) {
            $percent = 100;
            $rule = 'cancel_within_12h_full';
        } elseif ($hoursSinceBooking <= 24) {
            $percent = 70;
            $rule = 'cancel_12h_to_24h_70';
        } elseif ($hoursSinceBooking <= 48) {
            $percent = 50;
            $rule = 'cancel_24h_to_48h_50';
        } elseif ($hoursSinceBooking <= 72) {
            $percent = 0;
            $rule = 'cancel_48h_to_72h_0';
        } else {
            $percent = 0;
            $rule = 'cancel_after_72h_0';
        }
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
