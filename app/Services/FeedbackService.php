<?php

namespace App\Services;

/**
 * FeedbackService
 *
 * Generates Indonesian-language feedback from predefined templates.
 * No AI is used — feedback is selected and composed from template pools
 * based on score range, detected patterns, and sub-scores.
 */
class FeedbackService
{
    /**
     * Opening templates by score band.
     */
    private array $openings = [
        'excellent' => [
            'Jawaban Anda sangat bagus dan menunjukkan kemampuan berbahasa Jepang yang baik.',
            'Luar biasa! Anda menjawab dengan lancar dan struktur kalimat yang tepat.',
            'Jawaban Anda sangat meyakinkan dan menunjukkan persiapan yang matang.',
        ],
        'good' => [
            'Jawaban Anda cukup baik dan dapat dipahami dengan jelas.',
            'Secara keseluruhan, jawaban Anda menunjukkan kemampuan yang memadai.',
            'Anda menjawab dengan cukup baik. Ada beberapa hal yang bisa ditingkatkan.',
        ],
        'average' => [
            'Jawaban Anda dapat dipahami, namun masih perlu beberapa perbaikan.',
            'Anda sudah berusaha menjawab dengan baik, namun ada area yang perlu diperkuat.',
            'Jawaban Anda cukup, namun pewawancara mungkin butuh penjelasan tambahan.',
        ],
        'poor' => [
            'Jawaban Anda masih sangat singkat dan perlu diperluas.',
            'Jawaban Anda kurang detail. Latihan lebih banyak akan sangat membantu.',
            'Masih perlu banyak latihan untuk menjawab dengan lebih percaya diri.',
        ],
    ];

    /**
     * Fluency feedback templates.
     */
    private array $fluencyFeedback = [
        'high'   => 'Kelancaran bicara Anda terlihat baik dan tempo menjawab cukup natural.',
        'medium' => 'Tempo bicara Anda wajar, namun coba jawab sedikit lebih panjang dan spontan.',
        'low'    => 'Usahakan menjawab lebih panjang dan tidak terlalu singkat agar terdengar lancar.',
    ];

    /**
     * Grammar feedback templates.
     */
    private array $grammarFeedback = [
        'high'   => 'Struktur kalimat dan pilihan kata Anda sudah tepat.',
        'medium' => 'Struktur kalimat dasar sudah ada, coba tambahkan partikel dan kata penghubung.',
        'low'    => 'Perlu berlatih pola kalimat dasar bahasa Jepang seperti penggunaan は、が、を、に.',
    ];

    /**
     * Pronunciation feedback templates.
     */
    private array $pronunciationFeedback = [
        'high'   => 'Pengucapan Anda terdengar jelas dan mudah dipahami.',
        'medium' => 'Pengucapan masih bisa ditingkatkan terutama pada vokal panjang dan pendek.',
        'low'    => 'Perhatikan panjang pendek vokal dalam bahasa Jepang agar pengucapan lebih akurat.',
    ];

    /**
     * Closing encouragement templates by band.
     */
    private array $closings = [
        'excellent' => [
            'Pertahankan kemampuan ini dan terus berlatih untuk menjadi lebih baik!',
            'Terus tingkatkan kosakata dan Anda akan siap untuk wawancara sesungguhnya.',
        ],
        'good' => [
            'Dengan sedikit latihan tambahan, Anda akan tampil sangat baik dalam wawancara.',
            'Terus berlatih berbicara bahasa Jepang setiap hari untuk meningkatkan kelancaran.',
        ],
        'average' => [
            'Perbanyak latihan berbicara dan mendengarkan bahasa Jepang setiap hari.',
            'Latihan rutin selama 15–30 menit per hari akan sangat membantu perkembangan Anda.',
        ],
        'poor' => [
            'Jangan menyerah! Konsistensi latihan adalah kunci keberhasilan.',
            'Mulailah dari kalimat-kalimat sederhana dan tingkatkan secara bertahap.',
        ],
    ];

    /**
     * Generate feedback text based on evaluation results.
     */
    public function generate(array $evaluation, string $transcript): string
    {
        $reason = $evaluation['error_reason'] ?? null;

        if ($reason === 'empty' || trim($transcript) === '') {
            return 'Tidak ada jawaban suara yang terdeteksi.';
        }

        if ($reason === 'no_japanese') {
            return 'Tidak ada bahasa Jepang yang terdeteksi dalam jawaban Anda.';
        }

        if ($reason === 'meaningless') {
            return 'Jawaban terlalu singkat atau tidak memiliki makna yang jelas untuk dievaluasi.';
        }

        $score              = (int) $evaluation['score'];
        $pronunciationScore = (int) $evaluation['pronunciation_score'];
        $fluencyScore       = (int) $evaluation['fluency_score'];
        $grammarScore       = (int) $evaluation['grammar_score'];
        $similarityPct      = (float) ($evaluation['details']['similarity_pct'] ?? 50.0);

        $band  = $this->scoreBand($score);
        $parts = [];

        // 1. Opening sentence
        $parts[] = $this->pick($this->openings[$band]);

        // 2. Pronunciation similarity hint (injected before fluency when score is low)
        //    This makes the feedback directly address phonetic accuracy.
        if ($similarityPct < 20.0) {
            $parts[] = 'Kata-kata yang diucapkan terdeteksi sangat berbeda dari konteks pertanyaan. Pastikan Anda menjawab sesuai topik pertanyaan dengan kosakata bahasa Jepang yang relevan.';
        } elseif ($similarityPct < 40.0) {
            $parts[] = 'Pelafalan Anda masih kurang sesuai dengan pola jawaban yang diharapkan. Coba pelajari kembali kosakata kunci untuk pertanyaan ini dan ucapkan dengan lebih jelas.';
        }

        // 3. Fluency feedback
        $parts[] = $this->fluencyFeedback[$this->subBand($fluencyScore)];

        // 4. Grammar feedback
        $parts[] = $this->grammarFeedback[$this->subBand($grammarScore)];

        // 5. Pronunciation feedback (based on derived pronunciation_score)
        $parts[] = $this->pronunciationFeedback[$this->subBand($pronunciationScore)];

        // 6. Short answer note
        $len = mb_strlen(trim($transcript));
        if ($len < 15 && $score < 60) {
            $parts[] = 'Jawaban Anda sangat singkat — coba latihan menjawab dengan minimal 1–2 kalimat lengkap.';
        }

        // 7. Closing encouragement
        $parts[] = $this->pick($this->closings[$band]);

        return implode(' ', $parts);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function scoreBand(int $score): string
    {
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 45) return 'average';

        return 'poor';
    }

    private function subBand(int $score): string
    {
        if ($score >= 70) return 'high';
        if ($score >= 45) return 'medium';

        return 'low';
    }

    /**
     * Pick a pseudo-random item from an array, deterministically based on
     * the current minute so results are stable within a session but vary over time.
     */
    private function pick(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $index = (int) (floor(time() / 60) % count($items));

        return $items[$index];
    }
}
