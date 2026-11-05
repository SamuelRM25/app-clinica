<?php
/**
 * includes/lab_seed.php
 * Plantillas tomadas de las hojas de "Laboratorio Clínico Morales" y pruebas del
 * LISTADO DE LABORATORIOS.xlsx (precio al público). Se cargan desde
 * Laboratorio > Plantillas > "Cargar plantillas y precios del laboratorio".
 */

if (!function_exists('lab_seed_data')) {

    /** Fila de tabla: [nombre, unidad, referencia, opciones, min, max] */
    function _ls_f(string $nombre, string $unidad = '', string $ref = '', array $opc = [], $min = null, $max = null): array
    {
        return ['nombre' => $nombre, 'unidad' => $unidad, 'ref' => $ref, 'opciones' => $opc, 'min' => $min, 'max' => $max];
    }
    function _ls_tabla(array $filas, string $titulo = ''): array { return ['tipo' => 'tabla', 'titulo' => $titulo, 'filas' => $filas]; }
    function _ls_tpl(string $hoja, array $secciones, int $columnas = 1, bool $ocultar = false): array
    {
        return ['hoja' => $hoja, 'columnas' => $columnas, 'ocultar_vacios' => $ocultar, 'secciones' => $secciones];
    }

    function lab_seed_data(): array
    {
        $PN = ['NEGATIVO', 'POSITIVO'];
        $CRUCES = ['NEGATIVO (-)', 'ESCASO (+)', 'MODERADO (++)', 'ABUNDANTE (+++)'];
        $QUIM = 'BIOQUÍMICA';
        $ESP = 'ESPECIALES';
        $SERO = 'SEROLOGÍA';

        $P = []; // nombre de plantilla => diseño

        // ── Bioquímica (cada analito se cobra aparte; se imprimen juntos) ──
        $P['Glucosa'] = _ls_tpl($QUIM, [_ls_tabla([
            _ls_f('Glucosa Pre-Prandial', 'mg/dl', '65-110 mg/dl', [], 65, 110),
            _ls_f('Glucosa Post-Prandial', 'mg/dl', '65-110 mg/dl', [], 65, 110)])], 1, true);
        $P['Nitrógeno de urea (BUN)'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Nitrógeno de urea', 'mg/dl', '7-18 mg/dl', [], 7, 18)])], 1, true);
        $P['Creatinina'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Creatinina', 'mg/dl', "M 0.6-0.9 mg/dl\nH 0.9-1.3 mg/dl")])], 1, true);
        $P['Bilirrubinas'] = _ls_tpl($QUIM, [_ls_tabla([
            _ls_f('Bilirrubina Total', 'mg/dl', '0.2-1.2 mg/dl', [], 0.2, 1.2),
            _ls_f('Bilirrubina Directa', 'mg/dl', 'Hasta 1.2 mg/dl', [], null, 1.2),
            _ls_f('Bilirrubina Indirecta', 'mg/dl', 'Hasta 0.8 mg/dl', [], null, 0.8)])], 1, true);
        $P['T3'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('T3', 'nmol/L', '1.23 - 3.08 nmol/L', [], 1.23, 3.08)])], 1, true);
        $P['T4 libre'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('T4 Libre', 'ng/dL', '0.8 - 1.8 ng/dL', [], 0.8, 1.8)])], 1, true);
        $P['TSH'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('TSH', 'uIU/mL', '0.4 - 4.2 uIU/mL', [], 0.4, 4.2)])], 1, true);
        $P['Ácido úrico'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Ácido Úrico', 'mg/dl', "M: 2.5-6.8 mg/dl\nH: 3.6-7.7 mg/dl")])], 1, true);
        $P['TGO y TGP'] = _ls_tpl($QUIM, [_ls_tabla([
            _ls_f('TGO (AST)', 'U/L', 'Hasta 40 U/L', [], null, 40),
            _ls_f('TGP (ALT)', 'U/L', 'Hasta 38 U/L', [], null, 38)])], 1, true);
        $P['Colesterol'] = _ls_tpl($QUIM, [_ls_tabla([
            _ls_f('Colesterol Total', 'mg/dl', '120-240 mg/dl', [], 120, 240),
            _ls_f('Colesterol HDL', 'mg/dl', '30-70 mg/dl', [], 30, 70),
            _ls_f('Colesterol LDL', 'mg/dl', '70-170 mg/dl', [], 70, 170)])], 1, true);
        $P['Triglicéridos'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Triglicéridos', 'mg/dl', '36-165 mg/dl', [], 36, 165)])], 1, true);
        $P['Tiempo de protrombina (TP)'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Tiempo de protrombina (TP)', 'seg', '10 a 16 segundos', [], 10, 16)])], 1, true);
        $P['Tiempo parcial de tromboplastina (TPT)'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Tiempo parcial de tromboplastina (TPT)', 'seg', '24 a 36 segundos', [], 24, 36)])], 1, true);
        $P['Amilasa'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Amilasa', 'U/L', 'Menor de 220 U/L', [], null, 220)])], 1, true);
        $P['Lipasa'] = _ls_tpl($QUIM, [_ls_tabla([_ls_f('Lipasa', 'U/L', 'Menor de 50 U/L', [], null, 50)])], 1, true);

        $P['Hemoglobina glicosilada'] = _ls_tpl('HEMOGLOBINA GLICOSILADA', [_ls_tabla([
            _ls_f('Hemoglobina glicosilada (HbA1c)', '%', "Normal: hasta 5.6%\nPrediabetes: 5.7% - 6.4%\nDiabético controlado: 6.5% - 7.4%\nMal controlado: mayor a 7.5%", [], null, 5.6)])]);

        // ── Serología ──
        $P['VIH'] = _ls_tpl($SERO, [_ls_tabla([_ls_f('VIH', '', '', $PN)])], 1, true);
        $P['VDRL'] = _ls_tpl($SERO, [_ls_tabla([_ls_f('VDRL', '', '', ['NO REACTIVO', 'REACTIVO'])])], 1, true);
        $P['Hepatitis A'] = _ls_tpl($SERO, [_ls_tabla([_ls_f('Hepatitis A IgG', '', '', $PN), _ls_f('Hepatitis A IgM', '', '', $PN)])], 1, true);
        $P['Hepatitis B'] = _ls_tpl($SERO, [_ls_tabla([_ls_f('Hepatitis B', '', '', $PN)])], 1, true);
        $P['Hepatitis C'] = _ls_tpl($SERO, [_ls_tabla([_ls_f('Hepatitis C', '', '', $PN)])], 1, true);

        // ── Especiales ──
        $P['Dengue'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Dengue NS1', '', '', $PN), _ls_f('Dengue IgG', '', '', $PN), _ls_f('Dengue IgM', '', '', $PN)])], 1, true);
        $P['Fiebre tifoidea'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Salmonella Typhi IgG', '', '', $PN), _ls_f('Salmonella Typhi IgM', '', '', $PN)])], 1, true);
        $P['Salmonella antígeno'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Salmonella Antígeno', '', '', $PN)])], 1, true);
        $P['H. pylori en suero'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Helicobacter Pylori (suero)', '', '', $PN)])], 1, true);
        $P['H. pylori en heces'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Helicobacter Pylori Ag (heces)', '', '', $PN)])], 1, true);
        $P['HCG sérica'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('HCG Sérico', '', '', $PN)])], 1, true);
        $P['Rotavirus y adenovirus'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Rotavirus', '', '', $PN), _ls_f('Adenovirus', '', '', $PN)])], 1, true);
        $P['PSA'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Antígeno Prostático Específico Total (PSA)', '', '', $PN)])], 1, true);
        $P['Factor reumatoideo (FR)'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Factor Reumatoideo (FR)', '', 'Menor de 8 UI/mL', $PN)])], 1, true);
        $P['Proteína C reactiva (PCR)'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Proteína C Reactiva (PCR)', '', 'Menor de 6.0 mg/L', $PN)])], 1, true);
        $P['Antiestreptolisina O (ASO)'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Antiestreptolisina O (ASO)', '', 'Menor de 200 UI/mL', $PN)])], 1, true);
        $P['Dímero D'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Dímero D', '', '', $PN)])], 1, true);
        $P['Grupo y Rh'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Grupo sanguíneo y Rh', '', '', ['O RH POSITIVO', 'O RH NEGATIVO', 'A RH POSITIVO', 'A RH NEGATIVO', 'B RH POSITIVO', 'B RH NEGATIVO', 'AB RH POSITIVO', 'AB RH NEGATIVO'])])], 1, true);
        $P['Guayacol'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Guayacol (sangre oculta)', '', '', $PN)])], 1, true);
        $P['Clinitest'] = _ls_tpl($ESP, [_ls_tabla([_ls_f('Clinitest', '', '', $PN)])], 1, true);

        // ── Hojas propias ──
        $P['B-HCG cuantitativa'] = _ls_tpl('B-HCG CUANTITATIVA', [
            _ls_tabla([_ls_f('B-HCG Cuantificada', 'mIU/mL')]),
            ['tipo' => 'referencia', 'titulo' => 'Nivel de B-HCG durante el embarazo', 'columnas' => ['Semanas de amenorrea', 'Rango (mIU/mL)'],
             'filas' => [['3', '5 - 50'], ['4', '5 - 426'], ['5', '18 - 7,340'], ['6', '1,080 - 56,500'], ['7 - 8', '7,650 - 229,000'],
                         ['9 - 12', '25,700 - 288,000'], ['13 - 16', '13,300 - 254,000'], ['17 - 24', '4,060 - 165,400'], ['25 - 40', '3,640 - 117,000']]],
        ], 2);
        $P['Widal'] = _ls_tpl('WIDAL', [
            ['tipo' => 'matriz', 'titulo' => 'Valor de referencia (títulos)', 'columnas' => ['1/20', '1/40', '1/80', '1/160', '1/320'],
             'opciones' => $PN, 'filas' => [['nombre' => 'Antígeno O'], ['nombre' => 'Antígeno H']]],
        ]);
        $P['TORCH'] = _ls_tpl('TORCH', [_ls_tabla([
            _ls_f('Toxo IgM', '', '', $PN), _ls_f('Toxo IgG', '', '', $PN),
            _ls_f('Rubéola IgG', '', '', $PN), _ls_f('Rubéola IgM', '', '', $PN),
            _ls_f('CMV IgM', '', '', $PN), _ls_f('CMV IgG', '', '', $PN),
            _ls_f('HSV-1 IgM', '', '', $PN), _ls_f('HSV-1 IgG', '', '', $PN),
            _ls_f('HSV-2 IgM', '', '', $PN), _ls_f('HSV-2 IgG', '', '', $PN)])]);

        $P['Examen de orina'] = _ls_tpl('EXAMEN DE ORINA', [
            _ls_tabla([
                _ls_f('Color', '', '', ['AMARILLO', 'AMARILLO CLARO', 'ÁMBAR', 'ROJIZO']),
                _ls_f('Aspecto', '', '', ['TRANSPARENTE', 'LIG. TURBIA', 'TURBIA'])], 'EXAMEN FÍSICO'),
            _ls_tabla([
                _ls_f('Bilirrubina', '', '', $PN), _ls_f('Urobilinógeno', '', '', $PN), _ls_f('Cetona', '', '', $PN),
                _ls_f('Ácido Ascórbico', '', '', $PN), _ls_f('Glucosa', '', '', $PN), _ls_f('Proteína', '', '', $PN),
                _ls_f('Sangre', '', '', $PN), _ls_f('pH'), _ls_f('Nitrito', '', '', $PN), _ls_f('Leucocitos', '', '', $PN),
                _ls_f('Densidad')], 'EXAMEN QUÍMICO'),
            _ls_tabla([
                _ls_f('Leucocitos', 'p/campo'), _ls_f('Eritrocitos', 'p/campo'),
                _ls_f('Células Epiteliales', '', '', $CRUCES), _ls_f('Moco', '', '', $CRUCES), _ls_f('Bacterias', '', '', $CRUCES),
                _ls_f('Levaduras', '', '', $CRUCES), _ls_f('Cristales', '', '', ['NEGATIVO']), _ls_f('Cilindros', '', '', ['NEGATIVO']),
                _ls_f('Otros')], 'EXAMEN MICROSCÓPICO'),
        ], 3);

        $parasitos = ['Ascaris lumbricoides', 'Uncinaria', 'Enterobius vermicularis', 'Strongyloides stercolaris', 'Taenia sp',
            'Trichuris trichiura', 'Hymenolepis nana', 'Entamoeba histolytica', 'Entamoeba coli', 'Giardia lamblia', 'Endolimax nana',
            'Balantidium coli', 'Chilomastix mesnilii', 'Trichomonas hominis', 'Iodamoeba butschlii', 'Entamoeba hartmani'];
        $P['Examen de heces'] = _ls_tpl('EXAMEN DE HECES', [
            _ls_tabla([_ls_f('Color', '', '', ['CAFÉ', 'AMARILLO', 'VERDE', 'NEGRO']), _ls_f('Consistencia', '', '', ['FORMADA', 'PASTOSA', 'BLANDA', 'DIARREICA']),
                _ls_f('Sangre', '', '', $CRUCES), _ls_f('Moco', '', '', $CRUCES), _ls_f('Restos alimenticios', '', '', $CRUCES)], 'MACROSCÓPICO'),
            _ls_tabla([_ls_f('Leucocitos'), _ls_f('Eritrocitos'), _ls_f('Levaduras', '', '', $CRUCES), _ls_f('Células vegetales', '', '', $CRUCES),
                _ls_f('Fibra muscular', '', '', $CRUCES), _ls_f('Jabones', '', '', $CRUCES), _ls_f('Grasas', '', '', $CRUCES),
                _ls_f('Almidón', '', '', $CRUCES), _ls_f('Bacterias', '', '', $CRUCES)], 'MICROSCÓPICO'),
            _ls_tabla([_ls_f('pH'), _ls_f('Sangre oculta', '', '', $PN)], 'QUÍMICO'),
            ['tipo' => 'matriz', 'titulo' => 'PARÁSITOS', 'columnas' => ['Huevos', 'Larvas', 'Quistes', 'Trofozoítos'],
             'opciones' => ['+', '++', '+++'], 'filas' => array_map(fn($n) => ['nombre' => $n], $parasitos)],
        ], 3);

        $P['Hematología completa'] = _ls_tpl('HEMATOLOGÍA', [
            ['tipo' => 'nota', 'texto' => 'Rangos de referencia para adulto. Verifíquelos con los de su equipo antes de usar la plantilla.'],
            _ls_tabla([
                _ls_f('Glóbulos blancos (WBC)', '10³/µL', '4.0 - 10.0', [], 4.0, 10.0),
                _ls_f('Linfocitos %', '%', '20 - 40', [], 20, 40), _ls_f('Monocitos/MID %', '%', '3 - 15', [], 3, 15),
                _ls_f('Granulocitos %', '%', '50 - 70', [], 50, 70),
                _ls_f('Linfocitos #', '10³/µL', '0.8 - 4.0', [], 0.8, 4.0), _ls_f('Monocitos/MID #', '10³/µL', '0.1 - 1.5', [], 0.1, 1.5),
                _ls_f('Granulocitos #', '10³/µL', '2.0 - 7.0', [], 2.0, 7.0),
                _ls_f('Glóbulos rojos (RBC)', '10⁶/µL', '3.50 - 5.50', [], 3.5, 5.5), _ls_f('Hemoglobina (HGB)', 'g/dL', '11.0 - 16.0', [], 11, 16),
                _ls_f('Hematocrito (HCT)', '%', '37.0 - 54.0', [], 37, 54), _ls_f('VCM (MCV)', 'fL', '80 - 100', [], 80, 100),
                _ls_f('HCM (MCH)', 'pg', '27 - 34', [], 27, 34), _ls_f('CHCM (MCHC)', 'g/dL', '32 - 36', [], 32, 36),
                _ls_f('RDW-CV', '%', '11.0 - 16.0', [], 11, 16), _ls_f('RDW-SD', 'fL', '35 - 56', [], 35, 56),
                _ls_f('Plaquetas (PLT)', '10³/µL', '100 - 300', [], 100, 300), _ls_f('VPM (MPV)', 'fL', '6.5 - 12.0', [], 6.5, 12),
                _ls_f('PDW', '', '9.0 - 17.0', [], 9, 17), _ls_f('PCT', '%', '0.108 - 0.282', [], 0.108, 0.282)])]);

        // ── Pruebas: [código, nombre, categoría, muestra, precio público, plantilla] ──
        $T = [
            ['GLU', 'Glucosa', 'Química', 'Suero', 50, 'Glucosa'],
            ['TRIG', 'Triglicéridos', 'Química', 'Suero', 50, 'Triglicéridos'],
            ['COL', 'Colesterol', 'Química', 'Suero', 50, 'Colesterol'],
            ['CREA', 'Creatinina', 'Química', 'Suero', 50, 'Creatinina'],
            ['AURI', 'Ácido úrico', 'Química', 'Suero', 50, 'Ácido úrico'],
            ['BILI', 'Bilirrubinas', 'Química', 'Suero', 50, 'Bilirrubinas'],
            ['BUN', 'Nitrógeno de urea (BUN)', 'Química', 'Suero', 50, 'Nitrógeno de urea (BUN)'],
            ['TGOTGP', 'TGO y TGP', 'Química', 'Suero', 200, 'TGO y TGP'],
            ['AMIL', 'Amilasa', 'Química', 'Suero', 125, 'Amilasa'],
            ['LIPA', 'Lipasa', 'Química', 'Suero', 125, 'Lipasa'],
            ['HBA1C', 'Hemoglobina glicosilada', 'Química', 'Sangre total (EDTA)', 170, 'Hemoglobina glicosilada'],
            ['T3', 'T3', 'Hormonas', 'Suero', 90, 'T3'],
            ['T4', 'T4 libre', 'Hormonas', 'Suero', 90, 'T4 libre'],
            ['TSH', 'TSH', 'Hormonas', 'Suero', 90, 'TSH'],
            ['BHCG', 'B-HCG cuantitativa', 'Hormonas', 'Suero', 160, 'B-HCG cuantitativa'],
            ['HCGS', 'HCG sérica', 'Hormonas', 'Suero', 50, 'HCG sérica'],
            ['TP', 'Tiempo de protrombina (TP)', 'Coagulación', 'Plasma citrato', 95, 'Tiempo de protrombina (TP)'],
            ['TPT', 'Tiempo parcial de tromboplastina (TPT)', 'Coagulación', 'Plasma citrato', 95, 'Tiempo parcial de tromboplastina (TPT)'],
            ['DIMD', 'Dímero D', 'Coagulación', 'Plasma citrato', 90, 'Dímero D'],
            ['HEMA', 'Hematología completa', 'Hematología', 'Sangre total (EDTA)', 90, 'Hematología completa'],
            ['GRH', 'Grupo y Rh', 'Hematología', 'Sangre total (EDTA)', 50, 'Grupo y Rh'],
            ['VIH', 'VIH', 'Serología', 'Suero', 130, 'VIH'],
            ['VDRL', 'VDRL', 'Serología', 'Suero', 130, 'VDRL'],
            ['HEPA', 'Hepatitis A', 'Serología', 'Suero', 130, 'Hepatitis A'],
            ['HEPB', 'Hepatitis B', 'Serología', 'Suero', 130, 'Hepatitis B'],
            ['HEPC', 'Hepatitis C', 'Serología', 'Suero', 130, 'Hepatitis C'],
            ['TORCH', 'TORCH', 'Serología', 'Suero', 220, 'TORCH'],
            ['DENG', 'Dengue (NS1, IgG, IgM)', 'Especiales', 'Suero', 170, 'Dengue'],
            ['TIFO', 'Fiebre tifoidea (Salmonella IgG/IgM)', 'Especiales', 'Suero', 130, 'Fiebre tifoidea'],
            ['SALAG', 'Salmonella antígeno', 'Especiales', 'Heces', 140, 'Salmonella antígeno'],
            ['WIDAL', 'Widal (antígenos O y H)', 'Especiales', 'Suero', 50, 'Widal'],
            ['HPYS', 'H. pylori en suero', 'Especiales', 'Suero', 130, 'H. pylori en suero'],
            ['HPYH', 'H. pylori en heces', 'Especiales', 'Heces', 130, 'H. pylori en heces'],
            ['ROTA', 'Rotavirus y adenovirus', 'Especiales', 'Heces', 130, 'Rotavirus y adenovirus'],
            ['PSA', 'PSA (antígeno prostático)', 'Especiales', 'Suero', 130, 'PSA'],
            ['FR', 'Factor reumatoideo (FR)', 'Especiales', 'Suero', 50, 'Factor reumatoideo (FR)'],
            ['PCR', 'Proteína C reactiva (PCR)', 'Especiales', 'Suero', 50, 'Proteína C reactiva (PCR)'],
            ['ASO', 'Antiestreptolisina O (ASO)', 'Especiales', 'Suero', 50, 'Antiestreptolisina O (ASO)'],
            ['GUAY', 'Guayacol (sangre oculta)', 'Coprología', 'Heces', 130, 'Guayacol'],
            ['CLINI', 'Clinitest', 'Coprología', 'Heces', 50, 'Clinitest'],
            ['EGO', 'Examen de orina', 'Uroanálisis', 'Orina', 0, 'Examen de orina'],
            ['HECES', 'Examen de heces', 'Coprología', 'Heces', 0, 'Examen de heces'],
        ];

        return ['plantillas' => $P, 'pruebas' => $T];
    }

    function lab_norm_name(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/[^a-z0-9]+/', ' ', $s);
    }

    /**
     * Calcula qué haría la carga inicial (sin cambiar nada) o la aplica.
     * Devuelve ['plantillas' => [[nombre, existe]], 'pruebas' => [[codigo, nombre, accion, precio_actual, precio_nuevo, id_prueba]]]
     */
    function lab_seed_plan($conn, int $id_hospital, bool $aplicar = false, bool $actualizar_precios = true, ?int $user_id = null): array
    {
        $data = lab_seed_data();
        $st = $conn->prepare("SELECT id_plantilla, nombre FROM lab_plantillas WHERE id_hospital = ?");
        $st->execute([$id_hospital]);
        $tpl_ids = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $tpl_ids[lab_norm_name($r['nombre'])] = (int) $r['id_plantilla'];

        $st = $conn->prepare("SELECT id_prueba, codigo_prueba, nombre_prueba, precio, id_plantilla FROM catalogo_pruebas WHERE id_hospital = ?");
        $st->execute([$id_hospital]);
        $existentes = $st->fetchAll(PDO::FETCH_ASSOC);
        $por_codigo = $por_nombre = [];
        foreach ($existentes as $e) {
            if ($e['codigo_prueba'] !== null && $e['codigo_prueba'] !== '') $por_codigo[mb_strtoupper(trim($e['codigo_prueba']))] = $e;
            $por_nombre[lab_norm_name($e['nombre_prueba'])] = $e;
        }

        $plan = ['plantillas' => [], 'pruebas' => []];
        if ($aplicar) $conn->beginTransaction();
        try {
            $insTpl = $conn->prepare("INSERT INTO lab_plantillas (id_hospital, nombre, hoja, diseno, updated_by) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['plantillas'] as $nombre => $diseno) {
                $k = lab_norm_name($nombre);
                $existe = isset($tpl_ids[$k]);
                $plan['plantillas'][] = [$nombre, $existe];
                if ($aplicar && !$existe) {
                    $d = lab_tpl_normalize($diseno);
                    $insTpl->execute([$id_hospital, $nombre, $d['hoja'], json_encode($d, JSON_UNESCAPED_UNICODE), $user_id]);
                    $tpl_ids[$k] = (int) $conn->lastInsertId();
                }
            }
            foreach ($data['pruebas'] as [$codigo, $nombre, $cat, $muestra, $precio, $tpl]) {
                $e = $por_codigo[$codigo] ?? $por_nombre[lab_norm_name($nombre)] ?? null;
                $id_tpl = $tpl_ids[lab_norm_name($tpl)] ?? null;
                if ($e) {
                    $cambia_precio = $actualizar_precios && $precio > 0 && abs((float) $e['precio'] - $precio) > 0.004;
                    $accion = $cambia_precio ? 'precio' : (empty($e['id_plantilla']) ? 'plantilla' : 'igual');
                    $plan['pruebas'][] = [$codigo, $e['nombre_prueba'], $accion, (float) $e['precio'], (float) $precio, (int) $e['id_prueba']];
                    if ($aplicar) {
                        if ($cambia_precio) $conn->prepare("UPDATE catalogo_pruebas SET precio = ? WHERE id_prueba = ? AND id_hospital = ?")->execute([$precio, $e['id_prueba'], $id_hospital]);
                        if (empty($e['id_plantilla']) && $id_tpl) $conn->prepare("UPDATE catalogo_pruebas SET id_plantilla = ? WHERE id_prueba = ? AND id_hospital = ?")->execute([$id_tpl, $e['id_prueba'], $id_hospital]);
                    }
                } else {
                    $plan['pruebas'][] = [$codigo, $nombre, 'nueva', null, (float) $precio, null];
                    if ($aplicar) {
                        $conn->prepare("INSERT INTO catalogo_pruebas (nombre_prueba, codigo_prueba, categoria, precio, muestra_requerida, tiempo_procesamiento_horas, id_plantilla, id_hospital) VALUES (?, ?, ?, ?, ?, 24, ?, ?)")
                            ->execute([$nombre, $codigo, $cat, $precio, $muestra, $id_tpl, $id_hospital]);
                    }
                }
            }
            if ($aplicar) $conn->commit();
        } catch (Throwable $ex) {
            if ($aplicar && $conn->inTransaction()) $conn->rollBack();
            throw $ex;
        }
        return $plan;
    }
}
