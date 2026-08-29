(function () {
    'use strict';

    var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var observer = null;
    var clickLock = false;
    var lastActiveId = '';
    var lastAutoPlayedId = '';
    var drawerDocBound = false;
    var navTopBound = false;
    var hoverLink = null;

    function pinNowToLink(link) {
        var nav = getNav();
        var now = nav ? nav.querySelector('[data-ma-now]') : null;
        if (!nav || !now) {
            return;
        }
        if (!link) {
            now.textContent = '';
            now.setAttribute('hidden', '');
            now.style.top = '';
            return;
        }

        var title = link.querySelector('.music-album-nav-title');
        var label = title ? (title.textContent || '').trim() : '';
        now.textContent = label;
        if (label) {
            now.removeAttribute('hidden');
        } else {
            now.setAttribute('hidden', '');
        }

        var navRect = nav.getBoundingClientRect();
        var linkRect = link.getBoundingClientRect();
        now.style.top = (linkRect.top + linkRect.height / 2 - navRect.top) + 'px';
    }

    function bindNowFollow() {
        var nav = getNav();
        if (!nav || nav.dataset.maNowFollow === '1') {
            return;
        }
        nav.dataset.maNowFollow = '1';

        nav.addEventListener('pointerover', function (event) {
            var link = event.target.closest('.music-album-nav-link');
            if (!link || !nav.contains(link)) {
                return;
            }
            hoverLink = link;
            pinNowToLink(link);
        });
        nav.addEventListener('pointerleave', function () {
            hoverLink = null;
            pinNowToLink(nav.querySelector('.music-album-nav-link.is-active'));
        });
        nav.addEventListener('focusin', function (event) {
            var link = event.target.closest('.music-album-nav-link');
            if (!link) {
                return;
            }
            hoverLink = link;
            pinNowToLink(link);
        });
        nav.addEventListener('focusout', function (event) {
            if (nav.contains(event.relatedTarget)) {
                return;
            }
            hoverLink = null;
            pinNowToLink(nav.querySelector('.music-album-nav-link.is-active'));
        });
    }

    function getMain() {
        return document.getElementById('pjax-container') || document.querySelector('main.container');
    }

    function getChapters() {
        return Array.prototype.slice.call(document.querySelectorAll('.music-album-chapter'));
    }

    function getLinks() {
        return Array.prototype.slice.call(document.querySelectorAll('.music-album-nav-link'));
    }

    function getNav() {
        return document.querySelector('.music-album-nav');
    }

    function markAlbumPage(active) {
        var main = getMain();
        if (!main) {
            return;
        }
        main.classList.toggle('is-music-album', !!active);
    }

    function updateProgress(chapterId) {
        var nav = getNav();
        if (!nav) {
            return;
        }

        var total = parseInt(nav.getAttribute('data-total') || '0', 10) || getLinks().length;
        var chapter = document.getElementById(chapterId);
        var current = chapter ? (parseInt(chapter.getAttribute('data-chapter') || '0', 10) + 1) : 1;
        if (current < 1) {
            current = 1;
        }
        if (total < 1) {
            total = 1;
        }

        var text = nav.querySelector('[data-ma-progress]');
        if (text) {
            text.textContent = current + ' / ' + total;
        }

        var now = nav.querySelector('[data-ma-now]');
        if (now) {
            pinNowToLink(hoverLink || nav.querySelector('.music-album-nav-link.is-active'));
        }

        scrollNavLinkIntoView(nav.querySelector('.music-album-nav-link.is-active'));
    }

    function findPlayerState(playerEl) {
        var mgr = window.MusicPlayerManager;
        if (!mgr || !mgr.players || !playerEl) {
            return null;
        }

        var found = null;
        mgr.players.forEach(function (state) {
            if (state.el === playerEl) {
                found = state;
            }
        });
        return found;
    }

    /**
     * C: 进入章节时自动播放该章内首个音乐播放器（不改插件，仅调用其公开 API）
     */
    function autoPlayChapterMusic(chapterEl) {
        if (!chapterEl || !window.MusicPlayerManager) {
            return;
        }

        var playerEl = chapterEl.querySelector('.music-player');
        if (!playerEl) {
            return;
        }

        var chapterId = chapterEl.id;
        if (chapterId && chapterId === lastAutoPlayedId) {
            return;
        }

        var mgr = window.MusicPlayerManager;
        var state = findPlayerState(playerEl);
        if (!state) {
            return;
        }

        // 已在播同一首则跳过
        if (mgr.currentPlayer === state && mgr.audio && !mgr.audio.paused) {
            lastAutoPlayedId = chapterId;
            return;
        }

        // 用户在该播放器上手动暂停过则不抢播，直到其再次点击
        if (state.userPaused) {
            return;
        }

        lastAutoPlayedId = chapterId;
        mgr.audioUnlocked = true;
        mgr.play(state, { fromScroll: true });
    }

    function setActiveChapter(chapterId, options) {
        options = options || {};
        if (!chapterId) {
            return;
        }

        getLinks().forEach(function (link) {
            var href = link.getAttribute('href') || '';
            link.classList.toggle('is-active', href === '#' + chapterId);
        });

        updateProgress(chapterId);

        var changed = chapterId !== lastActiveId;
        lastActiveId = chapterId;

        if (changed && options.autoPlay !== false) {
            var chapter = document.getElementById(chapterId);
            autoPlayChapterMusic(chapter);
        }
    }

    function scrollNavLinkIntoView(activeLink) {
        if (!activeLink) {
            return;
        }
        var panel = activeLink.closest('.music-album-nav-panel');
        if (panel) {
            var top = activeLink.offsetTop - panel.clientHeight / 2 + activeLink.offsetHeight / 2;
            panel.scrollTop = Math.max(0, top);
        }
    }

    function disconnectObserver() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    }

    function isDesktopNav() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    function syncNavTop() {
        var article = document.querySelector('.music-album-article');
        if (!article) {
            return;
        }
        var fixed = document.querySelector('.navbar.navbar-fixed');
        var wrap = document.querySelector('.header-navbar-wrapper');
        var top = 76;
        if (fixed) {
            top = Math.ceil(fixed.getBoundingClientRect().bottom) + 10;
        } else if (wrap) {
            top = Math.ceil(wrap.getBoundingClientRect().bottom) + 10;
        }
        if (top < 56) {
            top = 56;
        }
        article.style.setProperty('--ma-nav-top', top + 'px');
        pinNowToLink(hoverLink || (getNav() && getNav().querySelector('.music-album-nav-link.is-active')));
    }

    function bindNavTop() {
        syncNavTop();
        if (navTopBound) {
            return;
        }
        navTopBound = true;
        window.addEventListener('scroll', syncNavTop, { passive: true });
        window.addEventListener('resize', syncNavTop);
        var navbarEl = document.querySelector('.navbar');
        if (navbarEl && typeof MutationObserver === 'function') {
            new MutationObserver(syncNavTop).observe(navbarEl, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
    }

    function setNavOpen(open, pinned) {
        var nav = getNav();
        if (!nav) {
            return;
        }
        nav.classList.toggle('is-open', !!open);
        if (typeof pinned === 'boolean') {
            nav.classList.toggle('is-pinned', pinned);
        }
        var toggle = nav.querySelector('.music-album-nav-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function bindNavDrawer() {
        var nav = getNav();
        if (!nav) {
            return;
        }

        if (nav.dataset.maDrawer !== '1') {
            nav.dataset.maDrawer = '1';

            var toggle = nav.querySelector('.music-album-nav-toggle');
            if (toggle) {
                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    var pinned = !nav.classList.contains('is-pinned');
                    setNavOpen(pinned, pinned);
                });
            }
        }

        if (drawerDocBound) {
            return;
        }
        drawerDocBound = true;

        document.addEventListener('click', function (event) {
            var current = getNav();
            if (!current || !isDesktopNav() || !current.classList.contains('is-pinned')) {
                return;
            }
            if (!current.contains(event.target)) {
                setNavOpen(false, false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setNavOpen(false, false);
            }
        });
    }

    function resolveAutoLayouts() {
        getChapters().forEach(function (chapter) {
            if (!chapter.classList.contains('shot-auto')) {
                return;
            }
            var img = chapter.querySelector('.music-album-chapter-media img');
            if (!img) {
                return;
            }

            var apply = function () {
                var w = img.naturalWidth || 0;
                var h = img.naturalHeight || 0;
                chapter.classList.remove('shot-auto', 'shot-pos-top', 'shot-title-above', 'shot-pos-left', 'shot-title-beside');
                if (h > w && w > 0) {
                    chapter.classList.add('shot-pos-left', 'shot-title-beside');
                    chapter.setAttribute('data-layout', 'split-left');
                } else {
                    chapter.classList.add('shot-pos-top', 'shot-title-above');
                    chapter.setAttribute('data-layout', 'banner');
                }
            };

            if (img.complete && img.naturalWidth) {
                apply();
            } else {
                img.addEventListener('load', apply, { once: true });
            }
        });
    }

    function initMusicAlbumNav() {
        disconnectObserver();

        var layout = document.querySelector('.music-album-layout');
        if (!layout) {
            markAlbumPage(false);
            return;
        }

        markAlbumPage(true);
        bindNavTop();
        bindNowFollow();
        bindNavDrawer();
        resolveAutoLayouts();
        fitAlbumBoards();
        window.setTimeout(fitAlbumBoards, 400);

        var chapters = getChapters();
        var links = getLinks();

        if (!chapters.length || !links.length) {
            return;
        }

        links.forEach(function (link) {
            if (link.dataset.maBound) {
                return;
            }
            link.dataset.maBound = '1';
            link.addEventListener('click', function (event) {
                var href = link.getAttribute('href');
                if (!href || href.charAt(0) !== '#') {
                    return;
                }

                var target = document.getElementById(href.slice(1));
                if (!target) {
                    return;
                }

                event.preventDefault();
                clickLock = true;
                // 点击跳转：允许自动播放目标章
                lastAutoPlayedId = '';
                setActiveChapter(target.id, { autoPlay: true });

                target.scrollIntoView({
                    behavior: REDUCED_MOTION ? 'auto' : 'smooth',
                    block: 'start'
                });

                window.setTimeout(function () {
                    clickLock = false;
                }, REDUCED_MOTION ? 50 : 700);
            });
        });

        var visibleMap = new Map();

        observer = new IntersectionObserver(function (entries) {
            if (clickLock) {
                return;
            }

            entries.forEach(function (entry) {
                visibleMap.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
            });

            var bestId = '';
            var bestRatio = 0;

            chapters.forEach(function (chapter) {
                var ratio = visibleMap.get(chapter.id) || 0;
                if (ratio > bestRatio) {
                    bestRatio = ratio;
                    bestId = chapter.id;
                }
            });

            if (bestId && bestRatio > 0) {
                setActiveChapter(bestId, { autoPlay: true });
            }
        }, {
            root: null,
            rootMargin: '-18% 0px -52% 0px',
            threshold: [0, 0.15, 0.35, 0.55, 0.75, 1]
        });

        chapters.forEach(function (chapter) {
            observer.observe(chapter);
        });

        // 首屏：高亮第一章；音乐需用户手势后才能播，故首屏不强行 play
        lastActiveId = '';
        lastAutoPlayedId = chapters[0].id;
        setActiveChapter(chapters[0].id, { autoPlay: false });
    }

    function fitAlbumBoards() {
        var boards = document.querySelectorAll('.album-board');
        boards.forEach(function (board) {
            var stage = board.querySelector('.album-board-stage');
            if (!stage) {
                return;
            }
            stage.style.height = '';
            stage.style.aspectRatio = '';
            var boxH = stage.clientHeight;
            var extra = 0;
            Array.prototype.forEach.call(stage.querySelectorAll('.album-board-item'), function (item) {
                var bottom = item.offsetTop + item.offsetHeight;
                if (bottom - boxH > extra) {
                    extra = bottom - boxH;
                }
            });
            board.style.paddingBottom = extra > 2 ? Math.ceil(extra) + 'px' : '';
        });
    }

    function boot() {
        lastActiveId = '';
        lastAutoPlayedId = '';
        initMusicAlbumNav();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('pjax:send', function () {
            disconnectObserver();
            markAlbumPage(false);
        });
        jQuery(document).on('pjax:complete', boot);
    }
})();
