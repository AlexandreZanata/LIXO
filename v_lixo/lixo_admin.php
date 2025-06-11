<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo_adm') {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$mensagem = '';
$status = '';

// Períodos de trabalho predefinidos
$periodos_trabalho = [
    'MANHÃ - Segunda - Quarta - Sexta',
    'MANHÃ - Terça - Quinta - Sábado',
    'TARDE - Segunda - Quarta - Sexta',
    'TARDE - Terça - Quinta - Sábado',
    'NOITE - Segunda a Sexta'
];

// Processar exclusão de configuração
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $config_id = $_GET['id'];

    try {
        // Buscar nome do motorista antes de excluir (para mensagem de confirmação)
        $nome_query = "SELECT nome_usuario FROM config_motoristas_lixo WHERE id = :id LIMIT 1";
        $nome_stmt = $conn->prepare($nome_query);
        $nome_stmt->bindParam(':id', $config_id, PDO::PARAM_INT);
        $nome_stmt->execute();
        $nome_motorista = $nome_stmt->fetchColumn();

        // Excluir configuração
        $delete_query = "DELETE FROM config_motoristas_lixo WHERE id = :id";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':id', $config_id, PDO::PARAM_INT);
        $delete_stmt->execute();

        $mensagem = "Configuração excluída com sucesso para o motorista: $nome_motorista";
        $status = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir configuração: " . $e->getMessage();
        $status = 'error';
    }
}

// Buscar todos os usuários que têm role = 'lixo'
try {
    $query_usuarios = "SELECT id, name FROM usuarios WHERE role = 'lixo' ORDER BY name";
    $stmt_usuarios = $conn->prepare($query_usuarios);
    $stmt_usuarios->execute();
    $motoristas_lixo = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar usuários: " . $e->getMessage();
    $status = 'error';
}

// Buscar rotas existentes
try {
    $query_rotas = "SELECT DISTINCT rota_nome FROM rotas_lixo ORDER BY rota_nome";
    $stmt_rotas = $conn->prepare($query_rotas);
    $stmt_rotas->execute();
    $rotas = $stmt_rotas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar rotas: " . $e->getMessage();
    $status = 'error';
}

// Buscar veículos existentes com seus pesos
try {
    $query_veiculos = "SELECT prefixo, veiculo_peso FROM veiculos_lixo WHERE prefixo IS NOT NULL AND prefixo != '' ORDER BY prefixo";
    $stmt_veiculos = $conn->prepare($query_veiculos);
    $stmt_veiculos->execute();
    $veiculos = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar veículos: " . $e->getMessage();
    $status = 'error';
}

// Tipos de lixo pré-definidos
$tipos_lixo = ['UMIDO', 'SECO', 'RECICLÁVEL'];

