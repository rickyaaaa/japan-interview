<?php

namespace Database\Seeders;

use App\Models\Question;
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
        collect([
            'タバコを吸いますか。お酒を飲みますか。',
            '短所と長所を教えてください。',
            '日本で仕事している家族か親戚はいますか。',
            'どれぐらい日本語を勉強しましたか。',
            '共同生活は大丈夫ですか。',
            '断食はやっていますか。',
            'お祈りの時間を調整できますか。',
            '日本の仕事の中で職種がいっぱいありますが、なんで我々の会社で仕事したいですか。',
            '日本へ行く目的は、割合にすると、何割仕事か、何割遊びか、正直に答えてください。',
            '日本の文化で何を知っていますか。',
        ])->each(function (string $japaneseText, int $index): void {
            Question::updateOrCreate(
                ['order_index' => $index + 1],
                [
                    'japanese_text' => $japaneseText,
                    'indonesian_translation' => null,
                    'is_active' => true,
                ],
            );
        });
    }
}
