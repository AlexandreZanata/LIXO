<?php
session_start();
include 'conexao.php';

if (!isset($_SESSION['user_name'])) {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$secretaria = $_SESSION['secretaria'];
$hora = isset($_POST['hora']) ? $_POST['hora'] : '';
$pagina_atual = "diario.php";
$is_lixo_driver = false; // Variável para controlar interface de motorista de lixo

try {
    // Buscar dados do usuário incluindo codigo_veiculo e role
    $user_query = "SELECT id, codigo_veiculo, role FROM usuarios WHERE name = :name LIMIT 1";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':name', $user_name, PDO::PARAM_STR);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_id = $user_data['id'];
        $codigo_veiculo = $user_data['codigo_veiculo'];
        $user_role = $user_data['role'];

        // Verifica se o usuário é motorista de coleta de lixo
        $is_lixo_driver = ($user_role === 'lixo');

        // Atualiza última página
        $update_query = "UPDATE usuarios SET ultima_pagina = :pagina WHERE id = :user_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':pagina', $pagina_atual, PDO::PARAM_STR);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $update_stmt->execute();
    } else {
        echo '<div class="message-container error">Erro: Usuário não encontrado.</div>';
        exit();
    }
} catch (PDOException $e) {
    echo '<div class="message-container error">Erro: ' . $e->getMessage() . '</div>';
    exit();
}

// Adicionar após a verificação do motorista de lixo ($is_lixo_driver = true)

// Para motoristas de lixo, buscar configuração pré-definida pelo administrador
if ($is_lixo_driver) {
    try {
        $config_query = "SELECT * FROM config_motoristas_lixo WHERE usuario_id = :user_id LIMIT 1";
        $config_stmt = $conn->prepare($config_query);
        $config_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $config_stmt->execute();
        $driver_config = $config_stmt->fetch(PDO::FETCH_ASSOC);

        // Se houver configuração, usar esses valores por padrão
        if ($driver_config) {
            $rota_padrao = $driver_config['rota'];
            $periodo_padrao = $driver_config['periodo_trabalho'];
            $tipo_lixo_padrao = $driver_config['tipo_lixo'];
            $peso_caminhao_vazio = $driver_config['peso_caminhao_vazio'];
            $bairros_padrao = json_decode($driver_config['bairros'], true);
        } else {
            $rota_padrao = '';
            $periodo_padrao = '';
            $tipo_lixo_padrao = '';
            $peso_caminhao_vazio = 0;
            $bairros_padrao = [];
        }
    } catch (PDOException $e) {
        // Silenciar erro e usar valores padrão
        $rota_padrao = '';
        $periodo_padrao = '';
        $tipo_lixo_padrao = '';
        $peso_caminhao_vazio = 0;
        $bairros_padrao = [];
    }
}

// Verifica código do veículo
if (!$codigo_veiculo) {
    echo '<div class="message-container error">Erro: Nenhum veículo vinculado.</div>';
    exit();
}

try {
    // Busca dados do veículo usando o código do usuário
    $query = "SELECT veiculo, tipo, placa, status FROM veiculos WHERE id = :codigo";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':codigo', $codigo_veiculo, PDO::PARAM_INT);
    $stmt->execute();

    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$veiculo) {
        echo '<div class="message-container error">Erro: Veículo não encontrado.</div>';
        exit();
    }

    $veiculo_nome   = $veiculo['tipo'];
    $placa          = $veiculo['placa'];
    $nome_veiculo   = $veiculo['veiculo'];
    $status_veiculo = $veiculo['status'];

    // Busca o último usuário do veículo
    if ($is_lixo_driver) {
        // Para motoristas de lixo, procurar na tabela registros_lixo
        $query_usuario = "SELECT nome FROM registros_lixo
                      WHERE veiculo_id = :nome_veiculo
                      ORDER BY data DESC, hora DESC
                      LIMIT 1";
    } else {
        // Para outros motoristas, procurar na tabela registros normal
        $query_usuario = "SELECT nome FROM registros
                      WHERE veiculo_id = :nome_veiculo
                      ORDER BY data DESC, hora DESC
                      LIMIT 1";
    }
    $stmt_usuario = $conn->prepare($query_usuario);
    $stmt_usuario->bindValue(':nome_veiculo', $nome_veiculo, PDO::PARAM_STR);
    $stmt_usuario->execute();
    $ultimo_registro = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

    $nome_ultimo_usuario = $ultimo_registro ? $ultimo_registro['nome'] : 'Nenhum registro encontrado';
} catch (PDOException $e) {
    echo '<div class="message-container error">Erro: ' . $e->getMessage() . '</div>';
    exit();
}

