<?php
session_start();
include '../conexao.php';

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_name']) || !in_array($_SESSION['role'], ['lixo_adm', 'admin', 'geraladm'])) {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$motorista = isset($_GET['motorista']) ? $_GET['motorista'] : '';
$rota = isset($_GET['rota']) ? $_GET['rota'] : '';
$tipo_lixo = isset($_GET['tipo_lixo']) ? $_GET['tipo_lixo'] : '';

// Buscar todos os motoristas
try {
    $query_motoristas = "SELECT DISTINCT nome FROM registros_lixo ORDER BY nome";
    $stmt_motoristas = $conn->prepare($query_motoristas);
    $stmt_motoristas->execute();
    $motoristas = $stmt_motoristas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $erro_motoristas = "Erro ao buscar motoristas: " . $e->getMessage();
}

// Buscar todas as rotas
try {
    $query_rotas = "SELECT DISTINCT rota FROM registros_lixo WHERE rota IS NOT NULL AND rota != '' ORDER BY rota";
    $stmt_rotas = $conn->prepare($query_rotas);
    $stmt_rotas->execute();
    $rotas = $stmt_rotas->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $erro_rotas = "Erro ao buscar rotas: " . $e->getMessage();
}

// Buscar todos os tipos de lixo
try {
    $query_tipos = "SELECT DISTINCT tipo_lixo FROM registros_lixo WHERE tipo_lixo IS NOT NULL AND tipo_lixo != '' ORDER BY tipo_lixo";
    $stmt_tipos = $conn->prepare($query_tipos);
    $stmt_tipos->execute();
    $tipos_lixo = $stmt_tipos->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $erro_tipos = "Erro ao buscar tipos de lixo: " . $e->getMessage();
}

// Construir a consulta base
$base_query = "SELECT 
                r.id,
                r.nome,
                r.data,
                r.hora,
                r.data_final,
                r.hora_final,
                r.rota,
                r.periodo_trabalho,
                r.tipo_lixo,
                r.bairros,
                r.km_inicial,
                r.km_final,
                r.peso_caminhao_vazio,
                r.peso_total,
                r.peso_liquido,
                v.veiculo,
                v.placa
              FROM registros_lixo r
              LEFT JOIN veiculos v ON r.veiculo_id = v.veiculo
              WHERE r.data BETWEEN :data_inicio AND :data_fim";

$params = [
    ':data_inicio' => $data_inicio,
    ':data_fim' => $data_fim
];

// Adicionar filtros opcionais
if (!empty($motorista)) {
    $base_query .= " AND r.nome = :motorista";
    $params[':motorista'] = $motorista;
}

if (!empty($rota)) {
    $base_query .= " AND r.rota = :rota";
    $params[':rota'] = $rota;
}

if (!empty($tipo_lixo)) {
    $base_query .= " AND r.tipo_lixo = :tipo_lixo";
    $params[':tipo_lixo'] = $tipo_lixo;
}

// Ordenar resultados
$base_query .= " ORDER BY r.data DESC, r.hora DESC";

