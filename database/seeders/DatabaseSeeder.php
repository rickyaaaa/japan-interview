<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Truncate tables to prevent duplicates
        User::truncate();
        Question::truncate();

        // Seed Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin',
            'password' => Hash::make('password'),
        ]);

        // Seed 10 Active Japanese Interview Questions
        $questions = [
            '自己紹介をしてください。',
            '短所と長所を教えてください。',
            '志望動機は何ですか。',
            'なぜ日本で働きたいのですか。',
            '将来の夢は何ですか。',
            '前の仕事を辞めた理由は何ですか。',
            'あなたの専門スキルは何ですか。',
            'チームワークで大切なことは何だと思いますか。',
            'ストレスを感じたとき、どうやって解消しますか。',
            '日本の文化についてどう思いますか。',
        ];

        foreach ($questions as $index => $text) {
            Question::create([
                'order_index' => $index + 1,
                'japanese_text' => $text,
                'indonesian_translation' => '',
                'is_active' => true,
            ]);
        }
    }
}
