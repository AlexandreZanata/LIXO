<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo') {
    header("Location: ../index.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$secretaria = $_SESSION['secretaria'];
$message = '';
$status = '';

date_default_timezone_set('America/Cuiaba');

try {
    // Buscar dados do usuário
    $user_query = "SELECT id, codigo_veiculo FROM usuarios WHERE name = :name LIMIT 1";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':name', $user_name, PDO::PARAM_STR);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $user_id = $user_data['id'];
        $codigo_veiculo = $user_data['codigo_veiculo'];
    } else {
        echo '<div class="message-container error">Erro: Usuário não encontrado.</div>';
        exit();
    }

    // Buscar configuração do motorista de lixo
    $config_query = "SELECT * FROM config_motoristas_lixo WHERE usuario_id = :user_id LIMIT 1";
    $config_stmt = $conn->prepare($config_query);
    $config_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $config_stmt->execute();
    $driver_config = $config_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver_config) {
        echo '<div class="message-container error">Erro: Configuração não encontrada. Entre em contato com o administrador.</div>';
        exit();
    }

    // Extrair bairros da configuração
    $bairros_config = json_decode($driver_config['bairros'], true);
    $bairros_list = is_array($bairros_config) ? $bairros_config : [];

} catch (PDOException $e) {
    echo '<div class="message-container error">Erro: ' . $e->getMessage() . '</div>';
    exit();
}

// Verificar se a rota já foi iniciada hoje
$today = date('Y-m-d');
$rota_iniciada = false;

