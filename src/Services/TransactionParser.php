<?php
/**
 * TransactionParser - Parser de transações Alchemy
 * 
 * Converte dados brutos da API Alchemy em formato padronizado
 * para armazenamento no banco de dados.
 */

class TransactionParser {
    
    /** @var array Contratos DeFi conhecidos */
    private const DEFI_CONTRACTS = [
        'uniswap' => ['0x7a250d5630B4cF539739dF2C5dAcb4c659F2488D', '0xE592427A0AEce92De3Edee1F18E0157C05861564'],
        'pancakeswap' => ['0x10ED43C718714eb63d5aA57B78B54704E256024E', '0x13f4EA83D0bd40E75C8222255bc855a974568Dd4'],
        'aave' => ['0x7d2768dE32b0b80b7a3454c06BdAc94A69DDc7A9'],
        'venus' => ['0xfD36E2c2a6789Db23113685031d7F16329158384'],
        'morpho' => ['0x777777c9898D384F785Ee44Acfe945efDFf5f3E0'],
        'curve' => ['0x99a58482BD75cbab83b27EC03CA68fF489b5788f'],
    ];
    
    /** @var array Bridges conhecidas */
    private const BRIDGE_CONTRACTS = [
        'relay' => ['0x53773E034d9784153471813dacAFF53dBBA78021'],
        'debridge' => ['0x43dE2d77BF8027e25dBD179B491e8d64f38398aA'],
        'hop' => ['0x3666f603Cc164936C1b87e207F36BEBa4AC5f18a'],
        'across' => ['0x5C7BCd6E7De5423a257D81B442095A1a6ced35C5'],
    ];
    
    /**
     * Parsear transfer nativo (ETH/BNB/MATIC)
     * 
     * @param array $transfer Dados brutos da Alchemy
     * @param string $network Nome da rede
     * @param string $nativeSymbol Símbolo nativo (ETH, BNB, MATIC)
     * @return array Dados padronizados
     */
    public static function parseNativeTransfer(array $transfer, string $network, string $nativeSymbol): array {
        $decimals = 18;
        $rawValue = $transfer['rawContract']['value'] ?? '0x0';
        $value = self::hexToDecimal($rawValue, $decimals);
        
        return [
            'tx_hash' => $transfer['hash'] ?? '',
            'block_number' => isset($transfer['blockNum']) ? hexdec($transfer['blockNum']) : 0,
            'timestamp' => isset($transfer['blockTimestamp']) ? strtotime($transfer['blockTimestamp']) : time(),
            'from_address' => strtolower($transfer['from'] ?? ''),
            'to_address' => strtolower($transfer['to'] ?? ''),
            'value' => $value,
            'token_address' => null,
            'token_symbol' => $nativeSymbol,
            'token_name' => $nativeSymbol,
            'token_decimals' => $decimals,
            'transaction_type' => self::detectTransactionType($transfer, 'native'),
            'defi_protocol' => self::detectDeFiProtocol($transfer),
            'gas_used' => isset($transfer['gasUsed']) ? hexdec($transfer['gasUsed']) : null,
            'gas_price' => isset($transfer['gasPrice']) ? hexdec($transfer['gasPrice']) : null,
            'status' => 'confirmed',
            'usd_value_at_tx' => null, // Será calculado depois
            'raw_data' => json_encode($transfer),
        ];
    }
    
