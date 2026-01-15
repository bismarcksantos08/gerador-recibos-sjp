<?php
require 'conexao.php';
header('Content-Type: application/json; charset=utf-8');
try { $stmt = $pdo->query("SELECT * FROM eventos_recibos WHERE tipo='exclusao' ORDER BY ts DESC, id DESC"); echo json_encode($stmt->fetchAll()); }
catch (Throwable $e) { http_response_code(500); echo json_encode(['erro'=>$e->getMessage()]); }
