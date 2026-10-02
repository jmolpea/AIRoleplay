// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AI Roleplay session UI: speech capture, typed fallback, avatar playback and the session lifecycle.
 *
 * @module     mod_airoleplay/roleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import * as utils from 'mod_airoleplay/utils';

/** Keys of the language strings used by this module. */
const STRING_KEYS = [
    'roleplay_ready_title', 'roleplay_ready_notice', 'roleplay_start_btn', 'roleplay_resume_btn',
    'roleplay_loading', 'roleplay_thinking', 'roleplay_ending', 'roleplay_finished',
    'evaluation_complete', 'evaluation_delayed', 'warning_1min', 'you_label', 'listening',
    'stt_unsupported', 'mic_denied', 'mic_unavailable', 'mic_insecure', 'stt_network',
    'stt_nospeech', 'stt_empty', 'typed_reply_toggle', 'error_generic', 'mic_checking',
];

/** Seconds between two evaluation status checks. */
const POLL_INTERVAL_MS = 5000;

/** Give up polling after this many checks (10 minutes). */
const POLL_MAX_CHECKS = 120;

/** Wait this long for the recogniser to deliver its last result after release. */
const STT_FLUSH_MS = 1500;

let cfg = {};
let strings = {};
const state = {
    sessionActive: false,
    ending: false,
    remaining: 0,
    timer: null,
    busy: false,
    rotation: 0,
    textMode: false,
    failedCaptures: 0,
    audioCtx: null,
    recognition: null,
    listening: false,
    pendingSubmit: false,
    finalText: '',
    interimText: '',
    flushTimer: null,
};

/**
 * Returns a translated string loaded at start-up.
 *
 * @param {string} key String key.
 * @returns {string}
 */
const t = (key) => strings[key] || key;

/**
 * Shorthand for document.getElementById.
 *
 * @param {string} id Element id.
 * @returns {HTMLElement|null}
 */
const $ = (id) => document.getElementById(id);

/**
 * Shows a message in the session status area.
 *
 * @param {string} message Text to show.
 * @param {string} type Bootstrap alert type.
 */
const status = (message, type = 'info') => utils.showStatus($('airoleplay_status'), message, type);

/**
 * The browser's speech recognition constructor, if any.
 *
 * @returns {Function|null}
 */
const speechRecognitionCtor = () => window.SpeechRecognition || window.webkitSpeechRecognition || null;

/**
 * Calls one of the plugin's session services for the current attempt.
 *
 * @param {string} service Service name without the component prefix.
 * @param {Object} data Extra arguments.
 * @returns {Promise<Object>}
 */
const call = (service, data = {}) => utils.callService(
    'mod_airoleplay_' + service,
    Object.assign({cmid: cfg.cmid, submissionid: cfg.submissionid || 0}, data)
);

/**
 * Initialises the roleplay module.
 *
 * @param {Object} config Configuration from view.php.
 * @param {number} config.cmid Course module id.
 * @param {number} config.submissionid Current attempt id.
 * @param {number} config.remaining Seconds left in the session.
 * @param {number} config.numavatars Number of active avatars (1-3).
 * @param {Array} config.avatarnames [{index, name}, ...] for addressing detection.
 * @param {string} config.speechlang BCP-47 language code for the Web Speech API.
 * @param {string} config.submissionstatus Current attempt status.
 * @param {string} config.ttsmode 'openai' | 'gemini' | 'browser' | 'none'.
 */
export const init = async(config) => {
    cfg = config;
    try {
        const values = await getStrings(STRING_KEYS.map((key) => ({key: key, component: 'mod_airoleplay'})));
        STRING_KEYS.forEach((key, i) => {
            strings[key] = values[i];
        });
    } catch (e) {
        // Keys are shown instead of the texts; the session still works.
    }

    if (cfg.submissionstatus === 'submitted' || cfg.submissionstatus === 'grading') {
        finalise();
        return;
    }
    const panel = $('airoleplay_roleplay_panel');
    if (panel) {
        showReadyScreen(panel);
    }
};