    /**
     * Parsear transfer de token ERC-20
     * 
     * @param array $transfer Dados brutos da Alchemy
     * @param string $network Nome da rede
     * @return array Dados padronizados
     */
    public static function parseTokenTransfer(array $transfer, string $network): array {
        $tokenInfo = $transfer['rawContract'] ?? [];
        $decimals = isset($tokenInfo['decimals']) ? (int) $tokenInfo['decimals'] : 18;
        $rawValue = $tokenInfo['value'] ?? '0x0';
        $value = self::hexToDecimal($rawValue, $decimals);
        
        // Tentar extrair símbolo do asset field
        $symbol = $transfer['asset'] ?? $tokenInfo['address'] ?? '';
        // Limpar símbolo (remover sufixos como .e, .n, etc.)
        $symbol = preg_replace('/\.[a-z]$/', '', $symbol);
        
        return [
            'tx_hash' => $transfer['hash'] ?? '',
            'block_number' => isset($transfer['blockNum']) ? hexdec($transfer['blockNum']) : 0,
            'timestamp' => isset($transfer['blockTimestamp']) ? strtotime($transfer['blockTimestamp']) : time(),
            'from_address' => strtolower($transfer['from'] ?? ''),
            'to_address' => strtolower($transfer['to'] ?? ''),
            'value' => $value,
            'token_address' => isset($tokenInfo['address']) ? strtolower($tokenInfo['address']) : null,
            'token_symbol' => strtoupper($symbol),
            'token_name' => $transfer['asset'] ?? null,
            'token_decimals' => $decimals,
            'transaction_type' => self::detectTransactionType($transfer, 'erc20'),
            'defi_protocol' => self::detectDeFiProtocol($transfer),
            'gas_used' => isset($transfer['gasUsed']) ? hexdec($transfer['gasUsed']) : null,
            'gas_price' => isset($transfer['gasPrice']) ? hexdec($transfer['gasPrice']) : null,
            'status' => 'confirmed',
            'usd_value_at_tx' => null,
            'raw_data' => json_encode($transfer),
        ];
    }
    
    /**
     * Detectar tipo de transação (transfer, swap, deposit, withdraw, bridge, defi)
     * 
     * @param array $transfer Dados brutos
     * @param string $transferType Tipo base (native ou erc20)
     * @return string Tipo detectado
     */
    private static function detectTransactionType(array $transfer, string $transferType): string {
        $to = strtolower($transfer['to'] ?? '');
        $from = strtolower($transfer['from'] ?? '');
        
        // Verificar se é bridge
        foreach (self::BRIDGE_CONTRACTS as $protocol => $addresses) {
            foreach ($addresses as $addr) {
                if (strtolower($addr) === $to || strtolower($addr) === $from) {
                    return 'bridge';
                }
            }
        }
        
        // Verificar se é DeFi
        foreach (self::DEFI_CONTRACTS as $protocol => $addresses) {
            foreach ($addresses as $addr) {
                if (strtolower($addr) === $to || strtolower($addr) === $from) {
                    return 'defi';
                }
            }
        }
        
        // Se é erc20 e o to é um contrato conhecido de DEX
        if ($transferType === 'erc20') {
            // Verificar se é swap (to é um router de DEX)
            $allDexRouters = array_merge(...array_values(self::DEFI_CONTRACTS));
            foreach ($allDexRouters as $router) {
                if (strtolower($router) === $to) {
                    return 'swap';
                }
            }
        }
        
        return 'transfer';
    }
    
    /**
     * Detectar protocolo DeFi envolvido na transação
     * 
     * @param array $transfer Dados brutos
     * @return string|null Nome do protocolo ou null
     */
    private static function detectDeFiProtocol(array $transfer): ?string {
        $to = strtolower($transfer['to'] ?? '');
        $from = strtolower($transfer['from'] ?? '');
        
        $allContracts = array_merge(self::DEFI_CONTRACTS, self::BRIDGE_CONTRACTS);
        
        foreach ($allContracts as $protocol => $addresses) {
            foreach ($addresses as $addr) {
                if (strtolower($addr) === $to || strtolower($addr) === $from) {
                    return $protocol;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Converter valor hex para decimal
     * 
     * @param string $hex Valor em hex
     * @param int $decimals Decimais do token
     * @return string Valor decimal como string
     */
    private static function hexToDecimal(string $hex, int $decimals): string {
        if (empty($hex) || $hex === '0x') return '0';
        
        $wei = hexdec($hex);
        return WeiConverter::weiToDecimal($wei, $decimals);
    }
    
    /**
     * Verificar se transferência é para a wallet (recebimento) ou da wallet (envio)
     * 
     * @param array $transfer Dados brutos
     * @param string $walletAddress Endereço da wallet sendo monitorada
     * @return string 'in' ou 'out'
     */
    public static function getDirection(array $transfer, string $walletAddress): string {
        $from = strtolower($transfer['from'] ?? '');
        $wallet = strtolower($walletAddress);
        
        return $from === $wallet ? 'out' : 'in';
    }
    
    /**
     * Verificar se transação é um self-transfer (mesma origem e destino)
     * 
     * @param array $transfer Dados brutos
     * @return bool
     */
    public static function isSelfTransfer(array $transfer): bool {
        return strtolower($transfer['from'] ?? '') === strtolower($transfer['to'] ?? '');
    }
}
