<?php

namespace App\Services;

use App\Models\Question;

/**
 * ScoringService
 *
 * Evaluates a Japanese interview transcript using rule-based heuristics.
 * No AI is used in this service — only transcript analysis.
 *
 * Score breakdown (total 100 pts):
 *   - Length score      : 0–30 pts  (based on transcript character count)
 *   - Keyword score     : 0–35 pts  (question-specific Japanese keyword matching)
 *   - Structure score   : 0–20 pts  (Japanese sentence structure patterns)
 *   - Duration score    : 0–15 pts  (answer duration reasonableness)
 */
class ScoringService
{
    /**
     * Keyword bank: maps question order_index (1–10) to expected Japanese keywords.
     * Each keyword adds weight to the answer if found in the transcript.
     */
    private array $keywordBank = [
        1 => [
            // タバコを吸いますか。お酒を飲みますか。
            'keywords'  => ['吸いません', '飲みません', '吸います', '飲みます', 'タバコ', 'お酒', 'たばこ', '酒', '煙草'],
            'negatives' => ['はい、吸います', 'はい、飲みます'], // negative keywords reduce score slightly
        ],
        2 => [
            // 短所と長所を教えてください。
            'keywords'  => ['長所', '短所', '得意', '苦手', '頑張', '真面目', '責任感', '協力', '改善', '努力', '忍耐', '集中'],
            'negatives' => [],
        ],
        3 => [
            // 日本で仕事している家族か親戚はいますか。
            'keywords'  => ['家族', '親戚', '兄', '姉', '弟', '妹', '父', '母', '叔父', '叔母', 'います', 'いません', '友達'],
            'negatives' => [],
        ],
        4 => [
            // どれぐらい日本語を勉強しましたか。
            'keywords'  => ['勉強', '年', 'ヶ月', 'か月', 'ヵ月', '日本語', '学校', '独学', '習', 'クラス', '教室', 'JLPT', 'N4', 'N3', 'N5'],
            'negatives' => [],
        ],
        5 => [
            // 共同生活は大丈夫ですか。
            'keywords'  => ['大丈夫', '問題', '一緒', '生活', '住', '友達', '協力', 'はい', '慣れ', 'できます'],
            'negatives' => [],
        ],
        6 => [
            // 断食はやっていますか。
            'keywords'  => ['断食', 'ラマダン', 'やっています', 'やりません', 'ムスリム', 'イスラム', '宗教', 'します', 'しません', 'はい'],
            'negatives' => [],
        ],
        7 => [
            // お祈りの時間を調整できますか。
            'keywords'  => ['調整', 'できます', 'お祈り', '時間', '休憩', '仕事', '合わせ', '大丈夫', 'はい', '工夫'],
            'negatives' => [],
        ],
        8 => [
            // なんで我々の会社で仕事したいですか。
            'keywords'  => ['御社', '会社', '仕事', 'したい', '経験', '学びたい', '成長', '貢献', '働', '日本', '機会', '挑戦'],
            'negatives' => [],
        ],
        9 => [
            // 日本へ行く目的は、何割仕事か、何割遊びか
            'keywords'  => ['仕事', '割', '遊び', '目的', 'パーセント', '経験', 'お金', '稼', '学び', '将来', 'キャリア'],
            'negatives' => [],
        ],
        10 => [
            // 日本の文化で何を知っていますか。
            'keywords'  => ['文化', 'アニメ', '食べ物', '礼儀', '食べ物', '寿司', 'ラーメン', '桜', '祭り', '伝統', '着物', '富士山', '温泉', 'マナー'],
            'negatives' => [],
        ],
    ];

    /**
     * Japanese sentence structure patterns — simple regex heuristics.
     */
    private array $structurePatterns = [
        '/[はがをにでもへ]/',           // particles
        '/です|ます|でした|ました/',     // polite verb endings
        '/ので|から|けど|が、/',         // conjunctions
        '/と思います|と思い/',           // opinion expression
        '/できます|できません/',         // ability expression
        '/[。、！？]/',                  // Japanese punctuation
    ];