// ---------------------------------------------------------------------------
// Ready screen and start-up checks.
// ---------------------------------------------------------------------------

/**
 * Renders the "ready to start" screen inside the roleplay panel.
 *
 * @param {HTMLElement} panel Roleplay panel.
 */
const showReadyScreen = (panel) => {
    const room = panel.querySelector('.airoleplay-room');
    room.classList.add('d-none');

    const screen = document.createElement('div');
    screen.id = 'airoleplay_ready_screen';
    screen.className = 'text-center p-4 my-4';

    const title = document.createElement('h3');
    title.className = 'mb-3';
    title.textContent = t('roleplay_ready_title');

    const notice = document.createElement('div');
    notice.className = 'alert alert-warning d-inline-block text-start mb-3 airoleplay-ready-notice';
    notice.textContent = t('roleplay_ready_notice');

    const check = document.createElement('div');
    check.id = 'airoleplay_ready_check';
    check.className = 'mb-3';
    check.setAttribute('role', 'status');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-primary btn-lg';
    button.id = 'airoleplay_start_btn';
    button.textContent = cfg.submissionstatus === 'active' ? t('roleplay_resume_btn') : t('roleplay_start_btn');

    screen.append(title, notice, document.createElement('br'), check, button);
    panel.insertBefore(screen, room);

    if (!speechRecognitionCtor()) {
        utils.showStatus(check, t('stt_unsupported'), 'warning');
    }

    button.addEventListener('click', async() => {
        button.disabled = true;
        unlockAudio();
        await checkMicrophone(check);
        screen.remove();
        room.classList.remove('d-none');
        startSession();
    });
};

/**
 * Creates/resumes the audio context inside the user gesture so later avatar
 * audio is allowed to play, and primes speech synthesis on browsers that need it.
 */
const unlockAudio = () => {
    try {
        if (!state.audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            state.audioCtx = Ctx ? new Ctx() : null;
        }
        if (state.audioCtx && state.audioCtx.state === 'suspended') {
            state.audioCtx.resume();
        }
    } catch (e) {
        state.audioCtx = null;
    }
    if (cfg.ttsmode === 'browser' && window.speechSynthesis) {
        window.speechSynthesis.cancel();
    }
};

/**
 * Asks for microphone permission before the clock starts, so the permission
 * prompt never eats into the session time. Any failure switches the session
 * to typed replies with an explanation, instead of a silent dead button.
 *
 * @param {HTMLElement} checkEl Element where the result is reported.
 * @returns {Promise<void>}
 */
const checkMicrophone = async(checkEl) => {
    if (!speechRecognitionCtor()) {
        enableTextMode(t('stt_unsupported'));
        return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        enableTextMode(t('mic_insecure'));
        return;
    }
    utils.showStatus(checkEl, t('mic_checking'), 'info');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({audio: true});
        // Recognition opens its own capture; this stream only proved access.
        stream.getTracks().forEach((track) => track.stop());
    } catch (e) {
        const denied = e && (e.name === 'NotAllowedError' || e.name === 'SecurityError');
        enableTextMode(denied ? t('mic_denied') : t('mic_unavailable'));
    }
};

// ---------------------------------------------------------------------------
// Session lifecycle.
// ---------------------------------------------------------------------------

/**
 * Starts (or resumes) the session on the server and delivers the opening line.
 */
const startSession = async() => {
    state.sessionActive = true;
    status(t('roleplay_loading'));
    window.addEventListener('beforeunload', warnBeforeLeaving);

    let data;
    try {
        data = await call('start_session');
    } catch (e) {
        status(t('error_generic'), 'danger');
        state.sessionActive = false;
        return;
    }
    if (!data.success) {
        status(data.error || t('error_generic'), 'danger');
        state.sessionActive = false;
        return;
    }
    cfg.submissionid = data.submissionid || cfg.submissionid;
    if (data.expired) {
        endSession();
        return;
    }
    startTimer(data.remaining);

    if (data.resume) {
        (data.transcript || []).forEach((turn) => appendTurn(turn.speaker === 'participant' ? 0 : turn.avatar, turn.text));
        clearStatus();
        showControls();
        return;
    }
    await deliverAvatarTurn(data);
    clearStatus();
    if (state.sessionActive) {
        showControls();
    }
};

