<?php
require_once dirname(__DIR__) . '/config/database.php';

echo "Wiping transactions cache to force a clean full sync...\n";
$db = Database::getInstance()->getConnection();

$db->query("TRUNCATE TABLE transactions_cache");
$db->query("UPDATE sync_state SET last_block_synced = 0, sync_status = 'idle'");

echo "All cleared! The next sync will fetch all transactions from scratch and apply the correct timestamps.\n";
