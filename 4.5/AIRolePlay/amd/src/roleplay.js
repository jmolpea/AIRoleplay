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
 * AI Roleplay UI: TTS playback, STT capture, avatar animations, avatar addressing detection.
 *
 * @module     mod_airoleplay/roleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['mod_airoleplay/utils', 'core/str'], function(utils, str) {
    'use strict';

    var cfg              = {};
    var sessionActive    = false;
    var currentTurn      = 0;
    var sessionTimer     = null;
    var remainingSeconds = 0;
    var recognition      = null;
    var isRecognising    = false;
    var currentTranscript = '';
    var audioCtx         = null;
    var pttSetupDone     = false;
    var pendingSubmit    = false;
    var rotationIndex    = 0;

    // ------------------------------------------------------------------
    // Public init
    // ------------------------------------------------------------------

    /**
     * Initialises the roleplay module.
     *
     * @param {Object} config
     * @param {number} config.cmid              Course module id.
     * @param {string} config.sesskey           Moodle session key.
     * @param {number} config.submissionid      Submission id (0 if not yet created).
     * @param {number} config.durationmins      Session duration in minutes.
     * @param {number} config.numavatars        Number of active avatars (1-3).
     * @param {Array}  config.avatarnames       [{index, name}, ...] for addressing detection.
     * @param {string} config.speechlang        BCP-47 language code for Web Speech API.
     * @param {string} config.submissionstatus  Current submission status.
     */
    var init = function(config) {
        cfg = config;

        var roleplayPanel = document.getElementById('airoleplay_roleplay_panel');
        if (!roleplayPanel) {
            return;
        }

        if (!roleplayPanel.classList.contains('d-none') && !sessionActive) {
            showReadyScreen(roleplayPanel);
        } else {
            var observer = new MutationObserver(function() {
                if (!roleplayPanel.classList.contains('d-none') && !sessionActive) {
                    observer.disconnect();
                    showReadyScreen(roleplayPanel);
                }
            });
            observer.observe(roleplayPanel, {attributes: true, attributeFilter: ['class']});
        }

        // If evaluation already pending (page reload after session ends), start polling.
        if (cfg.submissionstatus === 'submitted' || cfg.submissionstatus === 'grading') {
            var evalEl = document.getElementById('airoleplay_eval_status');
            pollEvaluationStatus(evalEl);
        }
    };

    // ------------------------------------------------------------------
    // Ready screen
    // ------------------------------------------------------------------

    /**
     * Renders the "ready to start" screen inside the roleplay panel.
     * @param {HTMLElement} roleplayPanel
     */
    var showReadyScreen = function(roleplayPanel) {
        var room = roleplayPanel.querySelector('.airoleplay-room');
        if (room) {
            room.style.display = 'none';
        }

        str.get_strings([
            {key: 'roleplay_ready_title', component: 'mod_airoleplay'},
            {key: 'roleplay_ready_notice', component: 'mod_airoleplay'},
            {key: 'roleplay_start_btn',    component: 'mod_airoleplay'}
        ]).then(function(strings) {
            var title   = strings[0];
            var notice  = strings[1];
            var btnText = strings[2];

            var screen = document.createElement('div');
            screen.id = 'airoleplay_ready_screen';
            screen.className = 'text-center p-4 my-4';
            screen.innerHTML =
                '<h3 class="mb-3">' + escapeHtml(title) + '</h3>' +
                '<div class="alert alert-warning d-inline-block text-start mb-4" style="max-width:600px">' +
                '<strong>&#9888;&#65039;</strong> ' + escapeHtml(notice) +
                '</div><br>' +
                '<button type="button" class="btn btn-primary btn-lg" id="airoleplay_start_btn">' +
                '&#127917; ' + escapeHtml(btnText) +
                '</button>';

            roleplayPanel.insertBefore(screen, room || roleplayPanel.firstChild);

            document.getElementById('airoleplay_start_btn').addEventListener('click', function() {
                screen.remove();
                if (room) {
                    room.style.display = '';
                }
                // Unlock AudioContext on user gesture.
                if (!audioCtx) {
                    try {
                        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    } catch (e) {
                        // ignore
                    }
                }
                startSession();
            });
        }).catch(function() {
            // Fallback if lang strings unavailable.
            var screen = document.createElement('div');
            screen.id = 'airoleplay_ready_screen';
            screen.className = 'text-center p-4 my-4';
            screen.innerHTML =
                '<button type="button" class="btn btn-primary btn-lg" id="airoleplay_start_btn">' +
                '&#127917; Start Roleplay</button>';
            roleplayPanel.insertBefore(screen, room || roleplayPanel.firstChild);
            document.getElementById('airoleplay_start_btn').addEventListener('click', function() {
                screen.remove();
                if (room) { room.style.display = ''; }
                startSession();
            });
        });
    };

    // ------------------------------------------------------------------
    // Session lifecycle
    // ------------------------------------------------------------------

    var startSession = function() {
        sessionActive    = true;
        currentTurn      = 0;
        rotationIndex    = 0;
        remainingSeconds = cfg.durationmins * 60;

        startSessionTimer();
        setupSpeechRecognition();

        var statusEl = document.getElementById('airoleplay_status');
        str.get_string('roleplay_loading', 'mod_airoleplay').then(function(msg) {
            utils.showStatus(statusEl, msg, 'info');
        }).catch(function() {});

        utils.ajaxPost(
            M.cfg.wwwroot + '/mod/airoleplay/ajax.php',
            {action: 'roleplay_opening', submissionid: cfg.submissionid, cmid: cfg.cmid},
            cfg.sesskey
        ).then(function(data) {
            if (data.success) {
                return deliverAvatarTurn(data.avatar, data.text, data.audio_base64).then(function() {
                    showPushToTalk();
                });
            } else {
                utils.showStatus(document.getElementById('airoleplay_status'), data.error || 'Failed to start roleplay', 'danger');
            }
        }).catch(function(e) {
            utils.showStatus(document.getElementById('airoleplay_status'), e.message, 'danger');
        });
    };

    var startSessionTimer = function() {
        var timerEl = document.getElementById('airoleplay_timer');
        updateTimerDisplay(timerEl);

        sessionTimer = setInterval(function() {
            remainingSeconds--;
            updateTimerDisplay(timerEl);

            if (remainingSeconds <= 0) {
                clearInterval(sessionTimer);
                endSession();
            } else if (remainingSeconds === 60) {
                utils.playBeep(660, 400, 0.3);
                str.get_string('warning_1min', 'mod_airoleplay').then(function(msg) {
                    utils.showStatus(document.getElementById('airoleplay_status'), msg, 'warning');
                }).catch(function() {});
            }
        }, 1000);
    };

    var updateTimerDisplay = function(timerEl) {
        if (timerEl) {
            timerEl.textContent = utils.formatTime(remainingSeconds);
            timerEl.className   = 'airoleplay-timer' + (remainingSeconds <= 60 ? ' urgent' : '');
        }
    };

    var endSession = function() {
        sessionActive = false;
        hidePushToTalk();

        var statusEl = document.getElementById('airoleplay_status');
        str.get_string('roleplay_ending', 'mod_airoleplay').then(function(msg) {
            utils.showStatus(statusEl, msg, 'info');
        }).catch(function() {});

        utils.ajaxPost(
            M.cfg.wwwroot + '/mod/airoleplay/ajax.php',
            {action: 'roleplay_closing', submissionid: cfg.submissionid, cmid: cfg.cmid, turn: currentTurn},
            cfg.sesskey
        ).then(function(data) {
            if (data.success) {
                return deliverAvatarTurn(data.avatar, data.text, data.audio_base64).then(function() {
                    if (data.evaluation_status === 'graded') {
                        str.get_string('evaluation_complete', 'mod_airoleplay').then(function(msg) {
                            utils.showStatus(statusEl, msg, 'success');
                        }).catch(function() {});
                        setTimeout(function() { window.location.reload(); }, 2000);
                    } else {
                        str.get_string('roleplay_finished', 'mod_airoleplay').then(function(msg) {
                            utils.showStatus(statusEl, msg, 'info');
                        }).catch(function() {});
                        pollEvaluationStatus(statusEl);
                    }
                });
            } else {
                str.get_string('roleplay_finished', 'mod_airoleplay').then(function(msg) {
                    utils.showStatus(statusEl, msg, 'info');
                }).catch(function() {});
                pollEvaluationStatus(statusEl);
            }
        }).catch(function() {
            str.get_string('roleplay_finished', 'mod_airoleplay').then(function(msg) {
                utils.showStatus(document.getElementById('airoleplay_status'), msg, 'info');
            }).catch(function() {});
            pollEvaluationStatus(document.getElementById('airoleplay_status'));
        });
    };

    // ------------------------------------------------------------------
    // Avatar turn delivery (TTS + animation)
    // ------------------------------------------------------------------

    var setAvatarTalking = function(avatarNum, talking) {
        var video = document.getElementById('avatar_video_' + avatarNum);
        if (!video) {
            return;
        }
        var newSrc = talking ? video.dataset.talking : video.dataset.idle;
        if (video.getAttribute('src') !== newSrc) {
            video.src  = newSrc;
            video.loop = true;
            video.play().catch(function() {});
        }
    };

    var deliverAvatarTurn = function(avatar, text, audioBase64) {
        currentTurn++;
        appendToTranscript('avatar_' + avatar, text, avatar);
        setActiveAvatar(avatar);

        var done;
        if (audioBase64) {
            done = playAudioBase64(audioBase64, avatar);
        } else {
            // No TTS: animate for an estimated duration based on text length.
            setAvatarTalking(avatar, true);
            done = new Promise(function(resolve) {
                setTimeout(resolve, Math.min(5000, text.length * 60));
            }).then(function() {
                setAvatarTalking(avatar, false);
            });
        }

        return done.then(function() {
            clearActiveAvatar();
        });
    };

    var playAudioBase64 = function(base64, avatar) {
        return new Promise(function(resolve) {
            try {
                var binary = atob(base64);
                var bytes  = new Uint8Array(binary.length);
                for (var i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }

                var avatarEl = document.getElementById('airoleplay_avatar_' + avatar);
                setAvatarTalking(avatar, true);
                if (avatarEl) {
                    avatarEl.classList.add('speaking');
                }

                if (!audioCtx) {
                    // AudioContext not yet created — use plain Audio element.
                    var blob  = new Blob([bytes], {type: 'audio/mpeg'});
                    var url   = URL.createObjectURL(blob);
                    var audio = new Audio(url);
                    var cleanup = function() {
                        if (avatarEl) { avatarEl.classList.remove('speaking'); }
                        setAvatarTalking(avatar, false);
                        URL.revokeObjectURL(url);
                        resolve();
                    };
                    audio.onended = cleanup;
                    audio.onerror = cleanup;
                    audio.play().catch(cleanup);
                    return;
                }

                audioCtx.decodeAudioData(bytes.buffer.slice(0), function(audioBuffer) {
                    var source   = audioCtx.createBufferSource();
                    var analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 256;
                    source.buffer = audioBuffer;
                    source.connect(analyser);
                    analyser.connect(audioCtx.destination);

                    source.onended = function() {
                        if (avatarEl) { avatarEl.classList.remove('speaking'); }
                        setAvatarTalking(avatar, false);
                        resolve();
                    };
                    source.start(0);
                }, function() {
                    // decodeAudioData failed — just resolve.
                    if (avatarEl) { avatarEl.classList.remove('speaking'); }
                    setAvatarTalking(avatar, false);
                    resolve();
                });
            } catch (e) {
                resolve();
            }
        });
    };

    var setActiveAvatar = function(avatar) {
        var n = cfg.numavatars || 1;
        for (var i = 1; i <= n; i++) {
            var el = document.getElementById('airoleplay_avatar_' + i);
            if (el) {
                el.classList.toggle('active-speaker',   i === avatar);
                el.classList.toggle('inactive-speaker', i !== avatar);
            }
        }
    };

    var clearActiveAvatar = function() {
        var n = cfg.numavatars || 1;
        for (var i = 1; i <= n; i++) {
            var el = document.getElementById('airoleplay_avatar_' + i);
            if (el) {
                el.classList.remove('active-speaker', 'inactive-speaker');
            }
        }
    };

    // ------------------------------------------------------------------
    // Speech recognition (STT)
    // ------------------------------------------------------------------

    var setupSpeechRecognition = function() {
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) {
            return;
        }

        recognition = new SR();
        recognition.continuous     = true;
        recognition.interimResults = true;
        recognition.lang           = cfg.speechlang || document.documentElement.lang || 'en-US';

        recognition.onresult = function(e) {
            var interim = '';
            var final   = '';
            for (var i = e.resultIndex; i < e.results.length; i++) {
                var t = e.results[i][0].transcript;
                if (e.results[i].isFinal) {
                    final += t;
                } else {
                    interim += t;
                }
            }
            currentTranscript += final;

            // Show live transcription in the transcript area.
            var transcriptEl = document.getElementById('airoleplay_transcript');
            if (transcriptEl) {
                var liveEl = transcriptEl.querySelector('.live-transcript');
                if (!liveEl) {
                    liveEl = document.createElement('div');
                    liveEl.className = 'live-transcript participant-turn';
                    transcriptEl.appendChild(liveEl);
                }
                liveEl.textContent = currentTranscript + (interim ? ' ' + interim : '');
            }
        };

        recognition.onerror = function(e) {
            if (e.error !== 'no-speech') {
                // Non-critical errors — silently ignore.
            }
        };

        recognition.onend = function() {
            if (pendingSubmit) {
                pendingSubmit = false;
                var transcript = currentTranscript;
                if (!transcript.trim()) {
                    var liveEl = document.querySelector('#airoleplay_transcript .live-transcript');
                    transcript = liveEl ? liveEl.textContent.trim() : '';
                }
                submitParticipantResponse(transcript);
            }
        };
    };

    var showPushToTalk = function() {
        var btn = document.getElementById('airoleplay_ptt_btn');
        if (btn) { btn.classList.remove('d-none'); }
        if (!pttSetupDone) {
            pttSetupDone = true;
            setupPTT();
        }
    };

    var hidePushToTalk = function() {
        var btn = document.getElementById('airoleplay_ptt_btn');
        if (btn) { btn.classList.add('d-none'); }
        if (recognition && isRecognising) {
            recognition.stop();
        }
    };

    var setupPTT = function() {
        var pttBtn = document.getElementById('airoleplay_ptt_btn');
        if (!pttBtn) {
            return;
        }

        var startListening = function() {
            if (!sessionActive) { return; }
            currentTranscript = '';
            isRecognising     = true;
            pttBtn.classList.add('active');
            if (recognition) {
                try { recognition.start(); } catch (e) {}
            }
        };

        var stopListening = function() {
            if (!isRecognising) { return; }
            isRecognising = false;
            pttBtn.classList.remove('active');

            if (recognition) {
                pendingSubmit = true;
                recognition.stop();
                // Fallback: if onend fires before stopListening's timeout, onend handles submission.
                setTimeout(function() {
                    if (pendingSubmit) {
                        pendingSubmit = false;
                        var transcript = currentTranscript;
                        if (!transcript.trim()) {
                            var liveEl = document.querySelector('#airoleplay_transcript .live-transcript');
                            transcript = liveEl ? liveEl.textContent.trim() : '';
                        }
                        submitParticipantResponse(transcript);
                    }
                }, 1000);
            } else {
                submitParticipantResponse(currentTranscript);
            }
        };

        pttBtn.addEventListener('mousedown',  startListening);
        pttBtn.addEventListener('mouseup',    stopListening);
        pttBtn.addEventListener('touchstart', function(e) { e.preventDefault(); startListening(); }, {passive: false});
        pttBtn.addEventListener('touchend',   function(e) { e.preventDefault(); stopListening(); },  {passive: false});
    };

    // ------------------------------------------------------------------
    // Participant response submission
    // ------------------------------------------------------------------

    var submitParticipantResponse = function(transcript) {
        if (!sessionActive || !transcript.trim()) {
            return;
        }

        hidePushToTalk();
        appendToTranscript('participant', transcript, 0);

        var statusEl = document.getElementById('airoleplay_status');
        str.get_string('roleplay_thinking', 'mod_airoleplay').then(function(msg) {
            utils.showStatus(statusEl, msg, 'info');
        }).catch(function() {});

        var numavatars      = cfg.numavatars || 1;
        var suggestedAvatar = (rotationIndex % numavatars) + 1;
        rotationIndex++;

        var preferredAvatar = detectAddressedAvatar(transcript);

        utils.ajaxPost(
            M.cfg.wwwroot + '/mod/airoleplay/ajax.php',
            {
                action:           'roleplay_turn',
                submissionid:     cfg.submissionid,
                cmid:             cfg.cmid,
                response:         transcript,
                suggested_avatar: suggestedAvatar,
                preferred_avatar: preferredAvatar,
                turn:             currentTurn + 1
            },
            cfg.sesskey
        ).then(function(data) {
            if (data.success) {
                return deliverAvatarTurn(data.avatar, data.text, data.audio_base64).then(function() {
                    if (sessionActive) { showPushToTalk(); }
                });
            } else {
                utils.showStatus(statusEl, data.error || 'Error processing response', 'danger');
                if (sessionActive) { showPushToTalk(); }
            }
        }).catch(function(e) {
            utils.showStatus(statusEl, 'Error: ' + e.message, 'danger');
            if (sessionActive) { showPushToTalk(); }
        });
    };

    /**
     * Detects if the participant addressed a specific avatar by name at the start of their message.
     * @param {string} transcript
     * @returns {number} Avatar index (1-3) or 0 if no avatar addressed.
     */
    var detectAddressedAvatar = function(transcript) {
        if (!cfg.avatarnames || cfg.avatarnames.length <= 1) {
            return 0;
        }
        var lower = transcript.toLowerCase().trim();

        // Check for name at start of message (e.g. "Alex, ..." or "Alex: ...").
        for (var i = 0; i < cfg.avatarnames.length; i++) {
            var name = cfg.avatarnames[i].name.toLowerCase();
            if (lower.indexOf(name) === 0 ||
                lower.indexOf(name + ',') === 0 ||
                lower.indexOf(name + ':') === 0) {
                return cfg.avatarnames[i].index;
            }
        }
        // Check for name anywhere in the message.
        for (var j = 0; j < cfg.avatarnames.length; j++) {
            if (lower.indexOf(cfg.avatarnames[j].name.toLowerCase()) !== -1) {
                return cfg.avatarnames[j].index;
            }
        }
        return 0;
    };

    // ------------------------------------------------------------------
    // Transcript UI
    // ------------------------------------------------------------------

    var appendToTranscript = function(speaker, text, avatar) {
        var transcriptEl = document.getElementById('airoleplay_transcript');
        var logEl        = document.getElementById('airoleplay_conversation_log');

        var isParticipant = (speaker === 'participant');
        var itemClass     = isParticipant ? 'participant-turn' : ('avatar-turn avatar-' + avatar);
        var avatarName    = '';
        if (!isParticipant && cfg.avatarnames) {
            for (var k = 0; k < cfg.avatarnames.length; k++) {
                if (cfg.avatarnames[k].index === avatar) {
                    avatarName = cfg.avatarnames[k].name;
                    break;
                }
            }
        }
        var label = isParticipant
            ? ('&#127917; ' + (cfg.participantlabel || 'You'))
            : ('&#128172; ' + (avatarName || ('Avatar ' + avatar)));

        [transcriptEl, logEl].forEach(function(container) {
            if (!container) { return; }
            // Remove any live/interim transcription element.
            var liveEl = container.querySelector('.live-transcript');
            if (liveEl) { liveEl.remove(); }

            var item = document.createElement('div');
            item.className = 'transcript-item ' + itemClass;
            item.innerHTML =
                '<span class="speaker-label">' + label + '</span>' +
                '<span class="message-text">' + escapeHtml(text) + '</span>';
            container.appendChild(item);
            container.scrollTop = container.scrollHeight;
        });
    };

    // ------------------------------------------------------------------
    // Evaluation polling
    // ------------------------------------------------------------------

    var pollEvaluationStatus = function(statusEl) {
        var pollUrl  = M.cfg.wwwroot + '/mod/airoleplay/ajax.php';
        var attempts = 0;

        var poll = function() {
            attempts++;
            if (attempts > 60) { return; } // Give up after 5 minutes.

            var url = pollUrl +
                '?action=check_evaluation&submissionid=' + cfg.submissionid +
                '&sesskey=' + cfg.sesskey +
                '&cmid=' + cfg.cmid;

            fetch(url).then(function(res) {
                return res.json();
            }).then(function(data) {
                if (data.status === 'graded') {
                    str.get_string('evaluation_complete', 'mod_airoleplay').then(function(msg) {
                        utils.showStatus(statusEl, msg, 'success');
                    }).catch(function() {});
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else if (data.status === 'error') {
                    utils.showStatus(statusEl, data.error || 'Evaluation failed', 'danger');
                } else {
                    setTimeout(poll, 5000);
                }
            }).catch(function() {
                setTimeout(poll, 5000);
            });
        };

        setTimeout(poll, 5000);
    };

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    var escapeHtml = function(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    return {
        init: init
    };
});
