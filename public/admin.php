<?php
/**
 * Painel do Administrador - CoinUp
 * Visão consolidada de todos os clientes
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/middleware.php';

// Requer autenticação e perfil de admin
Middleware::requireAuth();
Middleware::requireAdmin();

$auth = Auth::getInstance();
$user = $auth->getCurrentUser();

$db = Database::getInstance()->getConnection();

// Buscar todos os clientes com patrimônio
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.created_at,
        COUNT(DISTINCT w.id) as wallet_count,
        u.status
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id AND w.is_active = 1
    WHERE u.role = 'client' AND u.status != 'deleted'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$clients = $stmt->fetchAll();

// Resumo geral
$stmt = $db->query("
    SELECT 
        COUNT(DISTINCT u.id) as total_clients,
        COUNT(DISTINCT w.id) as total_wallets
    FROM users u
    LEFT JOIN wallets w ON u.id = w.user_id AND w.is_active = 1
    WHERE u.role = 'client' AND u.status != 'deleted'
");
$summary = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - CoinUp</title>
    <link rel="stylesheet" href="/main/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            color: #e2e8f0;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .logo h1 {
            color: #fff;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-menu {
            list-style: none;
            flex: 1;
        }

        .nav-menu li {
            margin-bottom: 8px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .nav-menu a span {
            margin-left: 10px;
        }

        .user-info {
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info p {
            font-size: 0.9rem;
            color: #e2e8f0;
        }

        .user-info small {
            color: #64748b;
        }

        .btn-logout {
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: #fff;
            font-size: 1.8rem;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #a855f7, #3b82f6);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(168, 85, 247, 0.4);
        }

        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
        }

        .card-title {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .card-value {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
        }

        /* Table */
        .table-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            color: #fff;
            font-size: 1.2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        th {
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.85rem;
        }

        td {
            color: #e2e8f0;
            font-size: 0.9rem;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
        }

        .status-inactive {
            background: rgba(248, 113, 113, 0.2);
            color: #f87171;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            margin-right: 5px;
            display: inline-block;
        }

        .btn-view {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-edit {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h1>🪙 CoinUp</h1>
            </div>

            <ul class="nav-menu">
                <li><a href="/main/public/admin.php" class="active"><span>👥 Clientes</span></a></li>
                <li><a href="/main/public/admin-wallets.php"><span>🔗 Carteiras</span></a></li>
                <li><a href="/main/public/admin-sync.php"><span>🔄 Sincronização</span></a></li>
                <li><a href="/main/public/sync-manual.php"><span>🔧 Sync Manual</span></a></li>
                <li><a href="/main/public/admin-logs.php"><span>📋 Logs</span></a></li>
            </ul>

            <div class="user-info">
                <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                <small>Administrador</small>
                <a href="/main/public/logout.php" class="btn-logout">Sair</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h2>Painel do Administrador</h2>
                    <p>Gerencie clientes e carteiras</p>
                </div>
                <a href="/main/public/admin-client-add.php" class="btn-primary">+ Novo Cliente</a>
            </div>

            <!-- Cards de Resumo -->
            <div class="cards-grid">
                <div class="card">
                    <div class="card-title">Total de Clientes</div>
                    <div class="card-value"><?= $summary['total_clients'] ?></div>
                </div>

                <div class="card">
                    <div class="card-title">Carteiras Ativas</div>
                    <div class="card-value"><?= $summary['total_wallets'] ?></div>
                </div>

                <div class="card">
                    <div class="card-title">Patrimônio Total</div>
                    <div class="card-value" style="font-size: 1.5rem;">Em cálculo</div>
                </div>
            </div>

            <!-- Tabela de Clientes -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Clientes Cadastrados</h3>
                </div>

                <?php if (count($clients) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Carteiras</th>
                                <th>Status</th>
                                <th>Criado Em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= htmlspecialchars($client['name']) ?></td>
                                    <td><?= htmlspecialchars($client['email']) ?></td>
                                    <td><?= $client['wallet_count'] ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $client['status'] ?>">
                                            <?= ucfirst($client['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td>
                                    <td>
                                        <a href="/main/public/admin-client-view.php?id=<?= $client['id'] ?>" class="btn-action btn-view">Ver Dashboard</a>
                                        <a href="/main/public/admin-client-edit.php?id=<?= $client['id'] ?>" class="btn-action btn-edit">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p style="font-size: 3rem;">📭</p>
                        <p>Nenhum cliente cadastrado</p>
                        <small>Clique em "Novo Cliente" para começar</small>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
