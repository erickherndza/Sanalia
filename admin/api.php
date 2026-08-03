<?php
/**
 * Sanalia CRM — API AJAX
 * POST /admin/api.php?action=...
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['sanalia_admin'])) {
    json_response(['ok' => false, 'error' => 'no autorizado'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'método no permitido'], 405);
}

$action = $_GET['action'] ?? '';

try {
    $db = get_db();

    switch ($action) {

        /* ── Guardar cliente (crear o actualizar) ── */
        case 'client_save':
            $id     = (int) ($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') json_response(['ok' => false, 'error' => 'Nombre requerido'], 422);

            $data = [
                'nombre'    => $nombre,
                'email'     => trim($_POST['email']    ?? ''),
                'telefono'  => trim($_POST['telefono'] ?? ''),
                'cedula'    => trim($_POST['cedula']   ?? ''),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'notas'     => trim($_POST['notas']    ?? ''),
            ];

            if ($id > 0) {
                $sql = 'UPDATE clients SET nombre=:nombre, email=:email, telefono=:telefono,
                        cedula=:cedula, direccion=:direccion, notas=:notas WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO clients (nombre,email,telefono,cedula,direccion,notas)
                        VALUES (:nombre,:email,:telefono,:cedula,:direccion,:notas)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Eliminar cliente ── */
        case 'client_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);
            $db->prepare('DELETE FROM clients WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        /* ── Guardar póliza (crear o actualizar) ── */
        case 'policy_save':
            $id        = (int) ($_POST['id'] ?? 0);
            $client_id = (int) ($_POST['client_id'] ?? 0);
            if (!$client_id) json_response(['ok' => false, 'error' => 'cliente requerido'], 422);

            $data = [
                'client_id'         => $client_id,
                'tipo'              => trim($_POST['tipo']              ?? ''),
                'numero_poliza'     => trim($_POST['numero_poliza']     ?? ''),
                'aseguradora'       => trim($_POST['aseguradora']       ?? ''),
                'fecha_inicio'      => $_POST['fecha_inicio']      ?: null,
                'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?: null,
                'prima_anual'       => is_numeric($_POST['prima_anual'] ?? '') ? (float) $_POST['prima_anual'] : null,
                'frecuencia_pago'   => $_POST['frecuencia_pago']   ?? 'anual',
                'estado'            => $_POST['estado']            ?? 'activa',
                'notas'             => trim($_POST['notas']             ?? ''),
            ];

            if ($id > 0) {
                $sql = 'UPDATE policies SET client_id=:client_id, tipo=:tipo, numero_poliza=:numero_poliza,
                        aseguradora=:aseguradora, fecha_inicio=:fecha_inicio,
                        fecha_vencimiento=:fecha_vencimiento, prima_anual=:prima_anual,
                        frecuencia_pago=:frecuencia_pago, estado=:estado, notas=:notas
                        WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO policies (client_id,tipo,numero_poliza,aseguradora,fecha_inicio,
                        fecha_vencimiento,prima_anual,frecuencia_pago,estado,notas)
                        VALUES (:client_id,:tipo,:numero_poliza,:aseguradora,:fecha_inicio,
                        :fecha_vencimiento,:prima_anual,:frecuencia_pago,:estado,:notas)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Eliminar póliza ── */
        case 'policy_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);
            $db->prepare('DELETE FROM policies WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        /* ── Guardar plantilla de campos ── */
        case 'template_save':
            $tipo   = trim($_POST['tipo']   ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $fields = trim($_POST['fields'] ?? '');
            if (!$tipo) json_response(['ok' => false, 'error' => 'tipo requerido'], 422);

            // Validar que fields sea JSON válido
            $decoded = json_decode($fields, true);
            if (!is_array($decoded)) json_response(['ok' => false, 'error' => 'campos inválidos'], 422);

            $stmt = $db->prepare(
                'INSERT INTO form_templates (tipo, nombre, fields)
                 VALUES (:tipo, :nombre, :fields)
                 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), fields=VALUES(fields), updated_at=NOW()'
            );
            $stmt->execute(['tipo' => $tipo, 'nombre' => $nombre ?: $tipo, 'fields' => $fields]);
            json_response(['ok' => true]);

        /* ── Guardar solicitud de póliza ── */
        case 'application_save':
            $id        = (int) ($_POST['id']        ?? 0);
            $client_id = (int) ($_POST['client_id'] ?? 0);
            $tipo      = trim($_POST['tipo']        ?? '');
            if (!$client_id) json_response(['ok' => false, 'error' => 'cliente requerido'], 422);
            if (!$tipo)      json_response(['ok' => false, 'error' => 'tipo requerido'],    422);

            $form_data = trim($_POST['form_data'] ?? '{}');
            if (!json_decode($form_data)) $form_data = '{}';

            $data = [
                'client_id'  => $client_id,
                'policy_id'  => ($_POST['policy_id'] ?? '') !== '' ? (int) $_POST['policy_id'] : null,
                'tipo'       => $tipo,
                'form_data'  => $form_data,
                'estado'     => $_POST['estado'] ?? 'borrador',
                'notas'      => trim($_POST['notas'] ?? ''),
            ];

            $valid_estados = ['borrador', 'en-revision', 'aprobada', 'rechazada'];
            if (!in_array($data['estado'], $valid_estados, true)) {
                $data['estado'] = 'borrador';
            }

            if ($id > 0) {
                $sql = 'UPDATE applications SET client_id=:client_id, policy_id=:policy_id, tipo=:tipo,
                        form_data=:form_data, estado=:estado, notas=:notas WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO applications (client_id, policy_id, tipo, form_data, estado, notas)
                        VALUES (:client_id, :policy_id, :tipo, :form_data, :estado, :notas)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Eliminar documento ── */
        case 'doc_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);

            $stmt = $db->prepare('SELECT filename FROM documents WHERE id=:id');
            $stmt->execute(['id' => $id]);
            $doc = $stmt->fetch();
            if (!$doc) json_response(['ok' => false, 'error' => 'Documento no encontrado'], 404);

            // Eliminar archivo físico
            $path = __DIR__ . '/../storage/docs/' . $doc['filename'];
            if (file_exists($path)) @unlink($path);

            $db->prepare('DELETE FROM documents WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        /* ── Importar CSV ── */
        case 'csv_import':
            if (empty($_FILES['csv']['tmp_name'])) {
                json_response(['ok' => false, 'error' => 'Archivo no recibido'], 422);
            }

            $file     = $_FILES['csv']['tmp_name'];
            $handle   = fopen($file, 'r');
            $headers  = array_map('strtolower', array_map('trim', fgetcsv($handle, 0, ';') ?: fgetcsv($handle, 0, ',') ?: []));
            $imported = 0;
            $errors   = 0;

            // Mapa de columnas posibles
            $col = function(array $row, array $keys) use ($headers): string {
                foreach ($keys as $k) {
                    $idx = array_search($k, $headers);
                    if ($idx !== false && isset($row[$idx])) return trim($row[$idx]);
                }
                return '';
            };

            $sep = in_array('nombre', $headers) || count($headers) > 1
                ? (str_contains(implode('', $headers), ';') ? ';' : ',')
                : ',';

            rewind($handle);
            fgetcsv($handle, 0, $sep); // skip header

            $ins_client = $db->prepare(
                'INSERT INTO clients (nombre,email,telefono,cedula,direccion,notas)
                 VALUES (:nombre,:email,:telefono,:cedula,:direccion,:notas)'
            );
            $ins_policy = $db->prepare(
                'INSERT INTO policies (client_id,tipo,numero_poliza,aseguradora,fecha_inicio,fecha_vencimiento,prima_anual,frecuencia_pago,estado)
                 VALUES (:client_id,:tipo,:numero_poliza,:aseguradora,:fecha_inicio,:fecha_vencimiento,:prima_anual,:frecuencia_pago,:estado)'
            );

            while (($row = fgetcsv($handle, 0, $sep)) !== false) {
                $nombre = $col($row, ['nombre','name','cliente']);
                if (!$nombre) { $errors++; continue; }

                try {
                    $ins_client->execute([
                        'nombre'    => $nombre,
                        'email'     => $col($row, ['email','correo']),
                        'telefono'  => $col($row, ['telefono','teléfono','tel','phone']),
                        'cedula'    => $col($row, ['cedula','cédula','id','rnc']),
                        'direccion' => $col($row, ['direccion','dirección','address']),
                        'notas'     => $col($row, ['notas','notes','observaciones']),
                    ]);
                    $client_id = (int) $db->lastInsertId();

                    $tipo  = $col($row, ['tipo','seguro','tipo de seguro','linea','línea']);
                    $vence = $col($row, ['vencimiento','fecha_vencimiento','fecha de vencimiento','expira','expiry']);
                    $inicio = $col($row, ['inicio','fecha_inicio','fecha de inicio','desde']);
                    $poliza = $col($row, ['poliza','póliza','numero_poliza','número de póliza','no. póliza']);
                    $prima  = $col($row, ['prima','prima_anual','monto','amount']);

                    if ($tipo || $vence || $poliza) {
                        $ins_policy->execute([
                            'client_id'         => $client_id,
                            'tipo'              => $tipo,
                            'numero_poliza'     => $poliza,
                            'aseguradora'       => $col($row, ['aseguradora','compañia','compañía','insurer']),
                            'fecha_inicio'      => $inicio ?: null,
                            'fecha_vencimiento' => $vence  ?: null,
                            'prima_anual'       => is_numeric($prima) ? (float) $prima : null,
                            'frecuencia_pago'   => 'anual',
                            'estado'            => 'activa',
                        ]);
                    }

                    $imported++;
                } catch (Exception $e) {
                    $errors++;
                }
            }
            fclose($handle);
            json_response(['ok' => true, 'imported' => $imported, 'errors' => $errors]);

        /* ── Guardar cuenta por cobrar ── */
        case 'receivable_save':
            $id        = (int) ($_POST['id'] ?? 0);
            $client_id = (int) ($_POST['client_id'] ?? 0);
            if (!$client_id) json_response(['ok' => false, 'error' => 'cliente requerido'], 422);

            $data = [
                'client_id'         => $client_id,
                'policy_id'         => ($_POST['policy_id'] ?? '') !== '' ? (int) $_POST['policy_id'] : null,
                'concepto'          => trim($_POST['concepto'] ?? ''),
                'monto'             => (float) ($_POST['monto'] ?? 0),
                'fecha_emision'     => $_POST['fecha_emision']     ?: date('Y-m-d'),
                'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?: date('Y-m-d'),
                'fecha_pago'        => ($_POST['fecha_pago'] ?? '') ?: null,
                'estado'            => $_POST['estado'] ?? 'pendiente',
                'notas'             => trim($_POST['notas'] ?? ''),
            ];

            if (!in_array($data['estado'], ['pendiente','pagado','vencido','anulado'], true)) {
                $data['estado'] = 'pendiente';
            }

            if ($id > 0) {
                $sql = 'UPDATE receivables SET client_id=:client_id, policy_id=:policy_id, concepto=:concepto,
                        monto=:monto, fecha_emision=:fecha_emision, fecha_vencimiento=:fecha_vencimiento,
                        fecha_pago=:fecha_pago, estado=:estado, notas=:notas WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO receivables (client_id,policy_id,concepto,monto,fecha_emision,fecha_vencimiento,fecha_pago,estado,notas)
                        VALUES (:client_id,:policy_id,:concepto,:monto,:fecha_emision,:fecha_vencimiento,:fecha_pago,:estado,:notas)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Eliminar cuenta por cobrar ── */
        case 'receivable_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);
            $db->prepare('DELETE FROM receivables WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        /* ── Guardar cuenta por pagar ── */
        case 'payable_save':
            $id = (int) ($_POST['id'] ?? 0);

            $data = [
                'beneficiario'      => trim($_POST['beneficiario']  ?? ''),
                'concepto'          => trim($_POST['concepto']      ?? ''),
                'monto'             => (float) ($_POST['monto']     ?? 0),
                'fecha_emision'     => $_POST['fecha_emision']     ?: date('Y-m-d'),
                'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?: date('Y-m-d'),
                'fecha_pago'        => ($_POST['fecha_pago'] ?? '') ?: null,
                'estado'            => $_POST['estado']    ?? 'pendiente',
                'categoria'         => $_POST['categoria'] ?? 'otro',
                'notas'             => trim($_POST['notas'] ?? ''),
            ];

            if (!in_array($data['estado'], ['pendiente','pagado','vencido','anulado'], true)) {
                $data['estado'] = 'pendiente';
            }
            if (!in_array($data['categoria'], ['comision','prima','proveedor','otro'], true)) {
                $data['categoria'] = 'otro';
            }

            if ($id > 0) {
                $sql = 'UPDATE payables SET beneficiario=:beneficiario, concepto=:concepto, monto=:monto,
                        fecha_emision=:fecha_emision, fecha_vencimiento=:fecha_vencimiento,
                        fecha_pago=:fecha_pago, estado=:estado, categoria=:categoria, notas=:notas WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO payables (beneficiario,concepto,monto,fecha_emision,fecha_vencimiento,fecha_pago,estado,categoria,notas)
                        VALUES (:beneficiario,:concepto,:monto,:fecha_emision,:fecha_vencimiento,:fecha_pago,:estado,:categoria,:notas)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Eliminar cuenta por pagar ── */
        case 'payable_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);
            $db->prepare('DELETE FROM payables WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        /* ── Importación masiva desde Excel (SheetJS → JSON → PHP) ── */
        case 'bulk_import':
            $rows = json_decode($_POST['rows'] ?? '[]', true);
            if (!is_array($rows) || empty($rows)) {
                json_response(['ok' => true, 'imported' => 0, 'skipped' => 0, 'errors' => []]);
            }

            $type           = $_POST['type']           ?? 'cxc';
            $skip_dupes     = ($_POST['skip_dupes']     ?? '0') === '1';
            $create_clients = ($_POST['create_clients'] ?? '1') === '1';
            $mark_pagados   = ($_POST['mark_pagados']   ?? '0') === '1';
            $cxc_pendiente  = ($_POST['cxc_pendiente']  ?? '0') === '1';

            $imported = 0;
            $skipped  = 0;
            $errors   = [];

            /** Limpia un valor monetario: "RD$ 1,500.00" → 1500.00 */
            $cleanMonto = static function (mixed $v): ?float {
                if ($v === null || $v === '') return null;
                if (is_float($v) || is_int($v)) return (float) $v;
                $s = preg_replace('/[RD$\s,]/u', '', (string) $v) ?? '';
                $n = (float) $s;
                return $n > 0 ? $n : null;
            };

            /** Normaliza fechas: serial Excel, dd/mm/yyyy, yyyy-mm-dd → Y-m-d */
            $cleanDate = static function (mixed $v): ?string {
                if ($v === null || $v === '') return null;
                if (is_numeric($v)) {
                    // Número serial de Excel (días desde 1899-12-30)
                    $ts = (int) round(((float) $v - 25569) * 86400);
                    return date('Y-m-d', $ts);
                }
                $s = trim((string) $v);
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $s, $m)) {
                    $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
                    return sprintf('%04d-%02d-%02d', (int) $y, (int) $m[2], (int) $m[1]);
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
                return null;
            };

            /** Busca cliente por nombre; crea uno nuevo si $create_clients y no existe */
            $findOrCreateClient = function (string $nombre, string $tel, string $correo)
                use ($db, $create_clients): ?int {
                $nombre = trim($nombre);
                if ($nombre === '') return null;

                $stmt = $db->prepare('SELECT id FROM clients WHERE LOWER(nombre) = LOWER(:n) LIMIT 1');
                $stmt->execute(['n' => $nombre]);
                $row = $stmt->fetch();
                if ($row) return (int) $row['id'];

                if (!$create_clients) return null;

                $db->prepare('INSERT INTO clients (nombre, telefono, email) VALUES (:n, :t, :e)')
                   ->execute(['n' => $nombre, 't' => $tel, 'e' => $correo]);
                return (int) $db->lastInsertId();
            };

            /* ── CxC: una fila = una cuenta por cobrar ── */
            if ($type === 'cxc') {
                $insRec = $db->prepare(
                    'INSERT INTO receivables
                        (client_id, concepto, monto, fecha_emision, fecha_vencimiento, estado, notas)
                     VALUES
                        (:client_id,:concepto,:monto,:fecha_emision,:fecha_vencimiento,:estado,:notas)'
                );

                $dupeStmt = $skip_dupes
                    ? $db->prepare(
                        'SELECT id FROM receivables
                         WHERE client_id=:c AND concepto=:cp AND monto=:m AND fecha_emision=:f LIMIT 1')
                    : null;

                foreach ($rows as $i => $row) {
                    $nombre  = trim((string) ($row['nombre']      ?? ''));
                    $poliza  = trim((string) ($row['poliza']      ?? ''));
                    $ars     = trim((string) ($row['aseguradora'] ?? ''));
                    $forma   = trim((string) ($row['forma_pago']  ?? ''));
                    $vendor  = trim((string) ($row['vendedor']    ?? ''));
                    $tel     = trim((string) ($row['telefono']    ?? ''));
                    $correo  = trim((string) ($row['correo']      ?? ''));
                    $monto   = $cleanMonto($row['monto'] ?? null);
                    $fecha   = $cleanDate($row['fecha']  ?? null) ?? date('Y-m-d');

                    if ($nombre === '') { $skipped++; continue; }

                    try {
                        $client_id = $findOrCreateClient($nombre, $tel, $correo);
                        if (!$client_id) { $skipped++; continue; }

                        $parts    = array_filter([$poliza ? "Póliza $poliza" : '', $ars]);
                        $concepto = 'Prima' . ($parts ? ' — ' . implode(' | ', $parts) : '');

                        if ($dupeStmt) {
                            $dupeStmt->execute(['c' => $client_id, 'cp' => $concepto, 'm' => $monto ?? 0, 'f' => $fecha]);
                            if ($dupeStmt->fetch()) { $skipped++; continue; }
                        }

                        $formaLow = mb_strtolower($forma);
                        // mark_pagados: hoja de cobros ya recibidos → todos pagado
                        // cxc_pendiente: hoja CXC sin monto → siempre pendiente
                        if ($mark_pagados) {
                            $estado = 'pagado';
                        } elseif ($cxc_pendiente) {
                            $estado = 'pendiente';
                        } else {
                            $estado = ($formaLow === 'pagado' || $formaLow === 'paid') ? 'pagado' : 'pendiente';
                        }
                        $notas = implode(' | ', array_filter([$forma, $vendor]));

                        $insRec->execute([
                            'client_id'         => $client_id,
                            'concepto'          => $concepto,
                            'monto'             => $monto ?? 0,
                            'fecha_emision'     => $fecha,
                            'fecha_vencimiento' => $fecha,
                            'estado'            => $estado,
                            'notas'             => $notas,
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Fila " . ($i + 1) . " ($nombre): " . $e->getMessage();
                    }
                }
            }

            /* ── Clientes: una fila = un cliente (+póliza opcional) ── */
            elseif ($type === 'clientes') {
                $insClient = $db->prepare(
                    'INSERT INTO clients (nombre, email, telefono, cedula, direccion)
                     VALUES (:nombre,:email,:telefono,:cedula,:direccion)'
                );
                $insPolicy = $db->prepare(
                    'INSERT INTO policies
                        (client_id,tipo,numero_poliza,aseguradora,fecha_inicio,fecha_vencimiento,prima_anual,frecuencia_pago,estado)
                     VALUES
                        (:client_id,:tipo,:numero_poliza,:aseguradora,:fecha_inicio,:fecha_vencimiento,:prima_anual,:frecuencia_pago,:estado)'
                );

                foreach ($rows as $i => $row) {
                    $nombre = trim((string) ($row['nombre'] ?? ''));
                    if ($nombre === '') { $skipped++; continue; }

                    if ($skip_dupes) {
                        $d = $db->prepare('SELECT id FROM clients WHERE LOWER(nombre)=LOWER(:n) LIMIT 1');
                        $d->execute(['n' => $nombre]);
                        if ($d->fetch()) { $skipped++; continue; }
                    }

                    try {
                        $insClient->execute([
                            'nombre'    => $nombre,
                            'email'     => trim((string) ($row['correo']    ?? '')),
                            'telefono'  => trim((string) ($row['telefono']  ?? '')),
                            'cedula'    => trim((string) ($row['cedula']    ?? '')),
                            'direccion' => trim((string) ($row['direccion'] ?? '')),
                        ]);
                        $client_id = (int) $db->lastInsertId();

                        $tipo   = trim((string) ($row['tipo_seguro'] ?? ''));
                        $poliza = trim((string) ($row['poliza']      ?? ''));
                        $ars    = trim((string) ($row['aseguradora'] ?? ''));
                        $prima  = $cleanMonto($row['prima'] ?? null);
                        $fi     = $cleanDate($row['fecha_inicio']      ?? null);
                        $fv     = $cleanDate($row['fecha_vencimiento'] ?? null);

                        if ($tipo !== '' || $poliza !== '' || $ars !== '') {
                            $insPolicy->execute([
                                'client_id'         => $client_id,
                                'tipo'              => $tipo,
                                'numero_poliza'     => $poliza,
                                'aseguradora'       => $ars,
                                'fecha_inicio'      => $fi,
                                'fecha_vencimiento' => $fv,
                                'prima_anual'       => $prima,
                                'frecuencia_pago'   => 'mensual',
                                'estado'            => 'activa',
                            ]);
                        }
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Fila " . ($i + 1) . " ($nombre): " . $e->getMessage();
                    }
                }
            }

            /* ── Renovaciones: una fila = una cuota (filtrar totales) ── */
            elseif ($type === 'renovaciones') {
                $insRec = $db->prepare(
                    'INSERT INTO receivables
                        (client_id, concepto, monto, fecha_emision, fecha_vencimiento, estado, notas)
                     VALUES
                        (:client_id,:concepto,:monto,:fecha_emision,:fecha_vencimiento,:estado,:notas)'
                );

                foreach ($rows as $i => $row) {
                    $nombre = trim((string) ($row['nombre'] ?? ''));
                    if ($nombre === '') { $skipped++; continue; }

                    $label    = trim((string) ($row['cuota_label'] ?? ''));
                    $labelLow = mb_strtolower($label);

                    // Saltar filas de totales/subtotales
                    if ($label === '' || str_contains($labelLow, 'total') || str_contains($labelLow, 'subtotal')) {
                        $skipped++;
                        continue;
                    }

                    $monto  = $cleanMonto($row['monto_cuota'] ?? null);
                    $fecha  = $cleanDate($row['fecha_pago']   ?? null) ?? date('Y-m-d');
                    $poliza = trim((string) ($row['poliza']      ?? ''));
                    $ars    = trim((string) ($row['aseguradora'] ?? ''));

                    try {
                        $client_id = $findOrCreateClient($nombre, '', '');
                        if (!$client_id) { $skipped++; continue; }

                        $parts    = array_filter([$label, $poliza ? "póliza $poliza" : '', $ars]);
                        $concepto = implode(' | ', $parts) ?: 'Cuota de renovación';

                        $insRec->execute([
                            'client_id'         => $client_id,
                            'concepto'          => $concepto,
                            'monto'             => $monto ?? 0,
                            'fecha_emision'     => $fecha,
                            'fecha_vencimiento' => $fecha,
                            'estado'            => 'pendiente',
                            'notas'             => $ars,
                        ]);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Fila " . ($i + 1) . " ($nombre): " . $e->getMessage();
                    }
                }
            } else {
                json_response(['ok' => false, 'error' => "Tipo de importación desconocido: $type"], 422);
            }

            json_response(['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);

        /* ── Importar lote de leads (desde importador Excel) ── */
        case 'leads_import':
            $rows       = json_decode($_POST['rows'] ?? '[]', true);
            $skip_dupes = ($_POST['skip_dupes'] ?? '1') === '1';
            $overwrite  = ($_POST['overwrite']  ?? '0') === '1';

            if (!is_array($rows) || empty($rows)) {
                json_response(['ok' => true, 'imported' => 0, 'skipped' => 0, 'errors' => []]);
            }

            $fuentes_validas = ['web','facebook','instagram','whatsapp','referido','otro'];
            $interes_validos = ['vida','salud','viajes','vehiculos','accidentes-personales',
                                'internacionales','riesgos-generales','mascotas','exequial','otro'];

            $ins = $db->prepare(
                'INSERT INTO leads (nombre,email,telefono,interes,fuente,campana,notas,estado)
                 VALUES (:nombre,:email,:telefono,:interes,:fuente,:campana,:notas,"nuevo")'
            );

            $imported_l = 0; $skipped_l = 0; $errors_l = [];

            foreach ($rows as $i => $row) {
                $nombre   = mb_substr(trim($row['nombre']   ?? ''), 0, 255);
                $email    = mb_substr(trim($row['email']    ?? ''), 0, 255);
                $telefono = mb_substr(trim($row['telefono'] ?? ''), 0, 60);
                $interes  = trim($row['interes']  ?? '');
                $campana  = mb_substr(trim($row['campana']  ?? ''), 0, 150);
                $notas    = trim($row['notas']    ?? '');
                $fuente   = in_array($row['fuente'] ?? '', $fuentes_validas, true) ? $row['fuente'] : 'web';

                if ($nombre === '') { $skipped_l++; continue; }

                // Normalizar interés: buscar coincidencia parcial
                if ($interes !== '' && !in_array($interes, $interes_validos, true)) {
                    $interes_low = mb_strtolower($interes);
                    $interes = 'otro';
                    foreach ($interes_validos as $iv) {
                        if (str_contains($interes_low, $iv) || str_contains($iv, $interes_low)) {
                            $interes = $iv; break;
                        }
                    }
                }

                // Check duplicado por nombre + teléfono
                if ($skip_dupes) {
                    $chk = $db->prepare(
                        'SELECT id FROM leads WHERE LOWER(nombre)=LOWER(:n)'
                        . ($telefono !== '' ? ' AND telefono=:t' : '') . ' LIMIT 1'
                    );
                    $params = ['n' => $nombre];
                    if ($telefono !== '') $params['t'] = $telefono;
                    $chk->execute($params);
                    if ($chk->fetch()) {
                        if ($overwrite) {
                            // Actualizar fuente/campaña
                            $upd_params = ['fuente' => $fuente, 'campana' => $campana, 'n' => $nombre];
                            $upd_sql = 'UPDATE leads SET fuente=:fuente, campana=:campana WHERE LOWER(nombre)=LOWER(:n)';
                            if ($telefono !== '') { $upd_params['t'] = $telefono; $upd_sql .= ' AND telefono=:t'; }
                            $db->prepare($upd_sql)->execute($upd_params);
                        }
                        $skipped_l++; continue;
                    }
                }

                try {
                    $ins->execute([
                        'nombre'   => $nombre,
                        'email'    => $email,
                        'telefono' => $telefono,
                        'interes'  => $interes,
                        'fuente'   => $fuente,
                        'campana'  => $campana,
                        'notas'    => $notas,
                    ]);
                    $imported_l++;
                } catch (Exception $e) {
                    $errors_l[] = "Fila " . ($i + 1) . " ($nombre): " . $e->getMessage();
                }
            }

            json_response(['ok' => true, 'imported' => $imported_l, 'skipped' => $skipped_l, 'errors' => $errors_l]);

        /* ── Guardar lead (crear o actualizar) ── */
        case 'lead_save':
            $id     = (int) ($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') json_response(['ok' => false, 'error' => 'Nombre requerido'], 422);

            $fuentes_validas = ['web','facebook','instagram','whatsapp','referido','otro'];
            $estados_validos = ['nuevo','contactado','seguimiento','ganado','perdido'];
            $fuente = in_array($_POST['fuente'] ?? '', $fuentes_validas, true) ? $_POST['fuente'] : 'web';
            $estado = in_array($_POST['estado'] ?? '', $estados_validos, true) ? $_POST['estado'] : 'nuevo';

            $data = [
                'nombre'                 => $nombre,
                'email'                  => trim($_POST['email']    ?? ''),
                'telefono'               => trim($_POST['telefono'] ?? ''),
                'interes'                => trim($_POST['interes']  ?? ''),
                'mensaje'                => trim($_POST['mensaje']  ?? ''),
                'fuente'                 => $fuente,
                'campana'                => mb_substr(trim($_POST['campana'] ?? ''), 0, 150),
                'estado'                 => $estado,
                'notas'                  => trim($_POST['notas']   ?? ''),
                'fecha_proximo_contacto' => ($_POST['fecha_proximo_contacto'] ?? '') ?: null,
            ];

            if ($id > 0) {
                $sql = 'UPDATE leads SET nombre=:nombre, email=:email, telefono=:telefono,
                        interes=:interes, mensaje=:mensaje, fuente=:fuente, campana=:campana,
                        estado=:estado, notas=:notas, fecha_proximo_contacto=:fecha_proximo_contacto
                        WHERE id=:id';
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => $id]);
            } else {
                $sql = 'INSERT INTO leads (nombre,email,telefono,interes,mensaje,fuente,campana,estado,notas,fecha_proximo_contacto)
                        VALUES (:nombre,:email,:telefono,:interes,:mensaje,:fuente,:campana,:estado,:notas,:fecha_proximo_contacto)';
                $db->prepare($sql)->execute($data);
                json_response(['ok' => true, 'id' => (int) $db->lastInsertId()]);
            }

        /* ── Cambiar estado de lead ── */
        case 'lead_status':
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            $validos = ['nuevo','contactado','seguimiento','ganado','perdido'];
            if (!$id || !in_array($estado, $validos, true)) {
                json_response(['ok' => false, 'error' => 'Datos inválidos'], 422);
            }
            $db->prepare('UPDATE leads SET estado=:estado WHERE id=:id')->execute(['estado' => $estado, 'id' => $id]);
            json_response(['ok' => true]);

        /* ── Eliminar lead ── */
        case 'lead_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if (!$id) json_response(['ok' => false, 'error' => 'ID inválido'], 422);
            $db->prepare('DELETE FROM leads WHERE id=:id')->execute(['id' => $id]);
            json_response(['ok' => true]);

        default:
            json_response(['ok' => false, 'error' => 'acción desconocida'], 404);
    }

} catch (Exception $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
