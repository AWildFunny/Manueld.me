(function () {
    'use strict';

    var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var observer = null;
    var clickLock = false;

    function getChapters() {
        return Array.prototype.slice.call(document.querySelectorAll('.music-album-chapter'));
    }

    function getLinks() {
        return Array.prototype.slice.call(document.querySelectorAll('.music-album-nav-link'));
    }

    function setActiveChapter(chapterId) {
        if (!chapterId) {
            return;
        }

        getLinks().forEach(function (link) {
            var href = link.getAttribute('href') || '';
            link.classList.toggle('is-active', href === '#' + chapterId);
        });
    }

    function disconnectObserver() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    }

    function initMusicAlbumNav() {
        disconnectObserver();

        var layout = document.querySelector('.music-album-layout');
        if (!layout) {
            return;
        }

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
                setActiveChapter(target.id);

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
                setActiveChapter(bestId);
            }
        }, {
            root: null,
            rootMargin: '-20% 0px -55% 0px',
            threshold: [0, 0.15, 0.35, 0.55, 0.75, 1]
        });

        chapters.forEach(function (chapter) {
            observer.observe(chapter);
        });

        setActiveChapter(chapters[0].id);
    }

    function boot() {
        initMusicAlbumNav();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('pjax:send', disconnectObserver);
        jQuery(document).on('pjax:complete', boot);
    }
})();
