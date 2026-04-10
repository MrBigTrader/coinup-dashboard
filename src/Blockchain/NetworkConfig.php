<?php
/**
 * NetworkConfig - Configurações de redes EVM suportadas
 * 
 * Contém chain ID, nome, símbolo nativo e endpoints RPC
 * para cada rede suportada pelo CoinUp.
 */

class NetworkConfig {
    
    /**
     * Configurações de todas as redes suportadas
     */
    private const NETWORKS = [
        'ethereum' => [
            'name' => 'Ethereum',
            'chain_id' => 1,
            'chain_id_hex' => '0x1',
            'symbol' => 'ETH',
            'decimals' => 18,
            'explorer' => 'https://etherscan.io',
            'explorer_api' => 'https://api.etherscan.io/api',
        ],
        'bnb' => [
            'name' => 'BNB Smart Chain',
            'chain_id' => 56,
            'chain_id_hex' => '0x38',
            'symbol' => 'BNB',
            'decimals' => 18,
            'explorer' => 'https://bscscan.com',
            'explorer_api' => 'https://api.bscscan.com/api',
        ],
        'arbitrum' => [
            'name' => 'Arbitrum One',
            'chain_id' => 42161,
            'chain_id_hex' => '0xa4b1',
            'symbol' => 'ETH',
            'decimals' => 18,
            'explorer' => 'https://arbiscan.io',
            'explorer_api' => 'https://api.arbiscan.io/api',
        ],
        'base' => [
            'name' => 'Base',
            'chain_id' => 8453,
            'chain_id_hex' => '0x2105',
            'symbol' => 'ETH',
            'decimals' => 18,
            'explorer' => 'https://basescan.org',
            'explorer_api' => 'https://api.basescan.org/api',
        ],
        'polygon' => [
            'name' => 'Polygon',
            'chain_id' => 137,
            'chain_id_hex' => '0x89',
            'symbol' => 'MATIC',
            'decimals' => 18,
            'explorer' => 'https://polygonscan.com',
            'explorer_api' => 'https://api.polygonscan.com/api',
        ],
    ];
    
    /**
     * Obter configuração de uma rede
     * 
     * @param string $network Nome da rede (ethereum, bnb, arbitrum, base, polygon)
     * @return array|null Configuração da rede ou null se não encontrada
     */
    public static function get(string $network): ?array {
        $network = strtolower($network);
        return self::NETWORKS[$network] ?? null;
    }
    
    /**
     * Obter todas as redes suportadas
     * 
     * @return array Lista de configurações
     */
    public static function all(): array {
        return self::NETWORKS;
    }
    
    /**
     * Obter nomes de todas as redes
     * 
     * @return array Lista de nomes
     */
    public static function names(): array {
        return array_keys(self::NETWORKS);
    }
    
    /**
     * Obter chain ID de uma rede
     * 
     * @param string $network Nome da rede
     * @return int|null Chain ID ou null
     */
    public static function getChainId(string $network): ?int {
        $config = self::get($network);
        return $config ? $config['chain_id'] : null;
    }
    
    /**
     * Obter chain ID em hexadecimal
     * 
     * @param string $network Nome da rede
     * @return string|null Chain ID hex ou null
     */
    public static function getChainIdHex(string $network): ?string {
        $config = self::get($network);
        return $config ? $config['chain_id_hex'] : null;
    }
    
    /**
     * Obter símbolo nativo de uma rede
     * 
     * @param string $network Nome da rede
     * @return string|null Símbolo ou null
     */
    public static function getSymbol(string $network): ?string {
        $config = self::get($network);
        return $config ? $config['symbol'] : null;
    }
    
    /**
     * Obter decimais de uma rede
     * 
     * @param string $network Nome da rede
     * @return int|null Decimais ou null
     */
    public static function getDecimals(string $network): ?int {
        $config = self::get($network);
        return $config ? $config['decimals'] : null;
    }
    
    /**
     * Obter URL do explorer de blocos
     * 
     * @param string $network Nome da rede
     * @param string $txHash Hash da transação (opcional)
     * @return string URL do explorer
     */
    public static function getExplorerUrl(string $network, string $txHash = ''): string {
        $config = self::get($network);
        if (!$config) return '#';
        
        return $txHash
            ? $config['explorer'] . '/tx/' . $txHash
            : $config['explorer'];
    }
    
    /**
     * Verificar se uma rede é suportada
     * 
     * @param string $network Nome da rede
     * @return bool True se suportada
     */
    public static function isSupported(string $network): bool {
        return isset(self::NETWORKS[strtolower($network)]);
    }
    
    /**
     * Buscar rede por chain ID
     * 
     * @param int $chainId Chain ID numérico
     * @return string|null Nome da rede ou null
     */
    public static function getByChainId(int $chainId): ?string {
        foreach (self::NETWORKS as $name => $config) {
            if ($config['chain_id'] === $chainId) {
                return $name;
            }
        }
        return null;
    }
}
