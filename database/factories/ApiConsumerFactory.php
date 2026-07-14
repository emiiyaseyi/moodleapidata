<?php

namespace Database\Factories;

use App\Models\ApiConsumer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiConsumerFactory extends Factory
{
    protected $model = ApiConsumer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->domainWord().'-portal',
            'is_active' => true,
        ];
    }
}
