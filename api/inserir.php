<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

/**
 * Normaliza data:
 * - aceita YYYY-MM-DD
 * - aceita DD/MM/YYYY
 * - aceita DD-MM-YYYY
 */
function normalize_date($v){
  if ($v === null) return null;
  $s = trim((string)$v);
  if ($s === '') return null;

  // YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

  // DD/MM/YYYY ou DD-MM-YYYY
  if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $s, $m)){
    return $m[3].'-'.$m[2].'-'.$m[1];
  }

  return $s; // deixa passar (vai falhar se inválido)
}

try {
  $pdo->beginTransaction();

  $id = $body['id'] ?? ($body['recibo_id'] ?? '');
  if (!$id) {
    // fallback: gera um id simples (melhor do que vazio)
    $id = bin2hex(random_bytes(16));
  }

  $venc = normalize_date($body['vencimento'] ?? null);

  $sql = "INSERT INTO recibos
    (id, nome, endereco, bairro, setor, telefone, telefonec, vencimento, numero, pago, valor, pagamento)
    VALUES
    (:id, :nome, :endereco, :bairro, :setor, :telefone, :telefonec, :vencimento, :numero, :pago, :valor, :pagamento)";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':id'        => $id,
    ':nome'      => $body['nome'] ?? '',
    ':endereco'  => $body['endereco'] ?? '',
    ':bairro'    => $body['bairro'] ?? '',
    ':setor'     => $body['setor'] ?? '',
    ':telefone'  => $body['telefone'] ?? '',
    ':telefonec' => $body['telefonec'] ?? ($body['telefone_cliente'] ?? ''),
    ':vencimento'=> $venc,
    ':numero'    => $body['numero'] ?? 'S/N',
    ':pago'      => isset($body['pago']) ? (int)!!$body['pago'] : 0,
    ':valor'     => $body['valor'] ?? 0,
    ':pagamento' => $body['pagamento'] ?? 'Dinheiro'
  ]);

  // Confirma que gravou mesmo (se não gravar, joga erro e rollback)
  $check = $pdo->prepare("SELECT id FROM recibos WHERE id = :id LIMIT 1");
  $check->execute([':id' => $id]);
  $exists = $check->fetch();

  if (!$exists) {
    throw new Exception("Inserção não confirmada no banco (id=$id).");
  }

  // Log de entrada completo
  $log = $pdo->prepare("
    INSERT INTO eventos_recibos
      (tipo, recibo_id, valor, vencimento, ts, usuario,
       nome, endereco, bairro, setor, telefone, telefonec, numero, pagamento)
    VALUES
      ('entrada', :recibo_id, :valor, :vencimento, NOW(), 'local',
       :nome, :endereco, :bairro, :setor, :telefone, :telefonec, :numero, :pagamento)
  ");

  $log->execute([
    ':recibo_id'  => $id,
    ':valor'      => $body['valor'] ?? 0,
    ':vencimento' => $venc,
    ':nome'       => $body['nome'] ?? '',
    ':endereco'   => $body['endereco'] ?? '',
    ':bairro'     => $body['bairro'] ?? '',
    ':setor'      => $body['setor'] ?? '',
    ':telefone'   => $body['telefone'] ?? '',
    ':telefonec'  => $body['telefonec'] ?? ($body['telefone_cliente'] ?? ''),
    ':numero'     => $body['numero'] ?? 'S/N',
    ':pagamento'  => $body['pagamento'] ?? 'Dinheiro'
  ]);

  $pdo->commit();
  echo json_encode(['status' => 'ok', 'id' => $id]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['erro' => $e->getMessage()]);
}
