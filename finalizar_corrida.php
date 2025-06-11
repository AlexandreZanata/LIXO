<?php
session_start();
include 'conexao.php';

if (!isset($_SESSION['user_name'])) {
    header("Location: index.html");
    exit();
}

$user_name = $_SESSION['user_name'];
$secretaria = $_SESSION['secretaria'];

// Modificação na definição da página atual
if ($_SESSION['role'] === 'admin') {
    $pagina_atual = ($_SERVER['REQUEST_METHOD'] === 'POST') ? "menuadm.php" : "finalizar_corrida.php";
} else {
    $pagina_atual = ($_SERVER['REQUEST_METHOD'] === 'POST') ? "menu.php" : "finalizar_corrida.php";
}

try {
    // Buscar dados do usuário incluindo codigo_veiculo
    $user_query = "SELECT id, codigo_veiculo FROM usuarios WHERE name = :name LIMIT 1";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':name', $user_name, PDO::PARAM_STR);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_id = $user_data['id'];
        $codigo_veiculo = $user_data['codigo_veiculo'];

        // Atualiza última página COM NOVA REGRA
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

// Verifica código do veículo
if (!$codigo_veiculo) {
    echo '<div class="message-container error">Erro: Nenhum veículo vinculado.</div>';
    exit();
}

