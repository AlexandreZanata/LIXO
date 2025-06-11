<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'lixo_adm') {
    header("Location: index.html");
    exit();
}

$user_name = $_SESSION['user_name'];
$mensagem = '';
$status = '';

// Processar exclusão de veículo
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id_excluir = $_GET['delete'];
    
    try {
        $query_delete = "DELETE FROM veiculos_lixo WHERE id = :id";
        $stmt_delete = $conn->prepare($query_delete);
        $stmt_delete->bindParam(':id', $id_excluir, PDO::PARAM_INT);
        $stmt_delete->execute();
        
        $mensagem = "Veículo excluído com sucesso!";
        $status = 'success';
    } catch (PDOException $e) {
        $mensagem = "Erro ao excluir veículo: " . $e->getMessage();
        $status = 'error';
    }
}

// Processar formulário quando submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $prefixo = $_POST['prefixo'];
        $placa = $_POST['placa'];
        $modelo = $_POST['modelo'];
        $peso_vazio = $_POST['peso_vazio'];
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        
        if ($id) {
            // Atualizar veículo existente
            $query = "UPDATE veiculos_lixo SET 
                      prefixo = :prefixo,
                      placa = :placa,
                      modelo = :modelo,
                      veiculo_peso = :peso_vazio
                      WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':prefixo', $prefixo, PDO::PARAM_STR);
            $stmt->bindParam(':placa', $placa, PDO::PARAM_STR);
            $stmt->bindParam(':modelo', $modelo, PDO::PARAM_STR);
            $stmt->bindParam(':peso_vazio', $peso_vazio, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensagem = "Veículo atualizado com sucesso!";
            $status = 'success';
        } else {
            // Verificar se o prefixo já existe
            $check_query = "SELECT id FROM veiculos_lixo WHERE prefixo = :prefixo LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':prefixo', $prefixo, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $mensagem = "Este prefixo de veículo já existe! Use um prefixo diferente.";
                $status = 'error';
            } else {
                // Inserir novo veículo
                $query = "INSERT INTO veiculos_lixo 
                          (prefixo, placa, modelo, veiculo_peso)
                          VALUES 
                          (:prefixo, :placa, :modelo, :peso_vazio)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':prefixo', $prefixo, PDO::PARAM_STR);
                $stmt->bindParam(':placa', $placa, PDO::PARAM_STR);
                $stmt->bindParam(':modelo', $modelo, PDO::PARAM_STR);
                $stmt->bindParam(':peso_vazio', $peso_vazio, PDO::PARAM_STR);
                $stmt->execute();
                
                $mensagem = "Veículo cadastrado com sucesso!";
                $status = 'success';
            }
        }
    } catch (PDOException $e) {
        $mensagem = "Erro ao salvar veículo: " . $e->getMessage();
        $status = 'error';
    }
}

