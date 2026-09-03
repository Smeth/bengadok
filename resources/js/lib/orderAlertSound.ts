import {
    getOrderAlertSoundPreset,
    isOrderAlertSoundEnabled,
    type OrderAlertSoundPreset,
} from '@/lib/orderAlertPreferences';

/** Conservé pour compatibilité des appelants (nouvelle commande vs info). */
export type AlertSoundProfile = 'urgent' | 'info';

export type { OrderAlertSoundPreset as OrderAlertSoundPresetId };

let audioContext: AudioContext | null = null;

export function unlockOrderAlertSound(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const Ctx =
        window.AudioContext ||
        (window as typeof window & { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!Ctx) {
        return;
    }

    if (!audioContext) {
        audioContext = new Ctx();
    }

    if (audioContext.state === 'suspended') {
        void audioContext.resume();
    }
}

function playTone(
    context: AudioContext,
    startAt: number,
    frequency: number,
    duration = 0.16,
    volume = 0.28,
    wave: OscillatorType = 'square',
): void {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = wave;
    oscillator.frequency.value = frequency;
    oscillator.connect(gain);
    gain.connect(context.destination);

    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.02);
}

function playPreset(context: AudioContext, preset: OrderAlertSoundPreset): void {
    const start = context.currentTime;

    switch (preset) {
        case 'urgent':
            playTone(context, start, 880, 0.14, 0.32);
            playTone(context, start + 0.18, 1175, 0.14, 0.32);
            playTone(context, start + 0.36, 880, 0.18, 0.34);
            return;
        case 'discret':
            playTone(context, start, 740, 0.16, 0.22, 'sine');
            playTone(context, start + 0.22, 988, 0.2, 0.24, 'sine');
            return;
        case 'classique':
            playTone(context, start, 523, 0.18, 0.26, 'sine');
            playTone(context, start + 0.2, 659, 0.22, 0.28, 'sine');
            playTone(context, start + 0.44, 784, 0.24, 0.3, 'sine');
            return;
        case 'court':
            playTone(context, start, 880, 0.1, 0.3);
            return;
    }
}

export function playOrderAlertSound(_profile?: AlertSoundProfile): void {
    if (!isOrderAlertSoundEnabled()) {
        return;
    }

    unlockOrderAlertSound();

    if (!audioContext) {
        return;
    }

    playPreset(audioContext, getOrderAlertSoundPreset());
}

/** Lecture d’un preset précis (page Paramètres → test). */
export function previewOrderAlertSoundPreset(
    preset: OrderAlertSoundPreset,
): void {
    unlockOrderAlertSound();

    if (!audioContext) {
        return;
    }

    playPreset(audioContext, preset);
}
