<?php
/** Run from cron every 5-15 minutes. Expires only unanswered founder-review claims. */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';
if (PHP_SAPI !== 'cli' && !defined('REUNITE_ALLOW_CLAIM_EXPIRY_HTTP')) {
    http_response_code(403); exit('CLI only');
}
if (empty($conn)) { fwrite(STDERR, "Database connection unavailable\n"); exit(1); }

$cols = [];
$res = @$conn->query('SHOW COLUMNS FROM matches');
if ($res) while ($row = $res->fetch_assoc()) $cols[] = strtolower($row['Field']);
$pk = in_array('match_id', $cols, true) ? 'match_id' : 'id';
$lostCol = in_array('lost_id', $cols, true) ? 'lost_id' : 'lost_report_id';
$foundCol = in_array('found_id', $cols, true) ? 'found_id' : 'found_report_id';
$timeCol = in_array('claim_submitted_at', $cols, true) ? 'claim_submitted_at' : (in_array('created_at', $cols, true) ? 'created_at' : null);
if (!$timeCol) { fwrite(STDERR, "Matches table has no claim timestamp; expiry cannot run safely.\n"); exit(2); }
$lostCols=[]; $q=@$conn->query('SHOW COLUMNS FROM lost_reports'); if($q) while($r=$q->fetch_assoc()) $lostCols[]=strtolower($r['Field']);
$foundCols=[]; $q=@$conn->query('SHOW COLUMNS FROM found_reports'); if($q) while($r=$q->fetch_assoc()) $foundCols[]=strtolower($r['Field']);
$lostPk=in_array('lost_id',$lostCols,true)?'lost_id':'id'; $lostOwner=in_array('user_id',$lostCols,true)?'user_id':'claimant_id';
$foundPk=in_array('found_id',$foundCols,true)?'found_id':'id'; $foundOwner=in_array('finder_id',$foundCols,true)?'finder_id':'user_id';
$conn->begin_transaction();
try {
    $sql="SELECT m.`$pk` AS match_id, m.`$lostCol` AS lost_id, m.`$foundCol` AS found_id, l.`$lostOwner` AS claimant_id, f.`$foundOwner` AS founder_id FROM matches m JOIN lost_reports l ON l.`$lostPk`=m.`$lostCol` JOIN found_reports f ON f.`$foundPk`=m.`$foundCol` WHERE m.status='pending_founder_approval' AND m.`$timeCol` < DATE_SUB(NOW(), INTERVAL 48 HOUR) ORDER BY m.`$timeCol` ASC LIMIT 100 FOR UPDATE";
    $items=[]; $q=$conn->query($sql); if($q) while($r=$q->fetch_assoc()) $items[]=$r;
    $update=$conn->prepare("UPDATE matches SET status='expired' WHERE `$pk`=? AND status='pending_founder_approval'");
    foreach($items as $item) {
        $mid=(int)$item['match_id']; $update->bind_param('i',$mid); $update->execute(); if($update->affected_rows < 1) continue;
        $claimant=(int)$item['claimant_id']; $founder=(int)$item['founder_id']; $found=(int)$item['found_id'];
        if(function_exists('insertSystemNotification')) {
            insertSystemNotification($claimant,'Claim review expired','The founder review window expired after 48 hours. You can try another eligible match.','search.php','FOUND',$found,'claim_expired');
            insertSystemNotification($founder,'Founder review overdue','A claim review expired after 48 hours without a response.','item.php?type=found&id='.$found.'&match_id='.$mid.'&view_claims=1','FOUND',$found,'claim_expired');
        }
        if(function_exists('logAccess')) logAccess(null,'Founder Review Expired','Match '.$mid.'; claimant '.$claimant.'; found report '.$found);
        $eventCheck=@$conn->query("SHOW TABLES LIKE 'claim_review_events'");
        if($eventCheck && $eventCheck->num_rows>0) {
            $event=$conn->prepare("INSERT INTO claim_review_events (match_id, claimant_id, actor_user_id, decision, reason) VALUES (?, ?, NULL, 'expired', 'No founder/admin response within 48 hours')");
            if($event) { $event->bind_param('ii',$mid,$claimant); $event->execute(); $event->close(); }
        }
    }
    if($update) $update->close(); $conn->commit(); echo 'Expired ' . count($items) . " pending founder review claim(s).\n";
} catch (Throwable $e) { $conn->rollback(); error_log('[ClaimExpiry] '.$e->getMessage()); fwrite(STDERR,"Claim expiry failed\n"); exit(1); }
