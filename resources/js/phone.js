const TOKEN_REFRESH_MS = 50 * 60 * 1000;

const HANGUP_MESSAGES = {
    REMOTE_BUSY: 'That number is busy.',
    REMOTE_NO_ANSWER_TIMEOUT: 'There was no answer.',
    REMOTE_NOT_FOUND: 'That number could not be reached.',
    REMOTE_FAILURE: 'The call failed on the network.',
    MEDIA_TIMEOUT: 'The call dropped because the audio was lost.',
};

const NEVER_CONNECTED_MESSAGE = 'The call ended before it connected. Check that the answer URL on your Vonage application is public and that your Vonage number includes its country code.';

/**
 * Explain why a call ended, or return null when ending was expected.
 */
function hangupMessage(reason, wasDialling) {
    if (reason === 'LOCAL_HANGUP') {
        return null;
    }

    return HANGUP_MESSAGES[reason] ?? (wasDialling ? NEVER_CONNECTED_MESSAGE : null);
}

/**
 * Alpine component that turns the dialler UI into a browser phone using the Vonage Client SDK.
 */
export default function phone({ configured }) {
    let client = null;
    let ringtone = null;
    let timerHandle = null;
    let refreshHandle = null;

    return {
        state: configured ? 'connecting' : 'offline',
        number: '',
        callId: null,
        incomingFrom: '',
        muted: false,
        startedAt: null,
        elapsed: '00:00',
        errorMessage: '',
        placeholder: 'Enter number',

        async init() {
            if (!configured) {
                return;
            }

            const { VonageClient } = await import('@vonage/client-sdk');
            client = new VonageClient();
            this.listen();
            await this.connect();
        },

        destroy() {
            this.stopRingtone();
            clearInterval(timerHandle);
            clearInterval(refreshHandle);
            client?.deleteSession().catch(() => {});
        },

        async connect() {
            this.state = 'connecting';
            this.errorMessage = '';

            try {
                const token = await this.$wire.issueToken();
                await client.createSession(token);
                this.state = 'ready';

                clearInterval(refreshHandle);
                refreshHandle = setInterval(async () => {
                    client.refreshSession(await this.$wire.issueToken()).catch(() => this.connect());
                }, TOKEN_REFRESH_MS);
            } catch (error) {
                this.fail('Could not connect to Vonage. Check your settings.', error);
            }
        },

        listen() {
            client.on('callInvite', (callId, from) => {
                if (this.callId) {
                    client.reject(callId).catch(() => {});

                    return;
                }

                this.callId = callId;
                this.incomingFrom = from;
                this.state = 'incoming';
                this.startRingtone();
            });

            client.on('callInviteCancel', (callId) => {
                if (callId === this.callId && this.state === 'incoming') {
                    this.reset();
                }
            });

            client.on('legStatusUpdate', (callId, legId, status) => {
                if (callId === this.callId && String(status) === 'ANSWERED') {
                    this.startCall();
                }
            });

            client.on('callHangup', (callId, callQuality, reason) => {
                const wasDialling = this.state === 'dialling';

                // A call can end before serverCall() has resolved with its ID.
                if (callId !== this.callId && !(wasDialling && !this.callId)) {
                    return;
                }

                this.reset();

                const message = hangupMessage(String(reason), wasDialling);

                if (message) {
                    this.fail(message, reason, 'ready');
                }
            });

            client.on('sessionError', () => {
                this.reset();
                this.state = 'connecting';
                setTimeout(() => this.connect(), 3000);
            });
        },

        async call() {
            if (this.state !== 'ready' || !this.number) {
                return;
            }

            this.errorMessage = '';
            this.state = 'dialling';

            try {
                const callId = await client.serverCall({ to: this.number.replace(/[^0-9]/g, '') });

                if (this.state === 'dialling') {
                    this.callId = callId;
                }
            } catch (error) {
                this.reset();
                this.fail('The call could not be placed.', error, 'ready');
            }
        },

        async answer() {
            this.stopRingtone();

            try {
                await client.answer(this.callId);
                this.startCall();
            } catch (error) {
                this.reset();
                this.fail('The call could not be answered.', error, 'ready');
            }
        },

        async reject() {
            const callId = this.callId;
            this.reset();
            await client.reject(callId).catch(() => {});
        },

        async hangup() {
            const callId = this.callId;
            this.reset();

            if (callId) {
                await client.hangup(callId).catch(() => {});
            }
        },

        async toggleMute() {
            if (this.state !== 'in-call') {
                return;
            }

            this.muted ? await client.unmute(this.callId) : await client.mute(this.callId);
            this.muted = !this.muted;
        },

        press(digit) {
            if (this.state === 'in-call') {
                client.sendDTMF(this.callId, digit).catch(() => {});
            }

            if (this.number.length < 20) {
                this.number += digit;
            }
        },

        backspace() {
            this.number = this.number.slice(0, -1);
        },

        handleKey(event) {
            if (!configured || event.target.closest('input, textarea, select')) {
                return;
            }

            if (/^[0-9*#+]$/.test(event.key)) {
                this.press(event.key);
            } else if (event.key === 'Backspace' && this.state !== 'in-call') {
                this.backspace();
            } else if (event.key === 'Enter') {
                this.state === 'incoming' ? this.answer() : this.call();
            } else if (event.key === 'Escape') {
                this.state === 'incoming' ? this.reject() : this.hangup();
            }
        },

        startCall() {
            if (this.state === 'in-call') {
                return;
            }

            this.stopRingtone();
            this.state = 'in-call';
            this.startedAt = Date.now();
            this.elapsed = '00:00';

            clearInterval(timerHandle);
            timerHandle = setInterval(() => {
                const seconds = Math.floor((Date.now() - this.startedAt) / 1000);
                this.elapsed = `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
            }, 1000);
        },

        reset() {
            this.stopRingtone();
            clearInterval(timerHandle);
            this.callId = null;
            this.incomingFrom = '';
            this.muted = false;
            this.startedAt = null;

            if (this.state !== 'connecting' && this.state !== 'offline') {
                this.state = 'ready';
            }
        },

        fail(message, error, state = 'error') {
            console.error(message, error);
            this.errorMessage = message;
            this.state = state;
        },

        startRingtone() {
            try {
                const context = new AudioContext();
                const gain = context.createGain();
                gain.gain.value = 0;
                gain.connect(context.destination);

                [440, 480].forEach((frequency) => {
                    const oscillator = context.createOscillator();
                    oscillator.frequency.value = frequency;
                    oscillator.connect(gain);
                    oscillator.start();
                });

                const ring = () => {
                    const now = context.currentTime;
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.setValueAtTime(0, now + 2);
                };

                ring();
                ringtone = { context, interval: setInterval(ring, 6000) };
            } catch {
                ringtone = null;
            }
        },

        stopRingtone() {
            if (ringtone) {
                clearInterval(ringtone.interval);
                ringtone.context.close();
                ringtone = null;
            }
        },

        formatNumber(value) {
            return /^[0-9]+$/.test(value ?? '') ? `+${value}` : value || 'Unknown';
        },

        get statusLabel() {
            return {
                offline: 'Not configured',
                connecting: 'Connecting…',
                ready: 'Online',
                dialling: 'Dialling',
                incoming: 'Ringing',
                'in-call': 'On call',
                error: 'Offline',
            }[this.state];
        },

        get statusDotClass() {
            return {
                ready: 'bg-emerald-400',
                'in-call': 'bg-emerald-400 animate-pulse',
                incoming: 'bg-vonage-purple animate-ping',
                dialling: 'bg-amber-300 animate-pulse',
                connecting: 'bg-amber-300 animate-pulse',
            }[this.state] ?? 'bg-white/30';
        },
    };
}
