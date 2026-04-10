<?php
/**
 * Adicionar Carteira - CoinUp Dashboard
 * Modelo híbrido: Web3 (MetaMask) + Manual
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

Middleware::requireAuth();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

if (!$auth->isClient()) {
    header('Location: /main/public/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Carteira - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        .add-wallet-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .method-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .method-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .method-card:hover {
            border-color: rgba(168, 85, 247, 0.5);
            background: rgba(168, 85, 247, 0.1);
        }

        .method-card.active {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.15);
        }

        .method-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .method-card h3 {
            color: #fff;
            margin-bottom: 10px;
        }

        .method-card p {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .web3-status {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }

        .web3-status.success {
            background: rgba(74, 222, 128, 0.1);
            border-color: rgba(74, 222, 128, 0.3);
        }

        .web3-status.error {
            background: rgba(248, 113, 113, 0.1);
            border-color: rgba(248, 113, 113, 0.3);
        }

        .network-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .network-option {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .network-option:hover {
            border-color: rgba(168, 85, 247, 0.5);
        }

        .network-option.active {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.15);
        }

        .network-option .icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .network-option .label {
            color: #e2e8f0;
            font-size: 0.85rem;
        }

        .address-preview {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            font-family: 'Courier New', monospace;
            color: #a855f7;
            word-break: break-all;
            margin-top: 10px;
        }

        .btn-connect {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f6851b, #e2761b);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-connect:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(246, 133, 27, 0.4);
        }

        .btn-connect:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #64748b;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider span {
            padding: 0 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container" style="display: flex; min-height: 100vh;">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🪙 CoinUp</h1>
            </div>

            <ul class="nav-menu">
                <li><a href="/main/public/dashboard.php"><span>📊 Overview</span></a></li>
                <li><a href="/main/public/my-wallets.php" class="active"><span>🔗 Minhas Carteiras</span></a></li>
                <li><a href="/main/public/assets.php"><span>💼 Assets</span></a></li>
                <li><a href="/main/public/transactions.php"><span>📝 Transactions</span></a></li>
                <li><a href="/main/public/market.php"><span>📈 Market</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small><?= htmlspecialchars($user['email']) ?></small>
                <a href="/main/public/logout.php" style="margin-top: 10px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; border-radius: 8px; cursor: pointer; width: 100%; text-decoration: none; display: block; text-align: center;">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Adicionar Carteira</h2>
                    <p>Escolha como adicionar sua carteira EVM</p>
                </div>
                <a href="/main/public/my-wallets.php" class="btn btn-secondary">← Voltar</a>
            </div>

            <div class="add-wallet-container">
                <!-- Método de Adição -->
                <div class="method-selector">
                    <div class="method-card active" onclick="selectMethod('web3')">
                        <div class="method-icon">🦊</div>
                        <h3>Conectar Carteira</h3>
                        <p>Conecte via MetaMask ou Web3</p>
                    </div>
                    <div class="method-card" onclick="selectMethod('manual')">
                        <div class="method-icon">✏️</div>
                        <h3>Digitar Endereço</h3>
                        <p>Adicione manualmente</p>
                    </div>
                </div>

                <!-- Formulário Web3 -->
                <div id="web3-form" class="form-section active">
                    <div class="card">
                        <h3 style="color: #fff; margin-bottom: 20px;">🦊 Conectar com MetaMask</h3>
                        
                        <div id="web3-status" class="web3-status">
                            <p id="web3-message">Clique no botão abaixo para conectar sua carteira</p>
                        </div>

                        <div id="wallet-info" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Endereço Detectado</label>
                                <div class="address-preview" id="detected-address"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Rede</label>
                                <select id="web3-network" class="form-select">
                                    <option value="ethereum">Ethereum</option>
                                    <option value="bnb">BNB Chain</option>
                                    <option value="arbitrum">Arbitrum</option>
                                    <option value="base">Base</option>
                                    <option value="polygon">Polygon</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Label (opcional)</label>
                                <input type="text" id="web3-label" class="form-input" placeholder="Ex: Minha carteira principal">
                            </div>

                            <button type="button" class="btn btn-primary" onclick="saveWeb3Wallet()" style="width: 100%;">
                                💾 Salvar Carteira
                            </button>
                        </div>

                        <button type="button" id="btn-connect" class="btn-connect" onclick="connectWallet()">
                            <span>🦊</span>
                            <span>Conectar Carteira</span>
                        </button>
                    </div>
                </div>

                <!-- Formulário Manual -->
                <div id="manual-form" class="form-section">
                    <div class="card">
                        <h3 style="color: #fff; margin-bottom: 20px;">✏️ Adicionar Manualmente</h3>

                        <form id="manual-wallet-form" onsubmit="saveManualWallet(event)">
                            <div class="form-group">
                                <label class="form-label">Rede *</label>
                                <div class="network-grid">
                                    <div class="network-option" onclick="selectNetwork('ethereum')">
                                        <div class="icon">⟠</div>
                                        <div class="label">Ethereum</div>
                                    </div>
                                    <div class="network-option" onclick="selectNetwork('bnb')">
                                        <div class="icon">🟡</div>
                                        <div class="label">BNB Chain</div>
                                    </div>
                                    <div class="network-option" onclick="selectNetwork('arbitrum')">
                                        <div class="icon">🔵</div>
                                        <div class="label">Arbitrum</div>
                                    </div>
                                    <div class="network-option" onclick="selectNetwork('base')">
                                        <div class="icon">🔷</div>
                                        <div class="label">Base</div>
                                    </div>
                                    <div class="network-option" onclick="selectNetwork('polygon')">
                                        <div class="icon">🟣</div>
                                        <div class="label">Polygon</div>
                                    </div>
                                </div>
                                <input type="hidden" id="manual-network" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Endereço da Carteira *</label>
                                <input
                                    type="text"
                                    id="manual-address"
                                    class="form-input"
                                    placeholder="0x..."
                                    required
                                    pattern="^0x[a-fA-F0-9]{40}$"
                                    title="Endereço EVM válido (0x seguido de 40 caracteres hexadecimais)"
                                >
                                <small class="form-hint">Formato: 0x seguido de 40 caracteres (ex: 0x742d35Cc6634C0532925a3b844Bc9e7595f2bD38)</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Label (opcional)</label>
                                <input
                                    type="text"
                                    id="manual-label"
                                    class="form-input"
                                    placeholder="Ex: Carteira de investimentos"
                                >
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                💾 Salvar Carteira
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let selectedMethod = 'web3';
        let connectedAddress = '';
        let selectedNetwork = '';

        function selectMethod(method) {
            selectedMethod = method;
            
            // Atualizar cards
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Mostrar formulário correto
            document.querySelectorAll('.form-section').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(method + '-form').classList.add('active');
        }

        function selectNetwork(network) {
            selectedNetwork = network;
            document.getElementById('manual-network').value = network;
            
            // Atualizar visual
            document.querySelectorAll('.network-option').forEach(opt => {
                opt.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
        }

        async function connectWallet() {
            const btn = document.getElementById('btn-connect');
            const status = document.getElementById('web3-status');
            const message = document.getElementById('web3-message');
            
            if (typeof window.ethereum === 'undefined') {
                status.className = 'web3-status error';
                status.style.display = 'block';
                message.textContent = 'MetaMask não detectado! Instale o MetaMask para continuar.';
                return;
            }

            try {
                btn.disabled = true;
                btn.innerHTML = '<span>⏳</span><span>Conectando...</span>';
                
                status.className = 'web3-status';
                status.style.display = 'block';
                message.textContent = 'Aprovando conexão no MetaMask...';
                
                // Solicitar acesso às contas
                const accounts = await window.ethereum.request({
                    method: 'eth_requestAccounts'
                });
                
                connectedAddress = accounts[0];
                
                // Obter chain ID
                const chainId = await window.ethereum.request({
                    method: 'eth_chainId'
                });
                
                // Mapear chain ID para rede
                const networkMap = {
                    '0x1': 'ethereum',
                    '0x38': 'bnb',
                    '0xa4b1': 'arbitrum',
                    '0x2105': 'base',
                    '0x89': 'polygon'
                };
                
                const network = networkMap[chainId] || 'ethereum';
                
                // Mostrar informações da carteira
                status.className = 'web3-status success';
                message.textContent = '✅ Carteira conectada com sucesso!';
                
                document.getElementById('detected-address').textContent = connectedAddress;
                document.getElementById('web3-network').value = network;
                document.getElementById('wallet-info').style.display = 'block';
                btn.style.display = 'none';
                
            } catch (error) {
                status.className = 'web3-status error';
                message.textContent = '❌ Erro: ' + error.message;
                btn.disabled = false;
                btn.innerHTML = '<span>🦊</span><span>Conectar Carteira</span>';
            }
        }

        async function saveWeb3Wallet() {
            const network = document.getElementById('web3-network').value;
            const label = document.getElementById('web3-label').value;
            
            if (!connectedAddress) {
                alert('Endereço não detectado!');
                return;
            }
            
            try {
                const response = await fetch('/main/public/api/add-wallet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        address: connectedAddress,
                        network: network,
                        label: label
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/main/public/my-wallets.php?success=Carteira adicionada com sucesso!';
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                alert('Erro ao salvar carteira');
                console.error('Error:', error);
            }
        }

        async function saveManualWallet(event) {
            event.preventDefault();
            
            const network = document.getElementById('manual-network').value;
            const address = document.getElementById('manual-address').value;
            const label = document.getElementById('manual-label').value;
            
            if (!network) {
                alert('Selecione uma rede!');
                return;
            }
            
            // Validar formato do endereço
            if (!/^0x[a-fA-F0-9]{40}$/.test(address)) {
                alert('Endereço EVM inválido! O formato correto é 0x seguido de 40 caracteres hexadecimais.');
                return;
            }
            
            try {
                const response = await fetch('/main/public/api/add-wallet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        address: address,
                        network: network,
                        label: label
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = '/main/public/my-wallets.php?success=Carteira adicionada com sucesso!';
                } else {
                    alert('Erro: ' + data.message);
                }
            } catch (error) {
                alert('Erro ao salvar carteira');
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>
