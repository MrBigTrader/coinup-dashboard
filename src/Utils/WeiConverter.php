<?php
/**
 * WeiConverter - Utilitário para conversão entre Wei e unidades decimais
 * 
 * Wei é a menor unidade de Ethereum (1 ETH = 10^18 Wei)
 * Suporta todas as redes EVM com 18 decimais padrão
 */

class WeiConverter {
    
    /**
     * Converter Wei para decimal (ex: 1000000000000000000 → 1.0)
     * 
     * @param string|int $wei Valor em Wei
     * @param int $decimals Número de decimais (padrão 18)
     * @return string Valor decimal como string para precisão
     */
    public static function weiToDecimal($wei, int $decimals = 18): string {
        $wei = (string) $wei;
        
        // Remover sinal negativo se existir
        $negative = false;
        if (strpos($wei, '-') === 0) {
            $negative = true;
            $wei = substr($wei, 1);
        }
        
        // Preencher com zeros à esquerda se necessário
        $wei = str_pad($wei, $decimals + 1, '0', STR_PAD_LEFT);
        
        // Separar parte inteira e decimal
        $integerPart = substr($wei, 0, - $decimals);
        $decimalPart = substr($wei, - $decimals);
        
        // Remover zeros à direita da parte decimal
        $decimalPart = rtrim($decimalPart, '0');
        
        // Montar resultado
        $result = $integerPart;
        if ($decimalPart !== '') {
            $result .= '.' . $decimalPart;
        }
        
        return $negative ? '-' . $result : $result;
    }
    
    /**
     * Converter decimal para Wei (ex: 1.5 → 1500000000000000000)
     * 
     * @param string|float $decimal Valor decimal
     * @param int $decimals Número de decimais (padrão 18)
     * @return string Valor em Wei como string
     */
    public static function decimalToWei($decimal, int $decimals = 18): string {
        $decimal = (string) $decimal;
        
        // Remover sinal negativo se existir
        $negative = false;
        if (strpos($decimal, '-') === 0) {
            $negative = true;
            $decimal = substr($decimal, 1);
        }
        
        // Separar parte inteira e decimal
        if (strpos($decimal, '.') !== false) {
            list($integerPart, $decimalPart) = explode('.', $decimal, 2);
            // Truncar ou preencher parte decimal
            $decimalPart = str_pad(substr($decimalPart, 0, $decimals), $decimals, '0', STR_PAD_RIGHT);
        } else {
            $integerPart = $decimal;
            $decimalPart = str_repeat('0', $decimals);
        }
        
        $result = $integerPart . $decimalPart;
        
        // Remover zeros à esquerda
        $result = ltrim($result, '0');
        if ($result === '') {
            $result = '0';
        }
        
        return $negative ? '-' . $result : $result;
    }
    
    /**
     * Formatar Wei para exibição legível (ex: 1.5 ETH)
     * 
     * @param string|int $wei Valor em Wei
     * @param string $symbol Símbolo do token (ex: ETH)
     * @param int $decimals Número de decimais
     * @param int $precision Precisão de exibição
     * @return string Valor formatado
     */
    public static function formatWei($wei, string $symbol = '', int $decimals = 18, int $precision = 6): string {
        $decimal = self::weiToDecimal($wei, $decimals);
        $formatted = number_format((float) $decimal, $precision, '.', ',');
        
        return $symbol ? $formatted . ' ' . $symbol : $formatted;
    }
    
    /**
     * Obter decimais padrão por tipo de rede
     * 
     * @return array Mapa de rede → decimais
     */
    public static function getDefaultDecimals(): array {
        return [
            'ethereum' => 18,
            'bnb' => 18,
            'arbitrum' => 18,
            'base' => 18,
            'polygon' => 18,
        ];
    }
}