/**
 * Starts the visible countdown from the server's remaining time.
 *
 * @param {number} seconds Seconds left.
 */
const startTimer = (seconds) => {
    state.remaining = Math.max(0, parseInt(seconds, 10) || 0);
    const timerEl = $('airoleplay_timer');
    const render = () => {
        if (timerEl) {
            timerEl.textContent = utils.formatTime(state.remaining);
            timerEl.classList.toggle('urgent', state.remaining <= 60);
        }
    };
    render();
    clearInterval(state.timer);
    state.timer = setInterval(() => {
        state.remaining--;
        render();
        if (state.remaining === 60) {
            utils.playBeep(660, 400, 0.3);
            status(t('warning_1min'), 'warning');
        }
        if (state.remaining <= 0) {
            clearInterval(state.timer);
            // Let an avatar reply in progress finish before closing.
            if (!state.busy) {
                endSession();
            }
        }
    }, 1000);
};

/**
 * Closes the session: stops capture, plays the closing line and evaluates.
 */
const endSession = async() => {
    if (state.ending) {
        return;
    }
    state.ending = true;
    state.sessionActive = false;
    clearInterval(state.timer);
    hideControls();
    abortRecognition();
    status(t('roleplay_ending'));

    try {
        const data = await call('close_session');
        if (data.success && data.text) {
            await deliverAvatarTurn(data);
        }
    } catch (e) {
        // The attempt is closed server-side by the background task anyway.
    }
    window.removeEventListener('beforeunload', warnBeforeLeaving);
    finalise();
};

/**
 * Evaluates the attempt, then reloads to show the results (or polls while
 * the evaluation runs in the background).
 */
const finalise = async() => {
    const room = document.querySelector('#airoleplay_roleplay_panel');
    if (room) {
        room.classList.add('d-none');
    }
    const panel = $('airoleplay_evaluating_panel');
    if (panel) {
        panel.classList.remove('d-none');
    }
    const evalStatus = $('airoleplay_eval_status');

    try {
        const data = await call('finalise_session');
        if (data.success && data.evaluation_status === 'graded') {
            utils.showStatus(evalStatus, t('evaluation_complete'), 'success');
            setTimeout(() => window.location.reload(), 1500);
            return;
        }
    } catch (e) {
        // Fall through to polling: the background task will evaluate.
    }
    pollEvaluation(evalStatus, 0);
};

/**
 * Polls the attempt status until it is graded.
 *
 * @param {HTMLElement} evalStatus Status element.
 * @param {number} checks Checks done so far.
 */
const pollEvaluation = (evalStatus, checks) => {
    if (checks >= POLL_MAX_CHECKS) {
        utils.showStatus(evalStatus, t('evaluation_delayed'), 'info');
        return;
    }
    setTimeout(async() => {
        try {
            const data = await call('get_evaluation_status');
            if (data.status === 'graded') {
                utils.showStatus(evalStatus, t('evaluation_complete'), 'success');
                setTimeout(() => window.location.reload(), 1500);
                return;
            }
        } catch (e) {
            // Transient network error: keep polling.
        }
        pollEvaluation(evalStatus, checks + 1);
    }, POLL_INTERVAL_MS);
};

/**
 * Warns before leaving the page while the session runs.
 *
 * @param {Event} e beforeunload event.
 */
const warnBeforeLeaving = (e) => {
    if (state.sessionActive) {
        e.preventDefault();
        e.returnValue = '';
    }
};

// ---------------------------------------------------------------------------
// Participant input: push-to-talk and typed replies.
// ---------------------------------------------------------------------------

let controlsBound = false;

/**
 * Shows the reply controls (push-to-talk and/or the typed reply form).
 */
