<?php
/**
 * SyncService - Serviço de Sincronização Blockchain
 * Revisão: 2026-04-07-006 (Modo Turbo + Rastreabilidade)
 * 
 * Orquestra a sincronização incremental de transações EVM.
 * Consulta o sync_state, busca apenas blocos novos via Alchemy,
 * parseia as transações e salva no banco de dados.
 */

class SyncService {

    /** @var PDO Conexão com banco */
    private $db;

    /** @var array Chaves API Alchemy por rede */
    private $alchemyKeys;

    /** @var int Blocos máximos por requisição */
    private $maxBlocksPerRequest;

    /**
     * Construtor
     *
     * @param PDO $db Conexão com banco
     * @param array $alchemyKeys Chaves API por rede
     * @param int $maxBlocks Blocos máximos por requisição
     */
    public function __construct(PDO $db, array $alchemyKeys, int $maxBlocks = 200000) {
        $this->db = $db;
        $this->alchemyKeys = $alchemyKeys;
        $this->maxBlocksPerRequest = $maxBlocks;
    }

    /**
     * Sincronizar todas as wallets ativas
     *
     * Método principal chamado pelo Cron Job.
     * Processa uma wallet por vez para evitar timeout.
     *
     * @return array Resumo da execução
     */
    public function syncAllWallets(): array {
        $results = [
            'wallets_processed' => 0,
            'transactions_found' => 0,
            'errors' => 0,
            'details' => [],
        ];

        // Buscar wallets ativas ordenadas por última tentativa de sync
        $stmt = $this->db->query("
            SELECT w.*, u.email as user_email
            FROM wallets w
            JOIN users u ON w.user_id = u.id
            WHERE w.is_active = 1 AND u.status = 'active'
            ORDER BY COALESCE(w.last_sync_attempt, '1970-01-01') ASC
        ");
        $wallets = $stmt->fetchAll();

        foreach ($wallets as $wallet) {
            try {
                $walletResult = $this->syncWallet($wallet);
                $results['wallets_processed']++;
                $results['transactions_found'] += $walletResult['transactions_found'];
                $results['details'][] = $walletResult;
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'wallet_id' => $wallet['id'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];

                // Log do erro
                $this->logSyncError($wallet['id'], $e->getMessage());

                // Incrementar contador de erro
                $this->incrementErrorCount($wallet['id']);
            }

            // Atualizar última tentativa de sync
            $this->updateLastSyncAttempt($wallet['id']);
        }

        return $results;
    }

    /**
     * Sincronizar uma wallet específica
     *
     * @param array $wallet Dados da wallet
     * @return array Resultado da sincronização
     */
    public function syncWallet(array $wallet): array {
        $walletId = $wallet['id'];
        $network = $wallet['network'];
        $address = $wallet['address'];

        $result = [
            'wallet_id' => $walletId,
            'network' => $network,
            'address' => $address,
            'status' => 'success',
            'transactions_found' => 0,
            'blocks_processed' => 0,
        ];

        // Verificar se temos chave API para esta rede
        $apiKey = $this->alchemyKeys[$network] ?? null;
        if (!$apiKey) {
            throw new Exception("Sem chave API para rede: $network");
        }

        // Criar cliente Alchemy
        $alchemy = new AlchemyClient($network, $apiKey);
        $nativeSymbol = $alchemy->getNativeSymbol();

        // Buscar último bloco sincronizado
        $fromBlock = $this->getLastSyncedBlock($walletId, $network);

        // Otimização WP2: Se é o primeiro sync (bloco 0), encontrar o primeiro bloco com atividade
        if ($fromBlock === 0) {
            echo "   🔍 Primeira sincronização - buscando primeiro bloco com atividade...\n";
            $firstBlock = $alchemy->findFirstActivityBlock($address);
            
            if ($firstBlock > 0) {
                echo "   ✅ Primeira atividade encontrada no bloco: $firstBlock\n";
                $fromBlock = max(0, $firstBlock - 10);
            } else {
                echo "   ⚠️ Não foi possível encontrar primeira atividade, sincronizando desde o bloco 0\n";
            }
        }

        // Buscar bloco atual
        $currentBlock = $alchemy->getCurrentBlock();

        // Se já está sincronizado, retornar
        if ($fromBlock >= $currentBlock) {
            $result['status'] = 'idle';
            return $result;
        }

        // Lógica de Aceleração Dinâmica (Modo Turbo)
        $lastSyncLog = $this->getLastSyncLog($walletId);
        $dynamicBlocks = $this->maxBlocksPerRequest; // Padrão (200.000)
        
        if ($lastSyncLog && $lastSyncLog['transactions_found'] == 0 && $lastSyncLog['blocks_processed'] > 0) {
            // Salto gigante: 500.000 blocos se não achou nada
            $dynamicBlocks = 500000;
            echo "   ⚡ MODO TURBO: Nenhuma transação encontrada. Pulando $dynamicBlocks blocos...\n";
        } elseif ($lastSyncLog && $lastSyncLog['transactions_found'] > 0) {
            // Reduz para 50k quando encontra transações (para não perder detalhes)
            $dynamicBlocks = 50000;
            echo "   📍 Transações encontradas anteriormente. Processando $dynamicBlocks blocos...\n";
        }

        // Limitar blocos por requisição
        $toBlock = min($fromBlock + $dynamicBlocks, $currentBlock);

        // Buscar todas as transações (nativo + ERC-20)
        $allTransfers = $alchemy->getAllTransfers($address, $fromBlock, $toBlock);

        $txCount = 0;
        foreach ($allTransfers as $transfer) {
            // Determinar tipo de transferência
            $transferType = $transfer['_transferType'] ?? 'native';

            // Parsear transação
            if ($transferType === 'native') {
                $txData = TransactionParser::parseNativeTransfer($transfer, $network, $nativeSymbol);
            } else {
                $txData = TransactionParser::parseTokenTransfer($transfer, $network);
            }

            // Salvar no banco
            if ($this->saveTransaction($walletId, $txData)) {
                $txCount++;
            }
        }

        // Atualizar sync_state
        $this->updateSyncState($walletId, $network, $toBlock);

        // Log de sucesso
        $this->logSyncSuccess($walletId, $toBlock - $fromBlock, $txCount);

        // Resetar contador de erro
        $this->resetErrorCount($walletId);

        $result['transactions_found'] = $txCount;
        $result['blocks_processed'] = $toBlock - $fromBlock;

        return $result;
    }

    /**
     * Salvar transação no banco (idempotente por tx_hash)
     */
    private function saveTransaction(int $walletId, array $txData): bool {
        $stmt = $this->db->prepare("SELECT id FROM transactions_cache WHERE wallet_id = ? AND tx_hash = ?");
        $stmt->execute([$walletId, $txData['tx_hash']]);
        if ($stmt->fetch()) {
            return false; // Já existe
        }

        $stmt = $this->db->prepare("
            INSERT INTO transactions_cache (
                wallet_id, tx_hash, block_number, timestamp,
                from_address, to_address, value,
                token_address, token_symbol, token_name, token_decimals,
                transaction_type, defi_protocol, gas_used, gas_price,
                status, usd_value_at_tx, raw_data
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $walletId,
            $txData['tx_hash'],
            $txData['block_number'],
            $txData['timestamp'],
            $txData['from_address'],
            $txData['to_address'],
            $txData['value'],
            $txData['token_address'],
            $txData['token_symbol'],
            $txData['token_name'],
            $txData['token_decimals'],
            $txData['transaction_type'],
            $txData['defi_protocol'],
            $txData['gas_used'],
            $txData['gas_price'],
            $txData['status'],
            $txData['usd_value_at_tx'],
            $txData['raw_data'],
        ]);

        return true;
    }

    /**
     * Obter último bloco sincronizado de uma wallet
     */
    private function getLastSyncedBlock(int $walletId, string $network): int {
        $stmt = $this->db->prepare("
            SELECT last_block_synced FROM sync_state WHERE wallet_id = ? AND network = ?
        ");
        $stmt->execute([$walletId, $network]);
        $result = $stmt->fetch();

        return $result ? (int) $result['last_block_synced'] : 0;
    }

    /**
     * Obter o último log de sync de uma wallet para análise de performance
     */
    private function getLastSyncLog(int $walletId): ?array {
        $stmt = $this->db->prepare("
            SELECT transactions_found, blocks_processed 
            FROM sync_logs 
            WHERE wallet_id = ? 
            ORDER BY executed_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$walletId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Atualizar estado de sincronização
     */
    private function updateSyncState(int $walletId, string $network, int $lastBlock): void {
        $stmt = $this->db->prepare("
            INSERT INTO sync_state (wallet_id, network, last_block_synced, last_sync_at, sync_status)
            VALUES (?, ?, ?, NOW(), 'idle')
            ON DUPLICATE KEY UPDATE
                last_block_synced = VALUES(last_block_synced),
                last_sync_at = NOW(),
                sync_status = 'idle'
        ");
        $stmt->execute([$walletId, $network, $lastBlock]);
    }

    /**
     * Log de sync bem sucedido
     */
    private function logSyncSuccess(int $walletId, int $blocksProcessed, int $txCount): void {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (wallet_id, status, blocks_processed, transactions_found, executed_at)
            VALUES (?, 'success', ?, ?, NOW())
        ");
        $stmt->execute([$walletId, $blocksProcessed, $txCount]);
    }

    /**
     * Log de erro de sync
     */
    private function logSyncError(int $walletId, string $errorMessage): void {
        $stmt = $this->db->prepare("
            INSERT INTO sync_logs (wallet_id, status, error_message)
            VALUES (?, 'error', ?)
        ");
        $stmt->execute([$walletId, $errorMessage]);
    }

    /**
     * Atualizar última tentativa de sync
     */
    private function updateLastSyncAttempt(int $walletId): void {
        $stmt = $this->db->prepare("UPDATE wallets SET last_sync_attempt = NOW() WHERE id = ?");
        $stmt->execute([$walletId]);
    }

    /**
     * Incrementar contador de erro
     */
    private function incrementErrorCount(int $walletId): void {
        $stmt = $this->db->prepare("UPDATE wallets SET sync_error_count = sync_error_count + 1 WHERE id = ?");
        $stmt->execute([$walletId]);
    }

    /**
     * Resetar contador de erro
     */
    private function resetErrorCount(int $walletId): void {
        $stmt = $this->db->prepare("UPDATE wallets SET sync_error_count = 0 WHERE id = ?");
        $stmt->execute([$walletId]);
    }
}
?>
