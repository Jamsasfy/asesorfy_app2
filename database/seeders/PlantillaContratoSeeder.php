<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlantillaContrato;

class PlantillaContratoSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la tabla antes de insertar
        PlantillaContrato::truncate();

        $plantillas = [
            // ---------------------------------------------------------
            // PARTE 1: CABECERA Y OBJETO (CORREGIDO CON TABLA)
            // ---------------------------------------------------------
            [
                'clave' => 'contrato_cabecera',
                'titulo' => 'Parte 1: Encabezado y Objeto',
                'contenido' => '
                    <h1 style="text-align: center; border-bottom: 3px solid #0ea5e9; padding-bottom: 10px;">CONTRATO MARCO DE PRESTACIÓN DE SERVICIOS</h1>
                    <h2 style="text-align: center; margin-top: 0;">[AFY_RAZON]</h2>
                    <br>

                    <h3>1. PARTES CONTRATANTES</h3>
                    <p>
                        De una parte, <strong>[AFY_RAZON]</strong>, con CIF <strong>[AFY_CIF]</strong> y domicilio social en [AFY_DIRECCION], marca profesional dedicada a la prestación de servicios de asesoría fiscal, contable y laboral (en adelante, "ASESORFY").
                    </p>
                    <p>
                        Y de otra parte, <strong>[CLIENTE_NOMBRE]</strong>, con DNI/NIF <strong>[CLIENTE_DNI]</strong>, domicilio en [CLIENTE_DIRECCION] y correo electrónico [CLIENTE_EMAIL] (en adelante, "EL CLIENTE").
                    </p>
                    <p>
                        Ambas partes, en adelante <strong>LAS PARTES</strong>, reconocen su capacidad legal y acuerdan suscribir el presente <strong>Contrato Marco de Prestación de Servicios</strong>.
                    </p>

                    <h3>2. OBJETO Y SERVICIOS CONTRATADOS</h3>
                    <p>El presente contrato regula los términos bajo los cuales ASESORFY prestará los servicios profesionales que se detallan a continuación:</p>
                    
                    [TABLA_SERVICIOS]
                    <br>

                    <p>Dichos servicios se regirán conforme a los siguientes anexos, que forman parte inseparable de este documento:</p>
                    <ul>
                        <li><strong>Anexo I – Servicios Recurrentes</strong> (si procede)</li>
                        <li><strong>Anexo II – Servicios Únicos</strong> (si procede)</li>
                        <li><strong>Anexo III – Condiciones Económicas</strong></li>
                        <li><strong>Anexo IV – Protección de Datos y Tecnología</strong></li>
                    </ul>
                    <p>Los servicios recurrentes constituyen un acompañamiento continuado y periódico. Los servicios únicos son intervenciones puntuales que finalizan al concluir su ejecución.</p>
                '
            ],

            // ---------------------------------------------------------
            // PARTE 2: MARCO LEGAL (NATURALEZA Y OBLIGACIONES)
            // ---------------------------------------------------------
            [
                'clave' => 'contrato_marco_legal',
                'titulo' => 'Parte 2: Naturaleza Jurídica y Obligaciones',
                'contenido' => '
                    <h3>3. NATURALEZA DE LA PRESTACIÓN Y RÉGIMEN JURÍDICO</h3>
                    <p>La relación profesional entre ASESORFY y EL CLIENTE se configura como un <strong>arrendamiento de servicios</strong>, de conformidad con los artículos <strong>1544, 1583, 1588 y concordantes del Código Civil</strong>.</p>
                    <p>En virtud de dicho régimen jurídico:</p>
                    <ul>
                        <li>ASESORFY se compromete a la correcta ejecución de los servicios contratados conforme a la diligencia profesional exigible, pero <strong>sin garantizar un resultado concreto</strong>, ya que se trata de una obligación de medios.</li>
                        <li>No existe relación laboral, societaria, dependencia o subordinación entre EL CLIENTE y ASESORFY.</li>
                        <li>La relación tampoco implica representación legal del cliente, salvo que se otorguen poderes o autorizaciones específicas.</li>
                        <li>La calidad y viabilidad de los servicios prestados dependen directamente de la documentación que facilite EL CLIENTE, de su exactitud y de su entrega en plazo.</li>
                    </ul>

                    <h3>4. OBLIGACIONES DE ASESORFY</h3>
                    <p>ASESORFY se compromete a:</p>
                    <ol>
                        <li>Prestar los servicios profesionales conforme al alcance descrito en los Anexos I y II.</li>
                        <li>Analizar y procesar la documentación entregada por EL CLIENTE.</li>
                        <li>Gestionar y presentar declaraciones fiscales, laborales, contables o administrativas cuando los documentos se entreguen en plazo.</li>
                        <li>Mantener informado al cliente de obligaciones relevantes que afecten al servicio contratado.</li>
                        <li>Proteger los datos personales y documentación del cliente según la normativa RGPD y LOPDGDD.</li>
                        <li>Asignar un asesor responsable o equipo técnico para la prestación del servicio.</li>
                        <li>Atender consultas dentro del ámbito de la modalidad contratada.</li>
                        <li>Mantener los medios técnicos y la plataforma digital en funcionamiento dentro de parámetros razonables.</li>
                    </ol>
                    <p>ASESORFY no estará obligada a realizar servicios no incluidos expresamente en los anexos, salvo contratación adicional.</p>

                    <h3>5. OBLIGACIONES DEL CLIENTE</h3>
                    <p>EL CLIENTE se compromete a:</p>
                    <ol>
                        <li>Facilitar la documentación necesaria en los plazos establecidos por ASESORFY.</li>
                        <li>Entregar información veraz, completa y actualizada.</li>
                        <li>Mantener un medio de contacto activo (email y/o teléfono).</li>
                        <li>Revisar los borradores de declaraciones o documentos antes de su presentación.</li>
                        <li>Mantener activos los métodos de pago y abonar puntualmente todas las cuotas.</li>
                        <li>Poner en conocimiento de ASESORFY cualquier cambio relevante en la actividad o empresa.</li>
                        <li>Cumplir las instrucciones técnicas proporcionadas por ASESORFY para una correcta prestación del servicio.</li>
                        <li>Utilizar la plataforma digital conforme a las normas indicadas y remitir por ella la documentación requerida.</li>
                    </ol>
                    <p>En caso de no cumplir estas obligaciones, EL CLIENTE acepta que ASESORFY queda exonerada de toda responsabilidad derivada.</p>
                '
            ],

            // ---------------------------------------------------------
            // PARTE 3: CONDICIONES GENERALES (RESPONSABILIDAD, IMPAGOS...)
            // ---------------------------------------------------------
            [
                'clave' => 'contrato_condiciones_grales',
                'titulo' => 'Parte 3: Condiciones Generales',
                'contenido' => '
                    <h3>6. RESPONSABILIDAD</h3>
                    <p>ASESORFY actuará siempre con la diligencia profesional exigible en el desempeño de sus funciones. No obstante, dado que la relación es un <strong>arrendamiento de servicios</strong>, la responsabilidad asumida es estrictamente de <strong>medios y no de resultado</strong>.</p>
                    <p>En consecuencia:</p>
                    <ul>
                        <li>ASESORFY no será responsable de sanciones, recargos o perjuicios derivados de la <strong>entrega tardía</strong>, <strong>incompleta</strong> o <strong>errónea</strong> de documentación por parte del cliente.</li>
                        <li>Tampoco será responsable de decisiones empresariales adoptadas por EL CLIENTE sin consulta previa.</li>
                        <li>En caso de impago, suspensión del servicio o falta de documentación, ASESORFY queda plenamente exonerada de cualquier responsabilidad derivada.</li>
                        <li>La responsabilidad máxima de ASESORFY, por cualquier concepto, no superará en ningún caso el equivalente a <strong>seis cuotas mensuales</strong> del servicio recurrente contratado.</li>
                    </ul>

                    <h3>7. DURACIÓN DEL CONTRATO</h3>
                    <p>El presente contrato entrará en vigor en la fecha de aceptación electrónica, firma manuscrita o validación mediante el formulario de alta del cliente.</p>
                    <ul>
                        <li>Su duración será <strong>indefinida</strong> para los servicios recurrentes.</li>
                        <li>Los servicios únicos tendrán duración limitada al tiempo necesario para su ejecución y finalizarán automáticamente una vez completados.</li>
                    </ul>

                    <h3>8. BAJA, SUSPENSIÓN Y RESOLUCIÓN</h3>
                    
                    <h4>8.1. Baja solicitada por el cliente</h4>
                    <p>EL CLIENTE podrá solicitar la baja de los servicios recurrentes mediante notificación con <strong>30 días naturales de antelación</strong>. Durante este periodo, ASESORFY continuará prestando el servicio y el cliente deberá estar al corriente de pago.</p>

                    <h4>8.2. Suspensión del servicio por impago</h4>
                    <p>Cuando se cumplan <strong>30 días naturales</strong> desde la emisión de una factura recurrente sin haber recibido su pago, el cliente entrará automáticamente en <strong>impago</strong>, y:</p>
                    <ul>
                        <li>El servicio quedará suspendido de forma inmediata.</li>
                        <li>No se presentará ninguna declaración ni documento hasta regularización.</li>
                        <li>ASESORFY quedará exenta de responsabilidad por cualquier efecto derivado.</li>
                    </ul>

                    <h4>8.3. Resolución automática por impago prolongado</h4>
                    <p>Si EL CLIENTE acumula <strong>dos meses consecutivos</strong> de deuda, el contrato se resolverá automáticamente. La deuda seguirá siendo exigible y no podrá solicitar nuevos servicios hasta su regularización.</p>

                    <h4>8.4. Resolución por incumplimiento contractual</h4>
                    <p>ASESORFY podrá resolver el contrato sin preaviso en caso de falsedad documental, ocultación de información relevante, actividad ilícita o fraudulenta, o conducta abusiva que dificulte la prestación del servicio.</p>

                    <h3>9. CONDICIONES ECONÓMICAS — COBROS Y PAGOS</h3>
                    <ul>
                        <li><strong>Servicios recurrentes:</strong> Se facturan <strong>a mes por adelantado</strong>, entre los días 1 y 5 del mes en curso. Deben abonarse antes de los 30 días naturales.</li>
                        <li><strong>Servicios únicos:</strong> No pueden domiciliarse. Deben pagarse <strong>siempre por adelantado</strong>. El servicio no comenzará hasta confirmar el pago.</li>
                        <li><strong>Penalizaciones:</strong> Toda devolución de recibo, impago o retraso generará una penalización automática de <strong>10 €</strong> para cubrir costes bancarios y administrativos.</li>
                    </ul>

                    <h3>10. JURISDICCIÓN</h3>
                    <p>LAS PARTES acuerdan que cualquier controversia derivada de este contrato será resuelta por los <strong>Juzgados y Tribunales de Cádiz, sede Chiclana de la Frontera</strong>, renunciando expresamente a cualquier otro fuero que pudiera corresponderles.</p>
                    
                    <h3>11. ACEPTACIÓN</h3>
                    <p>La aceptación electrónica mediante formulario, la firma digital o la firma manuscrita suponen la plena aceptación de todas las cláusulas del contrato y de los anexos incorporados.</p>
                '
            ],

            // ---------------------------------------------------------
            // ANEXO 1: SERVICIOS RECURRENTES
            // ---------------------------------------------------------
            [
                'clave' => 'servicio_recurrentes',
                'titulo' => 'Anexo I: Servicios Recurrentes',
                'contenido' => '
                    <div style="background-color: #f0f9ff; padding: 20px; border-left: 5px solid #0ea5e9; border-radius: 5px; margin-top: 20px;">
                        <h2 style="color: #0369a1; margin-top: 0;">ANEXO I — SERVICIOS RECURRENTES</h2>
                        <p>El presente Anexo regula el alcance de los servicios recurrentes que ASESORFY prestará al cliente de manera periódica y continuada, conforme a la modalidad contratada en el formulario de alta.</p>

                        <h3 style="color: #0284c7;">1. ASESORÍA FISCAL</h3>
                        <p>ASESORFY realizará la supervisión, preparación y presentación de las obligaciones fiscales periódicas:</p>
                        <ul>
                            <li><strong>Servicios incluidos:</strong> Análisis permanente de la situación fiscal. Cálculo, preparación y presentación de modelos trimestrales (IVA, IRPF, retenciones, pagos fraccionados) y anuales (IVA anual, 347, 349). Revisión de libros de IVA. Atención básica a requerimientos simples (uno por trimestre incluido).</li>
                            <li><strong>No incluidos:</strong> Recursos, alegaciones extensas, procedimientos inspectores, regularizaciones complejas o declaraciones fuera de plazo por entrega tardía del cliente.</li>
                        </ul>

                        <h3 style="color: #0284c7;">2. ASESORÍA CONTABLE</h3>
                        <p>ASESORFY llevará la contabilidad del cliente conforme al Plan General Contable y normativa fiscal:</p>
                        <ul>
                            <li><strong>Servicios incluidos:</strong> Registro y clasificación de facturas. Elaboración de Libros Registro obligatorios. Balances, cuentas de pérdidas y ganancias. Control de amortizaciones y cierre contable anual.</li>
                            <li><strong>No incluidos:</strong> Reconstrucción de contabilidades atrasadas, ejercicios anteriores no incluidos en la cuota, o ajustes especiales por operaciones societarias.</li>
                        </ul>

                        <h3 style="color: #0284c7;">3. ASESORÍA LABORAL (Según contratación)</h3>
                        <p>El servicio laboral podrá ser contratado por trabajador o como módulo general:</p>
                        <ul>
                            <li><strong>Servicios incluidos:</strong> Altas, bajas y variaciones en SS. Elaboración mensual de nóminas. Liquidación de Seguros Sociales. Certificados de empresa y trámites rutinarios del Sistema RED.</li>
                            <li><strong>No incluidos:</strong> ERTE, despidos, sanciones, procedimientos disciplinarios, inspecciones complejas o litigios laborales.</li>
                        </ul>

                        <h3 style="color: #0284c7;">4. COMPROMISOS DOCUMENTALES Y LÍMITES</h3>
                        <p>Para la ejecución del servicio, EL CLIENTE deberá entregar facturas y documentación en plazo y mantener actualizados sus datos. La entrega tardía, incompleta o incorrecta podrá provocar la imposibilidad de presentar declaraciones o sanciones de las que ASESORFY no será responsable.</p>
                        <p>Este Anexo no comprende asistencia presencial, servicios jurídicos no asociados, ni urgencias generadas por incumplimiento del cliente.</p>
                    </div>
                '
            ],

            // ---------------------------------------------------------
            // ANEXO 2: SERVICIOS ÚNICOS
            // ---------------------------------------------------------
            [
                'clave' => 'servicio_unicos',
                'titulo' => 'Anexo II: Servicios Únicos',
                'contenido' => '
                    <div style="background-color: #fff7ed; padding: 20px; border-left: 5px solid #f97316; border-radius: 5px; margin-top: 20px;">
                        <h2 style="color: #c2410c; margin-top: 0;">ANEXO II — SERVICIOS ÚNICOS</h2>
                        <p>Los servicios únicos son intervenciones puntuales que requieren contratación y pago independiente. Se ejecutan una sola vez y finalizan al completarse el encargo. <strong>Deben abonarse siempre por adelantado y no pueden domiciliarse.</strong></p>

                        <h3 style="color: #ea580c;">1. CAPITALIZACIÓN DEL PARO</h3>
                        <p>Asesoramiento y tramitación para la obtención del pago único de la prestación contributiva. Incluye: Análisis previo, elaboración de Memoria y Plan de Empresa, revisión de documentación y presentación telemática ante el SEPE.</p>

                        <h3 style="color: #ea580c;">2. CREACIÓN DE SOCIEDAD LIMITADA (SL)</h3>
                        <p>Tramitación integral de constitución. Incluye: Solicitud de denominación social, borrador de estatutos, coordinación con notaría, presentación en Registro Mercantil y alta en AEAT/SS. <strong>No incluye gastos notariales ni registrales.</strong></p>

                        <h3 style="color: #ea580c;">3. ALTA DE AUTÓNOMO</h3>
                        <p>Incluye: Alta en Hacienda (modelo 036/037), alta en Seguridad Social (RETA), y asesoramiento inicial sobre obligaciones y epígrafes.</p>

                        <h3 style="color: #ea580c;">4. OTROS TRÁMITES PUNTUALES</h3>
                        <ul>
                            <li><strong>Baja de Actividad:</strong> Cese en AEAT y Seguridad Social.</li>
                            <li><strong>Trámites AEAT/TGSS:</strong> Certificados digitales, aplazamientos, contestación a requerimientos extraordinarios.</li>
                            <li><strong>Consultoría Jurídica:</strong> Redacción de contratos, pactos de socios o consultas avanzadas (facturación por horas/proyecto).</li>
                            <li><strong>Reconstrucciones Contables:</strong> Recuperación de contabilidades atrasadas (requiere presupuesto previo).</li>
                        </ul>
                        <p>Cualquier servicio no contemplado expresamente podrá ser presupuestado individualmente.</p>
                    </div>
                '
            ],

            // ---------------------------------------------------------
            // ANEXO 3: CONDICIONES ECONÓMICAS
            // ---------------------------------------------------------
            [
                'clave' => 'anexo_economico',
                'titulo' => 'Anexo III: Condiciones Económicas',
                'contenido' => '
                    <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 5px; margin-top: 20px;">
                    <h2 style="margin-top: 0;">ANEXO III — CONDICIONES ECONÓMICAS</h2>

                    <h3>1. ESTRUCTURA DE TARIFAS</h3>
                    <p><strong>Servicios Recurrentes:</strong> Incluyen Asesoría Fiscal, Contable y Laboral según modalidad. Cada modalidad tiene un <strong>límite mensual de facturas</strong> indicado en la propuesta. Si se supera dicho límite, se aplicará automáticamente un coste adicional proporcional en la siguiente cuota, sin necesidad de presupuesto adicional.</p>
                    <p><strong>Servicios Únicos:</strong> Se facturan según precio individual comunicado previamente.</p>

                    <h3>2. FACTURACIÓN Y COBRO</h3>
                    <ul>
                        <li><strong>Recurrentes:</strong> Facturación a mes por adelantado (días 1-5 del mes). El servicio se considera iniciado el día 1.</li>
                        <li><strong>Únicos:</strong> Pago siempre por adelantado. No admiten domiciliación.</li>
                    </ul>

                    <h3>3. IMPAGOS Y PENALIZACIONES</h3>
                    <p>El impago se produce en el momento en que la cuota no se abona correctamente en el primer intento. Efectos inmediatos:</p>
                    <ul>
                        <li>Suspensión del servicio y de la tramitación de gestiones.</li>
                        <li>Prohibición de contratar nuevos servicios.</li>
                        <li>Aplicación automática de una <strong>penalización de 10 €</strong> por gastos de gestión y devolución.</li>
                    </ul>
                    <p>Si tras <strong>30 días naturales</strong> persiste el impago, se produce la rescisión automática del contrato. ASESORFY queda exonerada de responsabilidad y la deuda acumulada seguirá siendo exigible.</p>
                    </div>
                '
            ],

            // ---------------------------------------------------------
            // ANEXO 4: RGPD E INTELIGENCIA ARTIFICIAL
            // ---------------------------------------------------------
            [
                'clave' => 'anexo_rgpd_ia',
                'titulo' => 'Anexo IV: Protección de Datos y Tecnología',
                'contenido' => '
                    <div style="margin-top: 20px;">
                    <h2 style="margin-top: 0;">ANEXO IV — PROTECCIÓN DE DATOS, TECNOLOGÍA E IA</h2>
                    <p>Este Anexo regula el tratamiento de datos personales y el uso de tecnologías digitales conforme al RGPD y LOPDGDD.</p>

                    <h3>1. RESPONSABLE DEL TRATAMIENTO</h3>
                    <p><strong>ASESORFY</strong> es el responsable del tratamiento. Contacto: soporte@asesorfy.net. Finalidades: Gestión contractual, prestación de servicios fiscales/contables/laborales, facturación y seguridad.</p>

                    <h3>2. USO DE SOFTWARES Y TERCEROS</h3>
                    <p>La ejecución del servicio requiere el uso de softwares externos (contables, fiscales, nóminas, OCR) que actúan como <strong>encargados del tratamiento</strong>. El cliente autoriza su uso. ASESORFY puede colaborar con terceros (auditores, juristas) solo si es necesario para el servicio, garantizando la confidencialidad.</p>

                    <h3>3. INTELIGENCIA ARTIFICIAL (IA)</h3>
                    <p>ASESORFY utiliza herramientas avanzadas de IA (ej: OpenAI, Google Gemini, OCR) para lectura de facturas, categorización y apoyo administrativo. <strong>Garantías:</strong></p>
                    <ul>
                        <li>Los datos <strong>NO se utilizan para entrenar modelos públicos</strong>.</li>
                        <li>El uso está limitado a la ejecución del servicio.</li>
                        <li>Se aplican medidas de cifrado y seguridad.</li>
                    </ul>

                    <h3>4. CONTRATACIÓN DIGITAL Y PRUEBA</h3>
                    <p>El contrato se acepta mediante firma digital o formulario. ASESORFY conservará evidencias digitales (IP, timestamp, logs) que constituyen prueba plena de aceptación.</p>

                    <h3>5. TRANSFERENCIAS INTERNACIONALES</h3>
                    <p>El uso de tecnología global puede implicar procesamiento fuera del EEE. Se aplican Cláusulas Contractuales Tipo (SCC) y medidas de seguridad adicionales.</p>

                    <h3>6. DERECHOS Y CONSERVACIÓN</h3>
                    <p>El cliente puede ejercer sus derechos de acceso, rectificación, supresión, etc., en soporte@asesorfy.net. Los datos se conservarán mientras exista relación contractual y posteriormente durante los plazos legales obligatorios.</p>
                    </div>
                '
            ],
        ];

        foreach ($plantillas as $plantilla) {
            PlantillaContrato::create($plantilla);
        }
    }
}