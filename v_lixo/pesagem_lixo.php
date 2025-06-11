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

date_default_timezone_set('America/Cuiaba');

// Processar o formulário de pesagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro_id']) && isset($_POST['peso_total'])) {
    $registro_id = $_POST['registro_id'];
    $peso_total = $_POST['peso_total'];

    try {
        // Buscar informações do registro
        $query = "SELECT peso_caminhao_vazio FROM registros_lixo WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
        $stmt->execute();
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($registro) {
            $peso_caminhao_vazio = $registro['peso_caminhao_vazio'];
            $peso_liquido = max(0, $peso_total - $peso_caminhao_vazio);

            // Atualizar o registro com os dados de pesagem
            $update_query = "UPDATE registros_lixo SET
                peso_total = :peso_total,
                peso_liquido = :peso_liquido,
                status_pesagem = 'finalizado',
                pesado_por = :pesado_por
                WHERE id = :id";

            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':peso_total', $peso_total, PDO::PARAM_STR);
            $update_stmt->bindParam(':peso_liquido', $peso_liquido, PDO::PARAM_STR);
            $update_stmt->bindParam(':pesado_por', $user_name, PDO::PARAM_STR);
            $update_stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
            $update_stmt->execute();

            $message = '<div class="message-container success">Pesagem registrada com sucesso!</div>';
        } else {
            $message = '<div class="message-container error">Registro não encontrado.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div class="message-container error">Erro: ' . $e->getMessage() . '</div>';
    }
}

