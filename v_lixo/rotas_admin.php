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

// Processar exclusão de rota
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id_excluir = $_GET['delete'];
    
    try {
        $query_delete = "DELETE FROM rotas_lixo WHERE id = :id";
        $stmt_delete = $conn->prepare($query_delete);
        $stmt_delete->bindParam(':id', $id_excluir, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $mensagem = "Rota excluída com sucesso!";
        $status = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir rota: " . $e->getMessage();
        $status = 'error';
    }
}

// Processar formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $rota = $_POST['rota'];
        $bairros = isset($_POST['bairros']) ? $_POST['bairros'] : [];
        $bairros_json = json_encode($bairros);
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        
        if ($id) {
            // Atualizar rota existente
            $query = "UPDATE rotas_lixo SET rota_nome = :rota, bairros_json = :bairros WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
            $stmt->bindParam(':bairros', $bairros_json, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensagem = "Rota atualizada com sucesso!";
            $status = 'success';
        } else {
            // Verificar se a rota já existe
            $check_query = "SELECT id FROM rotas_lixo WHERE rota_nome = :rota LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $mensagem = "Esta rota já existe! Use um nome diferente.";
                $status = 'error';
            } else {
                // Inserir nova rota
                $query = "INSERT INTO rotas_lixo (rota_nome, bairros_json) VALUES (:rota, :bairros)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
                $stmt->bindParam(':bairros', $bairros_json, PDO::PARAM_STR);
                $stmt->execute();
                
                $mensagem = "Rota criada com sucesso!";
                $status = 'success';
            }
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar rota: " . $e->getMessage();
        $status = 'error';
    }
}

// Buscar bairros existentes para sugestões
try {
    $bairros_unicos = [];
    $query_bairros = "SELECT bairros FROM registros_lixo WHERE bairros IS NOT NULL AND bairros != '' ORDER BY data DESC LIMIT 30";
    $stmt_bairros = $conn->prepare($query_bairros);
    $stmt_bairros->execute();
    
    while ($row = $stmt_bairros->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['bairros'])) {
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
    
    sort($bairros_unicos);
} catch (PDOException $e) {
    $mensagem = "Erro ao buscar bairros: " . $e->getMessage();
    $status = 'error';
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Administração de Rotas</title>
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
            top: 1.25rem; /* 20px */
            left: 1.25rem; /* 20px */
            z-index: 1000;
            background-color: #FFFFFF; /* Fundo branco */
            color: #4F46E5; /* Cor do ícone (primária do seu tema) */
            width: 44px;
            height: 44px;
            border-radius: 9999px; /* rounded-full */
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0; /* Borda sutil para contraste */
            transition: all 0.2s ease;
        }
        .back-button:hover {
            background-color: #4F46E5; /* Cor primária no hover */
            color: #FFFFFF; /* Ícone fica branco */
            border-color: #4F46E5; /* Borda da mesma cor do fundo */
            transform: translateY(-2px);
            box-shadow: 0 6px 14px -3px rgba(79, 70, 229, 0.4);
        }
    </style>
