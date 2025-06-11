<?php
include '../conexao.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get data from the request
        $rota_nome = $_POST['rota_nome'];
        $bairros = json_decode($_POST['bairros'], true);
        
        // Validate data
        if (empty($rota_nome)) {
            throw new Exception('Nome da rota não pode estar vazio');
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Formato de bairros inválido: ' . json_last_error_msg());
        }
        
        // Encode bairros as JSON
        $bairros_json = json_encode($bairros);
        
        // Check if route already exists
        $check_query = "SELECT id FROM rotas_lixo WHERE rota_nome = :rota_nome LIMIT 1";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':rota_nome', $rota_nome, PDO::PARAM_STR);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing route
            $route_id = $check_stmt->fetchColumn();
            $update_query = "UPDATE rotas_lixo SET bairros_json = :bairros_json WHERE id = :id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':bairros_json', $bairros_json, PDO::PARAM_STR);
            $update_stmt->bindParam(':id', $route_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bairros atualizados com sucesso para a rota: ' . $rota_nome
            ]);
        } else {
            // Insert new route
            $insert_query = "INSERT INTO rotas_lixo (rota_nome, bairros_json) VALUES (:rota_nome, :bairros_json)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':rota_nome', $rota_nome, PDO::PARAM_STR);
            $insert_stmt->bindParam(':bairros_json', $bairros_json, PDO::PARAM_STR);
            $insert_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Nova rota criada com sucesso: ' . $rota_nome
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao salvar bairros: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método de requisição inválido'
    ]);
}
?>