// Processar formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $usuario_id = $_POST['usuario_id'];
        $nome_usuario = $_POST['nome_usuario'];
        $rota = $_POST['rota'];
        $periodo_trabalho = $_POST['periodo_trabalho'];
        $tipo_lixo = $_POST['tipo_lixo'];
        $prefixo_veiculo = $_POST['prefixo_veiculo'];
        $peso_caminhao_vazio = $_POST['peso_caminhao_vazio'];
        $bairros_json = $_POST['bairros_json'] ?? '[]'; // Adicionado esta linha

        // Verificar se já existe uma configuração para este usuário
        $check_query = "SELECT id FROM config_motoristas_lixo WHERE usuario_id = :usuario_id LIMIT 1";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            // Atualizar configuração existente
            $config_id = $check_stmt->fetchColumn();
            $update_query = "UPDATE config_motoristas_lixo SET
                            rota = :rota,
                            periodo_trabalho = :periodo_trabalho,
                            tipo_lixo = :tipo_lixo,
                            prefixo_veiculo = :prefixo_veiculo,
                            peso_caminhao_vazio = :peso_caminhao_vazio,
                            bairros = :bairros_json
                            WHERE id = :id";

            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
            $update_stmt->bindParam(':periodo_trabalho', $periodo_trabalho, PDO::PARAM_STR);
            $update_stmt->bindParam(':tipo_lixo', $tipo_lixo, PDO::PARAM_STR);
            $update_stmt->bindParam(':prefixo_veiculo', $prefixo_veiculo, PDO::PARAM_STR);
            $update_stmt->bindParam(':peso_caminhao_vazio', $peso_caminhao_vazio, PDO::PARAM_STR);
            $update_stmt->bindParam(':bairros_json', $bairros_json, PDO::PARAM_STR); // Adicionado esta linha
            $update_stmt->bindParam(':id', $config_id, PDO::PARAM_INT);
            $update_stmt->execute();

            $mensagem = "Configuração atualizada com sucesso para o motorista: $nome_usuario";
            $status = 'success';
        } else {
            // Inserir nova configuração
            $insert_query = "INSERT INTO config_motoristas_lixo
                            (usuario_id, nome_usuario, rota, periodo_trabalho, tipo_lixo, prefixo_veiculo, peso_caminhao_vazio, bairros)
                            VALUES
                            (:usuario_id, :nome_usuario, :rota, :periodo_trabalho, :tipo_lixo, :prefixo_veiculo, :peso_caminhao_vazio, :bairros_json)";

            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':nome_usuario', $nome_usuario, PDO::PARAM_STR);
            $insert_stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
            $insert_stmt->bindParam(':periodo_trabalho', $periodo_trabalho, PDO::PARAM_STR);
            $insert_stmt->bindParam(':tipo_lixo', $tipo_lixo, PDO::PARAM_STR);
            $insert_stmt->bindParam(':prefixo_veiculo', $prefixo_veiculo, PDO::PARAM_STR);
            $insert_stmt->bindParam(':peso_caminhao_vazio', $peso_caminhao_vazio, PDO::PARAM_STR);
            $insert_stmt->bindParam(':bairros_json', $bairros_json, PDO::PARAM_STR); // Adicionado esta linha
            $insert_stmt->execute();

            $mensagem = "Configuração criada com sucesso para o motorista: $nome_usuario";
            $status = 'success';
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar configuração: " . $e->getMessage();
        $status = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Administração de Coleta de Lixo</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#4F46E5',
                        'primary-dark': '#4338CA',
                        'secondary': '#F59E0B',
                        'accent': '#10B981',
                        'success': '#10B981',
                        'warning': '#F59E0B',
                        'danger': '#EF4444',
                    },
                    boxShadow: {
                        'soft': '0 4px 24px -6px rgba(0, 0, 0, 0.1)',
                        'hard': '0 8px 24px -6px rgba(79, 70, 229, 0.3)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
        }
        .header-container {
            width: 100%;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            display: flex;
            justify-content: center;
            box-shadow: 0 4px 12px -2px rgba(79, 70, 229, 0.3);
        }
        .logo-container {
            height: 12rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 10px;
        }
        .app-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .content-container {
            width: 100%;
            max-width: 900px;
            margin-top: -3rem;
            padding: 0 1.25rem;
            flex: 1;
            margin-bottom: 2rem;
        }
        .input-field {
            transition: all 0.2s ease;
            position: relative;
        }
        .input-field:focus-within {
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn-primary {
            background-color: #4F46E5;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(79, 70, 229, 0.25);
        }
        .btn-danger {
            background-color: #EF4444;
            transition: all 0.2s ease;
        }
        .btn-danger:hover {
            background-color: #DC2626;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.25);
        }
        .forms-container {
            width: 100%;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
        }
        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            text-align: center;
            background: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            font-size: 16px;
            font-weight: 500;
            opacity: 1;
            transition: opacity 0.5s ease-in-out;
            z-index: 9999;
        }
        .success {
            border-left: 5px solid #10B981;
            color: #10B981;
        }
        .error {
            border-left: 5px solid #EF4444;
            color: #EF4444;
        }
        table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }
        .back-button {
            position: fixed;
            top: 1.25rem;
            left: 1.25rem;
            z-index: 1000;
            background-color: #FFFFFF;
            color: #4F46E5;
            width: 44px;
            height: 44px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .back-button:hover {
            background-color: #4F46E5;
            color: #FFFFFF;
            border-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px -3px rgba(79, 70, 229, 0.4);
        }
        input:disabled, select:disabled {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }
        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            user-select: none;
            position: relative;
            overflow: hidden;
        }
        .nav-button:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .nav-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: transparent;
            pointer-events: none;
        }
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-backdrop.active {
            opacity: 1;
            visibility: visible;
        }
        .modal {
            background-color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .modal-backdrop.active .modal {
            transform: translateY(0);
        }
        .delete-btn {
            transition: all 0.2s ease;
            opacity: 0.8;
        }
        .delete-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <a href="../menu_lixo_adm.php" class="back-button" aria-label="Voltar ao menu">
        <i class="fas fa-arrow-left"></i>
    </a>
    <div class="app-container">
        <div class="header-container">
            <div class="logo-container">
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-trash-alt text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-8">Administração de Coleta de Lixo</h1>
            </div>
        </div>

        <div class="content-container">
            <?php if (!empty($mensagem)): ?>
                <div class="message-container <?php echo $status; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <div class="flex space-x-4 mb-6">
                <a href="rotas_admin.php" class="nav-button btn-primary text-white flex items-center justify-center py-2 px-4 rounded-xl" role="button">
                    <i class="fas fa-route mr-2"></i> Painel de Rotas
                </a>
                <a href="veiculos_admin.php" class="nav-button btn-primary text-white flex items-center justify-center py-2 px-4 rounded-xl" role="button">
                    <i class="fas fa-truck mr-2"></i> Painel de Veículos
                </a>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6">
                <h2 class="text-xl font-bold mb-4">Configurar Motorista de Lixo</h2>

                <form method="POST" id="configForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motorista</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-user"></i>
                            </div>
                            <select
                                name="usuario_id"
                                id="usuario_id"
                                class="w-full bg-transparent focus:outline-none"
                                required
                            >
                                <option value="">Selecione um motorista</option>
                                <?php foreach ($motoristas_lixo as $motorista): ?>
                                    <option value="<?php echo $motorista['id']; ?>" data-name="<?php echo $motorista['name']; ?>"><?php echo $motorista['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="nome_usuario" id="nome_usuario" value="">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rota</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-route"></i>
                            </div>
                            <select name="rota" id="rota" class="w-full bg-transparent focus:outline-none" required>
                                <option value="">Selecione uma rota</option>
                                <?php foreach ($rotas as $rota): ?>
                                    <option value="<?php echo htmlspecialchars($rota); ?>"><?php echo htmlspecialchars($rota); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Período de Trabalho</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <select
                                name="periodo_trabalho"
                                id="periodo_trabalho"
                                class="w-full bg-transparent focus:outline-none"
                                required
                            >
                                <option value="">Selecione um período</option>
                                <?php foreach ($periodos_trabalho as $periodo): ?>
                                    <option value="<?php echo htmlspecialchars($periodo); ?>"><?php echo htmlspecialchars($periodo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Lixo</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-trash"></i>
                            </div>
                            <select
                                name="tipo_lixo"
                                id="tipo_lixo"
                                class="w-full bg-transparent focus:outline-none"
                                required
                            >
                                <option value="">Selecione o tipo de lixo</option>
                                <?php foreach ($tipos_lixo as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bairros da Rota</label>
                        <div id="bairros-container" class="bg-gray-50 rounded-xl p-3 border border-gray-300 min-h-[48px] text-gray-900"></div>
                        <input type="hidden" name="bairros_json" id="bairros_json" value="">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prefixo do Veículo</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-truck"></i>
                            </div>
                            <select
                                name="prefixo_veiculo"
                                id="prefixo_veiculo"
                                class="w-full bg-transparent focus:outline-none"
                                required
                            >
                                <option value="">Selecione um veículo</option>
                                <?php foreach ($veiculos as $veiculo): ?>
                                    <option value="<?php echo htmlspecialchars($veiculo['prefixo']); ?>" data-peso="<?php echo htmlspecialchars($veiculo['veiculo_peso']); ?>">
                                        <?php echo htmlspecialchars($veiculo['prefixo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Peso do Caminhão Vazio (kg)</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                            <input
                                type="number"
                                name="peso_caminhao_vazio"
                                id="peso_caminhao_vazio"
                                class="w-full bg-transparent focus:outline-none"
                                required
                                step="0.01"
                                min="0"
                                placeholder="Ex: 5000.00"
                                readonly
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="btn-primary w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-4"
                    >
                        <i class="fas fa-save mr-2"></i> Salvar Configuração
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <h2 class="text-xl font-bold mb-4">Configurações Existentes</h2>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th class="w-[20%]">Motorista</th>
                                <th class="w-[15%]">Rota</th>
                                <th class="w-[20%]">Período</th>
                                <th class="w-[10%]">Tipo Lixo</th>
                                <th class="w-[15%]">Veículo</th>
                                <th class="w-[12%]">Peso Caminhão</th>
                                <th class="w-[8%] text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="config-table-body">
                            <?php
                            try {
                                $query = "SELECT * FROM config_motoristas_lixo ORDER BY nome_usuario";
                                $stmt = $conn->prepare($query);
                                $stmt->execute();

                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<tr data-id="' . $row['id'] . '">';
                                    echo '<td>' . htmlspecialchars($row['nome_usuario']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['rota']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['periodo_trabalho']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['tipo_lixo']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['prefixo_veiculo'] ?? '') . '</td>';
                                    echo '<td>' . number_format($row['peso_caminhao_vazio'], 2, ',', '.') . ' kg</td>';
                                    echo '<td class="text-center">';
                                    echo '<div class="flex justify-center space-x-2">';
                                    echo '<button class="edit-config text-primary" data-id="' . $row['id'] . '"><i class="fas fa-edit"></i></button>';
                                    echo '<button class="delete-config text-danger delete-btn" data-id="' . $row['id'] . '" data-name="' . htmlspecialchars($row['nome_usuario']) . '"><i class="fas fa-trash-alt"></i></button>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '</tr>';
                                }

                                if ($stmt->rowCount() == 0) {
                                    echo '<tr><td colspan="7" class="text-center py-4">Nenhuma configuração encontrada</td></tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="7" class="text-center py-4 text-red-500">Erro ao carregar configurações: ' . $e->getMessage() . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-center mt-6 space-x-4">
                <a href="menu_lixo_adm.php" class="nav-button btn-primary py-2 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all" role="button">
                    <i class="fas fa-home mr-2"></i> Menu Principal
                </a>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="deleteModal">
        <div class="modal">
            <h3 class="text-xl font-bold mb-4">Confirmar Exclusão</h3>
            <p class="mb-6">Tem certeza que deseja excluir a configuração para o motorista <span id="deleteMotoristaName" class="font-bold"></span>?</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelDelete" class="px-4 py-2 bg-gray-200 rounded-lg text-gray-800 hover:bg-gray-300 transition-all">Cancelar</button>
                <button id="confirmDelete" class="px-4 py-2 bg-red-500 rounded-lg text-white hover:bg-red-600 transition-all">Excluir</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.message-container')) {
                setTimeout(function() {
                    document.querySelector('.message-container').style.opacity = '0';
                    setTimeout(function() {
                        document.querySelector('.message-container').remove();
                    }, 500);
                }, 3000);
            }

            const usuarioSelect = document.getElementById('usuario_id');
            const nomeUsuarioInput = document.getElementById('nome_usuario');

            if (usuarioSelect && nomeUsuarioInput) {
                // Definir o valor inicial do nome_usuario se um motorista já estiver selecionado
                if (usuarioSelect.selectedIndex > 0) {
                    const selectedOption = usuarioSelect.options[usuarioSelect.selectedIndex];
                    nomeUsuarioInput.value = selectedOption.getAttribute('data-name') || '';
                }

                usuarioSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption) {
                        nomeUsuarioInput.value = selectedOption.getAttribute('data-name') || '';

                        const userId = this.value;
                        if (userId) {
                            fetch('get_motorista_config.php?id=' + userId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.config) {
                                        document.getElementById('rota').value = data.config.rota || '';
                                        document.getElementById('periodo_trabalho').value = data.config.periodo_trabalho || '';
                                        document.getElementById('tipo_lixo').value = data.config.tipo_lixo || '';

                                        const prefixoSelect = document.getElementById('prefixo_veiculo');
                                        const prefixoValue = data.config.prefixo_veiculo || '';

                                        for (let i = 0; i < prefixoSelect.options.length; i++) {
                                            if (prefixoSelect.options[i].value === prefixoValue) {
                                                prefixoSelect.selectedIndex = i;
                                                updatePesoFromPrefixo(prefixoSelect);
                                                break;
                                            }
                                        }

                                        const rotaSelect = document.getElementById('rota');
                                        if (rotaSelect.value) {
                                            rotaSelect.dispatchEvent(new Event('change'));
                                        }
                                    } else {
                                        const motoristaNome = nomeUsuarioInput.value;
                                        document.getElementById('configForm').reset();
                                        usuarioSelect.value = userId;
                                        nomeUsuarioInput.value = motoristaNome;
                                        document.getElementById('peso_caminhao_vazio').value = '';
                                    }
                                })
                                .catch(error => {
                                    console.error('Erro ao buscar configurações:', error);
                                });
                        }
                    }
                });
            }

            document.getElementById('rota').addEventListener('change', function() {
                let rota = this.value;
                let bairrosDiv = document.getElementById('bairros-container');
                let bairrosJsonInput = document.getElementById('bairros_json');
                bairrosDiv.innerHTML = 'Carregando...';

                if (rota) {
                    fetch('get_bairros_rota.php?rota=' + encodeURIComponent(rota))
                        .then(r => r.json())
                        .then(data => {
                            if (data.bairros && Array.isArray(data.bairros)) {
                                bairrosDiv.innerHTML = data.bairros.map(b =>
                                    `<div class="py-1 px-2 rounded bg-gray-200 my-1 inline-block mr-1">${b}</div>`
                                ).join(' ');
                                bairrosJsonInput.value = JSON.stringify(data.bairros);
                            } else {
                                bairrosDiv.innerHTML = '<span class="text-gray-500">Nenhum bairro cadastrado para esta rota.</span>';
                                bairrosJsonInput.value = JSON.stringify([]);
                            }
                        })
                        .catch(error => {
                            bairrosDiv.innerHTML = '<span class="text-red-500">Erro ao carregar bairros: ' + error.message + '</span>';
                            bairrosJsonInput.value = JSON.stringify([]);
                        });
                } else {
                    bairrosDiv.innerHTML = '';
                    bairrosJsonInput.value = JSON.stringify([]);
                }
            });

            function updatePesoFromPrefixo(selectElement) {
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const pesoInput = document.getElementById('peso_caminhao_vazio');

                if (selectedOption && selectedOption.value) {
                    const peso = selectedOption.getAttribute('data-peso');
                    if (peso) {
                        pesoInput.value = peso;
                    } else {
                        pesoInput.value = '';
                    }
                } else {
                    pesoInput.value = '';
                }
            }

            document.getElementById('prefixo_veiculo').addEventListener('change', function() {
                updatePesoFromPrefixo(this);
            });

            const editBtns = document.querySelectorAll('.edit-config');
            editBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const configId = this.getAttribute('data-id');

                    fetch('get_config_details.php?id=' + configId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.config) {
                                const userSelect = document.getElementById('usuario_id');
                                for (let i = 0; i < userSelect.options.length; i++) {
                                    if (userSelect.options[i].value == data.config.usuario_id) {
                                        userSelect.selectedIndex = i;
                                        document.getElementById('nome_usuario').value = data.config.nome_usuario;
                                        break;
                                    }
                                }

                                document.getElementById('rota').value = data.config.rota;
                                document.getElementById('periodo_trabalho').value = data.config.periodo_trabalho;
                                document.getElementById('tipo_lixo').value = data.config.tipo_lixo;

                                const prefixoSelect = document.getElementById('prefixo_veiculo');
                                const prefixoValue = data.config.prefixo_veiculo || '';

                                for (let i = 0; i < prefixoSelect.options.length; i++) {
                                    if (prefixoSelect.options[i].value === prefixoValue) {
                                        prefixoSelect.selectedIndex = i;
                                        updatePesoFromPrefixo(prefixoSelect);
                                        break;
                                    }
                                }

                                const rotaSelect = document.getElementById('rota');
                                if (rotaSelect.value) {
                                    rotaSelect.dispatchEvent(new Event('change'));
                                }

                                document.getElementById('configForm').scrollIntoView({ behavior: 'smooth' });
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao buscar detalhes da configuração:', error);
                        });
                });
            });

            const deleteModal = document.getElementById('deleteModal');
            const deleteMotoristaName = document.getElementById('deleteMotoristaName');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            const cancelDeleteBtn = document.getElementById('cancelDelete');
            let configIdToDelete = null;

            document.querySelectorAll('.delete-config').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    configIdToDelete = this.getAttribute('data-id');
                    const motoristaNome = this.getAttribute('data-name');

                    deleteMotoristaName.textContent = motoristaNome;
                    deleteModal.classList.add('active');
                });
            });

            cancelDeleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                deleteModal.classList.remove('active');
            });

            confirmDeleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (configIdToDelete) {
                    const formData = new FormData();
                    formData.append('id', configIdToDelete);

                    fetch('delete_config.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        deleteModal.classList.remove('active');

                        const messageContainer = document.createElement('div');
                        messageContainer.className = `message-container ${data.success ? 'success' : 'error'}`;
                        messageContainer.textContent = data.message;
                        document.body.appendChild(messageContainer);

                        if (data.success) {
                            const row = document.querySelector(`tr[data-id="${configIdToDelete}"]`);
                            if (row) {
                                row.remove();
                            }

                            const tableBody = document.getElementById('config-table-body');
                            if (tableBody.childElementCount === 0) {
                                const emptyRow = document.createElement('tr');
                                emptyRow.innerHTML = '<td colspan="7" class="text-center py-4">Nenhuma configuração encontrada</td>';
                                tableBody.appendChild(emptyRow);
                            }
                        }

                        setTimeout(function() {
                            messageContainer.style.opacity = '0';
                            setTimeout(function() {
                                messageContainer.remove();
                            }, 500);
                        }, 3000);
                    })
                    .catch(error => {
                        console.error('Erro ao excluir:', error);
                        deleteModal.classList.remove('active');
                    });
                }
            });

            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
