<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [

            // 1) Intento de contacto fallback seguridad
            [
                'nombre'         => 'Intento de contacto fallback seguridad',
                'slug'           => 'intento_contacto',
                'asunto'         => 'Intentamos contactar contigo — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Hemos intentado ponernos en contacto contigo por teléfono, pero no hemos podido localizarte.</p>

<p>
Si lo prefieres, puedes responder directamente a este correo indicándonos:
</p>

<ul>
  <li>El mejor día y franja horaria para llamarte.</li>
  <li>O bien contarnos brevemente tu consulta para que podamos ayudarte mejor.</li>
</ul>

<p>
También puedes escribirnos por WhatsApp o llamarnos al 
<strong>722 873 562</strong> y te atenderemos encantados.
</p>

<p>
<strong>Correo:</strong> info@asesorfy.net
</p>

<p>Quedamos pendientes de tu respuesta.</p>
HTML,
                'activo'         => true,
            ],

            // 2) Análisis de necesidades
            [
                'nombre'         => 'Análisis de necesidades',
                'slug'           => 'analisis_necesidades',
                'asunto'         => 'Estamos analizando tu información — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Hemos recibido la información que nos has enviado y estamos revisando tu caso para entender bien tu situación y lo que necesitas.
</p>

<p>
En cuanto tengamos el análisis preparado, nos pondremos en contacto contigo para comentártelo y presentarte la propuesta que mejor encaje contigo.
</p>

<p>
Si mientras tanto quieres añadir algo más, aclarar algún punto o tienes nuevas dudas, puedes responder directamente a este correo,
o escribirnos por WhatsApp o llamarnos al <strong>722 873 562</strong>.
</p>

<p>Gracias por tu tiempo.</p>
HTML,
                'activo'         => true,
            ],

            // 3) Esperando información fallback seguridad
            [
                'nombre'         => 'Esperando información fallback seguridad',
                'slug'           => 'esperando_informacion',
                'asunto'         => 'Estamos a la espera de información o documentación',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Estamos pendientes de la información que te solicitamos para poder continuar con tu consulta y darte una respuesta exacta. 
Cuando nos envíes lo que necesitamos (ya sean documentos, respuestas o aclaraciones), podremos ayudarte y avanzar con tu caso.
</p>

<p>
Si tienes dudas sobre qué enviarnos, necesitas que te lo expliquemos de nuevo o te resulta más cómodo hacerlo por otro medio, 
puedes respondernos directamente a este correo.
</p>

<p>
También puedes escribirnos por WhatsApp o llamarnos al <strong>722 873 562</strong>, o contactar en <strong>info@asesorfy.net</strong>.
</p>

<p>Quedamos a la espera para poder ayudarte.</p>
HTML,
                'activo'         => true,
            ],

            // 4) Propuesta enviada
            [
                'nombre'         => 'Propuesta enviada',
                'slug'           => 'propuesta_enviada',
                'asunto'         => 'Te hemos enviado la propuesta — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Ya te hemos enviado la propuesta detallada con toda la información que necesitas para tomar la decisión. 
Si quieres revisar algún punto, ajustar algo o tienes cualquier duda, estamos totalmente a tu disposición.
</p>

<p>
Puedes responder directamente a este correo o, si lo prefieres, escribirnos por WhatsApp o llamarnos al 
<strong>722 873 562</strong>. Estaremos encantados de ayudarte.
</p>

<p>
Gracias por tu interés en {{ config('app.name') }}.
</p>
HTML,
                'activo'         => true,
            ],

            // 5) Convertido (bienvenida)
            [
                'nombre'         => 'Convertido (bienvenida)',
                'slug'           => 'convertido',
                'asunto'         => '¡Gracias por confiar en AsesorFy!',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Gracias por confiar en <strong>{{ config('app.name') }}</strong>. A partir de ahora empezamos contigo el proceso de bienvenida como cliente.</p>

<p>
En las próximas 24-48 horas tu asesor/a asignado/a se pondrá en contacto contigo por teléfono para explicarte los siguientes pasos, 
resolver tus dudas y revisar los servicios que has contratado 
(por ejemplo, alta de autónomo, creación de empresa, capitalización del paro u otros trámites puntuales, además de los servicios recurrentes si los hubiera).
</p>

<p>
Durante esa llamada te indicaremos la documentación necesaria y el orden en el que iremos realizando cada gestión para que todo el proceso sea lo más sencillo posible. Si el asesor/a no te puede localizar te mandará email para agendar o que puedas contestar por ese medio.
</p>

<p>
Si necesitas comentarnos algo antes de la llamada, puedes:
</p>

<ul>
  <li>Responder directamente a este correo.</li>
  <li>Escribirnos por WhatsApp o llamarnos al <strong>722 873 562</strong>.</li>
  <li>Contactar por email en <strong>info@asesorfy.net</strong>.</li>
</ul>

<p>
Gracias de nuevo por tu confianza.<br>
El equipo de {{ config('app.name') }}
</p>
HTML,
                'activo'         => true,
            ],

            // 6) Intento de contacto 1
            [
                'nombre'         => 'Intento de contacto 1',
                'slug'           => 'intento_contacto_1',
                'asunto'         => 'Intentamos contactar contigo — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Hemos intentado ponernos en contacto contigo por teléfono, pero no hemos podido localizarte.</p>

<p>
Si lo prefieres, puedes responder directamente a este correo indicándonos:
</p>

<ul>
  <li>El mejor día y franja horaria para llamarte.</li>
  <li>O bien contarnos brevemente tu consulta para que podamos ayudarte mejor.</li>
</ul>

<p>
También puedes escribirnos por WhatsApp o llamarnos al 
<strong>722 873 562</strong> y te atenderemos encantados.
</p>

<p>
<strong>Correo:</strong> info@asesorfy.net
</p>

<p>Quedamos pendientes de tu respuesta.</p>
HTML,
                'activo'         => true,
            ],

            // 7) Intento de contacto 2
            [
                'nombre'         => 'Intento de contacto 2',
                'slug'           => 'intento_contacto_2',
                'asunto'         => 'Seguimos pendientes de tu consulta — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Queríamos recordarte que tenemos tu consulta pendiente y estaremos encantados de ayudarte cuando te venga bien.</p>

<p>
Si te resulta más cómodo, puedes responder directamente a este correo indicándonos:
</p>

<ul>
  <li>La franja horaria en la que prefieres que te contactemos.</li>
  <li>O bien contarnos brevemente tu consulta para orientarte cuanto antes.</li>
</ul>

<p>
También puedes escribirnos por WhatsApp o llamarnos al 
<strong>722 873 562</strong>.
</p>

<p><strong>Correo:</strong> info@asesorfy.net</p>

<p>Quedamos a tu disposición.</p>
HTML,
                'activo'         => true,
            ],

            // 8) Intento de contacto 3
            [
                'nombre'         => 'Intento de contacto 3',
                'slug'           => 'intento_contacto_3',
                'asunto'         => 'Te acompañamos con tu consulta — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Hace unos días nos contactaste y seguimos disponibles para ayudarte en lo que necesites.</p>

<p>
Si quieres avanzar, puedes responder directamente a este correo con:
</p>

<ul>
  <li>El mejor momento para que te contactemos.</li>
  <li>O una breve descripción de tu consulta.</li>
</ul>

<p>
Si lo prefieres, puedes escribirnos por WhatsApp o llamarnos al 
<strong>722 873 562</strong>.
</p>

<p><strong>Correo:</strong> info@asesorfy.net</p>

<p>Estamos aquí para ayudarte siempre que lo necesites.</p>
HTML,
                'activo'         => true,
            ],

            // 9) Intento de contacto 4
            [
                'nombre'         => 'Intento de contacto 4',
                'slug'           => 'intento_contacto_4',
                'asunto'         => 'Seguimos disponibles para ayudarte — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>Solo queríamos recordarte que seguimos aquí para ayudarte con tu consulta cuando lo necesites.</p>

<p>
Puedes responder a este correo indicándonos:
</p>

<ul>
  <li>La hora aproximada que te viene mejor.</li>
  <li>O los detalles de tu consulta para darte respuesta cuanto antes.</li>
</ul>

<p>
También puedes escribirnos o llamarnos al 
<strong>722 873 562</strong>.
</p>

<p><strong>Correo:</strong> info@asesorfy.net</p>

<p>Estaremos encantados de atenderte.</p>
HTML,
                'activo'         => true,
            ],

            // 10) Intento de contacto 5 final
            [
                'nombre'         => 'Intento de contacto 5 final',
                'slug'           => 'intento_contacto_5',
                'asunto'         => 'Cerramos tu solicitud por el momento — AsesorFy',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Queríamos informarte de que, al no haber recibido respuesta, vamos a cerrar tu solicitud por el momento para no enviarte más mensajes.
</p>

<p>
Si en cualquier momento deseas retomar tu consulta, puedes volver a escribirnos desde la web o contactar directamente y te atenderemos encantados.
</p>

<p>
WhatsApp / Teléfono: <strong>722 873 562</strong><br>
<strong>Correo:</strong> info@asesorfy.net
</p>

<p>Gracias por tu interés en AsesorFy.</p>
HTML,
                'activo'         => true,
            ],

            // 11) Esperando información 1
            [
                'nombre'         => 'Esperando información 1',
                'slug'           => 'esperando_informacion_1',
                'asunto'         => 'Estamos a la espera de información o documentación',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Estamos pendientes de la información que te solicitamos para poder continuar con tu consulta y darte una respuesta exacta. 
Cuando nos envíes lo que necesitamos (ya sean documentos, respuestas o aclaraciones), podremos ayudarte y avanzar con tu caso.
</p>

<p>
Si tienes dudas sobre qué enviarnos, necesitas que te lo expliquemos de nuevo o te resulta más cómodo hacerlo por otro medio, 
puedes respondernos directamente a este correo.
</p>

<p>
También puedes escribirnos por WhatsApp o llamarnos al <strong>722 873 562</strong>, o contactar en <strong>info@asesorfy.net</strong>.
</p>

<p>Quedamos a la espera para poder ayudarte.</p>
HTML,
                'activo'         => true,
            ],

            // 12) Esperando información 2
            [
                'nombre'         => 'Esperando información – 2',
                'slug'           => 'esperando_informacion_2',
                'asunto'         => '¿Nos puedes enviar la información pendiente?',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Te escribimos para recordarte que seguimos a la espera de la información o documentación que necesitamos para poder avanzar con tu consulta.
Cuanto antes la recibamos, antes podremos darte una respuesta clara y completa.
</p>

<p>
Si no tienes claro qué debes enviarnos o prefieres que te lo resumamos de nuevo, puedes responder directamente a este correo y te ayudamos paso a paso.
</p>

<p>
También puedes consultarnos por WhatsApp o teléfono en el 
<strong>722 873 562</strong>, o escribirnos a <strong>info@asesorfy.net</strong>.
</p>

<p>Quedamos atentos a lo que nos indiques para poder continuar.</p>
HTML,
                'activo'         => true,
            ],

            // 13) Esperando información 3
            [
                'nombre'         => 'Esperando información – 3',
                'slug'           => 'esperando_informacion_3',
                'asunto'         => 'Recordatorio: información pendiente para completar tu consulta',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Queríamos hacerte un recordatorio amistoso: seguimos pendientes de la información o documentación que te solicitamos para poder completar el estudio de tu caso.
Sin esos datos, no podemos cerrar tu consulta con la precisión que nos gustaría.
</p>

<p>
Si te resulta más sencillo, puedes responder a este correo indicándonos qué tienes ya preparado y qué te falta,
y te diremos exactamente cómo enviarlo o qué es prioritario.
</p>

<p>
Recuerda que también estamos disponibles por WhatsApp o teléfono en el 
<strong>722 873 562</strong>, y en el correo <strong>info@asesorfy.net</strong>.
</p>

<p>Estamos aquí para ayudarte y hacer el proceso lo más sencillo posible.</p>
HTML,
                'activo'         => true,
            ],

            // 14) Esperando información 4
            [
                'nombre'         => 'Esperando información – 4',
                'slug'           => 'esperando_informacion_4',
                'asunto'         => 'Último recordatorio sobre la información pendiente',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Este es un último recordatorio para informarte de que seguimos a la espera de la información o documentación necesaria para poder continuar con tu consulta.
Mientras no recibamos esos datos, tu caso quedará en pausa.
</p>

<p>
Si has tenido cualquier dificultad para recopilar la información o no recuerdas exactamente qué te pedimos, 
puedes responder a este correo y te lo explicamos de nuevo de forma sencilla.
</p>

<p>
También puedes contactarnos por WhatsApp o teléfono en el 
<strong>722 873 562</strong>, o escribirnos a <strong>info@asesorfy.net</strong>.
</p>

<p>Nos gustaría poder ayudarte y cerrar tu consulta correctamente en cuanto nos envíes lo pendiente.</p>
HTML,
                'activo'         => true,
            ],

            // 15) Esperando información 5 final
            [
                'nombre'         => 'Esperando información –5 final',
                'slug'           => 'esperando_informacion_5',
                'asunto'         => 'Cierre temporal de tu solicitud por falta de información',
                'contenido_html' => <<<'HTML'
<p>Hola {{ $lead->nombre ?? '¡Hola!' }},</p>

<p>
Hemos estado a la espera de la información o documentación necesaria para continuar con tu consulta, 
pero al no haber recibido respuesta, vamos a dejar tu solicitud en pausa por el momento.
</p>

<p>
Esto no significa que no podamos ayudarte más adelante: 
cuando tengas la información preparada o quieras retomar el tema, 
puedes volver a contactarnos y continuaremos desde donde lo dejamos.
</p>

<p>
Puedes hacerlo desde la web, por WhatsApp o teléfono en el 
<strong>722 873 562</strong>, o escribiéndonos directamente a <strong>info@asesorfy.net</strong>.
</p>

<p>Muchas gracias por tu interés en AsesorFy. Quedamos a tu disposición cuando quieras retomar tu consulta.</p>
HTML,
                'activo'         => true,
            ],
        ];

        EmailTemplate::upsert(
            $rows,
            ['slug'], // índice único
            ['nombre', 'asunto', 'contenido_html', 'activo', 'updated_at']
        );
    }
}
