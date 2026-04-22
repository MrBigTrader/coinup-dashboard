<?php
/**
 * DCAService - Dollar Cost Average & P&L Engine
 * WP3: Cálculo de preço médio ponderado e P&L por token
 * 
 * Calcula:
 * - Preço médio de compra (DCA) por token por carteira
 * - P&L absoluto (USD) e percentual (%)
 * - Classificação de transações como buy/sell
 */

class DCAService {

    /** @var PDO */
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Calcular DCA e P&L para um usuário específico
     * 
     * @param int $userId ID do usuário
     * @return array Lista de tokens com DCA e P&L calculados
     */
    public function calculateForUser(int $userId): array {
        // Buscar todas as transações do usuário agrupadas por token
        $stmt = $this->db->prepare("
            SELECT 
                tc.token_symbol,
                tc.token_name,
                tc.value,
                tc.usd_value_at_tx,
                tc.from_address,
                tc.to_address,
                tc.timestamp,
                tc.transaction_type,
                tc.wallet_id,
                w.address as wallet_address,
                w.network
            FROM transactions_cache tc
            JOIN wallets w ON tc.wallet_id = w.id
            WHERE w.user_id = ?
            AND tc.token_symbol IS NOT NULL
            AND tc.value > 0
            ORDER BY tc.token_symbol, tc.timestamp ASC
        ");
        $stmt->execute([$userId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar preços atuais
        $priceStmt = $this->db->query("
            SELECT token_symbol, price_usd, price_brl, change_24h
            FROM token_prices
            WHERE price_usd > 0
        ");
        $prices = [];
        while ($row = $priceStmt->fetch(PDO::FETCH_ASSOC)) {
            $prices[$row['token_symbol']] = $row;
        }

        // Buscar saldos atuais
        $balanceStmt = $this->db->prepare("
            SELECT wb.token_symbol, SUM(wb.balance) as total_balance, SUM(wb.balance_usd) as total_balance_usd
            FROM wallet_balances wb
            JOIN wallets w ON wb.wallet_id = w.id
            WHERE w.user_id = ?
            AND wb.balance > 0
            GROUP BY wb.token_symbol
        ");
        $balanceStmt->execute([$userId]);
        $balances = [];
        while ($row = $balanceStmt->fetch(PDO::FETCH_ASSOC)) {
            $balances[$row['token_symbol']] = $row;
        }

        // Agrupar transações por token
        $tokenTxs = [];
        foreach ($transactions as $tx) {
            $symbol = $tx['token_symbol'];
            if (!isset($tokenTxs[$symbol])) {
                $tokenTxs[$symbol] = [];
            }
            $tokenTxs[$symbol][] = $tx;
        }

        // Calcular DCA por token
        $results = [];
        foreach ($tokenTxs as $symbol => $txs) {
            $dca = $this->calculateTokenDCA($symbol, $txs);
            
            // Enriquecer com preço atual e saldo
            $currentPrice = $prices[$symbol]['price_usd'] ?? 0;
            $currentPriceBrl = $prices[$symbol]['price_brl'] ?? 0;
            $change24h = $prices[$symbol]['change_24h'] ?? 0;
            $currentBalance = (float)($balances[$symbol]['total_balance'] ?? 0);
            $currentValueUsd = (float)($balances[$symbol]['total_balance_usd'] ?? ($currentBalance * $currentPrice));

            // Se não temos saldo real, usar DCA holdings
            if ($currentBalance <= 0 && $dca['total_quantity'] > 0) {
                $currentBalance = $dca['total_quantity'];
                $currentValueUsd = $currentBalance * $currentPrice;
            }

            // Pular tokens sem saldo nem transações significativas
            if ($currentBalance <= 0 && $dca['total_quantity'] <= 0) {
                continue;
            }

            // P&L calculado
            $totalCost = $dca['total_cost'];
            $pnlAbsolute = $currentValueUsd - $totalCost;
            $pnlPercent = $totalCost > 0 ? (($currentValueUsd - $totalCost) / $totalCost) * 100 : 0;

            $results[] = [
                'token_symbol' => $symbol,
                'token_name' => $dca['token_name'],
                'network' => $dca['network'],
                'avg_price' => $dca['avg_price'],
                'total_quantity' => $dca['total_quantity'],
                'total_cost' => $totalCost,
                'buy_count' => $dca['buy_count'],
                'sell_count' => $dca['sell_count'],
                'current_price' => $currentPrice,
                'current_price_brl' => $currentPriceBrl,
                'current_balance' => $currentBalance,
                'current_value_usd' => $currentValueUsd,
                'pnl_absolute' => $pnlAbsolute,
                'pnl_percent' => $pnlPercent,
                'change_24h' => $change24h,
                'first_buy_date' => $dca['first_buy_date'],
                'last_buy_date' => $dca['last_buy_date'],
            ];
        }

        // Incluir ativos que tem saldo mas não possuem transações válidas (ex: airdrops, transfers mal formatados)
        foreach ($balances as $symbol => $bal) {
            if (!isset($tokenTxs[$symbol])) {
                $currentPrice = $prices[$symbol]['price_usd'] ?? 0;
                $currentPriceBrl = $prices[$symbol]['price_brl'] ?? 0;
                $change24h = $prices[$symbol]['change_24h'] ?? 0;
                $currentBalance = (float)$bal['total_balance'];
                $currentValueUsd = (float)$bal['total_balance_usd'];

                if ($currentBalance > 0 || $currentValueUsd > 0) {
                    $results[] = [
                        'token_symbol' => $symbol,
                        'token_name' => $symbol,
                        'network' => 'Variadas',
                        'avg_price' => 0,
                        'total_quantity' => $currentBalance,
                        'total_cost' => 0,
                        'buy_count' => 0,
                        'sell_count' => 0,
                        'current_price' => $currentPrice,
                        'current_price_brl' => $currentPriceBrl,
                        'current_balance' => $currentBalance,
                        'current_value_usd' => $currentValueUsd,
                        'pnl_absolute' => 0,
                        'pnl_percent' => 0,
                        'change_24h' => $change24h,
                        'first_buy_date' => null,
                        'last_buy_date' => null,
                    ];
                }
            }
        }

        // Ordenar por valor atual (maior primeiro)
        usort($results, function($a, $b) {
            return $b['current_value_usd'] <=> $a['current_value_usd'];
        });

        return $results;
    }

    /**
     * Calcular DCA para um token específico
     */
    private function calculateTokenDCA(string $symbol, array $transactions): array {
        $totalQuantityBought = 0;
        $totalCost = 0;
        $buyCount = 0;
        $sellCount = 0;
        $tokenName = '';
        $network = '';
        $firstBuyDate = null;
        $lastBuyDate = null;
        $avgPrice = 0;

        foreach ($transactions as $tx) {
            $walletAddress = strtolower($tx['wallet_address']);
            $from = strtolower($tx['from_address']);
            $to = strtolower($tx['to_address']);
            $value = (float)$tx['value'];
            $usdValueAtTx = (float)($tx['usd_value_at_tx'] ?? 0);

            if (empty($tokenName) && !empty($tx['token_name'])) {
                $tokenName = $tx['token_name'];
            }
            if (empty($network) && !empty($tx['network'])) {
                $network = $tx['network'];
            }

            // Classificar: BUY (recebeu) ou SELL (enviou)
            $isBuy = ($to === $walletAddress);
            $isSell = ($from === $walletAddress);

            if ($isBuy && $value > 0) {
                $buyCount++;
                $totalQuantityBought += $value;
                $totalCost += $usdValueAtTx > 0 ? $usdValueAtTx : 0;
                $avgPrice = $totalQuantityBought > 0 ? ($totalCost / $totalQuantityBought) : 0;

                $txDate = date('Y-m-d', $tx['timestamp']);
                if ($firstBuyDate === null) $firstBuyDate = $txDate;
                $lastBuyDate = $txDate;
            } elseif ($isSell && $value > 0) {
                $sellCount++;
                $totalQuantityBought -= $value;
                if ($totalQuantityBought <= 0) {
                    $totalQuantityBought = 0;
                    $totalCost = 0;
                    $avgPrice = 0;
                } else {
                    $totalCost = $totalQuantityBought * $avgPrice;
                }
            }
        }

        return [
            'token_name' => $tokenName,
            'network' => $network,
            'avg_price' => $avgPrice,
            'total_quantity' => $totalQuantityBought,
            'total_cost' => $totalCost,
            'buy_count' => $buyCount,
            'sell_count' => $sellCount,
            'first_buy_date' => $firstBuyDate,
            'last_buy_date' => $lastBuyDate,
        ];
    }

    /**
     * Obter histórico de DCA para um token específico (para gráfico)
     * Retorna preço médio acumulado ao longo do tempo
     */
    public function getDCAHistory(int $userId, string $tokenSymbol): array {
        $stmt = $this->db->prepare("
            SELECT 
                tc.value,
                tc.usd_value_at_tx,
                tc.from_address,
                tc.to_address,
                tc.timestamp,
                w.address as wallet_address
            FROM transactions_cache tc
            JOIN wallets w ON tc.wallet_id = w.id
            WHERE w.user_id = ?
            AND tc.token_symbol = ?
            AND tc.value > 0
            ORDER BY tc.timestamp ASC
        ");
        $stmt->execute([$userId, $tokenSymbol]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = [];
        $runningQuantity = 0;
        $runningCost = 0;
        $avgPrice = 0;

        foreach ($transactions as $tx) {
            $walletAddress = strtolower($tx['wallet_address']);
            $from = strtolower($tx['from_address']);
            $to = strtolower($tx['to_address']);
            $value = (float)$tx['value'];
            $usdValue = (float)($tx['usd_value_at_tx'] ?? 0);

            $isBuy = ($to === $walletAddress);
            $isSell = ($from === $walletAddress);

            if ($isBuy && $value > 0) {
                $runningQuantity += $value;
                $runningCost += $usdValue;
                $avgPrice = $runningQuantity > 0 ? ($runningCost / $runningQuantity) : 0;

                $history[] = [
                    'date' => date('Y-m-d', $tx['timestamp']),
                    'timestamp' => (int)$tx['timestamp'],
                    'avg_price' => round($avgPrice, 6),
                    'total_quantity' => $runningQuantity,
                    'total_cost' => $runningCost,
                ];
            } elseif ($isSell && $value > 0) {
                $runningQuantity -= $value;
                if ($runningQuantity <= 0) {
                    $runningQuantity = 0;
                    $runningCost = 0;
                    $avgPrice = 0;
                } else {
                    // Custo diminui proporcionalmente à venda para manter o preço médio (DCA) intacto
                    $runningCost = $runningQuantity * $avgPrice;
                }

                $history[] = [
                    'date' => date('Y-m-d', $tx['timestamp']),
                    'timestamp' => (int)$tx['timestamp'],
                    'avg_price' => round($avgPrice, 6),
                    'total_quantity' => $runningQuantity,
                    'total_cost' => $runningCost,
                ];
            }
        }

        return $history;
    }

    /**
     * Obter resumo rápido de P&L para o dashboard
     */
    public function getPortfolioPnLSummary(int $userId): array {
        $holdings = $this->calculateForUser($userId);
        
        $totalCost = 0;
        $totalValue = 0;
        $totalPnl = 0;

        foreach ($holdings as $h) {
            $totalCost += $h['total_cost'];
            $totalValue += $h['current_value_usd'];
            $totalPnl += $h['pnl_absolute'];
        }

        $pnlPercent = $totalCost > 0 ? (($totalValue - $totalCost) / $totalCost) * 100 : 0;

        return [
            'total_invested' => $totalCost,
            'total_value' => $totalValue,
            'total_pnl' => $totalPnl,
            'pnl_percent' => $pnlPercent,
            'holdings_count' => count($holdings),
            'top_gainer' => !empty($holdings) ? $this->getTopByPnl($holdings, 'best') : null,
            'top_loser' => !empty($holdings) ? $this->getTopByPnl($holdings, 'worst') : null,
        ];
    }

    /**
     * Obter melhor ou pior token por P&L
     */
    private function getTopByPnl(array $holdings, string $type): ?array {
        if (empty($holdings)) return null;

        usort($holdings, function($a, $b) use ($type) {
            if ($type === 'best') {
                return $b['pnl_percent'] <=> $a['pnl_percent'];
            }
            return $a['pnl_percent'] <=> $b['pnl_percent'];
        });

        $top = $holdings[0];
        return [
            'symbol' => $top['token_symbol'],
            'pnl_percent' => $top['pnl_percent'],
            'pnl_absolute' => $top['pnl_absolute'],
        ];
    }
}
