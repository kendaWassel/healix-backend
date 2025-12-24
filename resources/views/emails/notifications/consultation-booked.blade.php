@component('mail::message')

Hello {{ $doctor->name }}
You have a new consultation booked.


Patient Name: {{ $patient->full_name ?? $patient->name }} 

Consultation Type: {{ ucfirst($consultation->type) }} 

Scheduled Date & Time: {{ optional($consultation->scheduled_at)->format('Y-m-d H:i') }}

Check our website for more details



Thanks,<br>

@endcomponent
