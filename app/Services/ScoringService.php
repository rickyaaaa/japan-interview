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
 *   - Pronunciation similarity : 0–35 pts  (similar_text vs. ideal answer patterns)
 *   - Keyword score            : 0–30 pts  (question-specific Japanese keyword matching)
 *   - Structure score          : 0–20 pts  (Japanese sentence structure patterns)
 *   - Duration score           : 0–15 pts  (answer duration — penalized if similarity < 40%)
 *   - Length score             : 0–15 pts  (excluded entirely if similarity < 40%)
 *
 * NOTE: Length score and duration score are down-weighted when pronunciation
 *       similarity is below 40%, to prevent inflated scores from long but
 *       phonetically incorrect answers.
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
            'negatives' => ['はい、吸います', 'はい、飲みます'],
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
            'keywords'  => ['文化', 'アニメ', '食べ物', '礼儀', '寿司', 'ラーメン', '桜', '祭り', '伝統', '着物', '富士山', '温泉', 'マナー'],
            'negatives' => [],
        ],
    ];

    /**
     * Pronunciation bank: maps question order_index to ideal answer phrases.
     * These phrases are used to compute text similarity against the transcript.
     * Multiple variants are accepted — the best matching score is used.
     */
    private array $pronunciationBank = [
        1 => [
            'はい、タバコは吸いません。お酒も飲みません。',
            'いいえ、タバコは吸いません。お酒は少し飲みます。',
            'タバコは吸いません。お酒も飲みません。',
        ],
        2 => [
            '私の長所は責任感が強いことです。短所は少し心配性なところです。',
            '長所は真面目なところです。短所は時間がかかることです。',
            '私の短所は慎重すぎることで、長所は努力を続けられることです。',
        ],
        3 => [
            '日本で仕事している家族はいません。',
            'はい、兄が日本で仕事しています。',
            '親戚は日本にいません。家族も日本にはいません。',
        ],
        4 => [
            '日本語を一年間勉強しました。',
            '学校で六ヶ月間日本語を勉強しました。',
            '独学で日本語を二年間勉強しました。JLPTのN4を持っています。',
        ],
        5 => [
            'はい、共同生活は大丈夫です。問題ありません。',
            '共同生活は慣れています。一緒に住むことができます。',
            'はい、大丈夫です。協力して生活できます。',
        ],
        6 => [
            'はい、断食はやっています。ラマダンの時に断食します。',
            'いいえ、断食はやっていません。',
            'はい、私はムスリムなので断食します。',
        ],
        7 => [
            'はい、お祈りの時間は調整できます。',
            '休憩の時間に合わせてお祈りします。仕事に影響はありません。',
            'お祈りの時間は工夫して調整できます。大丈夫です。',
        ],
        8 => [
            '御社で働きたい理由は、経験を積みたいからです。',
            '日本で成長したいので、御社で仕事したいです。',
            '御社に貢献したいと思います。日本での仕事の機会が欲しいです。',
        ],
        9 => [
            '日本へ行く目的は八割仕事で、二割は経験です。',
            '仕事が九割で、遊びは一割です。',
            '目的は将来のキャリアのために働くことです。',
        ],
        10 => [
            '日本の文化といえばアニメや食べ物、礼儀が好きです。',
            '日本の文化で知っているのは寿司やラーメン、桜の花見です。',
            '日本の伝統文化は着物や祭り、富士山などが有名です。',
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

        // 1. Pronunciation similarity (primary signal, 0–35 pts)
        $similarityPct    = $this->computeSimilarity($question, $transcript);
        $pronunciationRaw = $this->scorePronunciationSimilarity($similarityPct);

        // 2. Keyword matching (0–30 pts)
        $keywordScore = $this->scoreKeywords($question, $transcript);

        // 3. Sentence structure (0–20 pts)
        $structureScore = $this->scoreStructure($transcript);

        // 4. Duration (0–15 pts) — penalized when similarity is low
        $durationRaw   = $this->scoreDuration($durationSeconds);
        $durationScore = $this->applyLowSimilarityPenalty($durationRaw, $similarityPct);

        // 5. Length (0–15 pts) — excluded entirely when similarity < 40%
        $lengthScore = $similarityPct >= 40.0
            ? $this->scoreLengthNormalized($transcript)
            : 0;

        // ── Total ─────────────────────────────────────────────────────────────
        $total = $pronunciationRaw + $keywordScore + $structureScore + $durationScore + $lengthScore;

        // Hard cap: if similarity is critically low (< 20%), cap total at 30
        if ($similarityPct < 20.0) {
            $total = min($total, 30);
        }

        $total = (int) max(0, min(100, round($total)));

        // ── Derived sub-scores (0–100 scale shown to user) ───────────────────
        $pronunciationScore = $this->derivePronunciationScore($similarityPct, $structureScore);
        $fluencyScore       = $this->deriveFluencyScore($durationScore, $structureScore);
        $grammarScore       = $this->deriveGrammarScore($structureScore, $keywordScore);

        return [
            'score'               => $total,
            'pronunciation_score' => $pronunciationScore,
            'fluency_score'       => $fluencyScore,
            'grammar_score'       => $grammarScore,
            'level'               => $this->determineLevel($total),
            'details'             => [
                'similarity_pct'  => round($similarityPct, 1),
                'pronunc_score'   => $pronunciationRaw,
                'keyword_score'   => $keywordScore,
                'structure_score' => $structureScore,
                'duration_score'  => $durationScore,
                'length_score'    => $lengthScore,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Pronunciation similarity
    // -------------------------------------------------------------------------

    private function computeSimilarity(Question $question, string $transcript): float
    {
        $orderIndex = (int) $question->order_index;
        $patterns   = $this->pronunciationBank[$orderIndex] ?? [];

        if (empty($patterns)) {
            return 50.0;
        }

        $best = 0.0;
        foreach ($patterns as $pattern) {
            similar_text($transcript, $pattern, $pct);
            if ($pct > $best) {
                $best = $pct;
            }
        }
        return (float) $best;
    }

    private function scorePronunciationSimilarity(float $similarityPct): int
    {
        if ($similarityPct >= 70.0) return 35;
        if ($similarityPct >= 55.0) return 28;
        if ($similarityPct >= 40.0) return 20;
        if ($similarityPct >= 25.0) return 12;
        if ($similarityPct >= 10.0) return 5;
        return 0;
    }

    private function applyLowSimilarityPenalty(int $rawScore, float $similarityPct): int
    {
        if ($similarityPct >= 40.0) return $rawScore;
        if ($similarityPct >= 20.0) return (int) round($rawScore * 0.4);
        return 0;
    }

    // -------------------------------------------------------------------------
    // Scoring components
    // -------------------------------------------------------------------------

    private function scoreLengthNormalized(string $transcript): int
    {
        $len = mb_strlen($transcript);
        if ($len === 0)  return 0;
        if ($len < 5)    return 2;
        if ($len < 15)   return 5;
        if ($len < 30)   return 9;
        if ($len < 60)   return 12;
        if ($len < 120)  return 15;
        return 13;
    }

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
            return 15;
        }

        $ratio = $hits / count($keywords);
        return (int) min(30, max(3, round(3 + ($ratio * 27))));
    }

    private function scoreGenericKeywords(string $transcript): int
    {
        $genericKeywords = ['です', 'ます', 'はい', 'います', 'できます', 'します', 'ました'];
        $hits = 0;
        foreach ($genericKeywords as $kw) {
            if (mb_strpos($transcript, $kw) !== false) {
                $hits++;
            }
        }
        return (int) min(30, max(5, $hits * 4));
    }

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

    private function scoreDuration(?int $durationSeconds): int
    {
        if ($durationSeconds === null || $durationSeconds <= 0) {
            return 5;
        }
        if ($durationSeconds < 3)    return 2;
        if ($durationSeconds < 5)    return 5;
        if ($durationSeconds < 10)   return 10;
        if ($durationSeconds <= 60)  return 15;
        if ($durationSeconds <= 120) return 12;
        return 8;
    }

    // -------------------------------------------------------------------------
    // Derived sub-scores
    // -------------------------------------------------------------------------

    private function derivePronunciationScore(float $similarityPct, int $structureScore): int
    {
        $base = (int) round(($similarityPct / 100) * 80 + ($structureScore / 20) * 20);
        return (int) max(0, min(100, $base));
    }

    private function deriveFluencyScore(int $durationScore, int $structureScore): int
    {
        $base = (int) round(($durationScore / 15) * 50 + ($structureScore / 20) * 50);
        return (int) max(0, min(100, $base));
    }

    private function deriveGrammarScore(int $structureScore, int $keywordScore): int
    {
        $base = (int) round(($structureScore / 20) * 50 + ($keywordScore / 30) * 50);
        return (int) max(0, min(100, $base));
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
            'empty'       => 'Tidak Menjawab',
            'no_japanese' => 'Tidak Valid (Bukan Bahasa Jepang)',
            'meaningless' => 'Tidak Valid (Jawaban Tidak Bermakna)',
            default       => 'Tidak Valid',
        };

        return [
            'score'               => 0,
            'pronunciation_score' => 0,
            'fluency_score'       => 0,
            'grammar_score'       => 0,
            'level'               => $level,
            'error_reason'        => $reason,
            'details'             => [
                'similarity_pct'  => 0,
                'pronunc_score'   => 0,
                'keyword_score'   => 0,
                'structure_score' => 0,
                'duration_score'  => 0,
                'length_score'    => 0,
            ],
        ];
    }
}
