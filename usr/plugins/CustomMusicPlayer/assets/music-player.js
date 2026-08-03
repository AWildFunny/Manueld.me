(function () {
    'use strict';

    var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var SCROLL_PLAY_RATIO = 0.5;

    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return '0:00';
        }
        var mins = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function getVisibleRatio(el) {
        var rect = el.getBoundingClientRect();
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        if (rect.bottom <= 0 || rect.top >= viewportHeight) {
            return 0;
        }

        var visibleHeight = Math.min(rect.bottom, viewportHeight) - Math.max(rect.top, 0);
        return visibleHeight / Math.max(rect.height, 1);
    }

    function MusicPlayerManager() {
        this.audio = new Audio();
        this.audio.preload = 'metadata';

        this.players = new Map();
        this.scrollObservers = new Map();
        this.currentPlayer = null;
        this.dock = null;
        this.dockDisc = null;
        this.dockCover = null;

        this.visibilityObserver = null;
        this.isSeeking = false;
        this.audioUnlocked = false;
        this.unlockBound = this.unlockAudio.bind(this);

        this.onTimeUpdate = this.onTimeUpdate.bind(this);
        this.onLoadedMetadata = this.onLoadedMetadata.bind(this);
        this.onEnded = this.onEnded.bind(this);
        this.onPlayEvent = this.onPlayEvent.bind(this);
        this.onPauseEvent = this.onPauseEvent.bind(this);
        this.onVisibilityChange = this.onVisibilityChange.bind(this);
        this.onAudioError = this.onAudioError.bind(this);
        this.onDockClick = this.onDockClick.bind(this);

        this.audio.addEventListener('timeupdate', this.onTimeUpdate);
        this.audio.addEventListener('loadedmetadata', this.onLoadedMetadata);
        this.audio.addEventListener('ended', this.onEnded);
        this.audio.addEventListener('play', this.onPlayEvent);
        this.audio.addEventListener('pause', this.onPauseEvent);
        this.audio.addEventListener('error', this.onAudioError);

        document.addEventListener('click', this.unlockBound, true);
        document.addEventListener('touchstart', this.unlockBound, { capture: true, passive: true });
        document.addEventListener('keydown', this.unlockBound, true);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', this.init.bind(this));
        } else {
            this.init();
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('pjax:send', this.teardown.bind(this));
            jQuery(document).on('pjax:complete', this.init.bind(this));
        }
    }

    MusicPlayerManager.prototype.unlockAudio = function () {
        if (this.audioUnlocked) {
            return;
        }
        this.audioUnlocked = true;
        this.evaluateScrollPlayers();
    };

    MusicPlayerManager.prototype.init = function () {
        var nodes = document.querySelectorAll('.music-player:not([data-mp-init])');
        for (var i = 0; i < nodes.length; i++) {
            this.registerPlayer(nodes[i]);
        }
        this.ensureDock();
        if (this.currentPlayer) {
            this.observeCurrentPlayer();
        }
        this.evaluateScrollPlayers();
    };

    MusicPlayerManager.prototype.teardown = function () {
        this.pauseAndReset();
        this.disconnectObservers();
        this.hideDock();

        this.players.forEach(function (state) {
            delete state.el.dataset.mpInit;
        });
        this.players.clear();
        this.currentPlayer = null;

        if (this.dock && this.dock.parentNode) {
            this.dock.parentNode.removeChild(this.dock);
        }
        this.dock = null;
        this.dockDisc = null;
        this.dockCover = null;
        this.audioUnlocked = false;
    };

    MusicPlayerManager.prototype.registerPlayer = function (el) {
        if (!el || el.dataset.mpInit) {
            return;
        }

        var id = el.dataset.mpId || el.id;
        var toggle = el.querySelector('.music-player-toggle');
        var seek = el.querySelector('.music-player-seek');
        var disc = el.querySelector('.music-player-disc');

        if (!toggle || !seek || !disc) {
            return;
        }

        el.dataset.mpInit = '1';

        var state = {
            el: el,
            toggle: toggle,
            seek: seek,
            disc: disc,
            timeCurrent: el.querySelector('.music-player-time--current'),
            timeTotal: el.querySelector('.music-player-time--total'),
            mode: el.dataset.mode || 'click',
            userPaused: false
        };

        this.players.set(id, state);

        toggle.addEventListener('click', this.handleToggleClick.bind(this, state));

        seek.addEventListener('input', this.handleSeekInput.bind(this, state));
        seek.addEventListener('change', this.handleSeekChange.bind(this, state));
        seek.addEventListener('mousedown', this.handleSeekStart.bind(this));
        seek.addEventListener('touchstart', this.handleSeekStart.bind(this), { passive: true });
        seek.addEventListener('mouseup', this.handleSeekEnd.bind(this));
        seek.addEventListener('touchend', this.handleSeekEnd.bind(this));

        if (state.mode === 'scroll') {
            this.attachScrollObserver(state);
        }
    };

    MusicPlayerManager.prototype.attachScrollObserver = function (state) {
        var id = state.el.dataset.mpId || state.el.id;
        if (this.scrollObservers.has(id)) {
            return;
        }

        var self = this;
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.target === state.el) {
                    self.onScrollModeChange(state, entry);
                }
            });
        }, {
            root: null,
            threshold: [0, 0.1, 0.25, 0.5, 0.75, 1]
        });

        observer.observe(state.el);
        this.scrollObservers.set(id, observer);
    };

    MusicPlayerManager.prototype.evaluateScrollPlayers = function () {
        var self = this;
        var bestState = null;
        var bestRatio = 0;

        this.players.forEach(function (state) {
            if (state.mode !== 'scroll' || state.userPaused) {
                return;
            }

            var ratio = getVisibleRatio(state.el);
            if (ratio >= SCROLL_PLAY_RATIO && ratio > bestRatio) {
                bestRatio = ratio;
                bestState = state;
            }
        });

        if (!bestState) {
            return;
        }

        if (this.currentPlayer !== bestState || this.audio.paused) {
            this.play(bestState, { fromScroll: true });
        }
    };

    MusicPlayerManager.prototype.ensureDock = function () {
        if (this.dock) {
            return;
        }

        var dock = document.createElement('button');
        dock.type = 'button';
        dock.className = 'music-player-dock';
        dock.setAttribute('aria-label', '返回正在播放的音乐');
        dock.hidden = true;

        var disc = document.createElement('div');
        disc.className = 'music-player-dock-disc';

        var cover = document.createElement('img');
        cover.className = 'music-player-dock-cover';
        cover.alt = '';
        cover.setAttribute('aria-hidden', 'true');

        var hole = document.createElement('span');
        hole.className = 'music-player-dock-hole';
        hole.setAttribute('aria-hidden', 'true');

        disc.appendChild(cover);
        disc.appendChild(hole);
        dock.appendChild(disc);
        document.body.appendChild(dock);

        dock.addEventListener('click', this.onDockClick);

        this.dock = dock;
        this.dockDisc = disc;
        this.dockCover = cover;
    };

    MusicPlayerManager.prototype.handleToggleClick = function (state) {
        this.audioUnlocked = true;
        if (this.currentPlayer === state && !this.audio.paused) {
            state.userPaused = true;
            this.audio.pause();
            return;
        }
        state.userPaused = false;
        this.play(state);
    };

    MusicPlayerManager.prototype.handleSeekStart = function () {
        this.isSeeking = true;
    };

    MusicPlayerManager.prototype.handleSeekEnd = function () {
        this.isSeeking = false;
    };

    MusicPlayerManager.prototype.handleSeekInput = function (state, event) {
        if (!this.audio.duration || this.currentPlayer !== state) {
            return;
        }
        var ratio = parseFloat(event.target.value) / 100;
        if (state.timeCurrent) {
            state.timeCurrent.textContent = formatTime(ratio * this.audio.duration);
        }
    };

    MusicPlayerManager.prototype.handleSeekChange = function (state, event) {
        if (!this.audio.duration || this.currentPlayer !== state) {
            return;
        }
        var ratio = parseFloat(event.target.value) / 100;
        this.audio.currentTime = ratio * this.audio.duration;
    };

    MusicPlayerManager.prototype.play = function (state, options) {
        var self = this;
        options = options || {};

        if (this.currentPlayer && this.currentPlayer !== state) {
            this.setPlayerPlaying(this.currentPlayer, false);
        }

        this.currentPlayer = state;
        var src = state.el.dataset.src;

        if (!src) {
            return;
        }

        if (this.audio.getAttribute('src') !== src) {
            this.audio.src = src;
        }

        this.observeCurrentPlayer();
        this.updateDockContent(state);
        state.el.classList.remove('is-autoplay-blocked');

        this.audio.play().then(function () {
            self.audioUnlocked = true;
            self.setPlayerPlaying(state, true);
            self.updateDockVisibility();
        }).catch(function () {
            self.setPlayerPlaying(state, false);
            if (options.fromScroll && !self.audioUnlocked) {
                state.el.classList.add('is-autoplay-blocked');
            }
        });
    };

    MusicPlayerManager.prototype.pauseAndReset = function () {
        this.audio.pause();
        if (this.currentPlayer) {
            this.setPlayerPlaying(this.currentPlayer, false);
        }
    };

    MusicPlayerManager.prototype.setPlayerPlaying = function (state, playing) {
        if (!state) {
            return;
        }
        state.el.classList.toggle('is-playing', playing);
        state.disc.classList.toggle('is-spinning', playing && !REDUCED_MOTION);
        if (this.dockDisc) {
            var dockShouldSpin = playing && !REDUCED_MOTION && this.shouldShowDock();
            this.dockDisc.classList.toggle('is-spinning', dockShouldSpin);
        }
    };

    MusicPlayerManager.prototype.onTimeUpdate = function () {
        if (!this.currentPlayer || this.isSeeking) {
            return;
        }
        var state = this.currentPlayer;
        var duration = this.audio.duration;
        var current = this.audio.currentTime;

        if (state.seek && duration) {
            state.seek.value = String((current / duration) * 100);
        }
        if (state.timeCurrent) {
            state.timeCurrent.textContent = formatTime(current);
        }
    };

    MusicPlayerManager.prototype.onLoadedMetadata = function () {
        if (!this.currentPlayer) {
            return;
        }
        var duration = this.audio.duration;
        if (this.currentPlayer.timeTotal) {
            this.currentPlayer.timeTotal.textContent = formatTime(duration);
        }
        if (this.currentPlayer.seek) {
            this.currentPlayer.seek.max = '100';
            this.currentPlayer.seek.value = '0';
        }
    };

    MusicPlayerManager.prototype.onEnded = function () {
        if (this.currentPlayer) {
            this.setPlayerPlaying(this.currentPlayer, false);
        }
        this.hideDock();
    };

    MusicPlayerManager.prototype.onPlayEvent = function () {
        if (this.currentPlayer) {
            this.setPlayerPlaying(this.currentPlayer, true);
            this.currentPlayer.el.classList.remove('is-autoplay-blocked');
        }
        this.updateDockVisibility();
    };

    MusicPlayerManager.prototype.onPauseEvent = function () {
        if (this.currentPlayer) {
            this.setPlayerPlaying(this.currentPlayer, false);
        }
        this.hideDock();
    };

    MusicPlayerManager.prototype.onAudioError = function () {
        if (!this.currentPlayer) {
            return;
        }
        this.currentPlayer.el.classList.add('is-load-error');
        this.setPlayerPlaying(this.currentPlayer, false);
    };

    MusicPlayerManager.prototype.disconnectObservers = function () {
        if (this.visibilityObserver) {
            this.visibilityObserver.disconnect();
            this.visibilityObserver = null;
        }

        this.scrollObservers.forEach(function (observer) {
            observer.disconnect();
        });
        this.scrollObservers.clear();
    };

    MusicPlayerManager.prototype.observeCurrentPlayer = function () {
        if (this.visibilityObserver) {
            this.visibilityObserver.disconnect();
            this.visibilityObserver = null;
        }

        if (!this.currentPlayer) {
            return;
        }

        var el = this.currentPlayer.el;
        var self = this;

        this.visibilityObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.target === el) {
                    self.onVisibilityChange(entry);
                }
            });
        }, {
            root: null,
            threshold: [0, 0.01, 0.25, 0.5, 1]
        });
        this.visibilityObserver.observe(el);
    };

    MusicPlayerManager.prototype.onVisibilityChange = function (entry) {
        this.updateDockVisibility(entry);
    };

    MusicPlayerManager.prototype.shouldShowDock = function (entry) {
        if (!this.currentPlayer || this.audio.paused) {
            return false;
        }

        var rect = entry ? entry.boundingClientRect : this.currentPlayer.el.getBoundingClientRect();
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        if (rect.top > viewportHeight) {
            return false;
        }

        return rect.bottom < 0;
    };

    MusicPlayerManager.prototype.updateDockVisibility = function (entry) {
        if (this.shouldShowDock(entry)) {
            this.showDock();
        } else {
            this.hideDock();
        }
    };

    MusicPlayerManager.prototype.onScrollModeChange = function (state, entry) {
        if (state.mode !== 'scroll') {
            return;
        }

        var visibleEnough = entry.isIntersecting && entry.intersectionRatio >= SCROLL_PLAY_RATIO;

        if (visibleEnough) {
            if (state.userPaused) {
                return;
            }
            if (this.currentPlayer !== state || this.audio.paused) {
                this.play(state, { fromScroll: true });
            }
            return;
        }

        if (entry.intersectionRatio <= 0.1 && this.currentPlayer === state && !this.audio.paused) {
            this.audio.pause();
        }
    };

    MusicPlayerManager.prototype.updateDockContent = function (state) {
        this.ensureDock();
        if (!this.dockCover) {
            return;
        }

        var cover = state.el.dataset.cover || '';
        var title = state.el.dataset.title || '';

        if (cover) {
            this.dockCover.src = cover;
            this.dockCover.classList.remove('music-player-dock-cover--placeholder');
            this.dockCover.style.display = '';
        } else {
            this.dockCover.removeAttribute('src');
            this.dockCover.classList.add('music-player-dock-cover--placeholder');
        }

        this.dock.setAttribute('aria-label', '返回正在播放：' + title);
    };

    MusicPlayerManager.prototype.showDock = function () {
        this.ensureDock();
        if (!this.dock) {
            return;
        }

        this.dock.hidden = false;
        this.dock.classList.add('is-visible');
        if (this.dockDisc && this.currentPlayer && !this.audio.paused) {
            this.dockDisc.classList.toggle('is-spinning', !REDUCED_MOTION);
        }
    };

    MusicPlayerManager.prototype.hideDock = function () {
        if (!this.dock) {
            return;
        }

        this.dock.classList.remove('is-visible');
        this.dock.hidden = true;
        if (this.dockDisc) {
            this.dockDisc.classList.remove('is-spinning');
        }
    };

    MusicPlayerManager.prototype.onDockClick = function () {
        if (!this.currentPlayer) {
            return;
        }

        var el = this.currentPlayer.el;
        el.scrollIntoView({
            behavior: REDUCED_MOTION ? 'auto' : 'smooth',
            block: 'center'
        });
        this.hideDock();
    };

    window.MusicPlayerManager = new MusicPlayerManager();
})();
