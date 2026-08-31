import { isOrderAlertSoundEnabled } from '@/lib/orderAlertPreferences';

export type AlertSoundProfile = 'urgent' | 'info';

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
): void {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = 'square';
    oscillator.frequency.value = frequency;
    oscillator.connect(gain);
    gain.connect(context.destination);

    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(volume, startAt + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.start(startAt);
    oscillator.stop(startAt + duration + 0.02);
}

export function playOrderAlertSound(profile: AlertSoundProfile): void {
    if (!isOrderAlertSoundEnabled()) {
        return;
    }

    unlockOrderAlertSound();

    if (!audioContext) {
        return;
    }

    const start = audioContext.currentTime;

    if (profile === 'urgent') {
        playTone(audioContext, start, 880, 0.14, 0.32);
        playTone(audioContext, start + 0.18, 1175, 0.14, 0.32);
        playTone(audioContext, start + 0.36, 880, 0.18, 0.34);
        return;
    }

    playTone(audioContext, start, 740, 0.16, 0.26);
    playTone(audioContext, start + 0.22, 988, 0.2, 0.28);
}
