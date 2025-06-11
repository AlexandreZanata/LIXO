<?php
session_start();
include '../conexao.php';
date_default_timezone_set('America/Cuiaba');

// Verificar se o usuário está logado e tem a role correta
if (!isset($_SESSION['user_name']) || $_SESSION['role'] !== 'balanca') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit();
}

try {
    // Buscar veículos aguardando pesagem
    $query = "SELECT r.id, r.nome, r.veiculo_id, r.placa, r.nome_veiculo, r.peso_caminhao_vazio, 
              r.km_inicial, r.km_final, r.data, r.hora, r.tipo_lixo, r.rota
              FROM registros_lixo r
              WHERE r.status_pesagem = 'em_pesagem'
              ORDER BY r.data DESC, r.hora DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar valores numéricos e data/hora para exibição
    foreach ($veiculos as &$veiculo) {
        // Garantir que km_final seja um valor, mesmo que seja 0
        $veiculo['km_final'] = $veiculo['km_final'] ?: '0';
        
        // Formatar data para padrão brasileiro se necessário
        if (isset($veiculo['data']) && $veiculo['data']) {
            $date = new DateTime($veiculo['data']);
            $veiculo['data'] = $date->format('d/m/Y');
        }
    }
    
    // Resposta com timestamp
    $response = [
        'veiculos' => $veiculos,
        'timestamp' => date('d/m/Y H:i:s')
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao buscar veículos: ' . $e->getMessage()]);
}
?>