const showControls = () => {
    bindControls();
    const ptt = $('airoleplay_ptt_btn');
    if (ptt && !state.textMode) {
        ptt.classList.remove('d-none');
        ptt.disabled = false;
    }
    const toggle = $('airoleplay_text_toggle');
    if (toggle) {
        toggle.classList.toggle('d-none', state.textMode);
    }
    const form = $('airoleplay_text_form');
    if (form && state.textMode) {
        form.classList.remove('d-none');
        $('airoleplay_text_input').disabled = false;
        $('airoleplay_text_send').disabled = false;
    }
};

/**
 * Hides/disables the reply controls while an avatar is answering.
 */
const hideControls = () => {
    const ptt = $('airoleplay_ptt_btn');
    if (ptt) {
        ptt.classList.add('d-none');
        ptt.classList.remove('active');
        ptt.setAttribute('aria-pressed', 'false');
    }
    const input = $('airoleplay_text_input');
    if (input) {
        input.disabled = true;
        $('airoleplay_text_send').disabled = true;
    }
};

/**
 * Switches the session to typed replies.
 *
 * @param {string} reason Explanation shown to the participant.
 */
const enableTextMode = (reason) => {
    state.textMode = true;
    abortRecognition();
    if (reason) {
        // Persistent notice: the status line is overwritten on every turn.
        const notice = $('airoleplay_mode_notice');
        if (notice) {
            utils.showStatus(notice, reason, 'warning');
            notice.classList.remove('d-none');
        } else {
            status(reason, 'warning');
        }
    }
    const ptt = $('airoleplay_ptt_btn');
    if (ptt) {
        ptt.classList.add('d-none');
    }
    if (state.sessionActive && !state.busy) {
        showControls();
        const input = $('airoleplay_text_input');
        if (input) {
            input.focus();
        }
    }
};

/**
 * Wires the push-to-talk button, the keyboard and the typed reply form (once).
 */
const bindControls = () => {
    if (controlsBound) {
        return;
    }
    controlsBound = true;

    const ptt = $('airoleplay_ptt_btn');
    if (ptt) {
        ptt.addEventListener('pointerdown', (e) => {
            e.preventDefault();
            if (ptt.setPointerCapture && e.pointerId !== undefined) {
                try {
                    ptt.setPointerCapture(e.pointerId);
                } catch (err) {
                    // Capture is an optimisation only.
                }
            }
            startListening();
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((type) => {
            ptt.addEventListener(type, () => stopListening());
        });
        ptt.addEventListener('contextmenu', (e) => e.preventDefault());
        // Keyboard: hold Space or Enter while the button has focus.
        ptt.addEventListener('keydown', (e) => {
            if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) {
                e.preventDefault();
                startListening();
            }
        });
        ptt.addEventListener('keyup', (e) => {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                stopListening();
            }
        });

        // "Type instead" link under the button.
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.id = 'airoleplay_text_toggle';
        toggle.className = 'btn btn-link btn-sm mt-1';
        toggle.textContent = t('typed_reply_toggle');
        toggle.addEventListener('click', () => enableTextMode(''));
        ptt.insertAdjacentElement('afterend', toggle);
    }

    const form = $('airoleplay_text_form');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = $('airoleplay_text_input');
            const text = input.value.trim();
            if (text) {
                input.value = '';
                submitResponse(text);
            }
        });
    }
};

/**
 * Starts capturing speech (a fresh recogniser per utterance avoids the
 * InvalidStateError some browsers raise when one instance is restarted).
 */
