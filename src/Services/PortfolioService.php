<?php
/**
 * PortfolioService - Portfolio Analytics Engine
 * WP3: Patrimônio total, evolução temporal, comparativo de benchmarks
 * 
 * Responsabilidades:
 * - Patrimônio total e por rede
 * - Evolução patrimonial (série temporal)
 * - Top holdings (alocação percentual)
 * - Rentabilidade vs. benchmarks desde o primeiro aporte
 * - Atividade recente
 */

class PortfolioService {

    /** @var PDO */
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Obter snapshot completo do portfólio de um usuário
     */
    public function getPortfolioSnapshot(int $userId): array {
        $totalUsd = $this->getTotalValueUsd($userId);
        $totalBrl = $this->getTotalValueBrl($userId);
        $change24h = $this->getChange24h($userId);
        $topHoldings = $this->getTopHoldings($userId, 5);
        $networkAllocation = $this->getNetworkAllocation($userId);
        $lastSync = $this->getLastSyncInfo($userId);

        return [
            'total_value_usd' => $totalUsd,
            'total_value_brl' => $totalBrl,
            'change_24h_usd' => $change24h['usd'],
            'change_24h_percent' => $change24h['percent'],
            'top_holdings' => $topHoldings,
            'network_allocation' => $networkAllocation,
            'last_sync' => $lastSync,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Patrimônio total em USD
     */
    public function getTotalValueUsd(int $userId): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(wb.balance_usd), 0) as total
            FROM wallet_balances wb
            JOIN wallets w ON wb.wallet_id = w.id
            WHERE w.user_id = ?
            AND w.is_active = 1
            AND wb.balance > 0
        ");
        $stmt->execute([$userId]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Patrimônio total em BRL (via câmbio ETH USD/BRL ou fallback)
     */
    public function getTotalValueBrl(int $userId): float {
        $totalUsd = $this->getTotalValueUsd($userId);
        $usdBrlRate = $this->getUsdBrlRate();
        return $totalUsd * $usdBrlRate;
    }

    /**
     * Obter taxa USD/BRL a partir dos preços de ETH
     */
    private function getUsdBrlRate(): float {
        $stmt = $this->db->query("
            SELECT price_brl / price_usd as rate
            FROM token_prices
            WHERE price_usd > 0 AND price_brl > 0
            LIMIT 1
        ");
        $rate = $stmt->fetchColumn();
        return $rate ? (float)$rate : 5.0; // Fallback BRL rate
    }

    /**
     * Variação 24h do portfólio
     * Compara o valor atual (tempo real) com o snapshot de ontem no portfolio_history
     */
    public function getChange24h(int $userId): array {
        $currentValue = $this->getTotalValueUsd($userId);

        // Pegar snapshot de ontem no portfolio_history
        $stmt = $this->db->prepare("
            SELECT total_value_usd
            FROM portfolio_history
            WHERE user_id = ?
            AND date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $yesterday = $stmt->fetchColumn();

        // Fallback: snapshot mais recente antes de hoje
        if (!$yesterday) {
            $stmt = $this->db->prepare("
                SELECT total_value_usd
                FROM portfolio_history
                WHERE user_id = ?
                AND date < CURDATE()
                ORDER BY date DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $yesterday = $stmt->fetchColumn();
        }

        if ($yesterday && (float)$yesterday > 0) {
            $changeUsd = $currentValue - (float)$yesterday;
            $changePercent = ($changeUsd / (float)$yesterday) * 100;
        } else {
            // Sem histórico: usar change_24h ponderada dos tokens como fallback
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(wb.balance_usd) as total_value,
                    SUM(wb.balance_usd * COALESCE(tp.change_24h, 0) / 100) as change_usd
                FROM wallet_balances wb
                JOIN wallets w ON wb.wallet_id = w.id
                LEFT JOIN token_prices tp ON wb.token_symbol = tp.token_symbol
                WHERE w.user_id = ?
                AND w.is_active = 1
                AND wb.balance > 0
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $changeUsd = (float)($row['change_usd'] ?? 0);
            $changePercent = $currentValue > 0 ? ($changeUsd / $currentValue) * 100 : 0;
        }

        return [
            'usd' => $changeUsd,
            'percent' => $changePercent,
        ];
    }

    /**
     * Top holdings ordenados por valor
     */
    public function getTopHoldings(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare("
            SELECT 
                wb.token_symbol,
                wb.token_name,
                SUM(wb.balance) as total_balance,
                SUM(wb.balance_usd) as total_value_usd,
                tp.price_usd,
                tp.change_24h,
                w.network
            FROM wallet_balances wb
            JOIN wallets w ON wb.wallet_id = w.id
            LEFT JOIN token_prices tp ON wb.token_symbol = tp.token_symbol
            WHERE w.user_id = ?
            AND w.is_active = 1
            AND wb.balance > 0
            GROUP BY wb.token_symbol, wb.token_name, tp.price_usd, tp.change_24h, w.network
            ORDER BY total_value_usd DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $holdings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPortfolio = $this->getTotalValueUsd($userId);

        return array_map(function($h) use ($totalPortfolio) {
            $value = (float)$h['total_value_usd'];
            $h['allocation_percent'] = $totalPortfolio > 0 ? ($value / $totalPortfolio) * 100 : 0;
            return $h;
        }, $holdings);
    }

    /**
     * Alocação por rede
     */
    public function getNetworkAllocation(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT 
                w.network,
                SUM(wb.balance_usd) as total_value_usd
            FROM wallet_balances wb
            JOIN wallets w ON wb.wallet_id = w.id
            WHERE w.user_id = ?
            AND w.is_active = 1
            AND wb.balance > 0
            GROUP BY w.network
            ORDER BY total_value_usd DESC
        ");
        $stmt->execute([$userId]);
        $networks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPortfolio = $this->getTotalValueUsd($userId);

        return array_map(function($n) use ($totalPortfolio) {
            $value = (float)$n['total_value_usd'];
            $n['allocation_percent'] = $totalPortfolio > 0 ? ($value / $totalPortfolio) * 100 : 0;
            return $n;
        }, $networks);
    }

    /**
     * Evolução patrimonial (série temporal do portfolio_history)
     * 
     * @param int $userId
     * @param string $period '7d', '30d', '90d', '1y', 'all'
     * @return array
     */
    public function getPortfolioHistory(int $userId, string $period = '30d'): array {
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            'all' => 3650,
            default => 30,
        };

        $stmt = $this->db->prepare("
            SELECT date, total_value_usd, total_value_brl
            FROM portfolio_history
            WHERE user_id = ?
            AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Informações da última sincronização
     */
    public function getLastSyncInfo(int $userId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                w.id as wallet_id,
                w.network,
                ss.last_sync_at,
                ss.sync_status,
                ss.last_block_synced
            FROM wallets w
            LEFT JOIN sync_state ss ON w.id = ss.wallet_id
            WHERE w.user_id = ?
            AND w.is_active = 1
            ORDER BY ss.last_sync_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atividade recente (últimas N transações)
     */
    public function getRecentActivity(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare("
            SELECT 
                tc.tx_hash,
                tc.token_symbol,
                tc.token_name,
                tc.value,
                tc.usd_value_at_tx,
                tc.from_address,
                tc.to_address,
                tc.transaction_type,
                tc.timestamp,
                tc.block_number,
                w.address as wallet_address,
                w.network
            FROM transactions_cache tc
            JOIN wallets w ON tc.wallet_id = w.id
            WHERE w.user_id = ?
            AND w.is_active = 1
            AND tc.value > 0
            ORDER BY tc.timestamp DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $txs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enriquecer com direção (in/out) e formatação
        return array_map(function($tx) {
            $walletAddr = strtolower($tx['wallet_address']);
            $tx['direction'] = strtolower($tx['to_address']) === $walletAddr ? 'in' : 'out';
            $tx['date_formatted'] = date('d/m/Y H:i', $tx['timestamp']);
            $tx['time_ago'] = $this->timeAgo($tx['timestamp']);
            return $tx;
        }, $txs);
    }

    /**
     * Rentabilidade vs. benchmarks desde o primeiro aporte
     */
    public function getBenchmarkComparison(int $userId): array {
        // Encontrar data do primeiro aporte
        $stmt = $this->db->prepare("
            SELECT MIN(tc.timestamp) as first_tx
            FROM transactions_cache tc
            JOIN wallets w ON tc.wallet_id = w.id
            WHERE w.user_id = ?
            AND tc.value > 0
        ");
        $stmt->execute([$userId]);
        $firstTx = $stmt->fetchColumn();

        if (!$firstTx) {
            return ['period_days' => 0, 'benchmarks' => []];
        }

        $firstDate = date('Y-m-d', $firstTx);
        $daysActive = (int)((time() - $firstTx) / 86400);

        // Pegar patrimônio inicial e atual
        $portfolioHistory = $this->getPortfolioHistory($userId, 'all');
        $initialValue = !empty($portfolioHistory) ? (float)$portfolioHistory[0]['total_value_usd'] : 0;
        $currentValue = $this->getTotalValueUsd($userId);

        $portfolioReturn = $initialValue > 0
            ? (($currentValue - $initialValue) / $initialValue) * 100
            : 0;

        // Benchmark: buscar valores históricos
        $benchmarks = $this->getBenchmarkReturns($firstDate);

        return [
            'first_investment_date' => $firstDate,
            'period_days' => $daysActive,
            'portfolio_return_percent' => round($portfolioReturn, 2),
            'portfolio_initial_value' => $initialValue,
            'portfolio_current_value' => $currentValue,
            'benchmarks' => $benchmarks,
        ];
    }

    /**
     * Rentabilidade dos benchmarks desde uma data
     */
    private function getBenchmarkReturns(string $fromDate): array {
        // Buscar valores iniciais (mais antigos disponíveis)
        $stmt = $this->db->prepare("
            SELECT bh.symbol, bh.name, bh.value as initial_value, bh.currency,
                   (SELECT bh2.value FROM benchmark_history bh2 
                    WHERE bh2.symbol = bh.symbol ORDER BY bh2.date DESC LIMIT 1) as current_value
            FROM benchmark_history bh
            WHERE bh.date = (
                SELECT MIN(bh3.date) FROM benchmark_history bh3 
                WHERE bh3.symbol = bh.symbol AND bh3.date >= ?
            )
            GROUP BY bh.symbol
        ");

        try {
            $stmt->execute([$fromDate]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback: use current benchmarks
            $rows = [];
        }

        $results = [];
        foreach ($rows as $row) {
            $initial = (float)$row['initial_value'];
            $current = (float)$row['current_value'];
            $returnPct = $initial > 0 ? (($current - $initial) / $initial) * 100 : 0;

            $results[] = [
                'symbol' => $row['symbol'],
                'name' => $row['name'],
                'currency' => $row['currency'],
                'initial_value' => $initial,
                'current_value' => $current,
                'return_percent' => round($returnPct, 2),
            ];
        }

        // Se não há histórico, pegar valores atuais como referência
        if (empty($results)) {
            $benchStmt = $this->db->query("
                SELECT symbol, name, value, currency FROM benchmarks WHERE value > 0
            ");
            while ($row = $benchStmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'symbol' => $row['symbol'],
                    'name' => $row['name'],
                    'currency' => $row['currency'],
                    'initial_value' => (float)$row['value'],
                    'current_value' => (float)$row['value'],
                    'return_percent' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Série temporal de benchmarks para gráfico
     */
    public function getBenchmarkHistory(string $period = '30d', ?string $currency = null): array {
        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            'all' => 3650,
            default => 30,
        };

        $sql = "
            SELECT symbol, name, value, currency, date
            FROM benchmark_history
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ";
        $params = [$days];

        if ($currency) {
            $sql .= " AND currency = ?";
            $params[] = $currency;
        }

        $sql .= " ORDER BY symbol, date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por symbol
        $grouped = [];
        foreach ($rows as $row) {
            $symbol = $row['symbol'];
            if (!isset($grouped[$symbol])) {
                $grouped[$symbol] = [
                    'symbol' => $symbol,
                    'name' => $row['name'],
                    'currency' => $row['currency'],
                    'data' => [],
                ];
            }
            $grouped[$symbol]['data'][] = [
                'date' => $row['date'],
                'value' => (float)$row['value'],
            ];
        }

        return array_values($grouped);
    }

    /**
     * Helper: tempo relativo
     */
    private function timeAgo(int $timestamp): string {
        $diff = time() - $timestamp;
        
        if ($diff < 60) return 'agora';
        if ($diff < 3600) return floor($diff / 60) . ' min atrás';
        if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
        if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
        if ($diff < 2592000) return floor($diff / 604800) . ' sem atrás';
        
        return date('d/m/Y', $timestamp);
    }

    /**
     * Dados completos para o dashboard (combinação de todos os métodos)
     */
    public function getDashboardData(int $userId): array {
        return [
            'snapshot' => $this->getPortfolioSnapshot($userId),
            'recent_activity' => $this->getRecentActivity($userId, 5),
        ];
    }
}