// Tipos de veículos pré-definidos
$tipos_veiculos = ['Compactador', 'Caminhão Caçamba', 'Caminhão Baú', 'Caminhão Carroceria', 'Outro'];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Administração de Veículos</title>
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
                    <i class="fas fa-truck text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-8">Administração de Veículos</h1>
            </div>
        </div>
        
        <!-- Container do conteúdo com largura limitada -->
        <div class="content-container">
            
            <?php if (!empty($mensagem)): ?>
                <div class="message-container <?php echo $status; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulário de Veículo -->
            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6">
                <h2 class="text-xl font-bold mb-4">Cadastrar Veículo</h2>
                
                <form method="POST" id="veiculoForm">
                    <input type="hidden" name="id" id="veiculo_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prefixo do Veículo</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <input 
                                    type="text" 
                                    name="prefixo" 
                                    id="prefixo"
                                    class="w-full bg-transparent focus:outline-none"
                                    required
                                    placeholder="Ex: CAM-001"
                                >
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Placa</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <input 
                                    type="text" 
                                    name="placa" 
                                    id="placa"
                                    class="w-full bg-transparent focus:outline-none"
                                    required
                                    placeholder="Ex: ABC1234"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Modelo do Veículo</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-truck-moving"></i>
                                </div>
                                <select 
                                    name="modelo" 
                                    id="modelo"
                                    class="w-full bg-transparent focus:outline-none"
                                    required
                                >
                                    <option value="">Selecione o modelo</option>
                                    <?php foreach ($tipos_veiculos as $tipo): ?>
                                        <option value="<?php echo htmlspecialchars($tipo); ?>"><?php echo htmlspecialchars($tipo); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Peso Vazio (kg)</label>
                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                                <div class="input-icon text-primary">
                                    <i class="fas fa-weight-hanging"></i>
                                </div>
                                <input 
                                    type="number" 
                                    name="peso_vazio" 
                                    id="peso_vazio"
                                    class="w-full bg-transparent focus:outline-none"
                                    required
                                    step="0.01"
                                    min="0"
                                    placeholder="Ex: 5000.00"
                                >
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button
                            type="submit"
                            class="btn-primary flex-grow py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all mt-4"
                        >
                            <i class="fas fa-save mr-2"></i> Salvar Veículo
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
            
            <!-- Lista de Veículos -->
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <h2 class="text-xl font-bold mb-4">Veículos Cadastrados</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Prefixo</th>
                                <th>Placa</th>
                                <th>Modelo</th>
                                <th>Peso Vazio</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="veiculos-table-body">
                            <?php
                            try {
                                $query = "SELECT * FROM veiculos_lixo ORDER BY prefixo";
                                $stmt = $conn->prepare($query);
                                $stmt->execute();
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<tr data-id="' . $row['id'] . '">';
                                    echo '<td>' . htmlspecialchars($row['prefixo']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['placa']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['modelo']) . '</td>';
                                    echo '<td>' . number_format($row['veiculo_peso'], 2, ',', '.') . ' kg</td>';
                                    echo '<td class="whitespace-nowrap">';
                                    echo '<button class="edit-veiculo text-primary mr-2" data-id="' . $row['id'] . '"><i class="fas fa-edit"></i></button>';
                                    echo '<button class="delete-veiculo text-danger" data-id="' . $row['id'] . '" data-name="' . htmlspecialchars($row['prefixo']) . '"><i class="fas fa-trash-alt"></i></button>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                                
                                if ($stmt->rowCount() == 0) {
                                    echo '<tr><td colspan="5" class="text-center py-4">Nenhum veículo encontrado</td></tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="5" class="text-center py-4 text-red-500">Erro ao carregar veículos: ' . $e->getMessage() . '</td></tr>';
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
            
            // Resetar formulário
            document.getElementById('reset-form').addEventListener('click', function() {
                document.getElementById('veiculoForm').reset();
                document.getElementById('veiculo_id').value = '';
            });
            
            // Editar veículo
            document.addEventListener('click', function(e) {
                if (e.target.closest('.edit-veiculo')) {
                    const btn = e.target.closest('.edit-veiculo');
                    const id = btn.getAttribute('data-id');
                    
                    fetch('get_veiculo_details.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.veiculo) {
                                document.getElementById('veiculo_id').value = data.veiculo.id;
                                document.getElementById('prefixo').value = data.veiculo.prefixo;
                                document.getElementById('placa').value = data.veiculo.placa;
                                document.getElementById('modelo').value = data.veiculo.modelo;
                                document.getElementById('peso_vazio').value = data.veiculo.veiculo_peso;
                                
                                // Rolar para o formulário
                                document.getElementById('veiculoForm').scrollIntoView({ behavior: 'smooth' });
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao buscar detalhes do veículo:', error);
                        });
                }
            });
            
            // Excluir veículo
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-veiculo')) {
                    const btn = e.target.closest('.delete-veiculo');
                    const id = btn.getAttribute('data-id');
                    const nome = btn.getAttribute('data-name');
                    
                    if (confirm(`Tem certeza que deseja excluir o veículo "${nome}"?`)) {
                        window.location.href = `veiculos_admin.php?delete=${id}`;
                    }
                }
            });
        });
    </script>
</body>
</html>