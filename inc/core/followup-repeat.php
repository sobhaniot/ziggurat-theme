<?php
if (!defined('ABSPATH')) exit;

/** One delivery cycle per Tehran calendar day, at the originally selected time. */
function zigurat_followup_delivery_cycle($due, $repeat, $now = null) {
    $due = (int)$due; $now = $now === null ? time() : (int)$now;
    if (!$due || $due > $now) return 0;
    if (!$repeat) return $due;
    $zone = new DateTimeZone('Asia/Tehran');
    $origin = (new DateTimeImmutable('@'.$due))->setTimezone($zone);
    $cycle = (new DateTimeImmutable('@'.$now))->setTimezone($zone)->setTime((int)$origin->format('H'), (int)$origin->format('i'), (int)$origin->format('s'));
    if ($cycle->getTimestamp() > $now) $cycle = $cycle->modify('-1 day');
    return max($due, $cycle->getTimestamp());
}
function zigurat_followup_next_cycle($cycle) {
    return (new DateTimeImmutable('@'.(int)$cycle))->setTimezone(new DateTimeZone('Asia/Tehran'))->modify('+1 day')->getTimestamp();
}
