import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const defaultConfig = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
    questions: [],
    routes: {
        sessions: '/sessions',
    },
};

Alpine.data('interviewApp', () => ({
    config: { ...defaultConfig, ...(window.interviewConfig || {}) },
    step: 'start',
    candidate: {
        username: 'admin',
        password: 'password',
    },
    session: null,
    questions: [],
    currentIndex: 0,
    recorderState: 'idle',
    elapsedSeconds: 0,
    timer: null,
    mediaStream: null,
    mediaRecorder: null,
    audioChunks: [],
    audioBlob: null,
    activeAnswer: null,
    answers: [],
    errorMessage: '',

    init() {
        this.questions = this.config.questions;
    },

    get currentQuestion() {
        return this.questions[this.currentIndex]?.japanese_text || '';
    },

    get currentQuestionId() {
        return this.questions[this.currentIndex]?.id;
    },

    get progressPercent() {
        return Math.round(((this.currentIndex + 1) / this.questions.length) * 100);
    },

    get formattedTime() {
        return this.formatDuration(this.elapsedSeconds);
    },

    get totalScore() {
        if (this.session?.total_score !== null && this.session?.total_score !== undefined) {
            return Math.round(Number(this.session.total_score));
        }

        if (this.answers.length === 0) {
            return 0;
        }

        const total = this.answers.reduce((sum, answer) => sum + Number(answer.score || 0), 0);

        return Math.round(total / this.answers.length);
    },

    get strongestArea() {
        if (this.totalScore >= 86) {
            return 'Kelancaran menjawab dan struktur kalimat dasar';
        }

        if (this.totalScore >= 76) {
            return 'Pemahaman pertanyaan dan keberanian berbicara';
        }

        return 'Konsistensi menjawab seluruh sesi';
    },

    get improvementArea() {
        if (this.totalScore >= 86) {
            return 'Perkaya kosakata kerja dan alasan spesifik';
        }

        if (this.totalScore >= 76) {
            return 'Perhalus pelafalan, tempo bicara, dan panjang vokal';
        }

        return 'Latihan tempo bicara dan pola kalimat dasar';
    },

    async startInterview() {
        if (!this.candidate.username.trim() || !this.candidate.password.trim()) {
            return;
        }

        this.errorMessage = '';

        try {
            const payload = await this.request(this.config.routes.sessions, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.candidate),
            });

            this.session = payload.session;
            this.questions = payload.questions;
            this.currentIndex = 0;
            this.answers = [];
            this.activeAnswer = null;
            this.step = 'interview';
            this.recorderState = 'idle';
        } catch (error) {
            this.errorMessage = error.message;
        }
    },

    async requestPermission() {
        if (this.recorderState !== 'idle') {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            this.errorMessage = 'Browser ini belum mendukung perekaman audio.';
            return;
        }

        this.errorMessage = '';
        this.recorderState = 'requesting';

        try {
            this.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.recorderState = 'ready';
        } catch (error) {
            this.recorderState = 'idle';
            this.errorMessage = 'Izin mikrofon ditolak atau tidak tersedia.';
        }
    },

    startRecording() {
        if (!['ready', 'stopped'].includes(this.recorderState) || !this.mediaStream) {
            return;
        }

        this.errorMessage = '';
        this.elapsedSeconds = 0;
        this.activeAnswer = null;
        this.audioBlob = null;
        this.audioChunks = [];

        const mimeType = this.preferredMimeType();

        this.mediaRecorder = new MediaRecorder(this.mediaStream, mimeType ? { mimeType } : {});

        this.mediaRecorder.addEventListener('dataavailable', (event) => {
            if (event.data.size > 0) {
                this.audioChunks.push(event.data);
            }
        });

        this.mediaRecorder.addEventListener('stop', () => {
            this.audioBlob = new Blob(this.audioChunks, { type: this.mediaRecorder.mimeType || 'audio/webm' });
            this.recorderState = 'stopped';
        });

        this.recorderState = 'recording';
        this.mediaRecorder.start();
        this.timer = window.setInterval(() => {
            this.elapsedSeconds += 1;
        }, 1000);
    },

    stopRecording() {
        if (this.recorderState !== 'recording') {
            return;
        }

        window.clearInterval(this.timer);
        this.timer = null;
        this.mediaRecorder?.stop();
    },

    async submitAnswer() {
        if (this.recorderState !== 'stopped' || !this.audioBlob || !this.currentQuestionId) {
            return;
        }

        this.errorMessage = '';
        this.recorderState = 'processing';

        const formData = new FormData();
        formData.append('question_id', this.currentQuestionId);
        formData.append('duration_seconds', String(this.elapsedSeconds));
        formData.append('audio', this.audioBlob, `question-${this.currentIndex + 1}.webm`);

        try {
            const payload = await this.request(`/sessions/${this.session.public_id}/answers`, {
                method: 'POST',
                body: formData,
            });

            this.activeAnswer = payload.answer;
            this.session = payload.session;
            this.answers.push(payload.answer);
            this.recorderState = 'processed';
        } catch (error) {
            this.recorderState = 'stopped';
            this.errorMessage = error.message;
        }
    },

    nextQuestion() {
        if (this.recorderState !== 'processed') {
            return;
        }

        if (this.session?.status === 'completed' || this.currentIndex === this.questions.length - 1) {
            this.step = 'results';
            this.releaseMicrophone();
            return;
        }

        this.currentIndex += 1;
        this.elapsedSeconds = 0;
        this.audioBlob = null;
        this.activeAnswer = null;
        this.recorderState = this.mediaStream ? 'ready' : 'idle';
    },

    restartDemo() {
        window.clearInterval(this.timer);
        this.releaseMicrophone();
        this.step = 'start';
        this.session = null;
        this.currentIndex = 0;
        this.recorderState = 'idle';
        this.elapsedSeconds = 0;
        this.timer = null;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.audioBlob = null;
        this.activeAnswer = null;
        this.answers = [];
        this.errorMessage = '';
    },

    async request(url, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-CSRF-TOKEN': this.config.csrfToken,
            ...(options.headers || {}),
        };

        const response = await fetch(url, {
            ...options,
            headers,
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(this.errorFromPayload(payload, response.status));
        }

        return payload;
    },

    errorFromPayload(payload, status) {
        if (payload?.errors) {
            return Object.values(payload.errors).flat().join(' ');
        }

        if (payload?.detail) {
            return payload.detail;
        }

        if (payload?.message) {
            return payload.message;
        }

        return `Request failed with status ${status}.`;
    },

    preferredMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
        ];

        return types.find((type) => MediaRecorder.isTypeSupported(type)) || '';
    },

    releaseMicrophone() {
        this.mediaStream?.getTracks().forEach((track) => track.stop());
        this.mediaStream = null;
    },

    formatDuration(totalSeconds) {
        const minutes = String(Math.floor((totalSeconds || 0) / 60)).padStart(2, '0');
        const seconds = String((totalSeconds || 0) % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },
}));

Alpine.start();
