<?php

declare(strict_types=1);

use App\Operations\SchedulerHeartbeatPolicy;

require __DIR__.'/vendor/autoload.php';

$heartbeatPath = getenv('SCHEDULER_HEARTBEAT_PATH') ?: '/tmp/jobpilot-scheduler-heartbeat';
$intervalSeconds = (int) (getenv('JOB_SYNC_INTERVAL_SECONDS') ?: SchedulerHeartbeatPolicy::DEFAULT_INTERVAL_SECONDS);
$graceSeconds = (int) (getenv('SCHEDULER_HEARTBEAT_GRACE_SECONDS') ?: SchedulerHeartbeatPolicy::DEFAULT_GRACE_SECONDS);

try {
    SchedulerHeartbeatPolicy::maxAgeSeconds($intervalSeconds, $graceSeconds);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(2);
}

if (!is_file($heartbeatPath)) {
    fwrite(STDERR, "Scheduler heartbeat is missing.\n");
    exit(1);
}

$rawTimestamp = trim((string) @file_get_contents($heartbeatPath));
if ($rawTimestamp === '' || !ctype_digit($rawTimestamp)) {
    fwrite(STDERR, "Scheduler heartbeat is invalid.\n");
    exit(1);
}

$heartbeatTimestamp = (int) $rawTimestamp;
$nowTimestamp = time();

if (!SchedulerHeartbeatPolicy::isFresh(
    $heartbeatTimestamp,
    $nowTimestamp,
    $intervalSeconds,
    $graceSeconds,
)) {
    $age = max(0, $nowTimestamp - $heartbeatTimestamp);
    $maxAge = SchedulerHeartbeatPolicy::maxAgeSeconds($intervalSeconds, $graceSeconds);
    fwrite(STDERR, sprintf("Scheduler heartbeat is stale (%ds > %ds).\n", $age, $maxAge));
    exit(1);
}

exit(0);