    /**
     * Evaluate a transcript and return scores.
     */
    public function evaluate(Question $question, string $transcript, ?int $durationSeconds = null): array
    {
        $transcript = trim($transcript);

        if ($transcript === '') {
            return $this->zeroScoreResult('empty');
        }

        $japaneseOnly = preg_replace('/[^\p{Hiragana}\p{Katakana}\p{Han}]/u', '', $transcript);
        
        if (mb_strlen($japaneseOnly) === 0) {
            return $this->zeroScoreResult('no_japanese');
        }

        if (mb_strlen($japaneseOnly) < 3) {
            $keywordScore = $this->scoreKeywords($question, $transcript);
            if ($keywordScore <= 8 && !preg_match('/(はい|いいえ|です|ます)/u', $transcript)) {
                return $this->zeroScoreResult('meaningless');
            }
        }

        $lengthScore    = $this->scoreLengthNormalized($transcript);
        $keywordScore   = $this->scoreKeywords($question, $transcript);
        $structureScore = $this->scoreStructure($transcript);
        $durationScore  = $this->scoreDuration($durationSeconds);

        // Weighted total out of 100
        $total = $lengthScore + $keywordScore + $structureScore + $durationScore;
        $total = (int) max(10, min(100, round($total)));

        // Sub-scores derived from components (each 0–100)
        $pronunciationScore = $this->derivePronunciationScore($structureScore, $lengthScore);
        $fluencyScore       = $this->deriveFluencyScore($durationScore, $lengthScore);
        $grammarScore       = $this->deriveGrammarScore($structureScore, $keywordScore);

        return [
            'score'              => $total,
            'pronunciation_score' => $pronunciationScore,
            'fluency_score'       => $fluencyScore,
            'grammar_score'       => $grammarScore,
            'level'               => $this->determineLevel($total),
            'details'             => [
                'length_score'    => $lengthScore,
                'keyword_score'   => $keywordScore,
                'structure_score' => $structureScore,
                'duration_score'  => $durationScore,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Scoring components
    // -------------------------------------------------------------------------

    /**
     * Score based on transcript character count (Japanese chars are compact).
     * Ideal answer: 30–120 characters → max 30 pts
     */
    private function scoreLengthNormalized(string $transcript): int
    {
        $len = mb_strlen($transcript);

        if ($len === 0) {
            return 0;
        }

        if ($len < 5) {
            return 3;
        }

        if ($len < 15) {
            return 10;
        }

        if ($len < 30) {
            return 18;
        }

        if ($len < 60) {
            return 24;
        }

        if ($len < 120) {
            return 30;
        }

        // Very long answer — still good but diminishing return
        return 28;
    }

    /**
     * Score keyword matches against question-specific keyword bank.
     * Max 35 pts.
     */
    private function scoreKeywords(Question $question, string $transcript): int
    {
        $orderIndex = (int) $question->order_index;
        $bank       = $this->keywordBank[$orderIndex] ?? null;

        if (! $bank) {
            return $this->scoreGenericKeywords($transcript);
        }

        $keywords  = $bank['keywords'] ?? [];
        $negatives = $bank['negatives'] ?? [];
        $hits      = 0;

        foreach ($keywords as $keyword) {
            if (mb_strpos($transcript, $keyword) !== false) {
                $hits++;
            }
        }

        foreach ($negatives as $neg) {
            if (mb_strpos($transcript, $neg) !== false) {
                $hits = max(0, $hits - 1);
            }
        }

        if (count($keywords) === 0) {
            return 18;
        }

        $ratio = $hits / count($keywords);

        // Scale: 0 hits → 5 pts (they spoke something), many hits → 35 pts
        return (int) min(35, max(5, round(5 + ($ratio * 30))));
    }

    /**
     * Fallback keyword scoring when no bank is defined for the question.
     */
    private function scoreGenericKeywords(string $transcript): int
    {
        $genericKeywords = ['です', 'ます', 'はい', 'います', 'できます', 'します', 'ました'];
        $hits = 0;

        foreach ($genericKeywords as $kw) {
            if (mb_strpos($transcript, $kw) !== false) {
                $hits++;
            }
        }

        return (int) min(35, max(8, $hits * 5));
    }

    /**
     * Score Japanese sentence structure patterns.
     * Max 20 pts.
     */
    private function scoreStructure(string $transcript): int
    {
        $matches = 0;

        foreach ($this->structurePatterns as $pattern) {
            if (preg_match($pattern, $transcript)) {
                $matches++;
            }
        }

        return (int) min(20, $matches * 4);
    }

    /**
     * Score based on answer duration.
     * Ideal range: 5–60 seconds → max 15 pts
     */
    private function scoreDuration(?int $durationSeconds): int
    {
        if ($durationSeconds === null || $durationSeconds <= 0) {
            return 8; // neutral — no duration data
        }

        if ($durationSeconds < 3) {
            return 2;
        }

        if ($durationSeconds < 5) {
            return 5;
        }

        if ($durationSeconds < 10) {
            return 10;
        }

        if ($durationSeconds <= 60) {
            return 15;
        }

        if ($durationSeconds <= 120) {
            return 12;
        }

        // Very long answer
        return 8;
    }

    // -------------------------------------------------------------------------
    // Derived sub-scores (0–100 scale)
    // -------------------------------------------------------------------------

    private function derivePronunciationScore(int $structureScore, int $lengthScore): int
    {
        // Pronunciation is estimated from structure clarity and answer length
        $base = (int) round(($structureScore / 20) * 60 + ($lengthScore / 30) * 40);

        return (int) max(20, min(100, $base));
    }

    private function deriveFluencyScore(int $durationScore, int $lengthScore): int
    {
        $base = (int) round(($durationScore / 15) * 50 + ($lengthScore / 30) * 50);

        return (int) max(20, min(100, $base));
    }

    private function deriveGrammarScore(int $structureScore, int $keywordScore): int
    {
        $base = (int) round(($structureScore / 20) * 50 + ($keywordScore / 35) * 50);

        return (int) max(20, min(100, $base));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function determineLevel(int $score): string
    {
        if ($score >= 85) return 'Sangat Baik';
        if ($score >= 70) return 'Baik';
        if ($score >= 55) return 'Cukup';
        if ($score >= 40) return 'Perlu Latihan';

        return 'Perlu Banyak Latihan';
    }

    private function zeroScoreResult(string $reason): array
    {
        $level = match ($reason) {
            'empty' => 'Tidak Menjawab',
            'no_japanese' => 'Tidak Valid (Bukan Bahasa Jepang)',
            'meaningless' => 'Tidak Valid (Jawaban Tidak Bermakna)',
            default => 'Tidak Valid',
        };

        return [
            'score'               => 0,
            'pronunciation_score' => 0,
            'fluency_score'       => 0,
            'grammar_score'       => 0,
            'level'               => $level,
            'error_reason'        => $reason,
            'details'             => [
                'length_score'    => 0,
                'keyword_score'   => 0,
                'structure_score' => 0,
                'duration_score'  => 0,
            ],
        ];
    }
}