const startListening = () => {
    if (!state.sessionActive || state.busy || state.listening || state.textMode) {
        return;
    }
    const Ctor = speechRecognitionCtor();
    if (!Ctor) {
        enableTextMode(t('stt_unsupported'));
        return;
    }
    const recognition = new Ctor();
    recognition.lang = cfg.speechlang || document.documentElement.lang || 'en-US';
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    state.recognition = recognition;
    state.listening = true;
    state.pendingSubmit = false;
    state.finalText = '';
    state.interimText = '';

    recognition.onresult = (e) => {
        let finalText = '';
        let interimText = '';
        for (let i = 0; i < e.results.length; i++) {
            const piece = e.results[i][0].transcript;
            if (e.results[i].isFinal) {
                finalText += piece;
            } else {
                interimText += piece;
            }
        }
        state.finalText = finalText;
        state.interimText = interimText;
        showLiveTranscript((finalText + ' ' + interimText).trim());
    };
    recognition.onerror = (e) => handleRecognitionError(e.error);
    recognition.onend = () => {
        state.listening = false;
        if (state.pendingSubmit) {
            flushCapture();
        }
    };

    const ptt = $('airoleplay_ptt_btn');
    ptt.classList.add('active');
    ptt.setAttribute('aria-pressed', 'true');
    status(t('listening'));
    try {
        recognition.start();
    } catch (e) {
        state.listening = false;
        handleRecognitionError('start-failed');
    }
};

/**
 * Stops capturing and submits what was heard once the recogniser flushes.
 */
const stopListening = () => {
    const ptt = $('airoleplay_ptt_btn');
    if (ptt) {
        ptt.classList.remove('active');
        ptt.setAttribute('aria-pressed', 'false');
    }
    if (!state.recognition || state.pendingSubmit) {
        return;
    }
    state.pendingSubmit = true;
    try {
        state.recognition.stop();
    } catch (e) {
        // Already stopped.
    }
    // Some browsers never fire onend after an error; do not wait forever.
    state.flushTimer = setTimeout(flushCapture, STT_FLUSH_MS);
};

/**
 * Submits the captured text (final plus any trailing interim result).
 */
const flushCapture = () => {
    clearTimeout(state.flushTimer);
    if (!state.pendingSubmit) {
        return;
    }
    state.pendingSubmit = false;
    state.recognition = null;
    const text = (state.finalText + ' ' + state.interimText).trim();
    removeLiveTranscript();
    if (!text) {
        state.failedCaptures++;
        // After two empty captures, offer typing prominently.
        status(t('stt_empty'), 'warning');
        if (state.failedCaptures >= 2) {
            const form = $('airoleplay_text_form');
            if (form) {
                form.classList.remove('d-none');
                $('airoleplay_text_input').disabled = false;
                $('airoleplay_text_send').disabled = false;
            }
        }
        return;
    }
    state.failedCaptures = 0;
    submitResponse(text);
};

/**
 * Explains a speech recognition failure and, when speech cannot work on this
 * device or network, switches to typed replies.
 *
 * @param {string} error SpeechRecognitionErrorEvent.error code.
 */
const handleRecognitionError = (error) => {
    switch (error) {
        case 'not-allowed':
        case 'service-not-allowed':
            enableTextMode(t('mic_denied'));
            break;
        case 'audio-capture':
            enableTextMode(t('mic_unavailable'));
            break;
        case 'network':
        case 'language-not-supported':
        case 'start-failed':
            enableTextMode(t('stt_network'));
            break;
        case 'no-speech':
            status(t('stt_nospeech'), 'warning');
            break;
        default:
            // 'aborted' and unknown codes: nothing useful to tell the user.
            break;
    }
};

/**
 * Cancels any capture in progress without submitting it.
 */
const abortRecognition = () => {
    clearTimeout(state.flushTimer);
    state.pendingSubmit = false;
    state.listening = false;
    if (state.recognition) {
        try {
            state.recognition.abort();
        } catch (e) {
            // Already stopped.
        }
        state.recognition = null;
    }
    removeLiveTranscript();
};

/**
 * Sends the participant's reply and delivers the avatar's answer.
 *
 * @param {string} text Participant reply.
 */
