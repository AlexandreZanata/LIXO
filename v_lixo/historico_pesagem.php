<?php
session_start();
include '../conexao.php';

// Verifica se o usuário está logado e tem a role correta
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'balanca') {
    header("Location: index.php");
    exit();
}

$user_name = $_SESSION['user_name'];
$message = '';

// Buscar histórico de pesagens (últimos 30 dias por padrão)
$dias = isset($_GET['dias']) ? intval($_GET['dias']) : 30;
$tipo_lixo = isset($_GET['tipo_lixo']) ? $_GET['tipo_lixo'] : '';

try {
    // Filtros para a consulta
    $filtro_tipo = '';
    $params = [':dias' => $dias];
    
    if (!empty($tipo_lixo)) {
        $filtro_tipo = "AND r.tipo_lixo = :tipo_lixo";
        $params[':tipo_lixo'] = $tipo_lixo;
    }
    
    // Buscar tipos de lixo disponíveis para o filtro
    $tipos_query = "SELECT DISTINCT tipo_lixo FROM registros_lixo ORDER BY tipo_lixo";
    $tipos_stmt = $conn->prepare($tipos_query);
    $tipos_stmt->execute();
    $tipos_lixo = $tipos_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar histórico de pesagens
    $query = "SELECT r.id, r.nome, r.veiculo_id, r.placa, r.nome_veiculo, 
                     r.peso_caminhao_vazio, r.peso_total, r.peso_liquido, 
                     r.km_inicial, r.km_final, r.data, r.hora, r.tipo_lixo, 
                     r.rota, r.pesado_por, r.status_pesagem
              FROM registros_lixo r
              WHERE r.status_pesagem = 'finalizado'
                AND r.data >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
                $filtro_tipo
              ORDER BY r.data DESC, r.hora DESC";
    
    $stmt = $conn->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $pesagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $total_pesagens = count($pesagens);
    $total_peso_liquido = 0;
    
    foreach ($pesagens as $pesagem) {
        $total_peso_liquido += floatval($pesagem['peso_liquido']);
    }
    
} catch (PDOException $e) {
    $message = '<div class="message-container error">Erro ao buscar histórico: ' . $e->getMessage() . '</div>';
    $pesagens = [];
    $total_pesagens = 0;
    $total_peso_liquido = 0;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="png" href="ico_nav/img.claro.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="png" href="ico_nav/img.escuro.png" media="(prefers-color-scheme: dark)">
    <title>Histórico de Pesagens</title>
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
        .logo-container {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            margin-bottom: 10px;
            width: 100vw; 
            left: 50%;
            transform: translateX(-50%);
            position: relative;
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
        .vehicle-card {
            transition: all 0.3s ease;
        }
        .vehicle-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Vehicle ID destacado */
        .vehicle-id {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4F46E5;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.1) 0%, rgba(79, 70, 229, 0.05) 100%);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid #4F46E5;
            letter-spacing: 0.05em;
            text-align: center;
        }
        
        /* Estilos para tabelas */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .data-table th {
            background-color: #f3f4f6;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table tr:hover td {
            background-color: #f9fafb;
        }
        
        /* Responsive fixes */
        @media (min-width: 640px) {
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
        /* iOS fix */
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
                <div class="back-button absolute top-6 left-6" onclick="window.location.href = 'pesagem_lixo.php'">
                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-md">
                        <i class="fas fa-arrow-left text-primary"></i>
                    </div>
                </div>
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-history text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-1">Histórico de Pesagens</h1>
            </div>
        </div>

        <!-- Content Area -->
        <div class="px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (!empty($message)) echo $message; ?>

                <!-- Filtros -->
                <div class="mb-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-3">Filtros</h2>
                    <form action="" method="GET" class="flex flex-col space-y-3 sm:flex-row sm:space-y-0 sm:space-x-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                            <select name="dias" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                <option value="7" <?php echo $dias == 7 ? 'selected' : ''; ?>>Últimos 7 dias</option>
                                <option value="15" <?php echo $dias == 15 ? 'selected' : ''; ?>>Últimos 15 dias</option>
                                <option value="30" <?php echo $dias == 30 ? 'selected' : ''; ?>>Últimos 30 dias</option>
                                <option value="90" <?php echo $dias == 90 ? 'selected' : ''; ?>>Últimos 3 meses</option>
                                <option value="180" <?php echo $dias == 180 ? 'selected' : ''; ?>>Últimos 6 meses</option>
                                <option value="365" <?php echo $dias == 365 ? 'selected' : ''; ?>>Último ano</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Lixo</label>
                            <select name="tipo_lixo" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                <option value="">Todos os tipos</option>
                                <?php foreach ($tipos_lixo as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo $tipo_lixo == $tipo ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="self-end">
                            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-all">
                                <i class="fas fa-filter mr-2"></i>Filtrar
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Estatísticas -->
                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                        <div class="flex items-center">
                            <div class="bg-indigo-100 p-3 rounded-full mr-4">
                                <i class="fas fa-truck text-primary text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800"><?php echo $total_pesagens; ?></h3>
                                <p class="text-sm text-gray-600">Total de pesagens</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 rounded-xl p-4 border border-green-100">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-full mr-4">
                                <i class="fas fa-weight-scale text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800"><?php echo number_format($total_peso_liquido, 2, ',', '.'); ?> kg</h3>
                                <p class="text-sm text-gray-600">Peso líquido total</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Pesagens -->
                <h2 class="text-lg font-bold text-gray-800 mb-3">Histórico de Pesagens</h2>
                
                <?php if (empty($pesagens)): ?>
                    <div class="bg-gray-50 rounded-xl p-8 text-center">
                        <div class="text-gray-400 text-5xl mb-3">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="text-gray-700 font-medium text-lg mb-2">Nenhum resultado encontrado</h3>
                        <p class="text-gray-500">Não foram encontradas pesagens no período selecionado.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Veículo</th>
                                    <th>Data</th>
                                    <th>Motorista</th>
                                    <th>Tipo de Lixo</th>
                                    <th>Peso (kg)</th>
                                    <th>Operador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pesagens as $pesagem): ?>
                                    <tr>
                                        <td>
                                            <div class="font-medium"><?php echo htmlspecialchars($pesagem['veiculo_id']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($pesagem['nome_veiculo']); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                                $data = new DateTime($pesagem['data']);
                                                echo $data->format('d/m/Y'); 
                                            ?>
                                            <div class="text-xs text-gray-500"><?php echo $pesagem['hora']; ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($pesagem['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($pesagem['tipo_lixo']); ?></td>
                                        <td class="font-medium">
                                            <?php echo number_format($pesagem['peso_liquido'], 2, ',', '.'); ?>
                                            <div class="text-xs text-gray-500">
                                                Total: <?php echo number_format($pesagem['peso_total'], 2, ',', '.'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($pesagem['pesado_por'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Esconder mensagens após alguns segundos
            const messages = document.querySelectorAll('.message-container');
            if (messages.length > 0) {
                setTimeout(function() {
                    messages.forEach(msg => {
                        msg.style.opacity = '0';
                        setTimeout(() => msg.remove(), 500);
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>