<?php

namespace App\Events;

use App\Models\User;
use App\Models\Consultation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ConsultationCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $patient;
    public $doctor;
    public $consultation;

    /**
     * Create a new event instance.
     */
    public function __construct(User $patient,User $doctor,Consultation $consultation)
    {
        $this->patient = $patient;
        $this->doctor = $doctor;
        $this->consultation = $consultation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
