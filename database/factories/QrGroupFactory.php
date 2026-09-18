<?php

namespace Database\Factories;

use App\Models\QrGroup;
use App\Services\QrTool\QrGroupService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QrGroup> */
class QrGroupFactory extends Factory
{
    protected $model = QrGroup::class;

    public function definition(): array
    {
        $eventType = 'Conference';
        $eventName = fake()->unique()->company().' '.fake()->year();
        $role = fake()->randomElement(['Session Chair', 'Invited Speaker', 'Keynote Speaker', 'Volunteer']);

        return [
            'event_type' => $eventType,
            'event_name' => $eventName,
            'role' => $role,
            'group_key' => app(QrGroupService::class)->groupKey($eventType, $eventName, $role),
            'is_active' => true,
        ];
    }
}