try {
    // Criar tabela registros_lixo_bairros se não existir
    $create_table_query = "CREATE TABLE IF NOT EXISTS registros_lixo_bairros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nome_usuario VARCHAR(255) NOT NULL,
        bairro VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL,
        data_registro DATE NOT NULL,
        hora_registro TIME NOT NULL,
        INDEX idx_usuario_data (usuario_id, data_registro),
        INDEX idx_status (status)
    )";
    $conn->exec($create_table_query);

    // Verificar se a rota já foi iniciada hoje
    $check_start_query = "SELECT COUNT(*) FROM registros_lixo_bairros 
                         WHERE usuario_id = :user_id AND data_registro = :today AND status = 'iniciado'";
    $check_start_stmt = $conn->prepare($check_start_query);
    $check_start_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_start_stmt->bindParam(':today', $today, PDO::PARAM_STR);
    $check_start_stmt->execute();
    $rota_iniciada = $check_start_stmt->fetchColumn() > 0;

    // Buscar bairros já finalizados hoje
    $finalized_bairros_query = "SELECT bairro FROM registros_lixo_bairros 
                               WHERE usuario_id = :user_id AND data_registro = :today AND status = 'finalizado'";
    $finalized_stmt = $conn->prepare($finalized_bairros_query);
    $finalized_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $finalized_stmt->bindParam(':today', $today, PDO::PARAM_STR);
    $finalized_stmt->execute();
    $finalized_bairros = $finalized_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    echo '<div class="message-container error">Erro ao verificar status: ' . $e->getMessage() . '</div>';
    exit();
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start_route') {
        try {
            // Verificar se já foi iniciada hoje
            if ($rota_iniciada) {
                echo json_encode(['success' => false, 'message' => 'Rota já foi iniciada hoje']);
                exit();
            }

            // Registrar início da rota
            $insert_query = "INSERT INTO registros_lixo_bairros 
                           (usuario_id, nome_usuario, bairro, status, data_registro, hora_registro) 
                           VALUES (:user_id, :nome_usuario, 'INICIO_ROTA', 'iniciado', :data, :hora)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':nome_usuario', $user_name, PDO::PARAM_STR);
            $insert_stmt->bindParam(':data', $today, PDO::PARAM_STR);
            $insert_stmt->bindParam(':hora', date('H:i:s'), PDO::PARAM_STR);
            $insert_stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Rota iniciada com sucesso']);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao iniciar rota: ' . $e->getMessage()]);
            exit();
        }
    }
    
    if ($action === 'mark_bairro') {
        $bairro = $_POST['bairro'] ?? '';
        
        try {
            // Verificar se a rota foi iniciada
            if (!$rota_iniciada) {
                echo json_encode(['success' => false, 'message' => 'Primeiro você deve iniciar a rota']);
                exit();
            }

            // Verificar se o bairro já foi finalizado hoje
            $check_query = "SELECT COUNT(*) FROM registros_lixo_bairros 
                           WHERE usuario_id = :user_id AND data_registro = :today 
                           AND bairro = :bairro AND status = 'finalizado'";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $check_stmt->bindParam(':today', $today, PDO::PARAM_STR);
            $check_stmt->bindParam(':bairro', $bairro, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Bairro já foi finalizado hoje']);
                exit();
            }

            // Registrar finalização do bairro
            $insert_query = "INSERT INTO registros_lixo_bairros 
                           (usuario_id, nome_usuario, bairro, status, data_registro, hora_registro) 
                           VALUES (:user_id, :nome_usuario, :bairro, 'finalizado', :data, :hora)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $insert_stmt->bindParam(':nome_usuario', $user_name, PDO::PARAM_STR);
            $insert_stmt->bindParam(':bairro', $bairro, PDO::PARAM_STR);
            $insert_stmt->bindParam(':data', $today, PDO::PARAM_STR);
            $insert_stmt->bindParam(':hora', date('H:i:s'), PDO::PARAM_STR);
            $insert_stmt->execute();

            echo json_encode(['success' => true, 'message' => 'Bairro marcado como finalizado']);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao marcar bairro: ' . $e->getMessage()]);
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="png" href="../ico_nav/img.claro.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="png" href="../ico_nav/img.escuro.png" media="(prefers-color-scheme: dark)">
    <title>Finalizar Bairros</title>
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
            background-color: #10B981;
            transition: all 0.2s ease;
        }
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.25);
        }
        .btn-disabled {
            background-color: #e5e7eb !important;
            color: #9ca3af !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
            box-shadow: none !important;
        }
        .btn-started {
            background-color: #059669;
            color: white;
        }
        .btn-completed {
            background-color: #10B981;
            color: white;
        }
        .logo-container {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            margin-bottom: 10px;
            width: 100vw;
            left: 50%;
            transform: translateX(-50%);
            position: relative;
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
        .bairro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        @media (max-width: 640px) {
            .bairro-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Logo Header -->
        <div class="logo-container h-48 rounded-b-3xl shadow-hard">
            <div class="content-wrapper flex flex-col items-center justify-center h-full relative">
                <div class="back-button" onclick="window.location.href = '../diario.php'">
                    <i class="fas fa-home text-primary"></i>
                </div>
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-map-marked-alt text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-2">Finalizar Bairros</h1>
                <p class="text-white/80 text-center">Rota: <?php echo htmlspecialchars($driver_config['rota']); ?></p>
            </div>
        </div>

        <!-- Forms Container -->
        <div class="forms-container px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (!empty($message)) echo $message; ?>

                <!-- Instruções -->
                <div class="mb-6 text-center">
                    <p class="text-gray-600 mb-4">
                        Toque nos bairros visitados para marcá-los como finalizados.
                    </p>
                    
                    <!-- Botão Iniciar Rota -->
                    <button 
                        id="startRouteBtn"
                        class="<?php echo $rota_iniciada ? 'btn-started' : 'btn-primary'; ?> w-full max-w-md py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mb-6 flex items-center justify-center gap-2"
                        <?php echo $rota_iniciada ? 'disabled' : ''; ?>
                    >
                        <i class="fas fa-play"></i>
                        <span><?php echo $rota_iniciada ? 'Rota Iniciada' : 'Iniciar Rota'; ?></span>
                    </button>
                </div>

                <!-- Lista de Bairros -->
                <div class="bairro-grid">
                    <?php foreach ($bairros_list as $bairro): ?>
                        <?php 
                        $is_completed = in_array($bairro, $finalized_bairros);
                        $is_disabled = !$rota_iniciada && !$is_completed;
                        ?>
                        <button 
                            class="bairro-btn <?php echo $is_completed ? 'btn-completed' : ($is_disabled ? 'btn-disabled' : 'btn-primary'); ?> py-4 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2"
                            data-bairro="<?php echo htmlspecialchars($bairro); ?>"
                            <?php echo ($is_disabled || $is_completed) ? 'disabled' : ''; ?>
                        >
                            <i class="fas <?php echo $is_completed ? 'fa-check-circle' : 'fa-map-marker-alt'; ?>"></i>
                            <span><?php echo htmlspecialchars($bairro); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($bairros_list)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-info-circle text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">Nenhum bairro configurado para esta rota.</p>
                        <p class="text-gray-500 text-sm mt-2">Entre em contato com o administrador.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startRouteBtn = document.getElementById('startRouteBtn');
            const bairroBtns = document.querySelectorAll('.bairro-btn');

            // Iniciar rota
            startRouteBtn.addEventListener('click', function() {
                if (this.disabled) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Iniciando...</span>';

                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=start_route'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        this.className = 'btn-started w-full max-w-md py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mb-6 flex items-center justify-center gap-2';
                        this.innerHTML = '<i class="fas fa-play"></i> <span>Rota Iniciada</span>';
                        
                        // Habilitar botões de bairro
                        bairroBtns.forEach(btn => {
                            if (!btn.classList.contains('btn-completed')) {
                                btn.disabled = false;
                                btn.classList.remove('btn-disabled');
                                btn.classList.add('btn-primary');
                            }
                        });
                    } else {
                        showMessage(data.message, 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-play"></i> <span>Iniciar Rota</span>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    showMessage('Erro ao iniciar rota', 'error');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play"></i> <span>Iniciar Rota</span>';
                });
            });

            // Marcar bairros
            bairroBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.disabled) return;

                    const bairro = this.getAttribute('data-bairro');
                    const originalContent = this.innerHTML;
                    
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Marcando...</span>';

                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_bairro&bairro=${encodeURIComponent(bairro)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            this.className = 'bairro-btn btn-completed py-4 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2';
                            this.innerHTML = '<i class="fas fa-check-circle"></i> <span>' + bairro + '</span>';
                        } else {
                            showMessage(data.message, 'error');
                            this.disabled = false;
                            this.innerHTML = originalContent;
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        showMessage('Erro ao marcar bairro', 'error');
                        this.disabled = false;
                        this.innerHTML = originalContent;
                    });
                });
            });

            function showMessage(message, type) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message-container ${type}`;
                messageDiv.innerHTML = message;
                document.body.appendChild(messageDiv);

                setTimeout(() => {
                    messageDiv.style.opacity = '0';
                    setTimeout(() => messageDiv.remove(), 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>