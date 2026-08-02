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

        default:
            json_response(['ok' => false, 'error' => 'acción desconocida'], 404);
    }

} catch (Exception $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
