{{-- resources/views/emails/leads/estado_cambiado.blade.php --}}
@component('mail::message')
# Hola {{ $lead->nombre ?? '¡Hola!' }}

Tu solicitud ahora está en el estado: **{{ $estadoLabel }}**.

@if ($estadoValue === \App\Enums\LeadEstadoEnum::PROPUESTA_ENVIADA->value)
@component('mail::button', ['url' => config('app.url')])
Ver Propuesta
@endcomponent
@endif

Gracias,<br>
{{ config('app.name') }}
@endcomponent
