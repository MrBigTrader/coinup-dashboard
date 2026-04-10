<?php
/**
 * ExplorerClient - Cliente para APIs Etherscan-based (BscScan, BaseScan, etc.)
 * Revisão: 2026-04-07-ExplorerAPI
 * 
 * Usa a API dos explorers para buscar histórico completo de transações
 * em uma única chamada (ao invés de varrer milhões de blocos via Alchemy).
 * 
 * APIs suportadas com a mesma chave:
 * - Etherscan (Ethereum)
 * - BscScan (BNB Chain)
 * - BaseScan (Base)
 * - Arbiscan (Arbitrum)
 * - Polygonscan (Polygon)
 */

class ExplorerClient {

    /** @var string Chave API dos explorers */
    private $apiKey;

    /** @var string Rede atual */
    private $network;

    /** @var string URL base da API do explorer */
    private $baseUrl;

    /** @var array URLs dos explorers por rede */
    private const EXPLORER_URLS = [
        'ethereum' => 'https://api.etherscan.io/api',
        'bnb'      => 'https://api.bscscan.com/api',
        'arbitrum' => 'https://api.arbiscan.io/api',
        'base'     => 'https://api.basescan.org/api',
        'polygon'  => 'https://api.polygonscan.com/api',
    ];

    /** @var int Timeout em segundos */
    private const TIMEOUT = 15;

    /** @var int Delay entre requests (rate limit: 5 req/seg) */
    private const REQUEST_DELAY = 250000; // 250ms

    /**
     * Construtor
     *
     * @param string $network Rede (ethereum, bnb, arbitrum, base, polygon)
     * @param string $apiKey Chave API
     */
    public function __construct(string $network, string $apiKey) {
        if (!isset(self::EXPLORER_URLS[$network])) {
            throw new InvalidArgumentException("Rede não suportada: $network");
        }

        $this->network = $network;
        $this->apiKey = $apiKey;
        $this->baseUrl = self::EXPLORER_URLS[$network];
    }

    /**
     * Buscar histórico completo de transações nativas (ETH/BNB/MATIC)
     * 
     * Retorna TODAS as transações do endereço em uma única chamada.
     *
     * @param string $address Endereço da carteira
     * @param int $startPage Página inicial (para paginação)
     * @param int $endPage Página final
     * @return array Lista de transações
     */
    public function getFullTransactionHistory(string $address, int $startPage = 1, int $endPage = 10): array {
        $allTxs = [];
        
        for ($page = $startPage; $page <= $endPage; $page++) {
            $url = $this->baseUrl . '?' . http_build_query([
                'module' => 'account',
                'action' => 'txlist',
                'address' => $address,
                'startblock' => 0,
                'endblock' => 99999999,
                'page' => $page,
                'offset' => 10000, // Máximo por página
                'sort' => 'asc',
                'apikey' => $this->apiKey,
            ]);

            $data = $this->request($url);
            
            if ($data['status'] === '1' && isset($data['result'])) {
                $allTxs = array_merge($allTxs, $data['result']);
                
                // Se recebeu menos de 10k, acabou
                if (count($data['result']) < 10000) break;
            } else {
                // Erro ou sem resultados
                if ($page === 1 && $data['message'] === 'No transactions found') {
                    return [];
                }
                break;
            }

            // Delay para respeitar rate limit
            usleep(self::REQUEST_DELAY);
        }

        return $allTxs;
    }

    /**
     * Buscar histórico completo de transfers de tokens ERC-20
     * 
     * @param string $address Endereço da carteira
     * @param int $startPage Página inicial
     * @param int $endPage Página final
     * @return array Lista de transfers
     */
    public function getFullTokenTransfers(string $address, int $startPage = 1, int $endPage = 10): array {
        $allTxs = [];
        
        for ($page = $startPage; $page <= $endPage; $page++) {
            $url = $this->baseUrl . '?' . http_build_query([
                'module' => 'account',
                'action' => 'tokentx',
                'address' => $address,
                'startblock' => 0,
                'endblock' => 99999999,
                'page' => $page,
                'offset' => 10000,
                'sort' => 'asc',
                'apikey' => $this->apiKey,
            ]);

            $data = $this->request($url);
            
            if ($data['status'] === '1' && isset($data['result'])) {
                $allTxs = array_merge($allTxs, $data['result']);
                if (count($data['result']) < 10000) break;
            } else {
                if ($page === 1 && $data['message'] === 'No transactions found') {
                    return [];
                }
                break;
            }

            usleep(self::REQUEST_DELAY);
        }

        return $allTxs;
    }

