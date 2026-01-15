<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['row'])) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"msg"=>"Payload inválido."]);
  exit;
}

$row = $data['row'];

// Ajuste os nomes das colunas conforme sua tabela eventos_recibos:
$tipo      = $row['tipo'] ?? null;
$valor     = $row['valor'] ?? null;
$cliente   = $row['cliente'] ?? null;
$endereco  = $row['endereco'] ?? null;
$venc      = $row['vencimento'] ?? ($row['venc'] ?? null);
$ts        = $row['ts'] ?? ($row['criado_em'] ?? null);
$recibo_id = $row['recibo_id'] ?? ($row['rid'] ?? null);

if (!$tipo) { http_response_code(400); echo json_encode(["ok"=>false,"msg"=>"tipo ausente"]); exit; }

try {
  // IMPORTANTE: não reinserir o ID antigo (evita conflito). Deixe o auto_increment criar outro.
  $stmt = $conn->prepare("INSERT INTO eventos_recibos (recibo_id, tipo, valor, cliente, endereco, vencimento, ts) VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("isdssss", $recibo_id, $tipo, $valor, $cliente, $endereco, $venc, $ts);
  $stmt->execute();

  echo json_encode(["ok"=>true, "msg"=>"Desfeito!", "new_id"=>$conn->insert_id]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"msg"=>"Erro ao desfazer.","detail"=>$e->getMessage()]);
}
