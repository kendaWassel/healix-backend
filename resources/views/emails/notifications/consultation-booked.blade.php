@component('mail::message')

{{ __('notification.booked_mail_hello', ['name' => $doctor->name]) }}

{{ __('notification.booked_mail_intro') }}


{{ __('notification.booked_mail_patient', ['name' => $patient->full_name ?? $patient->name]) }}

{{ __('notification.booked_mail_type', ['type' => \App\Support\Locale::label('consultation_type', $consultation->type)]) }}

{{ __('notification.booked_mail_scheduled', ['time' => optional($consultation->scheduled_at)->format('Y-m-d H:i')]) }}

{{ __('notification.booked_mail_outro') }}



{{ __('notification.booked_mail_thanks') }}<br>

@endcomponent