const submitResponse = async(text) => {
    if (!state.sessionActive || state.busy) {
        return;
    }
    state.busy = true;
    hideControls();
    appendTurn(0, text);
    status(t('roleplay_thinking'));

    const numavatars = cfg.numavatars || 1;
    const suggested = (state.rotation % numavatars) + 1;
    state.rotation++;

    try {
        const data = await call('submit_turn', {
            response: text,
            // eslint-disable-next-line camelcase
            suggested_avatar: suggested,
            // eslint-disable-next-line camelcase
            preferred_avatar: detectAddressedAvatar(text),
        });
        if (data.success && data.timeup) {
            state.busy = false;
            endSession();
            return;
        }
        if (data.success) {
            if (typeof data.remaining === 'number') {
                state.remaining = data.remaining;
            }
            await deliverAvatarTurn(data);
            clearStatus();
        } else if (data.errorcode === 'invalidsubmissionstatus') {
            window.location.reload();
            return;
        } else {
            status(data.error || t('error_generic'), 'danger');
        }
    } catch (e) {
        status(t('error_generic'), 'danger');
    }
    state.busy = false;
    if (state.remaining <= 0) {
        endSession();
    } else if (state.sessionActive) {
        showControls();
    }
};

/**
 * Detects the avatar the participant addressed by name (0 = none).
 *
 * @param {string} transcript Participant reply.
 * @returns {number}
 */
const detectAddressedAvatar = (transcript) => {
    const names = cfg.avatarnames || [];
    if (names.length <= 1) {
        return 0;
    }
    const lower = transcript.toLowerCase().trim();
    const match = (predicate) => {
        const hit = names.find((a) => a.name && predicate(a.name.toLowerCase()));
        return hit ? hit.index : 0;
    };
    return match((name) => lower.startsWith(name)) || match((name) => lower.includes(name));
};

// ---------------------------------------------------------------------------
// Avatar playback.
// ---------------------------------------------------------------------------

/**
 * Shows and speaks one avatar line.
 *
 * @param {Object} data Turn payload from the server.
 * @returns {Promise<void>}
 */
const deliverAvatarTurn = async(data) => {
    const avatar = data.avatar || 1;
    appendTurn(avatar, data.text || '');
    setActiveAvatar(avatar);
    setTalking(avatar, true);
    try {
        if (data.audio_base64) {
            await playServerAudio(data.audio_base64, data.audio_mime);
        } else if (cfg.ttsmode === 'browser' && window.speechSynthesis) {
            await speakInBrowser(data.text || '', avatar);
        } else {
            await new Promise((resolve) => setTimeout(resolve, Math.min(6000, 800 + (data.text || '').length * 45)));
        }
    } finally {
        setTalking(avatar, false);
        setActiveAvatar(0);
    }
};

/**
 * Plays base64 audio returned by the server TTS provider.
 *
 * @param {string} base64 Audio bytes, base64 encoded.
 * @param {string} mime MIME type.
 * @returns {Promise<void>}
 */
const playServerAudio = (base64, mime) => new Promise((resolve) => {
    let settled = false;
    const finish = () => {
        if (!settled) {
            settled = true;
            resolve();
        }
    };
    // Hard watchdog in case no audio event ever fires.
    setTimeout(finish, 60000);

    let bytes;
    try {
        const binary = atob(base64);
        bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
    } catch (e) {
        finish();
        return;
    }

    const playWithElement = () => {
        const url = URL.createObjectURL(new Blob([bytes], {type: mime || 'audio/mpeg'}));
        const audio = new Audio(url);
        const release = () => {
            URL.revokeObjectURL(url);
            finish();
        };
        audio.onended = release;
        audio.onerror = release;
        audio.play().catch(release);
    };

    const ctx = state.audioCtx;
    if (!ctx) {
        playWithElement();
        return;
    }
    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => null);
    }
    ctx.decodeAudioData(bytes.buffer.slice(0), (buffer) => {
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.connect(ctx.destination);
        source.onended = finish;
        // Duration-based fallback in case onended never fires.
        setTimeout(finish, Math.ceil(buffer.duration * 1000) + 2000);
        source.start(0);
    }, playWithElement);
});

/**
 * Speaks a line with the browser's speech synthesis (no server cost).
 *
 * @param {string} text Line to speak.
 * @param {number} avatar Avatar index, used to vary the voice.
 * @returns {Promise<void>}
 */