// Executar a consulta
try {
    $stmt = $conn->prepare($base_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $total_registros = count($registros);
    $total_peso = 0;
    
    foreach ($registros as $registro) {
        $total_peso += floatval($registro['peso_liquido']);
    }
} catch (PDOException $e) {
    $erro_registros = "Erro ao buscar registros: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Relatório de Coleta de Lixo</title>
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
            max-width: 1200px;
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
        @media print {
            .no-print {
                display: none;
            }
            body {
                background-color: white;
                font-size: 12px;
            }
            .content-container {
                margin-top: 0;
                max-width: 100%;
            }
            .print-break {
                page-break-before: always;
            }
        }
        .back-button {
            position: fixed;
            top: 1.25rem; /* 20px */
            left: 1.25rem; /* 20px */
            z-index: 1000;
            background-color: #FFFFFF; /* Fundo branco */
            color: #4F46E5; 
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
    <a href="menu_lixo_adm.php" class="back-button" aria-label="Voltar ao menu">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="app-container">
        <!-- Cabeçalho em largura total -->
        <div class="header-container no-print">
            <div class="logo-container">
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-trash-alt text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-2">Relatório de Coleta de Lixo</h1>
                <p class="text-white/80 text-sm">Visualize e exporte dados de coleta de lixo</p>
            </div>
        </div>

        <!-- Container do conteúdo com largura limitada -->
        <div class="content-container">
            <!-- Cabeçalho somente para impressão -->
            <div class="hidden print:block text-center mb-6">
                <h1 class="text-2xl font-bold">Relatório de Coleta de Lixo</h1>
                <p class="text-sm text-gray-600">Período: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></p>
            </div>
            
            <!-- Filtros de Busca -->
            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6 no-print">
                <h2 class="text-xl font-bold mb-4">Filtros</h2>
                
                <form method="GET" id="filtrosForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Inicial</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <input 
                                type="date" 
                                name="data_inicio" 
                                class="w-full bg-transparent focus:outline-none"
                                value="<?php echo $data_inicio; ?>"
                            >
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Final</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <input 
                                type="date" 
                                name="data_fim" 
                                class="w-full bg-transparent focus:outline-none"
                                value="<?php echo $data_fim; ?>"
                            >
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motorista</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-user"></i>
                            </div>
                            <select 
                                name="motorista" 
                                class="w-full bg-transparent focus:outline-none"
                            >
                                <option value="">Todos os Motoristas</option>
                                <?php foreach ($motoristas as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $motorista === $m ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rota</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-route"></i>
                            </div>
                            <select 
                                name="rota" 
                                class="w-full bg-transparent focus:outline-none"
                            >
                                <option value="">Todas as Rotas</option>
                                <?php foreach ($rotas as $r): ?>
                                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $rota === $r ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($r); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Lixo</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-300 pl-9">
                            <div class="input-icon text-primary">
                                <i class="fas fa-trash"></i>
                            </div>
                            <select 
                                name="tipo_lixo" 
                                class="w-full bg-transparent focus:outline-none"
                            >
                                <option value="">Todos os Tipos</option>
                                <?php foreach ($tipos_lixo as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $tipo_lixo === $tipo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-end">
                        <button 
                            type="submit" 
                            class="btn-primary w-full py-3 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all"
                        >
                            <i class="fas fa-search mr-2"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Resumo dos Resultados -->
            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Resultados</h2>
                    <div class="no-print">
                        <button onclick="window.print()" class="flex items-center justify-center bg-secondary text-white py-2 px-4 rounded-lg hover:bg-yellow-600 transition-all">
                            <i class="fas fa-print mr-2"></i> Imprimir
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="text-sm text-gray-500 mb-1">Total de Registros</div>
                        <div class="text-2xl font-bold text-primary"><?php echo $total_registros; ?></div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="text-sm text-gray-500 mb-1">Peso Total Coletado</div>
                        <div class="text-2xl font-bold text-primary"><?php echo number_format($total_peso, 2, ',', '.'); ?> kg</div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                        <div class="text-sm text-gray-500 mb-1">Período</div>
                        <div class="text-lg font-bold text-primary">
                            <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($erro_registros)): ?>
                    <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                        <?php echo $erro_registros; ?>
                    </div>
                <?php elseif (empty($registros)): ?>
                    <div class="bg-yellow-100 text-yellow-700 p-4 rounded-lg mb-4">
                        Nenhum registro encontrado com os filtros selecionados.
                    </div>
                <?php else: ?>
                    <!-- Tabela de Registros -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Motorista</th>
                                    <th>Veículo</th>
                                    <th>Rota</th>
                                    <th>Tipo</th>
                                    <th>Peso (kg)</th>
                                    <th>Bairros</th>
                                    <th class="no-print">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $registro): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($registro['data'])); ?></td>
                                        <td><?php echo htmlspecialchars($registro['nome']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($registro['veiculo'] ?? 'N/A'); ?>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($registro['placa'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($registro['rota']); ?></td>
                                        <td><?php echo htmlspecialchars($registro['tipo_lixo']); ?></td>
                                        <td>
                                            <?php if (!empty($registro['peso_liquido'])): ?>
                                                <?php echo number_format($registro['peso_liquido'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($registro['bairros'])) {
                                                $bairros = json_decode($registro['bairros'], true);
                                                if (is_array($bairros)) {
                                                    echo implode(', ', array_slice($bairros, 0, 2));
                                                    if (count($bairros) > 2) {
                                                        echo ' <span class="text-xs text-gray-500">+' . (count($bairros) - 2) . '</span>';
                                                    }
                                                } else {
                                                    echo htmlspecialchars($registro['bairros']);
                                                }
                                            } else {
                                                echo '<span class="text-gray-400">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="no-print">
                                            <button 
                                                class="view-details text-primary hover:text-primary-dark" 
                                                data-id="<?php echo $registro['id']; ?>"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Gráficos e Análises -->
            <div class="bg-white rounded-2xl p-6 shadow-hard mb-6 no-print">
                <h2 class="text-xl font-bold mb-4">Análise de Dados</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Peso Coletado por Tipo de Lixo</h3>
                        <div class="bg-gray-50 rounded-xl p-4 h-64 border border-gray-200" id="grafico-tipo-lixo">
                            <!-- Gráfico será renderizado aqui -->
                            <div class="w-full h-full flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-chart-pie text-3xl mb-2"></i>
                                    <p>Carregando gráfico...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Peso Coletado por Rota</h3>
                        <div class="bg-gray-50 rounded-xl p-4 h-64 border border-gray-200" id="grafico-rota">
                            <!-- Gráfico será renderizado aqui -->
                            <div class="w-full h-full flex items-center justify-center text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-chart-bar text-3xl mb-2"></i>
                                    <p>Carregando gráfico...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal de Detalhes (Visível apenas quando clicado) -->
            <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden no-print">
                <div class="bg-white rounded-2xl p-6 shadow-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold" id="modal-title">Detalhes do Registro</h3>
                        <button id="closeModal" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="modal-content">
                        <!-- Conteúdo será carregado dinamicamente -->
                        <div class="flex justify-center">
                            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botões de Navegação -->
            <div class="flex justify-center mt-6 space-x-4 no-print">
                <a href="menu.php" class="btn-primary py-2 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-home mr-2"></i> Menu Principal
                </a>
                <?php if ($_SESSION['role'] === 'lixo_adm'): ?>
                <a href="lixo_admin.php" class="bg-secondary py-2 px-6 rounded-xl text-white font-bold shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-cog mr-2"></i> Administração
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manipulação do modal de detalhes
            const modal = document.getElementById('detailsModal');
            const closeModalBtn = document.getElementById('closeModal');
            const modalContent = document.getElementById('modal-content');
            const modalTitle = document.getElementById('modal-title');
            
            // Fechar modal ao clicar no botão de fechar
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function() {
                    modal.classList.add('hidden');
                });
            }
            
            // Fechar modal ao clicar fora dele
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
            
            // Botões de visualização de detalhes
            const viewButtons = document.querySelectorAll('.view-details');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const registroId = this.getAttribute('data-id');
                    if (registroId) {
                        // Mostrar modal e carregador
                        modal.classList.remove('hidden');
                        modalContent.innerHTML = '<div class="flex justify-center"><div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div></div>';
                        
                        // Buscar detalhes do registro
                        fetch('get_registro_lixo_details.php?id=' + registroId)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Formatar a data
                                    const dataFormatada = new Date(data.registro.data).toLocaleDateString('pt-BR');
                                    
                                    // Calcular a distância percorrida
                                    let distancia = 0;
                                    if (data.registro.km_inicial && data.registro.km_final) {
                                        distancia = parseInt(data.registro.km_final) - parseInt(data.registro.km_inicial);
                                    }
                                    
                                    // Formatar os bairros
                                    let bairrosHtml = '<span class="text-gray-400">Não informados</span>';
                                    if (data.registro.bairros) {
                                        try {
                                            const bairros = JSON.parse(data.registro.bairros);
                                            if (Array.isArray(bairros) && bairros.length > 0) {
                                                bairrosHtml = bairros.map(b => `<span class="inline-block bg-gray-100 text-gray-800 rounded-full px-3 py-1 text-sm font-semibold mr-2 mb-2">${b}</span>`).join('');
                                            }
                                        } catch (e) {
                                            bairrosHtml = data.registro.bairros;
                                        }
                                    }
                                    
                                    // Atualizar título do modal
                                    modalTitle.textContent = `Registro de Coleta - ${dataFormatada}`;
                                    
                                    // Construir conteúdo do modal
                                    let htmlContent = `
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                            <div>
                                                <p class="text-sm text-gray-500">Motorista</p>
                                                <p class="font-semibold">${data.registro.nome}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Data e Hora</p>
                                                <p class="font-semibold">${dataFormatada} - ${data.registro.hora}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Veículo</p>
                                                <p class="font-semibold">${data.registro.veiculo || 'N/A'}</p>
                                                <p class="text-xs text-gray-500">${data.registro.placa || ''}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Rota</p>
                                                <p class="font-semibold">${data.registro.rota || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Período de Trabalho</p>
                                                <p class="font-semibold">${data.registro.periodo_trabalho || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Tipo de Lixo</p>
                                                <p class="font-semibold">${data.registro.tipo_lixo || 'N/A'}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <p class="text-sm text-gray-500">KM Inicial</p>
                                                <p class="font-semibold">${data.registro.km_inicial || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">KM Final</p>
                                                <p class="font-semibold">${data.registro.km_final || 'N/A'}</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Distância</p>
                                                <p class="font-semibold">${distancia} km</p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <p class="text-sm text-gray-500">Peso do Caminhão Vazio</p>
                                                <p class="font-semibold">${Number(data.registro.peso_caminhao_vazio || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Peso Total</p>
                                                <p class="font-semibold">${Number(data.registro.peso_total || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg</p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">Peso Líquido</p>
                                                <p class="font-semibold">${Number(data.registro.peso_liquido || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg</p>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <p class="text-sm text-gray-500 mb-2">Bairros Atendidos</p>
                                            <div class="flex flex-wrap">
                                                ${bairrosHtml}
                                            </div>
                                        </div>
                                    `;
                                    
                                    // Adicionar informações de conclusão se houver
                                    if (data.registro.data_final) {
                                        const dataFinalFormatada = new Date(data.registro.data_final).toLocaleDateString('pt-BR');
                                        htmlContent += `
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <h4 class="font-bold mb-2">Informações de Conclusão</h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <p class="text-sm text-gray-500">Data e Hora de Conclusão</p>
                                                        <p class="font-semibold">${dataFinalFormatada} - ${data.registro.hora_final || 'N/A'}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    }
                                    
                                    modalContent.innerHTML = htmlContent;
                                } else {
                                    modalContent.innerHTML = `<div class="text-center text-red-500">
                                        <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
                                        <p>${data.message || 'Erro ao carregar detalhes do registro.'}</p>
                                    </div>`;
                                }
                            })
                            .catch(error => {
                                console.error('Erro ao buscar detalhes:', error);
                                modalContent.innerHTML = `<div class="text-center text-red-500">
                                    <i class="fas fa-exclamation-circle text-3xl mb-2"></i>
                                    <p>Erro ao carregar detalhes do registro.</p>
                                </div>`;
                            });
                    }
                });
            });
            
            // Inicializar gráficos se houver dados
            const registros = <?php echo json_encode($registros ?? []); ?>;
            if (registros.length > 0) {
                // Preparar dados para gráfico por tipo de lixo
                const tiposLixo = {};
                const rotasPeso = {};
                
                registros.forEach(registro => {
                    const tipo = registro.tipo_lixo || 'Não especificado';
                    const peso = parseFloat(registro.peso_liquido) || 0;
                    
                    // Agregar por tipo de lixo
                    if (!tiposLixo[tipo]) {
                        tiposLixo[tipo] = 0;
                    }
                    tiposLixo[tipo] += peso;
                    
                    // Agregar por rota
                    const rota = registro.rota || 'Não especificada';
                    if (!rotasPeso[rota]) {
                        rotasPeso[rota] = 0;
                    }
                    rotasPeso[rota] += peso;
                });
                
                // Gerar cores aleatórias
                function getRandomColor() {
                    const letters = '0123456789ABCDEF';
                    let color = '#';
                    for (let i = 0; i < 6; i++) {
                        color += letters[Math.floor(Math.random() * 16)];
                    }
                    return color;
                }
                
                // Cores para os gráficos
                const coresTipos = Object.keys(tiposLixo).map(() => getRandomColor());
                const coresRotas = Object.keys(rotasPeso).map(() => getRandomColor());
                
                // Gráfico de pizza para tipos de lixo
                const ctxTiposLixo = document.getElementById('grafico-tipo-lixo');
                if (ctxTiposLixo) {
                    const canvasTiposLixo = document.createElement('canvas');
                    canvasTiposLixo.id = 'canvas-tipos-lixo';
                    ctxTiposLixo.innerHTML = '';
                    ctxTiposLixo.appendChild(canvasTiposLixo);
                    
                    new Chart(canvasTiposLixo, {
                        type: 'pie',
                        data: {
                            labels: Object.keys(tiposLixo),
                            datasets: [{
                                data: Object.values(tiposLixo),
                                backgroundColor: coresTipos,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 15,
                                        padding: 15
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const total = context.dataset.data.reduce((acc, curr) => acc + curr, 0);
                                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                            return `${label}: ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Gráfico de barras para rotas
                const ctxRotas = document.getElementById('grafico-rota');
                if (ctxRotas) {
                    const canvasRotas = document.createElement('canvas');
                    canvasRotas.id = 'canvas-rotas';
                    ctxRotas.innerHTML = '';
                    ctxRotas.appendChild(canvasRotas);
                    
                    new Chart(canvasRotas, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(rotasPeso),
                            datasets: [{
                                label: 'Peso Coletado (kg)',
                                data: Object.values(rotasPeso),
                                backgroundColor: coresRotas,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toLocaleString('pt-BR') + ' kg';
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw || 0;
                                            return `${value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } else {
                // Sem dados para os gráficos
                document.getElementById('grafico-tipo-lixo').innerHTML = `
                    <div class="w-full h-full flex items-center justify-center text-gray-500">
                        <div class="text-center">
                            <i class="fas fa-chart-pie text-3xl mb-2"></i>
                            <p>Sem dados para exibir</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('grafico-rota').innerHTML = `
                    <div class="w-full h-full flex items-center justify-center text-gray-500">
                        <div class="text-center">
                            <i class="fas fa-chart-bar text-3xl mb-2"></i>
                            <p>Sem dados para exibir</p>
                        </div>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>