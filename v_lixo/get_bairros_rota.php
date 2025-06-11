<?php
include '../conexao.php';

header('Content-Type: application/json');

if (isset($_GET['rota'])) {
    try {
        $rota = $_GET['rota'];
        
        // Query to get the bairros_json for the specified route
        $query = "SELECT bairros_json FROM rotas_lixo WHERE rota_nome = :rota LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':rota', $rota, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['bairros_json'])) {
            // Decode the JSON data
            $bairros = json_decode($result['bairros_json'], true);
            
            // Check if JSON was decoded properly
            if ($bairros === null && json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao decodificar JSON: ' . json_last_error_msg(),
                    'raw_data' => $result['bairros_json']
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'bairros' => $bairros
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum bairro encontrado para esta rota',
                'bairros' => []
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar bairros: ' . $e->getMessage(),
            'bairros' => []
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetro de rota não fornecido',
        'bairros' => []
    ]);
}
?>