<?php

namespace App\Events;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Consultation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ConsultationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patient;
    public $doctor;
    public $consultation;

    /*
     * Create a new event instance.
     */
    public function __construct( User $patient, User $doctor,Consultation $consultation)
    {
        $this->patient = $patient;
        $this->doctor = $doctor;
        $this->consultation = $consultation;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('doctor.' . $this->doctor->id)];
    }
}
    