// Buscar veículos aguardando pesagem (será carregado por AJAX também)
try {
    $query = "SELECT r.id, r.nome, r.veiculo_id, r.placa, r.nome_veiculo, r.peso_caminhao_vazio,
              r.km_inicial, r.km_final, r.data, r.hora, r.tipo_lixo, r.rota
              FROM registros_lixo r
              WHERE r.status_pesagem = 'em_pesagem'
              ORDER BY r.data DESC, r.hora DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $veiculos_pesagem = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="message-container error">Erro ao buscar veículos: ' . $e->getMessage() . '</div>';
    $veiculos_pesagem = [];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="png" href="ico_nav/img.claro.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="png" href="ico_nav/img.escuro.png" media="(prefers-color-scheme: dark)">
    <title>Pesagem de Veículos</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
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
                    },
                    animation: {
                        'pulse-fast': 'pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite',
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

        /* Animação para destacar novos veículos */
        @keyframes highlight {
            0% { background-color: rgba(254, 202, 202, 0.4); }
            50% { background-color: rgba(239, 68, 68, 0.2); }
            100% { background-color: rgba(254, 202, 202, 0.4); }
        }

        .highlight-new {
            animation: highlight 1.5s ease-in-out infinite;
        }

        /* Veículo ID destacado */
        .vehicle-id {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4F46E5;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.1) 0%, rgba(79, 70, 229, 0.05) 100%);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border-left: 4px solid #4F46E5;
            letter-spacing: 0.05em;
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

        /* Animação para o spinner de carregamento */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-spinner {
            width: 2rem;
            height: 2rem;
            border: 3px solid rgba(79, 70, 229, 0.3);
            border-radius: 50%;
            border-top-color: #4F46E5;
            animation: spin 1s linear infinite;
        }

        /* Transições suaves */
        .fade-transition {
            transition: opacity 0.4s ease, transform 0.4s ease;
        }

        .fade-enter {
            opacity: 0;
            transform: translateY(10px);
        }

        .fade-enter-active {
            opacity: 1;
            transform: translateY(0);
        }

        .fade-exit {
            opacity: 1;
            transform: translateY(0);
        }

        .fade-exit-active {
            opacity: 0;
            transform: translateY(-10px);
        }

        /* Animação mais suave para o ícone de alerta */
        @keyframes smooth-pulse {
            0% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 0.7; }
        }

        .smooth-pulse {
            animation: smooth-pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Logo Header -->
        <div class="logo-container h-48 rounded-b-3xl shadow-hard">
            <div class="content-wrapper flex flex-col items-center justify-center h-full relative">
                <div class="bg-white/20 p-4 rounded-full mb-4">
                    <i class="fas fa-weight-scale text-white text-4xl"></i>
                </div>
                <h1 class="text-white text-2xl font-bold mb-1">Pesagem de Veículos</h1>
                <p class="text-white/80 text-sm mb-6">Registre o peso dos veículos de coleta</p>

                <!-- Ver Histórico -->
                <div class="absolute top-6 right-6">
                    <a href="historico_pesagem.php" class="bg-white/20 hover:bg-white/30 w-10 h-10 rounded-full flex items-center justify-center transition-all">
                        <i class="fas fa-history text-white"></i>
                    </a>
                </div>

                <!-- Ícone de Logout -->
                <div class="absolute top-6 left-6">
                    <a href="../logout.php" class="bg-white/20 hover:bg-white/30 w-10 h-10 rounded-full flex items-center justify-center transition-all">
                        <i class="fas fa-sign-out-alt text-white"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (!empty($message)) echo $message; ?>

                <!-- Título com contador e atualização automática -->
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Veículos Aguardando Pesagem</h2>
                    <div class="flex items-center">
                        <div id="alert-icon" class="text-red-500 animate-pulse mr-2" style="display: none;">
                            <i class="fas fa-bell text-xl"></i>
                        </div>
                        <div id="loading-indicator" class="loading-spinner"></div>
                    </div>
                </div>

                <!-- Alerta destacado para novos veículos -->
                <div id="new-vehicle-alert" class="hidden mb-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-bell mr-3 text-xl text-red-500"></i>
                        <div>
                            <p class="font-bold text-red-700">Novo veículo para pesagem!</p>
                            <p class="text-sm text-gray-700">Um novo veículo chegou para ser pesado.</p>
                        </div>
                    </div>
                </div>

                <!-- Lista de Veículos -->
                <div id="veiculos-container" class="space-y-4">
                    <!-- Será preenchido via AJAX -->
                    <div class="flex justify-center items-center py-8">
                        <p class="text-gray-500">Carregando veículos...</p>
                    </div>
                </div>

                <!-- Status de Atualização -->
                <div class="mt-4 text-center text-xs text-gray-500">
                    Última atualização: <span id="ultima-atualizacao">Carregando...</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Pesagem -->
    <div id="pesagemModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 relative">
            <button type="button" class="absolute right-4 top-4 text-gray-500 hover:text-gray-700" onclick="closePesagemModal()">
                <i class="fas fa-times"></i>
            </button>

            <h3 class="text-xl font-bold text-gray-800 mb-4">Registrar Pesagem</h3>
            <p class="text-sm text-gray-600 mb-4" id="modalVeiculoInfo"></p>

            <!-- Destacar Veículo ID -->
            <div class="mb-4 text-center">
                <div class="vehicle-id" id="modalVeiculoID"></div>
                <p class="text-xs text-gray-500 mt-1">Número de identificação do veículo</p>
            </div>

            <form id="pesagemForm" method="POST" action="">
                <input type="hidden" id="registro_id" name="registro_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Peso do Caminhão Vazio</label>
                    <div class="bg-gray-100 rounded-lg p-3 text-gray-800 font-medium" id="pesoCaminhaoVazio"></div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Peso Total (Cheio)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-weight-hanging text-gray-400"></i>
                        </div>
                        <input
                            type="number"
                            name="peso_total"
                            id="peso_total"
                            class="block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="0.00"
                            step="0.01"
                            required
                            min="0">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500">kg</span>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Peso Líquido (calculado)</label>
                    <div class="bg-gray-100 rounded-lg p-3 text-center font-bold text-lg text-primary" id="pesoLiquidoDisplay">
                        0,00 kg
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button type="button" class="flex-1 py-3 bg-gray-200 text-gray-800 rounded-lg font-medium" onclick="closePesagemModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary-dark">
                        Confirmar Pesagem
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variável para controlar primeira carga
        let primeiraCarregamento = true;

        // Variáveis para controle de veículos
        let veiculosAtuais = [];
        let ultimosVeiculosIds = [];
        let tempoUltimoAlerta = 0;
        let intervaloAlerta = 10000; // 10 segundos
        let atualizacaoInterval = 5000; // Aumentar o intervalo para 5 segundos para evitar atualizações muito frequentes

        // Função para carregar veículos
        function carregarVeiculos() {
            document.getElementById('loading-indicator').style.display = 'block';

            fetch('get_veiculos_pesagem.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading-indicator').style.display = 'none';
                document.getElementById('ultima-atualizacao').textContent = data.timestamp;

                // Mostrar ou esconder o ícone de alerta baseado no número de veículos
                const alertIcon = document.getElementById('alert-icon');
                if (data.veiculos.length > 0) {
                    if (alertIcon.style.display === 'none') {
                        // Aplicar animação suave ao mostrar o ícone
                        alertIcon.classList.add('fade-enter');
                        alertIcon.style.display = 'block';
                        setTimeout(() => {
                            alertIcon.classList.remove('fade-enter');
                            alertIcon.classList.add('fade-enter-active');
                        }, 10);
                        setTimeout(() => {
                            alertIcon.classList.remove('fade-enter-active');
                        }, 400);
                    }

                    // Adicionar classe de pulsação suave
                    alertIcon.classList.add('smooth-pulse');
                } else {
                    if (alertIcon.style.display === 'block') {
                        // Aplicar animação suave ao esconder o ícone
                        alertIcon.classList.add('fade-exit');
                        setTimeout(() => {
                            alertIcon.classList.remove('fade-exit');
                            alertIcon.classList.add('fade-exit-active');
                        }, 10);
                        setTimeout(() => {
                            alertIcon.style.display = 'none';
                            alertIcon.classList.remove('fade-exit-active');
                            alertIcon.classList.remove('smooth-pulse');
                        }, 400);
                    }
                }

                // Verificar se há novos veículos comparando com o último carregamento
                const novosVeiculos = data.veiculos.filter(v => !ultimosVeiculosIds.includes(v.id));

                // Verificar se devemos mostrar alerta (se há novos veículos ou se passou o intervalo de tempo)
                const tempoAtual = new Date().getTime();
                const deveAlertarPorTempo = data.veiculos.length > 0 && (tempoAtual - tempoUltimoAlerta >= intervaloAlerta);

                // Se houver novos veículos ou se passou o intervalo e existem veículos pendentes
                if ((novosVeiculos.length > 0 && !primeiraCarregamento) || deveAlertarPorTempo) {
                    // Tocar som de notificação
                    window.playBeep();

                    // Mostrar alerta com animação suave
                    const alertElement = document.getElementById('new-vehicle-alert');
                    alertElement.classList.add('fade-enter');
                    alertElement.classList.remove('hidden');

                    setTimeout(() => {
                        alertElement.classList.remove('fade-enter');
                        alertElement.classList.add('fade-enter-active');
                    }, 10);

                    // Esconder o alerta após 5 segundos com animação
                    setTimeout(() => {
                        alertElement.classList.remove('fade-enter-active');
                        alertElement.classList.add('fade-exit');

                        setTimeout(() => {
                            alertElement.classList.remove('fade-exit');
                            alertElement.classList.add('fade-exit-active');

                            setTimeout(() => {
                                alertElement.classList.add('hidden');
                                alertElement.classList.remove('fade-exit-active');
                            }, 400);
                        }, 10);
                    }, 5000);

                    // Atualizar o tempo do último alerta
                    tempoUltimoAlerta = tempoAtual;
                }

                // Salvar os IDs atuais para a próxima comparação
                ultimosVeiculosIds = data.veiculos.map(v => v.id);

                // Atualizar a lista de veículos com animação suave
                veiculosAtuais = data.veiculos;
                renderizarVeiculos(data.veiculos, novosVeiculos.map(v => v.id));

                // Não é mais o primeiro carregamento
                primeiraCarregamento = false;
            })
            .catch(error => {
                console.error('Erro ao carregar veículos:', error);
                document.getElementById('loading-indicator').style.display = 'none';
                document.getElementById('ultima-atualizacao').textContent = 'Erro ao atualizar';
            });
        }

        // Função para renderizar veículos na tela
        function renderizarVeiculos(veiculos, novosIds = []) {
            const container = document.getElementById('veiculos-container');

            // Guardar o scroll position atual
            const scrollPos = container.scrollTop;

            if (veiculos.length === 0) {
                // Aplicar fade para estado vazio
                container.classList.add('fade-transition');
                container.style.opacity = '0';

                setTimeout(() => {
                    container.innerHTML = `
                        <div class="bg-gray-50 rounded-xl p-8 text-center">
                            <div class="text-gray-400 text-5xl mb-3">
                                <i class="fas fa-truck-loading"></i>
                            </div>
                            <h3 class="text-gray-700 font-medium text-lg mb-2">Nenhum veículo aguardando</h3>
                            <p class="text-gray-500">Não há veículos de coleta aguardando pesagem no momento.</p>
                        </div>
                    `;

                    container.style.opacity = '1';

                    setTimeout(() => {
                        container.classList.remove('fade-transition');
                    }, 400);
                }, 400);

                return;
            }

            // Se o número de veículos não mudou e não há novos veículos, apenas atualize os dados sem reconstruir tudo
            if (container.children.length === veiculos.length && novosIds.length === 0) {
                // Atualização sutil sem reconstrução completa
                veiculos.forEach((veiculo, index) => {
                    const card = container.children[index];
                    if (card) {
                        // Atualizar apenas timestamps ou outros dados dinâmicos
                        // sem reconstruir todo o card
                        const timestamps = card.querySelectorAll('.timestamp');
                        if (timestamps.length > 0) {
                            timestamps.forEach(ts => {
                                // Atualizar apenas dados que mudam com o tempo
                                if (ts.dataset.field === 'data_hora') {
                                    ts.textContent = `${veiculo.data} ${veiculo.hora}`;
                                }
                            });
                        }
                    }
                });
                return;
            }

            // Construir a nova HTML com animações
            let html = '';
            veiculos.forEach(veiculo => {
                const isNew = novosIds.includes(veiculo.id);
                html += `
                    <div class="vehicle-card fade-transition ${isNew ? 'fade-enter' : ''} bg-gray-50 rounded-xl p-4 border border-gray-200"
                         data-vehicle-id="${veiculo.id}">
                        <div class="flex justify-between items-center mb-3">
                            <div class="flex items-center gap-3">
                                <div class="bg-primary/10 p-2 rounded-full">
                                    <i class="fas fa-truck text-primary"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800">${veiculo.nome_veiculo}</h3>
                                    <p class="text-xs text-gray-500">Placa: ${veiculo.placa}</p>
                                </div>
                            </div>
                            <div class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                                Aguardando Pesagem
                            </div>
                        </div>

                        <!-- Veículo ID Destacado -->
                        <div class="mb-3 text-center">
                            <div class="vehicle-id">${veiculo.veiculo_id}</div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mb-3 text-sm">
                            <div>
                                <span class="text-gray-500">Motorista:</span>
                                <span class="font-medium">${veiculo.nome}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Tipo de Lixo:</span>
                                <span class="font-medium">${veiculo.tipo_lixo}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Rota:</span>
                                <span class="font-medium">${veiculo.rota}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Data/Hora:</span>
                                <span class="timestamp font-medium" data-field="data_hora">${veiculo.data} ${veiculo.hora}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Km Inicial:</span>
                                <span class="font-medium">${veiculo.km_inicial}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Km Final:</span>
                                <span class="font-medium">${veiculo.km_final}</span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-gray-500">Peso do Caminhão (vazio):</span>
                                <span class="font-medium">${parseFloat(veiculo.peso_caminhao_vazio).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg</span>
                            </div>
                        </div>

                        <button
                            class="w-full bg-primary text-white py-2 rounded-lg font-medium hover:bg-primary-dark transition-all"
                            onclick="openPesagemModal('${veiculo.id}', '${veiculo.nome_veiculo}', '${veiculo.placa}', ${veiculo.peso_caminhao_vazio}, '${veiculo.veiculo_id}')">
                            <i class="fas fa-weight-scale mr-2"></i> Registrar Pesagem
                        </button>
                    </div>
                `;
            });

            // Aplicar efeito de fade
            container.style.opacity = '0';

            setTimeout(() => {
                container.innerHTML = html;
                container.style.opacity = '1';

                // Restaurar posição de scroll
                container.scrollTop = scrollPos;

                // Animar novos itens
                setTimeout(() => {
                    const newCards = container.querySelectorAll('.fade-enter');
                    newCards.forEach(card => {
                        card.classList.remove('fade-enter');
                        card.classList.add('fade-enter-active');

                        setTimeout(() => {
                            card.classList.remove('fade-enter-active');
                        }, 400);
                    });
                }, 50);
            }, 300);
        }

        // Funções para o modal de pesagem
        function openPesagemModal(id, veiculo, placa, pesoVazio, veiculoId) {
            document.getElementById('registro_id').value = id;
            document.getElementById('modalVeiculoInfo').textContent = `Veículo: ${veiculo} (Placa: ${placa})`;
            document.getElementById('modalVeiculoID').textContent = veiculoId;
            document.getElementById('pesoCaminhaoVazio').textContent = `${pesoVazio.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} kg`;

            // Armazenar o peso vazio para cálculos
            document.getElementById('peso_total').setAttribute('data-peso-vazio', pesoVazio);
            document.getElementById('peso_total').setAttribute('min', pesoVazio);

            // Mostrar o modal
            document.getElementById('pesagemModal').classList.remove('hidden');
            document.getElementById('peso_total').focus();

            // Configurar o cálculo do peso líquido
            setupPesoLiquidoCalculation(pesoVazio);
        }

        function closePesagemModal() {
            document.getElementById('pesagemModal').classList.add('hidden');
            document.getElementById('pesagemForm').reset();
        }

        function setupPesoLiquidoCalculation(pesoVazio) {
            const pesoTotalInput = document.getElementById('peso_total');
            const pesoLiquidoDisplay = document.getElementById('pesoLiquidoDisplay');

            pesoTotalInput.addEventListener('input', function() {
                const pesoTotal = parseFloat(this.value) || 0;
                const pesoLiquido = Math.max(0, pesoTotal - pesoVazio);

                // Formatar com vírgula e pontos para exibição
                const pesoLiquidoFormatado = pesoLiquido.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                pesoLiquidoDisplay.textContent = pesoLiquidoFormatado + ' kg';
            });
        }

        // Iniciar carregamento e atualização automática
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Howler para notificações sonoras
            const notificationSound = new Howl({
                src: ['https://cdn.jsdelivr.net/gh/anars/blank-audio/250-milliseconds-of-silence.mp3'],
                onloaderror: function() {
                    console.log('Erro ao carregar o som, usando fallback');
                }
            });

            // Definir uma função de beep utilizando a API Web Audio
            window.playBeep = function() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    const context = new AudioContext();

                    // Primeiro beep (nota mais baixa)
                    const oscillator1 = context.createOscillator();
                    const gainNode1 = context.createGain();

                    oscillator1.connect(gainNode1);
                    gainNode1.connect(context.destination);

                    oscillator1.type = 'sine';
                    oscillator1.frequency.setValueAtTime(800, context.currentTime);

                    gainNode1.gain.setValueAtTime(0, context.currentTime);
                    gainNode1.gain.linearRampToValueAtTime(0.5, context.currentTime + 0.1);
                    gainNode1.gain.linearRampToValueAtTime(0, context.currentTime + 0.4);

                    oscillator1.start(context.currentTime);
                    oscillator1.stop(context.currentTime + 0.4);

                    // Segundo beep (nota mais alta) após um curto intervalo
                    setTimeout(() => {
                        const oscillator2 = context.createOscillator();
                        const gainNode2 = context.createGain();

                        oscillator2.connect(gainNode2);
                        gainNode2.connect(context.destination);

                        oscillator2.type = 'sine';
                        oscillator2.frequency.setValueAtTime(1200, context.currentTime);

                        gainNode2.gain.setValueAtTime(0, context.currentTime);
                        gainNode2.gain.linearRampToValueAtTime(0.5, context.currentTime + 0.1);
                        gainNode2.gain.linearRampToValueAtTime(0, context.currentTime + 0.4);

                        oscillator2.start(context.currentTime);
                        oscillator2.stop(context.currentTime + 0.4);
                    }, 300);
                } catch (e) {
                    console.error('Web Audio API não suportada:', e);
                }
            };

            // Carregar veículos inicialmente
            carregarVeiculos();

            // Configurar atualização automática com intervalo maior (5 segundos)
            setInterval(function() {
                carregarVeiculos();
            }, atualizacaoInterval);

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