    /**
     * Buscar transações recentes (últimos N dias) - Modo Turbo
     * 
     * Ideal para "Lazy Sync": sincroniza primeiro os dados recentes
     * para o cliente ver o saldo rapidamente.
     *
     * @param string $address Endereço da carteira
     * @param int $daysBack Quantos dias para trás buscar
     * @return array Lista de transações nativas e tokens
     */
    public function getRecentTransactions(string $address, int $daysBack = 7): array {
        $fromTimestamp = time() - ($daysBack * 86400);
        
        // Buscar transações nativas recentes
        $urlNative = $this->baseUrl . '?' . http_build_query([
            'module' => 'account',
            'action' => 'txlist',
            'address' => $address,
            'startblock' => 0,
            'endblock' => 99999999,
            'page' => 1,
            'offset' => 10000,
            'sort' => 'desc', // Mais recentes primeiro
            'apikey' => $this->apiKey,
        ]);

        $nativeData = $this->request($urlNative);
        $nativeTxs = $nativeData['status'] === '1' ? ($nativeData['result'] ?? []) : [];
        
        // Buscar transfers de tokens recentes
        $urlToken = $this->baseUrl . '?' . http_build_query([
            'module' => 'account',
            'action' => 'tokentx',
            'address' => $address,
            'startblock' => 0,
            'endblock' => 99999999,
            'page' => 1,
            'offset' => 10000,
            'sort' => 'desc',
            'apikey' => $this->apiKey,
        ]);

        $tokenData = $this->request($urlToken);
        $tokenTxs = $tokenData['status'] === '1' ? ($tokenData['result'] ?? []) : [];

        // Filtrar apenas os últimos $daysBack dias
        $now = time();
        $cutoff = $now - ($daysBack * 86400);

        $filterRecent = function($txs) use ($cutoff) {
            return array_filter($txs, function($tx) use ($cutoff) {
                return isset($tx['timeStamp']) && (int)$tx['timeStamp'] >= $cutoff;
            });
        };

        return [
            'native' => array_values($filterRecent($nativeTxs)),
            'tokens' => array_values($filterRecent($tokenTxs)),
        ];
    }

    /**
     * Fazer requisição HTTP com tratamento de erro
     *
     * @param string $url URL completa
     * @return array Dados decodificados
     */
    private function request(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'CoinUp-Dashboard/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return ['status' => '0', 'message' => 'HTTP Error', 'result' => []];
        }

        $data = json_decode($response, true);
        
        // Verificar rate limit ou erro de API
        if ($data['message'] === 'NOTOK') {
            error_log("Explorer API Error ({$this->network}): " . ($data['result'] ?? 'Unknown'));
            
            // Se for rate limit, esperar e retry uma vez
            if (strpos($data['result'], 'Max rate limit') !== false) {
                sleep(2);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => self::TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                return json_decode($response, true) ?: ['status' => '0', 'result' => []];
            }
        }

        return $data;
    }

    /**
     * Obter rede atual
     */
    public function getNetwork(): string {
        return $this->network;
    }

    /**
     * Verificar se a chave API é válida
     */
    public function validateApiKey(): bool {
        // Fazer uma request simples para testar
        $url = $this->baseUrl . '?' . http_build_query([
            'module' => 'stats',
            'action' => 'ethprice',
            'apikey' => $this->apiKey,
        ]);

        $data = $this->request($url);
        return $data['status'] === '1';
    }
}
