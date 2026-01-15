<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');

$tipo = $_GET['tipo'] ?? ''; // entrada | saida
$data = $_GET['data'] ?? ''; // YYYY-MM-DD
$limit = (int)($_GET['limit'] ?? 2000);

$where = [];
$params = [];

if ($tipo === 'entrada' || $tipo === 'saida' || $tipo === 'exclusao') {
  $where[] = "tipo = :tipo";
  $params[':tipo'] = $tipo;
}

if ($data) {
  $where[] = "DATE(ts) = :data";
  $params[':data'] = $data;
}

$sql = "SELECT id, tipo, recibo_id, valor, vencimento, ts, usuario, nome, endereco, bairro, telefone
        FROM eventos_recibos";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY ts DESC, id DESC LIMIT " . max(1, min($limit, 5000));

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['status'=>'ok','items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
