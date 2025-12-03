<?php

namespace App\Events;

use App\Models\Consultation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsultationBooked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $consultation;
    public $patient;
    public $doctor;

    /**
     * Create a new event instance.
     */
    public function __construct(Consultation $consultation, $patient, $doctor)
    {
        $this->consultation = $consultation;
        $this->patient = $patient;
        $this->doctor = $doctor;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('doctor.' . $this->doctor->id),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'consultation.booked';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'consultation_id' => $this->consultation->id,
            'patient_id' => $this->consultation->patient_id,
            'patient_name' => $this->getPatientName(),
            'doctor_id' => $this->consultation->doctor_id,
            'type' => $this->consultation->type,
            'status' => $this->consultation->status,
            'scheduled_at' => $this->consultation->scheduled_at ? $this->consultation->scheduled_at->toIso8601String() : null,
            'message' => 'A new consultation has been booked by ' . $this->getPatientName(),
            'created_at' => $this->consultation->created_at->toIso8601String(),
        ];
    }

    /**
     * Get patient name handling both User and Patient models
     */
    protected function getPatientName(): string
    {
        if ($this->patient instanceof User) {
            return $this->patient->full_name ?? 'Unknown';
        }
        
        if (method_exists($this->patient, 'user') && $this->patient->user) {
            return $this->patient->user->full_name ?? 'Unknown';
        }
        
        return 'Unknown';
    }
}

