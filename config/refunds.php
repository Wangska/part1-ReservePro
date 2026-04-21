<?php
/**
 * Refund policy + calculations for ReservePro.
 *
 * Cancellation (flexible + moderate): refund is based on hours since the
 * booking was placed (bookings.booking_date), not days before check-in.
 *
 * - 99% if cancel within 6 hours of booking.
 * - 50% if cancel after 6 hours through 12 hours after booking.
 * - 0% if cancel after more than 12 hours after booking.
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
        if ($hoursSinceBooking <= 6) {
            $percent = 99;
            $rule = 'cancel_within_6h_99';
        } elseif ($hoursSinceBooking <= 12) {
            $percent = 50;
            $rule = 'cancel_6h_to_12h_50';
        } else {
            $percent = 0;
            $rule = 'cancel_after_12h_0';
        }
    }

    $amount = round(max(0, $totalAmount) * ($percent / 100), 2);

    $warning = '';
    if ($percent >= 99) {
        $warning = "If you cancel now, you will receive a {$percent}% refund.";
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
/**
 * One-paragraph explanation of cancellation refund tiers (for hosts/admins).
 */
function reservepro_cancellation_policy_human_summary(string $policy): string
{
    $policy = strtolower(trim($policy));
    if ($policy === 'strict') {
        return 'Strict: no automatic refund when a guest cancels; any refund is decided in review (host/admin).';
    }
    return 'Flexible / moderate: guest cancellation refunds are based on time since the booking was placed — 99% within 6 hours, 50% within 12 hours, then no refund after 12 hours.';
}

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