try {
    // Busca os dados do veículo
    $query = "SELECT veiculo, tipo, placa FROM veiculos WHERE id = :codigo";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':codigo', $codigo_veiculo, PDO::PARAM_INT);
    $stmt->execute();

    $veiculo_dados = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($veiculo_dados) {
        $veiculo = $veiculo_dados['veiculo'];
        $veiculo_nome = $veiculo_dados['tipo'];
        $placa = $veiculo_dados['placa'];
    } else {
        $message = '<div class="message-container error">Veículo não encontrado.</div>';
    }
} catch (PDOException $e) {
    $message = '<div class="message-container error">Erro ao buscar dados do veículo: ' . $e->getMessage() . '</div>';
}

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['km_final'])) {
    $km_final = $_POST['km_final'];
    $destino_final = $_POST['destino'];
    $hora_final = $_POST['hora_final'];

    try {
        // Verifica se é motorista de lixo
        if ($_SESSION['role'] === 'lixo') {
            // Para motoristas de lixo, buscar da tabela registros_lixo
            $sql_lixo = "SELECT id, km_inicial FROM registros_lixo WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
            $stmt_lixo = $conn->prepare($sql_lixo);
            $stmt_lixo->bindParam(':veiculo_id', $veiculo, PDO::PARAM_STR);
            $stmt_lixo->execute();
            $registro_lixo = $stmt_lixo->fetch(PDO::FETCH_ASSOC);

            if (!$registro_lixo) {
                $message = '<div class="message-container error">Nenhum registro encontrado para este veículo.</div>';
            } else {
                $km_inicial = $registro_lixo['km_inicial'];
                $registro_lixo_id = $registro_lixo['id'];

                // Validação do Km final
                if (!is_numeric($km_final) || $km_final < $km_inicial) {
                    $message = '<div class="message-container error">Erro: O Km final deve ser maior ou igual ao Km inicial.</div>';
                } else {
                    // Atualiza apenas o km_final e muda o status para em_pesagem na tabela registros_lixo
                    $query_update_lixo = "UPDATE registros_lixo
                        SET km_final = :km_final, status_pesagem = 'em_pesagem'
                        WHERE id = :registro_id";
                    $stmt_update_lixo = $conn->prepare($query_update_lixo);
                    $stmt_update_lixo->bindParam(':km_final', $km_final);
                    $stmt_update_lixo->bindParam(':registro_id', $registro_lixo_id, PDO::PARAM_INT);
                    $stmt_update_lixo->execute();

                    // Também atualiza na tabela registros para manter compatibilidade
                    $sql = "SELECT id FROM registros WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':veiculo_id', $veiculo, PDO::PARAM_STR);
                    $stmt->execute();
                    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($registro) {
                        $registro_id = $registro['id'];

                        // Atualiza o registro normal também
                        $query_update = "UPDATE registros
                            SET km_final = :km_final, hora_final = :hora_final, ponto_parada = :destino_final
                            WHERE id = :registro_id";
                        $stmt_update = $conn->prepare($query_update);
                        $stmt_update->bindParam(':km_final', $km_final);
                        $stmt_update->bindParam(':hora_final', $hora_final);
                        $stmt_update->bindParam(':destino_final', $destino_final);
                        $stmt_update->bindParam(':registro_id', $registro_id, PDO::PARAM_INT);
                        $stmt_update->execute();
                    }

                    // Atualiza o status do veículo para "Ativo"
                    $update_veiculo_status = "UPDATE veiculos SET status = 'Ativo' WHERE id = :veiculo_id";
                    $stmt_update_status = $conn->prepare($update_veiculo_status);
                    $stmt_update_status->bindParam(':veiculo_id', $codigo_veiculo, PDO::PARAM_INT);
                    $stmt_update_status->execute();

                    $message = '<div class="message-container success">Corrida Finalizada com Sucesso!</div>';

                    // Redirecionamento baseado no tipo de usuário
                    $redirect_page = 'menu_lixo.php';

                    echo '<script type="text/javascript">
                        setTimeout(function() {
                            window.location.href = "'.$redirect_page.'";
                        }, 400);
                      </script>';
                }
            }
        } else {
            // Fluxo normal para outros usuários (não-lixo)
            $sql = "SELECT id, km_inicial FROM registros WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':veiculo_id', $veiculo, PDO::PARAM_STR);
            $stmt->execute();
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                $message = '<div class="message-container error">Nenhum registro encontrado para este veículo.</div>';
            } else {
                $km_inicial = $registro['km_inicial'];
                $registro_id = $registro['id'];

                // Validação do Km final
                if (!is_numeric($km_final) || $km_final < $km_inicial) {
                    $message = '<div class="message-container error">Erro: O Km final deve ser maior ou igual ao Km inicial.</div>';
                } else {
                    // Atualiza o registro com Km final, Hora final e Ponto de parada
                    $query_update = "UPDATE registros
                        SET km_final = :km_final, hora_final = :hora_final, ponto_parada = :destino_final
                        WHERE id = :registro_id";
                    $stmt_update = $conn->prepare($query_update);
                    $stmt_update->bindParam(':km_final', $km_final);
                    $stmt_update->bindParam(':hora_final', $hora_final);
                    $stmt_update->bindParam(':destino_final', $destino_final);
                    $stmt_update->bindParam(':registro_id', $registro_id, PDO::PARAM_INT);
                    $stmt_update->execute();

                    // Atualiza o status do veículo para "Ativo"
                    $update_veiculo_status = "UPDATE veiculos SET status = 'Ativo' WHERE id = :veiculo_id";
                    $stmt_update_status = $conn->prepare($update_veiculo_status);
                    $stmt_update_status->bindParam(':veiculo_id', $codigo_veiculo, PDO::PARAM_INT);
                    $stmt_update_status->execute();

                    $message = '<div class="message-container success">Corrida Finalizada com Sucesso!</div>';

                    // Redirecionamento baseado no tipo de usuário
                    $redirect_page = 'menu.php'; // padrão
                    if ($_SESSION['role'] === 'admin') {
                        $redirect_page = 'menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        $redirect_page = 'menugeraladm.php';
                    } elseif ($_SESSION['role'] === 'user_pan') {
                        $redirect_page = 'menu.php';
                    } elseif ($_SESSION['role'] === 'mecanico') {
                        $redirect_page = 'menu_mecanico.php';
                    } elseif ($_SESSION['role'] === 'lixo_adm') {
                        $redirect_page = 'menu_lixo_adm.php';
                    }

                    echo '<script type="text/javascript">
                        setTimeout(function() {
                            window.location.href = "'.$redirect_page.'";
                        }, 400);
                      </script>';
                }
            }
        }
    } catch (PDOException $e) {
        $message = '<div class="message-container error">Erro ao Finalizar Corrida: ' . $e->getMessage() . '</div>';
    }
} else {
    $message = '';
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
     <link rel="icon" type="png" href="ico_nav/img.claro.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="png" href="ico_nav/img.escuro.png" media="(prefers-color-scheme: dark)">
    <title>Finalizar Corrida</title>
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
            margin: 0;
            padding: 0;
        }
        .app-container {
            min-height: 100vh;
            background: white;
            display: flex;
            flex-direction: column;
            margin: 0 auto;
            width: 100%;
            position: relative;
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
        .btn-success {
            background-color:rgb(2, 194, 82);
            transition: all 0.2s ease;
        }
        .btn-success:hover {
            background-color:rgb(2, 155, 66);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.25);
        }
        .btn-success-manual {
            background-color:rgb(116, 116, 116);
            transition: all 0.2s ease;
        }
        .btn-success-manual:hover {
            background-color:rgb(82, 82, 82);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(82, 82, 82, 0.25);
        }
        .btn-warning {
            background-color: #F59E0B;
            transition: all 0.2s ease;
        }
        .btn-warning:hover {
            background-color: #D97706;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(245, 158, 11, 0.25);
        }
        .logo-container {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            margin-bottom: 10px;
            width: 100vw;
            left: 50%;
            transform: translateX(-50%);
            position: relative;
        }
        .forms-container {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4rem;
        }
        .forms-container::-webkit-scrollbar {
            display: none;
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
            grid-template-columns: 1fr;
        }
        @media (min-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            .app-container {
                max-width: 768px;
            }
            .content-wrapper {
                max-width: 768px;
                margin: 0 auto;
                width: 100%;
            }
        }
        @media (min-width: 1024px) {
            .app-container {
                max-width: 1024px;
            }
            .content-wrapper {
                max-width: 1024px;
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
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator {
            display: none;
        }
        input[type="date"], input[type="time"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .back-button {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 10;
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
        @supports (-webkit-touch-callout: none) {
            .app-container {
                min-height: -webkit-fill-available;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Logo Header -->
        <div class="logo-container h-48 rounded-b-3xl shadow-hard">
            <div class="content-wrapper flex flex-col items-center justify-center h-full relative">
                <div class="back-button" onclick="window.location.href = '<?php
                    if ($_SESSION['role'] === 'user') {
                        echo 'menu.php';
                    } elseif ($_SESSION['role'] === 'admin') {
                        echo 'menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        echo 'menugeraladm.php';
                    } elseif ($_SESSION['role'] === 'user_pan') {
                        echo 'menu.php';
                    } elseif ($_SESSION['role'] === 'mecanico') {
                        echo 'menu_mecanico.php';
                    } elseif ($_SESSION['role'] === 'lixo') {
                        echo 'menu_lixo.php';
                    } elseif ($_SESSION['role'] === 'lixo_adm') {
                        echo 'menu_lixo_adm.php';
                    }
                ?>'">
                    <i class="fas fa-home text-primary"></i>
                </div>
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-check-circle text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-4">Finalizar Corrida</h1>
            </div>
        </div>

        <!-- Forms Container -->
        <div class="forms-container px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (!empty($message)) echo $message; ?>

                <form method="POST" id="finalizarForm">
                    <!-- User Info -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-900 mb-1">Nome</label>
                        <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                            <div class="input-icon text-gray-900">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <input
                                type="text"
                                id="user_name"
                                name="name"
                                class="w-full bg-transparent focus:outline-none text-gray-600"
                                value="<?php echo $user_name; ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <!-- Date and Time -->
                    <div class="form-grid mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Data</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-8">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <input
                                    type="date"
                                    name="data"
                                    id="data"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    value="<?php echo date('Y-m-d'); ?>"
                                    readonly
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Hora</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <input
                                    type="time"
                                    id="idhora"
                                    name="hora_final"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    required
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Info -->
                    <div class="form-grid mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Prefixo</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                                <div class="input-icon text-gray-00">
                                    <i class="fas fa-car"></i>
                                </div>
                                <input
                                    type="text"
                                    id="codigo"
                                    name="veiculo_id"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    value="<?php echo $veiculo; ?>"
                                    readonly
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Placa do Veículo</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-car"></i>
                                </div>
                                <input
                                    type="text"
                                    name="placa"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    value="<?php echo $placa; ?>"
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Details -->
                    <div class="form-grid mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Nome do Veículo</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-car"></i>
                                </div>
                                <input
                                    type="text"
                                    name="nome_veiculo"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    value="<?php echo $veiculo_nome; ?>"
                                    readonly
                                >
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-900 mb-1">Secretaria</label>
                            <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                                <div class="input-icon text-gray-900">
                                    <i class="fas fa-building"></i>
                                </div>
                                <input
                                    type="text"
                                    name="secretaria"
                                    class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                    value="<?php echo $secretaria; ?>"
                                    readonly
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Km Inicial -->
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-900 mb-1">Km Inicial</label>
                        <div class="input-field bg-gray-200 rounded-xl p-3 border border-gray-200 pl-9">
                            <div class="input-icon text-gray-900">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <input
                                type="number"
                                id="km_inicial"
                                name="km_inicial"
                                class="input-uniform-width bg-transparent focus:outline-none text-gray-600"
                                value="<?php
                                    if ($_SESSION['role'] === 'lixo') {
                                        // Para usuários lixo, buscar o km_inicial da tabela registros_lixo
                                        try {
                                            $km_inicial_query = "SELECT km_inicial FROM registros_lixo
                                                                WHERE veiculo_id = :veiculo_id
                                                                ORDER BY id DESC LIMIT 1";
                                            $km_inicial_stmt = $conn->prepare($km_inicial_query);
                                            $km_inicial_stmt->bindParam(':veiculo_id', $veiculo, PDO::PARAM_STR);
                                            $km_inicial_stmt->execute();
                                            $km_inicial_result = $km_inicial_stmt->fetch(PDO::FETCH_ASSOC);

                                            echo $km_inicial_result ? $km_inicial_result['km_inicial'] : 'N/A';
                                        } catch (PDOException $e) {
                                            echo 'Erro ao buscar km inicial';
                                        }
                                    } else {
                                        // Exibição normal para outros usuários
                                        if (isset($veiculo)) {
                                            $sql = "SELECT km_inicial FROM registros WHERE veiculo_id = :veiculo_id ORDER BY id DESC LIMIT 1";
                                            $stmt = $conn->prepare($sql);
                                            $stmt->bindParam(':veiculo_id', $veiculo, PDO::PARAM_STR);
                                            $stmt->execute();
                                            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
                                            echo $registro ? $registro['km_inicial'] : 'N/A';
                                        }
                                    }
                                ?>"
                                readonly
                            >
                        </div>
                    </div>

                    <!-- Final KM and Destination -->
                    <div class="form-grid mb-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Km Final</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-warning">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <input
                                    type="number"
                                    id="km_final"
                                    name="km_final"
                                    class="input-uniform-width bg-transparent focus:outline-none"
                                    required
                                    min="1"
                                >
                            </div>
                            <div id="kmError" class="error-text"></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ponto de Parada</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <input
                                    type="text"
                                    id="destino"
                                    name="destino"
                                    class="input-uniform-width bg-transparent focus:outline-none"
                                    placeholder="Ex: Secretaria de Obras"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        id="submitBtn"
                        class="btn-primary w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-2 flex items-center justify-center gap-2">
                        <i class="fas fa-flag-checkered"></i>
                        <span>Finalizar Corrida</span>
                    </button>
                </form>

                <?php if ($_SESSION['role'] === 'lixo'): ?>
                    <!-- Botão para marcar para pesagem -->
                    <button
                        type="button"
                        id="pesagemBtn"
                        class="btn-warning w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-4 flex items-center justify-center gap-2"
                        onclick="marcarParaPesagem()">
                        <i class="fas fa-weight-scale"></i>
                        <span>Marcar para Pesagem</span>
                    </button>

                    <script>
                        function marcarParaPesagem() {
                            // Desabilitar o botão durante o envio
                            const button = document.getElementById('pesagemBtn');
                            button.disabled = true;
                            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

                            // Preparar dados para o servidor
                            const formData = new FormData();
                            formData.append('action', 'marcar_pesagem');
                            formData.append('veiculo_id', document.getElementById('codigo').value);

                            // Enviar via AJAX
                            fetch('marcar_pesagem.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Exibir mensagem de sucesso na interface
                                    const messageDiv = document.createElement('div');
                                    messageDiv.className = 'message-container success';
                                    messageDiv.innerHTML = 'Veículo marcado para pesagem com sucesso!';
                                    document.body.appendChild(messageDiv);

                                    // Reativar o botão
                                    button.disabled = false;
                                    button.innerHTML = '<i class="fas fa-weight-scale"></i> <span>Marcar para Pesagem</span>';

                                    // Remover mensagem após alguns segundos
                                    setTimeout(() => {
                                        messageDiv.style.opacity = '0';
                                        setTimeout(() => messageDiv.remove(), 500);
                                    }, 5000);
                                } else {
                                    // Exibir mensagem de erro na interface
                                    const messageDiv = document.createElement('div');
                                    messageDiv.className = 'message-container error';
                                    messageDiv.innerHTML = 'Erro: ' + data.message;
                                    document.body.appendChild(messageDiv);

                                    // Reativar o botão
                                    button.disabled = false;
                                    button.innerHTML = '<i class="fas fa-weight-scale"></i> <span>Marcar para Pesagem</span>';

                                    // Remover mensagem após alguns segundos
                                    setTimeout(() => {
                                        messageDiv.style.opacity = '0';
                                        setTimeout(() => messageDiv.remove(), 500);
                                    }, 5000);
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);

                                // Exibir mensagem de erro na interface
                                const messageDiv = document.createElement('div');
                                messageDiv.className = 'message-container error';
                                messageDiv.innerHTML = 'Ocorreu um erro ao processar sua solicitação.';
                                document.body.appendChild(messageDiv);

                                // Reativar o botão
                                button.disabled = false;
                                button.innerHTML = '<i class="fas fa-weight-scale"></i> <span>Marcar para Pesagem</span>';

                                // Remover mensagem após alguns segundos
                                setTimeout(() => {
                                    messageDiv.style.opacity = '0';
                                    setTimeout(() => messageDiv.remove(), 500);
                                }, 5000);
                            });
                        }
                    </script>
                <?php endif; ?>

                <!-- Abastecimento Button -->
                <form action="abastecimento.php" method="POST" class="mt-4">
                    <button
                        type="submit"
                        class="btn-success w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all"
                    >
                        <i class="fas fa-gas-pump mr-2"></i> Abastecimento
                    </button>
                </form>
                <form action="abastecimento_manual.php" method="POST" class="mt-4">
                    <button
                        type="submit"
                        class="btn-success-manual w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all">
                        <i class="fas fa-gas-pump mr-2"></i> Abastecimento Manual
                    </button>
                </form>
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

            setHoraAtual();
            setInterval(setHoraAtual, 1000);

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

            // Verificar e desabilitar botões não preenchidos
            const abastecimentoBtn = document.querySelector('form[action="abastecimento.php"] button');
            if (!abastecimentoBtn) {
                abastecimentoBtn.classList.add('btn-disabled');
            }

            // Verificar botão de Abastecimento Manual
            const abastecimentoManualBtn = document.querySelector('form[action="abastecimento_manual.php"] button');
            if (!abastecimentoManualBtn) {
                abastecimentoManualBtn.classList.add('btn-disabled');
            }

            // Validação do Km final
            const kmFinalInput = document.getElementById('km_final');
            if (kmFinalInput) {
                kmFinalInput.addEventListener('change', function() {
                    const kmFinal = parseFloat(this.value);
                    const kmInicial = parseFloat(document.getElementById('km_inicial').value) || 0;

                    if (isNaN(kmFinal)) {
                        document.getElementById('kmError').textContent = 'Por favor, insira um número válido';
                        return;
                    }

                    if (kmFinal < kmInicial) {
                        document.getElementById('kmError').textContent = 'Km final deve ser maior ou igual ao Km inicial';
                        return;
                    }

                    document.getElementById('kmError').textContent = '';
                });
            }

            // Garante que os campos readonly não sejam focáveis
            const readonlyInputs = document.querySelectorAll('input[readonly]');
            readonlyInputs.forEach(input => {
                input.addEventListener('focus', function(e) {
                    this.blur();
                });
            });
        });

        document.getElementById('finalizarForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-clock"></i><span>Finalizando corrida...</span>';
        submitBtn.classList.remove('bg-primary', 'hover:shadow-lg');
        submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');

        let seconds = 10;
        const interval = setInterval(() => {
            seconds--;
            submitBtn.innerHTML = `<i class="fas fa-clock"></i><span>Finalizando corrida... (${seconds}s)</span>`;

            if (seconds <= 0) {
                clearInterval(interval);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-flag-checkered"></i><span>Finalizar Corrida</span>';
                submitBtn.classList.add('bg-primary', 'hover:shadow-lg');
                submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            }
        }, 1000);
    });
    </script>
</body>
</html>