const speakInBrowser = (text, avatar) => new Promise((resolve) => {
    const synth = window.speechSynthesis;
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = cfg.speechlang || 'en-US';
    const langprefix = utterance.lang.split('-')[0].toLowerCase();
    const voices = synth.getVoices().filter((v) => v.lang && v.lang.toLowerCase().startsWith(langprefix));
    if (voices.length) {
        utterance.voice = voices[(avatar - 1) % voices.length];
    }
    // Different pitch per avatar so several avatars remain distinguishable.
    utterance.pitch = [1, 1.25, 0.8][(avatar - 1) % 3];
    let settled = false;
    const finish = () => {
        if (!settled) {
            settled = true;
            resolve();
        }
    };
    utterance.onend = finish;
    utterance.onerror = finish;
    setTimeout(finish, Math.max(8000, text.length * 120));
    synth.cancel();
    synth.speak(utterance);
});

/**
 * Switches an avatar video between its idle and talking loops.
 *
 * @param {number} avatar Avatar index.
 * @param {boolean} talking Whether the avatar is speaking.
 */
const setTalking = (avatar, talking) => {
    const wrapper = $('airoleplay_avatar_' + avatar);
    if (wrapper) {
        wrapper.classList.toggle('speaking', talking);
    }
    const video = $('avatar_video_' + avatar);
    if (!video) {
        return;
    }
    const src = talking ? video.dataset.talking : video.dataset.idle;
    if (src && video.getAttribute('src') !== src) {
        video.src = src;
        video.loop = true;
        video.play().catch(() => null);
    }
};

/**
 * Highlights the speaking avatar (0 clears the highlight).
 *
 * @param {number} avatar Avatar index.
 */
const setActiveAvatar = (avatar) => {
    for (let i = 1; i <= (cfg.numavatars || 1); i++) {
        const el = $('airoleplay_avatar_' + i);
        if (el) {
            el.classList.toggle('active-speaker', avatar !== 0 && i === avatar);
            el.classList.toggle('inactive-speaker', avatar !== 0 && i !== avatar);
        }
    }
};

// ---------------------------------------------------------------------------
// Transcript.
// ---------------------------------------------------------------------------

/**
 * Appends a line to the on-screen transcript (text only, never HTML).
 *
 * @param {number} avatar Avatar index, or 0 for the participant.
 * @param {string} text Line text.
 */
const appendTurn = (avatar, text) => {
    const transcript = $('airoleplay_transcript');
    if (!transcript) {
        return;
    }
    removeLiveTranscript();
    const item = document.createElement('div');
    item.className = 'transcript-item ' + (avatar ? 'avatar-turn avatar-' + avatar : 'participant-turn');
    const label = document.createElement('span');
    label.className = 'speaker-label';
    if (avatar) {
        const entry = (cfg.avatarnames || []).find((a) => a.index === avatar);
        label.textContent = entry ? entry.name : 'Avatar ' + avatar;
    } else {
        label.textContent = t('you_label');
    }
    const message = document.createElement('span');
    message.className = 'message-text';
    message.textContent = text;
    item.append(label, message);
    transcript.appendChild(item);
    transcript.scrollTop = transcript.scrollHeight;
};

/**
 * Shows what the recogniser is hearing while the button is held.
 *
 * @param {string} text Current capture.
 */
const showLiveTranscript = (text) => {
    const transcript = $('airoleplay_transcript');
    if (!transcript) {
        return;
    }
    let live = transcript.querySelector('.live-transcript');
    if (!live) {
        live = document.createElement('div');
        live.className = 'transcript-item participant-turn live-transcript';
        transcript.appendChild(live);
    }
    live.textContent = text;
    transcript.scrollTop = transcript.scrollHeight;
};

/**
 * Removes the live capture line.
 */
const removeLiveTranscript = () => {
    const live = document.querySelector('#airoleplay_transcript .live-transcript');
    if (live) {
        live.remove();
    }
};

/**
 * Clears the status area.
 */
const clearStatus = () => {
    const el = $('airoleplay_status');
    if (el) {
        el.className = 'airoleplay-status-message';
        el.textContent = '';
        el.style.display = 'none';
    }
};
