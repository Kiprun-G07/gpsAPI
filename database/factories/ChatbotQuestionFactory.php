<?php

namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatbotQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->sentence(),
            'answer' => fake()->paragraph(),
        ];
    }
}