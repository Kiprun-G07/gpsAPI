<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Admin;
use App\Models\Event;
use App\Models\ChatbotQuestion;
use App\Models\SpendingType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Adrianna Natasha',
            'email' => 'adrianna@example.com',
            'matriculation_number' => 'A24CS0992',
            'faculty' => 'Computing',
            'password' => bcrypt('123456'),
        ]);

        Admin::factory()->create([
            'name' => 'Ikhwan Hafizi',
            'email' => 'ikhwan@example.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        Event::factory(5)->create();

        $chatbot_questions = [
            [
                'question' => 'How to reset my password?',
                'answer' => 'To reset your password, go to the login page and click on "Forgot Password". Follow the instructions sent to your registered email address.',
            ],
            [
                'question' => 'Where can I find the event schedule?',
                'answer' => 'The event schedule can be found on the Events page of our website. You can also download the schedule as a PDF for your convenience.',
            ],
            [
                'question' => 'How to contact support?',
                'answer' => 'You can contact our support team by emailing gerakanpenggunasiswa@gmail.com',
            ],
            [
                'question' => 'Can I join an event as a crew member when I am a participant?',
                'answer' => 'No, participants are not allowed to join events as crew members. Each role has specific responsibilities to ensure the smooth running of the event.',
            ]
        ];

        foreach ($chatbot_questions as $item) {
            ChatbotQuestion::factory()->create($item);
        }

        $spending_types = [
            ['name' => 'Food & Beverages'],
            ['name' => 'Transportation'],
            ['name' => 'Accommodation'],
            ['name' => 'Event Fees'],
            ['name' => 'Miscellaneous'],
        ];

        foreach ($spending_types as $type) {
            SpendingType::factory()->create($type);
        }
    }
}