try {
    // Buscar último KM registrado
    if ($is_lixo_driver) {
        $sql = "SELECT km_final FROM registros_lixo WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
    } else {
        $sql = "SELECT km_final FROM registros WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':veiculo_id', $nome_veiculo, PDO::PARAM_STR);
    $stmt->execute();

    $linha = $stmt->fetch(PDO::FETCH_ASSOC);
    $km_inicial = $linha ? $linha['km_final'] : 0;

    // Para motoristas de lixo, buscar dados pré-existentes para rotas, períodos e bairros
    $rotas = [];
    $periodos = [];
    $bairros = [];
    $tipos_lixo = ['UMIDO', 'SECO', 'RECICLÁVEL'];

    if ($is_lixo_driver) {
        // Buscar rotas existentes
        $sql_rotas = "SELECT DISTINCT rota FROM registros_lixo ORDER BY rota";
        $stmt_rotas = $conn->prepare($sql_rotas);
        $stmt_rotas->execute();
        while ($row = $stmt_rotas->fetch(PDO::FETCH_ASSOC)) {
            $rotas[] = $row['rota'];
        }

        // Buscar períodos existentes
        $sql_periodos = "SELECT DISTINCT periodo_trabalho FROM registros_lixo ORDER BY periodo_trabalho";
        $stmt_periodos = $conn->prepare($sql_periodos);
        $stmt_periodos->execute();
        while ($row = $stmt_periodos->fetch(PDO::FETCH_ASSOC)) {
            $periodos[] = $row['periodo_trabalho'];
        }

        // Buscar bairros existentes
        $sql_bairros = "SELECT bairros FROM registros_lixo WHERE bairros IS NOT NULL
                         ORDER BY data DESC LIMIT 10";
        $stmt_bairros = $conn->prepare($sql_bairros);
        $stmt_bairros->execute();
        $bairros_unicos = [];
        while ($row = $stmt_bairros->fetch(PDO::FETCH_ASSOC)) {
            if ($row['bairros']) {
                $bairros_json = json_decode($row['bairros'], true);
                if (is_array($bairros_json)) {
                    foreach ($bairros_json as $bairro) {
                        if (!in_array($bairro, $bairros_unicos)) {
                            $bairros_unicos[] = $bairro;
                        }
                    }
                }
            }
        }
        $bairros = $bairros_unicos;
    }
} catch (PDOException $e) {
    echo '<div class="message-container error">Erro ao buscar último Km: ' . $e->getMessage() . '</div>';
    exit();
}

// Processa a submissão do formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Obtém o km_inicial enviado pelo usuário
        $km_inicial = $_POST['km_inicial'] ?? 0;

        // Atualiza o status do veículo para "em uso"
        $update_status = "UPDATE veiculos SET status = 'em uso' WHERE id = :codigo";
        $stmt_update = $conn->prepare($update_status);
        $stmt_update->bindParam(':codigo', $codigo_veiculo, PDO::PARAM_INT);
        $stmt_update->execute();

        if ($is_lixo_driver) {
            // Para motoristas de lixo
            $rota = $_POST['rota'] ?? '';
            $periodo_trabalho = $_POST['periodo_trabalho'] ?? '';
            $tipo_lixo = $_POST['tipo_lixo'] ?? '';
            $peso_caminhao_vazio = $_POST['peso_caminhao_vazio'] ?? 0;

            // Processa bairros enviados como array e converte para JSON
            $bairros_post = $_POST['bairros'] ?? [];
            $bairros_json = json_encode($bairros_post);

            // Insere o registro na tabela "registros_lixo"
            $query_insert = "INSERT INTO registros_lixo (nome, secretaria, veiculo_id, placa, nome_veiculo,
                            km_inicial, km_final, rota, periodo_trabalho, bairros, tipo_lixo, peso_caminhao_vazio, data, hora)
                         VALUES (:nome, :secretaria, :veiculo_id, :placa, :nome_veiculo, :km_inicial, 0,
                                :rota, :periodo_trabalho, :bairros, :tipo_lixo, :peso_caminhao_vazio, :data, :hora)";
            $stmt_insert = $conn->prepare($query_insert);

            $stmt_insert->bindParam(':nome', $user_name);
            $stmt_insert->bindParam(':secretaria', $secretaria);
            $stmt_insert->bindParam(':veiculo_id', $nome_veiculo, PDO::PARAM_STR);
            $stmt_insert->bindParam(':placa', $placa);
            $stmt_insert->bindParam(':nome_veiculo', $veiculo_nome);
            $stmt_insert->bindParam(':km_inicial', $km_inicial, PDO::PARAM_INT);
            $stmt_insert->bindParam(':rota', $rota);
            $stmt_insert->bindParam(':periodo_trabalho', $periodo_trabalho);
            $stmt_insert->bindParam(':bairros', $bairros_json);
            $stmt_insert->bindParam(':tipo_lixo', $tipo_lixo);
            $stmt_insert->bindParam(':peso_caminhao_vazio', $peso_caminhao_vazio);
            $stmt_insert->bindParam(':data', date('Y-m-d'));
            $stmt_insert->bindParam(':hora', $hora);

        } else {
            // Para motoristas normais
            $destino = $_POST['destino'] ?? '';

            // Insere o registro na tabela "registros"
            $query_insert = "INSERT INTO registros (nome, secretaria, veiculo_id, placa, nome_veiculo, km_inicial, km_final, destino, data, hora)
                         VALUES (:nome, :secretaria, :veiculo_id, :placa, :nome_veiculo, :km_inicial, 0, :destino, :data, :hora)";
            $stmt_insert = $conn->prepare($query_insert);

            $stmt_insert->bindParam(':nome', $user_name);
            $stmt_insert->bindParam(':secretaria', $secretaria);
            $stmt_insert->bindParam(':veiculo_id', $nome_veiculo, PDO::PARAM_STR);
            $stmt_insert->bindParam(':placa', $placa);
            $stmt_insert->bindParam(':nome_veiculo', $veiculo_nome);
            $stmt_insert->bindParam(':km_inicial', $km_inicial, PDO::PARAM_INT);
            $stmt_insert->bindParam(':destino', $destino);
            $stmt_insert->bindParam(':data', date('Y-m-d'));
            $stmt_insert->bindParam(':hora', $hora);
        }

        $stmt_insert->execute();

        echo '<div class="message-container success">Corrida registrada com sucesso!</div>';
    } catch (PDOException $e) {
        echo '<div class="message-container error">Erro ao registrar corrida: ' . $e->getMessage() . '</div>';
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Diário de Bordo</title>
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
        /* Cabeçalho em largura total */
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
        /* Container principal com largura máxima */
        .app-container {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        /* Container do conteúdo limitado em largura */
        .content-container {
            width: 100%;
            max-width: 900px;
            margin-top: -3rem;
            padding: 0 1.25rem;
            flex: 1;
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
        .forms-container {
            width: 100%;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
        }
        .forms-container::-webkit-scrollbar {
            width: 6px;
        }
        .forms-container::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        .nav-button {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }
        .nav-button:hover {
            transform: scale(1.05);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .hidden {
            display: none;
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
        .error-text {
            color: #EF4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        .input-uniform-width {
            width: 100%;
        }
        /* Remove as setas do input type="date" */
        input[type="date"]::-webkit-calendar-picker-indicator {
            display: none;
        }

        /* Remove as setas do input type="time" */
        input[type="time"]::-webkit-calendar-picker-indicator {
            display: none;
        }

        /* Garante que o campo pareça apenas texto em outros navegadores */
        input[type="date"], input[type="time"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .btn-disabled {
            background-color: #e5e7eb !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }

        .btn-disabled:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        .input-field[readonly] {
            background-color: #f3f4f6 !important;
            border-color: #d1d5db !important;
            cursor: not-allowed !important;
        }

        .input-field[readonly] .input-icon {
            color: #9ca3af !important;
        }

        .input-field[readonly] input {
            color: #6b7280 !important;
        }

        /* Ajustes para iOS Safari */
        @supports (-webkit-touch-callout: none) {
            .app-container {
                min-height: -webkit-fill-available;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Cabeçalho em largura total -->
        <div class="header-container h-48 rounded-b-3xl shadow-hard">
            <div class="logo-container">
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-flag-checkered text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-8">Diário de Bordo</h1>
            </div>
        </div>

        <!-- Container do conteúdo com largura limitada -->
        <div class="content-container">
            <!-- Forms Container -->
            <div class="forms-container">
                <div class="bg-white rounded-2xl p-6 shadow-hard">
                    <form method="POST" id="diarioForm">
                        <!-- User Info -->
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Nome</label>
                            <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <input
                                    type="text"
                                    id="user_name"
                                    name="name"
                                    class="w-full bg-transparent focus:outline-none"
                                    value="<?php echo $user_name; ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <!-- Date and Time -->
                        <div class="form-grid mb-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Data</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <input
                                        type="date"
                                        name="data"
                                        id="data"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        value="<?php echo date('Y-m-d'); ?>"
                                        readonly
                                    >
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Hora</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <input
                                        type="time"
                                        id="idhora"
                                        name="hora"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        required
                                        readonly
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Info -->
                        <div class="form-grid mb-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Último Km</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                    <input
                                        type="text"
                                        id="kminicial"
                                        name="km_inicial"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        value="<?php echo $km_inicial; ?>"
                                        readonly
                                    >
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Veículo</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <input
                                        type="text"
                                        id="codigo"
                                        name="veiculo_id"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        value="<?php echo $nome_veiculo; ?>"
                                        readonly
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Secretariat -->
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Secretaria</label>
                            <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-building"></i>
                                </div>
                                <input
                                    type="text"
                                    name="secretaria"
                                    class="w-full bg-transparent focus:outline-none"
                                    value="<?php echo $secretaria; ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <!-- Vehicle Details -->
                        <div class="form-grid mb-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Placa do Veículo</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <input
                                        type="text"
                                        name="placa"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        value="<?php echo $placa; ?>"
                                        readonly
                                    >
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-900 mb-1">Nome do Veículo</label>
                                <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
                                    <div class="input-icon text-gray-900">
                                        <i class="fas fa-car"></i>
                                    </div>
                                    <input
                                        type="text"
                                        name="nome_veiculo"
                                        class="input-uniform-width bg-transparent focus:outline-none"
                                        value="<?php echo $veiculo_nome; ?>"
                                        readonly
                                    >
                                </div>
                            </div>
                        </div>

<!-- Current KM and Destination/Route Fields -->
<div class="form-grid mb-5">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Km Atual</label>
        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
            <div class="input-icon text-warning">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <input
                type="number"
                name="km_inicial"
                class="input-uniform-width bg-transparent focus:outline-none"
                required
                id="km_inicial_input"
                min="1"
                value="<?php echo $km_inicial; ?>"
            >
        </div>
        <div id="kmError" class="error-text"></div>
    </div>

    <?php if ($is_lixo_driver): ?>
    <!-- Interface para motoristas de coleta de lixo -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Rota</label>
        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
            <div class="input-icon text-primary">
                <i class="fas fa-route"></i>
            </div>
            <input
                type="text"
                name="rota"
                class="input-uniform-width bg-transparent focus:outline-none"
                placeholder="Ex: Rota Norte"
                required
                list="rotas-list"
                value="<?php echo htmlspecialchars($rota_padrao ?? ''); ?>"
            >
            <datalist id="rotas-list">
                <?php foreach ($rotas as $rota): ?>
                <option value="<?php echo htmlspecialchars($rota); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>
</div>

<div class="mb-5">
    <label class="block text-sm font-medium text-gray-700 mb-1">Período de Trabalho</label>
    <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
        <div class="input-icon text-primary">
            <i class="fas fa-calendar-week"></i>
        </div>
        <input
            type="text"
            name="periodo_trabalho"
            class="input-uniform-width bg-transparent focus:outline-none"
            placeholder="Ex: Segunda, Quarta e Sexta"
            required
            list="periodos-list"
            value="<?php echo htmlspecialchars($periodo_padrao ?? ''); ?>"
        >
        <datalist id="periodos-list">
            <?php foreach ($periodos as $periodo): ?>
            <option value="<?php echo htmlspecialchars($periodo); ?>">
            <?php endforeach; ?>
        </datalist>
    </div>
</div>

<div class="mb-5">
    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Lixo</label>
    <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
        <div class="input-icon text-primary">
            <i class="fas fa-trash"></i>
        </div>
        <select name="tipo_lixo" class="input-uniform-width bg-transparent focus:outline-none" required>
            <option value="" disabled <?php echo empty($tipo_lixo_padrao) ? 'selected' : ''; ?>>Selecione o tipo de lixo</option>
            <?php foreach ($tipos_lixo as $tipo): ?>
            <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $tipo === $tipo_lixo_padrao ? 'selected' : ''; ?>><?php echo htmlspecialchars($tipo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="mb-5">
    <label class="block text-sm font-medium text-gray-700 mb-1">Peso do Caminhão Vazio (kg)</label>
    <div class="input-field bg-gray-300 rounded-xl p-3 border border-gray-300 pl-9">
        <div class="input-icon text-gray-900">
            <i class="fas fa-weight-hanging"></i>
        </div>
        <input
            type="number"
            name="peso_caminhao_vazio"
            class="input-uniform-width bg-transparent focus:outline-none"
            value="<?php echo htmlspecialchars($peso_caminhao_vazio ?? 0); ?>"
            readonly
            step="0.01"
        >
    </div>
</div>

<div class="mb-5">
    <label class="block text-sm font-medium text-gray-700 mb-1">
        Bairros <button type="button" id="add-bairro" class="text-primary text-sm">
            <i class="fas fa-plus-circle"></i> Adicionar
        </button>
    </label>
    <div id="bairros-container">
        <?php if (!empty($bairros_padrao) && is_array($bairros_padrao)): ?>
            <?php foreach ($bairros_padrao as $bairro): ?>
                <div class="bairro-entry mb-2">
                    <div class="flex">
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9 flex-grow">
                            <div class="input-icon text-primary">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <input
                                type="text"
                                name="bairros[]"
                                class="input-uniform-width bg-transparent focus:outline-none bairro-input"
                                placeholder="Ex: Centro"
                                required
                                value="<?php echo htmlspecialchars($bairro); ?>"
                                list="bairros-list"
                            >
                            <datalist id="bairros-list">
                                <?php foreach ($bairros as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <button type="button" class="remove-bairro ml-2 text-danger p-2">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Campo padrão se não houver bairros configurados -->
            <div class="bairro-entry mb-2">
                <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                    <div class="input-icon text-primary">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <input
                        type="text"
                        name="bairros[]"
                        class="input-uniform-width bg-transparent focus:outline-none bairro-input"
                        placeholder="Ex: Centro"
                        required
                        list="bairros-list"
                    >
                    <datalist id="bairros-list">
                        <?php foreach ($bairros as $bairro): ?>
                        <option value="<?php echo htmlspecialchars($bairro); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
    <!-- Interface padrão para outros motoristas -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Destino</label>
        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
            <div class="input-icon text-primary">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <input
                type="text"
                name="destino"
                class="input-uniform-width bg-transparent focus:outline-none"
                placeholder="Ex: Secretaria de Obras"
                required
            >
        </div>
    </div>
</div>
<?php endif; ?>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            class="btn-primary w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-2"
                        >
                            <i class="fas fa-pen-fancy mr-2"></i> Iniciar Corrida
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function setHoraAtual() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, "0");
                const minutes = String(now.getMinutes()).padStart(2, "0");
                const timeString = `${hours}:${minutes}`;

                const horaInput = document.getElementById("idhora");
                if (horaInput) {
                    horaInput.value = timeString;
                }
            }

            setHoraAtual(); // Atualiza imediatamente
            setInterval(setHoraAtual, 1000); // Mantém atualizando

            const form = document.getElementById("diarioForm");
            if (!form) return;

            form.addEventListener("submit", function (event) {
                event.preventDefault();

                setHoraAtual(); // Força última atualização da hora antes do envio

                const submitButton = form.querySelector("button[type='submit']");
                submitButton.disabled = true;

                const formData = new FormData(this);

                function showMessage(message, type = "success") {
                    const msgContainer = document.createElement("div");
                    msgContainer.className = `message-container ${type}`;
                    msgContainer.textContent = message;
                    document.body.appendChild(msgContainer);

                    setTimeout(() => {
                        msgContainer.style.opacity = "0";
                        setTimeout(() => msgContainer.remove(), 500);
                    }, 3000);
                }

                fetch("", { // Envia para a própria página
                    method: "POST",
                    body: formData
                })
                .then(async (response) => {
                    const text = await response.text();
                    // Se a resposta contiver erro, exibe a mensagem retornada
                    if (text.includes("Erro:")) {
                        showMessage(text, "error");
                    } else {
                        showMessage("Dados salvos e corrida iniciada!", "success");
                        setTimeout(() => {
                            window.location.href = "finalizar_corrida.php"; // Redireciona para finalizar corrida
                        }, 300);
                    }
                })
                .catch((error) => {
                    console.error("Erro ao enviar os dados:", error);
                    showMessage("Houve um erro ao salvar os dados.", "error");
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
            });

            function redirectHome() {
                let role = '<?php echo $_SESSION['role']; ?>';
                if (role === 'user') {
                    window.location.href = 'menu.php';
                } else if (role === 'admin') {
                    window.location.href = 'menuadm.php';
                } else if (role === 'geraladm') {
                    window.location.href = 'menugeraladm.php';
                }
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            // Impede que campos readonly recebam foco
            const readonlyInputs = document.querySelectorAll('input[readonly]');
            readonlyInputs.forEach(input => {
                input.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    this.blur();
                    return false;
                });

                input.addEventListener('focus', function() {
                    this.blur();
                });
            });

            // Adiciona estilo para inputs desabilitados
            const disabledInputs = document.querySelectorAll('.input-field[readonly]');
            disabledInputs.forEach(input => {
                input.style.pointerEvents = 'none';
                input.querySelector('input').style.cursor = 'not-allowed';
            });

            // Validação básica do km inicial
            const kmInicialInput = document.getElementById("km_inicial_input");
            const kmError = document.getElementById("kmError");
            const ultimoKm = <?php echo $km_inicial; ?>;

            if (kmInicialInput) {
                kmInicialInput.addEventListener("change", function() {
                    const valor = parseInt(this.value);
                    if (valor < ultimoKm) {
                        kmError.textContent = "O valor não pode ser menor que o último km registrado.";
                        this.value = ultimoKm;
                    } else {
                        kmError.textContent = "";
                    }
                });
            }
        });
    </script>

    <!-- JavaScript adicional para gestão dinâmica de bairros (para motoristas de lixo) -->
    <?php if ($is_lixo_driver): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar novo campo de bairro
            const addBairroBtn = document.getElementById('add-bairro');
            const bairrosContainer = document.getElementById('bairros-container');

            if (addBairroBtn && bairrosContainer) {
                addBairroBtn.addEventListener('click', function() {
                    const bairroEntry = document.createElement('div');
                    bairroEntry.className = 'bairro-entry mb-2';
                    bairroEntry.innerHTML = `
                        <div class="flex">
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9 flex-grow">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <input
                                    type="text"
                                    name="bairros[]"
                                    class="input-uniform-width bg-transparent focus:outline-none bairro-input"
                                    placeholder="Ex: Centro"
                                    required
                                    list="bairros-list"
                                >
                            </div>
                            <button type="button" class="remove-bairro ml-2 text-danger p-2">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    bairrosContainer.appendChild(bairroEntry);

                    // Adicionar evento para remover o bairro
                    const removeBtn = bairroEntry.querySelector('.remove-bairro');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            bairroEntry.remove();
                        });
                    }
                });
            }

            // Remover bairros (para entradas iniciais)
            document.addEventListener('click', function(e) {
                if (e.target && e.target.closest('.remove-bairro')) {
                    const entry = e.target.closest('.bairro-entry');
                    if (entry) {
                        entry.remove();
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