</head>
<body>
    <a href="lixo_admin.php" class="back-button" aria-label="Voltar">
        <i class="fas fa-arrow-left"></i>
    </a>
    <div class="app-container">
        <!-- Cabeçalho em largura total -->
        <div class="header-container">
            <div class="logo-container">
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-route text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-8">Administração de Rotas</h1>
            </div>
        </div>
        
        <!-- Container do conteúdo com largura limitada -->
        <div class="content-container">
            
            <?php if (!empty($mensagem)): ?>
                <div class="message-container <?php echo $status; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário de Rota -->
            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6">
                <h2 class="text-xl font-bold mb-4">Gerenciar Rotas e Bairros</h2>
                
                <form method="POST" id="rotaForm">
                    <input type="hidden" name="id" id="rota_id" value="">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Rota</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-route"></i>
                            </div>
                            <input 
                                type="text" 
                                name="rota" 
                                id="rota_nome"
                                class="w-full bg-transparent focus:outline-none"
                                required
                                placeholder="Ex: ROTA 1"
                            >
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Bairros <button type="button" id="add-bairro" class="text-primary text-sm">
                                <i class="fas fa-plus-circle"></i> Adicionar
                            </button>
                        </label>
                        <div id="bairros-container">
                            <div class="bairro-entry mb-2">
                                <div class="flex">
                                    <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9 flex-grow">
                                        <div class="input-icon text-primary">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <input
                                            type="text"
                                            name="bairros[]"
                                            class="w-full bg-transparent focus:outline-none bairro-input"
                                            placeholder="Ex: Centro"
                                            required
                                            list="bairros-list"
                                        >
                                        <datalist id="bairros-list">
                                            <?php foreach ($bairros_unicos as $bairro): ?>
                                                <option value="<?php echo htmlspecialchars($bairro); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    <button type="button" class="remove-bairro ml-2 text-danger p-2">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button
                            type="submit"
                            class="btn-primary flex-grow py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-4"
                        >
                            <i class="fas fa-save mr-2"></i> Salvar Rota
                        </button>
                        <button
                            type="button"
                            id="reset-form"
                            class="bg-gray-500 flex-grow py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-4"
                        >
                            <i class="fas fa-times mr-2"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Rotas -->
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <h2 class="text-xl font-bold mb-4">Rotas Cadastradas</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Rota</th>
                                <th>Bairros</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="rotas-table-body">
                            <?php
                            try {
                                $query = "SELECT * FROM rotas_lixo ORDER BY rota_nome";
                                $stmt = $conn->prepare($query);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $bairros_arr = json_decode($row['bairros_json'], true);
                                    $bairros_str = '';
                                    
                                    if (is_array($bairros_arr) && count($bairros_arr) > 0) {
                                        $bairros_str = implode(', ', $bairros_arr);
                                    } else {
                                        $bairros_str = 'Nenhum bairro cadastrado';
                                    }
                                    
                                    echo '<tr data-id="' . $row['id'] . '">';
                                    echo '<td>' . htmlspecialchars($row['rota_nome']) . '</td>';
                                    echo '<td>' . htmlspecialchars($bairros_str) . '</td>';
                                    echo '<td class="whitespace-nowrap">';
                                    echo '<button class="edit-rota text-primary mr-2" data-id="' . $row['id'] . '"><i class="fas fa-edit"></i></button>';
                                    echo '<button class="delete-rota text-danger" data-id="' . $row['id'] . '" data-name="' . htmlspecialchars($row['rota_nome']) . '"><i class="fas fa-trash-alt"></i></button>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                                
                                if ($stmt->rowCount() == 0) {
                                    echo '<tr><td colspan="3" class="text-center py-4">Nenhuma rota encontrada</td></tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="3" class="text-center py-4 text-red-500">Erro ao carregar rotas: ' . $e->getMessage() . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Botões de Navegação -->
            <div class="flex justify-center mt-6 space-x-4">
                <a href="lixo_admin.php" class="btn-primary py-2 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-arrow-left mr-2"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar mensagens
            if (document.querySelector('.message-container')) {
                setTimeout(function() {
                    document.querySelector('.message-container').style.opacity = '0';
                    setTimeout(function() {
                        document.querySelector('.message-container').remove();
                    }, 500);
                }, 3000);
            }
            
            // Adicionar novo campo de bairro
            const addBairroBtn = document.getElementById('add-bairro');
            if (addBairroBtn) {
                addBairroBtn.addEventListener('click', function() {
                    addBairroField('');
                });
            }
            
            // Resetar formulário
            document.getElementById('reset-form').addEventListener('click', function() {
                document.getElementById('rotaForm').reset();
                document.getElementById('rota_id').value = '';
                
                // Limpar todos os bairros exceto o primeiro
                const bairrosContainer = document.getElementById('bairros-container');
                const bairroEntries = bairrosContainer.querySelectorAll('.bairro-entry');
                for (let i = 1; i < bairroEntries.length; i++) {
                    bairroEntries[i].remove();
                }
                
                // Limpar o primeiro bairro
                if (bairroEntries.length > 0) {
                    bairroEntries[0].querySelector('input').value = '';
                }
            });
            
            // Editar rota
            document.addEventListener('click', function(e) {
                if (e.target.closest('.edit-rota')) {
                    const btn = e.target.closest('.edit-rota');
                    const id = btn.getAttribute('data-id');
                    
                    fetch('get_rota_details.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.rota) {
                                document.getElementById('rota_id').value = data.rota.id;
                                document.getElementById('rota_nome').value = data.rota.rota_nome;
                                
                                // Limpar bairros
                                const bairrosContainer = document.getElementById('bairros-container');
                                bairrosContainer.innerHTML = '';
                                
                                // Adicionar bairros da rota
                                if (data.rota.bairros_json) {
                                    const bairros = JSON.parse(data.rota.bairros_json);
                                    if (Array.isArray(bairros) && bairros.length > 0) {
                                        bairros.forEach(bairro => {
                                            addBairroField(bairro);
                                        });
                                    } else {
                                        addBairroField('');
                                    }
                                } else {
                                    addBairroField('');
                                }
                                
                                // Rolar para o formulário
                                document.getElementById('rotaForm').scrollIntoView({ behavior: 'smooth' });
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao buscar detalhes da rota:', error);
                        });
                }
            });
            
            // Excluir rota
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-rota')) {
                    const btn = e.target.closest('.delete-rota');
                    const id = btn.getAttribute('data-id');
                    const nome = btn.getAttribute('data-name');
                    
                    if (confirm(`Tem certeza que deseja excluir a rota "${nome}"?`)) {
                        window.location.href = `rotas_admin.php?delete=${id}`;
                    }
                }
            });
            
            // Função para adicionar campo de bairro
            function addBairroField(valor) {
                const bairrosContainer = document.getElementById('bairros-container');
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
                                class="w-full bg-transparent focus:outline-none bairro-input"
                                placeholder="Ex: Centro"
                                required
                                value="${valor}"
                                list="bairros-list"
                            >
                        </div>
                        <button type="button" class="remove-bairro ml-2 text-danger p-2">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                `;
                bairrosContainer.appendChild(bairroEntry);
            }
            
            // Delegate para remover bairros
            document.addEventListener('click', function(e) {
                if (e.target && e.target.closest('.remove-bairro')) {
                    const entry = e.target.closest('.bairro-entry');
                    if (entry) {
                        // Não remover o último bairro
                        const bairrosContainer = document.getElementById('bairros-container');
                        if (bairrosContainer.querySelectorAll('.bairro-entry').length > 1) {
                            entry.remove();
                        } else {
                            // Limpar o campo se for o último
                            entry.querySelector('input').value = '';
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>