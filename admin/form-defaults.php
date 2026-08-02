<?php
/**
 * Campos predefinidos por tipo de póliza
 * Se usan como punto de partida si no hay template guardado
 */
function default_fields(string $tipo): array {
    $common = [
        ['id'=>'cedula',          'label'=>'Cédula de Identidad',   'name'=>'cedula',          'type'=>'text',   'required'=>true,  'placeholder'=>'000-0000000-0'],
        ['id'=>'fecha_nacimiento','label'=>'Fecha de Nacimiento',   'name'=>'fecha_nacimiento', 'type'=>'date',   'required'=>true,  'placeholder'=>''],
        ['id'=>'estado_civil',    'label'=>'Estado Civil',          'name'=>'estado_civil',     'type'=>'select', 'required'=>false, 'options'=>['Soltero/a','Casado/a','Divorciado/a','Viudo/a','Unión libre']],
        ['id'=>'ocupacion',       'label'=>'Ocupación / Profesión', 'name'=>'ocupacion',        'type'=>'text',   'required'=>false, 'placeholder'=>''],
        ['id'=>'direccion',       'label'=>'Dirección',             'name'=>'direccion',        'type'=>'textarea','required'=>false,'placeholder'=>''],
    ];

    $extra = match($tipo) {
        'vida' => [
            ['id'=>'fumador',               'label'=>'Fumador',                     'name'=>'fumador',               'type'=>'select',  'required'=>true,  'options'=>['No','Sí']],
            ['id'=>'suma_asegurada',        'label'=>'Suma Asegurada (RD$)',        'name'=>'suma_asegurada',        'type'=>'number',  'required'=>true,  'placeholder'=>''],
            ['id'=>'beneficiario_nombre',   'label'=>'Nombre del Beneficiario',     'name'=>'beneficiario_nombre',   'type'=>'text',    'required'=>false, 'placeholder'=>''],
            ['id'=>'beneficiario_cedula',   'label'=>'Cédula del Beneficiario',     'name'=>'beneficiario_cedula',   'type'=>'text',    'required'=>false, 'placeholder'=>''],
            ['id'=>'beneficiario_parentesco','label'=>'Parentesco del Beneficiario','name'=>'beneficiario_parentesco','type'=>'select', 'required'=>false, 'options'=>['Cónyuge','Hijo/a','Padre','Madre','Hermano/a','Otro']],
            ['id'=>'beneficiario_pct',      'label'=>'% al Beneficiario',           'name'=>'beneficiario_pct',      'type'=>'number',  'required'=>false, 'placeholder'=>'100'],
            ['id'=>'enfermedades',          'label'=>'Enfermedades Preexistentes',  'name'=>'enfermedades',          'type'=>'textarea','required'=>false, 'placeholder'=>''],
        ],
        'salud','salud-persona' => [
            ['id'=>'peso',                    'label'=>'Peso (kg)',                    'name'=>'peso',                    'type'=>'number',  'required'=>false, 'placeholder'=>''],
            ['id'=>'altura',                  'label'=>'Altura (cm)',                  'name'=>'altura',                  'type'=>'number',  'required'=>false, 'placeholder'=>''],
            ['id'=>'num_dependientes',        'label'=>'Número de Dependientes',       'name'=>'num_dependientes',        'type'=>'number',  'required'=>false, 'placeholder'=>'0'],
            ['id'=>'enfermedades_preexistentes','label'=>'Enfermedades Preexistentes','name'=>'enfermedades_preexistentes','type'=>'textarea','required'=>false,'placeholder'=>''],
            ['id'=>'medicamentos',            'label'=>'Medicamentos Actuales',        'name'=>'medicamentos',            'type'=>'textarea','required'=>false, 'placeholder'=>''],
            ['id'=>'medico_tratante',         'label'=>'Médico Tratante',              'name'=>'medico_tratante',         'type'=>'text',    'required'=>false, 'placeholder'=>''],
        ],
        'vehiculos' => [
            ['id'=>'marca',         'label'=>'Marca',              'name'=>'marca',         'type'=>'text',   'required'=>true,  'placeholder'=>'Toyota, Honda…'],
            ['id'=>'modelo',        'label'=>'Modelo',             'name'=>'modelo',        'type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'anio',          'label'=>'Año',                'name'=>'anio',          'type'=>'number', 'required'=>true,  'placeholder'=>'2024'],
            ['id'=>'placa',         'label'=>'Placa',              'name'=>'placa',         'type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'chasis',        'label'=>'No. de Chasis (VIN)','name'=>'chasis',        'type'=>'text',   'required'=>false, 'placeholder'=>''],
            ['id'=>'color',         'label'=>'Color',              'name'=>'color',         'type'=>'text',   'required'=>false, 'placeholder'=>''],
            ['id'=>'uso_vehiculo',  'label'=>'Uso del Vehículo',   'name'=>'uso_vehiculo',  'type'=>'select', 'required'=>true,  'options'=>['Personal','Comercial','Transporte público']],
            ['id'=>'valor_vehiculo','label'=>'Valor del Vehículo (RD$)','name'=>'valor_vehiculo','type'=>'number','required'=>false,'placeholder'=>''],
        ],
        'viajes' => [
            ['id'=>'destino',       'label'=>'Destino(s)',          'name'=>'destino',       'type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'fecha_salida',  'label'=>'Fecha de Salida',     'name'=>'fecha_salida',  'type'=>'date',   'required'=>true,  'placeholder'=>''],
            ['id'=>'fecha_regreso', 'label'=>'Fecha de Regreso',    'name'=>'fecha_regreso', 'type'=>'date',   'required'=>true,  'placeholder'=>''],
            ['id'=>'num_viajeros',  'label'=>'Número de Viajeros',  'name'=>'num_viajeros',  'type'=>'number', 'required'=>true,  'placeholder'=>'1'],
            ['id'=>'motivo_viaje',  'label'=>'Motivo del Viaje',    'name'=>'motivo_viaje',  'type'=>'select', 'required'=>false, 'options'=>['Turismo','Negocios','Estudios','Médico','Otro']],
        ],
        'accidentes-personales' => [
            ['id'=>'actividad_laboral','label'=>'Actividad Laboral',   'name'=>'actividad_laboral','type'=>'text',  'required'=>true,  'placeholder'=>''],
            ['id'=>'nivel_riesgo',     'label'=>'Nivel de Riesgo',     'name'=>'nivel_riesgo',     'type'=>'select','required'=>true,  'options'=>['Bajo','Medio','Alto']],
            ['id'=>'capital_muerte',   'label'=>'Capital por Muerte (RD$)','name'=>'capital_muerte','type'=>'number','required'=>false,'placeholder'=>''],
            ['id'=>'capital_invalidez','label'=>'Capital por Invalidez (RD$)','name'=>'capital_invalidez','type'=>'number','required'=>false,'placeholder'=>''],
        ],
        'internacionales' => [
            ['id'=>'pais_destino',          'label'=>'País de Residencia/Destino', 'name'=>'pais_destino',          'type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'fecha_inicio_cobertura','label'=>'Inicio de Cobertura',        'name'=>'fecha_inicio_cobertura','type'=>'date',   'required'=>true,  'placeholder'=>''],
            ['id'=>'duracion_meses',        'label'=>'Duración (meses)',            'name'=>'duracion_meses',        'type'=>'number', 'required'=>true,  'placeholder'=>'12'],
            ['id'=>'cobertura_oncologica',  'label'=>'Cobertura Oncológica',        'name'=>'cobertura_oncologica',  'type'=>'select', 'required'=>false, 'options'=>['Sí','No']],
            ['id'=>'enfermedades_inter',    'label'=>'Enfermedades Preexistentes',  'name'=>'enfermedades_inter',    'type'=>'textarea','required'=>false,'placeholder'=>''],
        ],
        'mascotas' => [
            ['id'=>'nombre_mascota','label'=>'Nombre de la Mascota','name'=>'nombre_mascota','type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'especie',       'label'=>'Especie',             'name'=>'especie',       'type'=>'select', 'required'=>true,  'options'=>['Perro','Gato','Otro']],
            ['id'=>'raza',         'label'=>'Raza',                'name'=>'raza',          'type'=>'text',   'required'=>false, 'placeholder'=>''],
            ['id'=>'edad_mascota', 'label'=>'Edad (años)',          'name'=>'edad_mascota',  'type'=>'number', 'required'=>true,  'placeholder'=>''],
            ['id'=>'peso_mascota', 'label'=>'Peso (kg)',            'name'=>'peso_mascota',  'type'=>'number', 'required'=>false, 'placeholder'=>''],
            ['id'=>'esterilizado', 'label'=>'Esterilizado/a',       'name'=>'esterilizado',  'type'=>'select', 'required'=>false, 'options'=>['Sí','No']],
            ['id'=>'chip',         'label'=>'Tiene chip de identificación','name'=>'chip',   'type'=>'select', 'required'=>false, 'options'=>['Sí','No']],
        ],
        'riesgos-generales' => [
            ['id'=>'tipo_negocio',       'label'=>'Tipo de Negocio/Actividad', 'name'=>'tipo_negocio',       'type'=>'text',   'required'=>true,  'placeholder'=>''],
            ['id'=>'rnc',               'label'=>'RNC de la Empresa',          'name'=>'rnc',               'type'=>'text',   'required'=>false, 'placeholder'=>''],
            ['id'=>'direccion_riesgo',  'label'=>'Dirección del Riesgo',       'name'=>'direccion_riesgo',  'type'=>'textarea','required'=>true, 'placeholder'=>''],
            ['id'=>'valor_asegurado',   'label'=>'Valor Asegurado (RD$)',      'name'=>'valor_asegurado',   'type'=>'number', 'required'=>false, 'placeholder'=>''],
            ['id'=>'num_empleados',     'label'=>'Número de Empleados',        'name'=>'num_empleados',     'type'=>'number', 'required'=>false, 'placeholder'=>''],
            ['id'=>'siniestros_previos','label'=>'Siniestros en últimos 3 años','name'=>'siniestros_previos','type'=>'select','required'=>false, 'options'=>['Ninguno','1-2','3 o más']],
        ],
        default => [],
    };

    return array_merge($common, $extra);
}

function tipo_nombre(string $tipo): string {
    return match($tipo) {
        'vida'                  => 'Seguro de Vida',
        'salud'                 => 'Seguro de Salud',
        'salud-persona'         => 'Salud Personal',
        'viajes'                => 'Asistencia en Viaje',
        'vehiculos'             => 'Seguro de Vehículos',
        'accidentes-personales' => 'Accidentes Personales',
        'internacionales'       => 'Médico Internacional',
        'riesgos-generales'     => 'Riesgos Generales',
        'mascotas'              => 'Seguro de Mascotas',
        default                 => ucfirst($tipo),
    };
}
