(function() {
	'use strict';

	/* ---------- Mobile Nav Toggle ---------- */
	var toggle = document.getElementById('nav-toggle');
	var nav = document.getElementById('primary-nav');

	if (toggle && nav) {
		toggle.addEventListener('click', function() {
			var expanded = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', String(!expanded));
			nav.classList.toggle('site-nav--open');
		});
	}

	/* ---------- Video Facade ---------- */
	document.addEventListener('click', function(e) {
		var facade = e.target.closest('.video-facade');
		if (!facade) return;

		var videoUrl = facade.getAttribute('data-video');
		if (!videoUrl) return;

		// Prevent double-init.
		if (facade.querySelector('video')) return;

		var captionsUrl = facade.getAttribute('data-captions');

		var video = document.createElement('video');
		video.setAttribute('controls', '');
		video.setAttribute('preload', 'auto');
		video.setAttribute('playsinline', '');
		video.style.width = '100%';
		video.style.height = '100%';

		var source = document.createElement('source');
		source.setAttribute('src', videoUrl);
		source.setAttribute('type', 'video/mp4');
		video.appendChild(source);

		if (captionsUrl) {
			var track = document.createElement('track');
			track.setAttribute('kind', 'captions');
			track.setAttribute('src', captionsUrl);
			track.setAttribute('srclang', 'en');
			track.setAttribute('label', 'English');
			track.setAttribute('default', '');
			video.appendChild(track);
		}

		// Replace facade contents with video.
		facade.innerHTML = '';
		facade.style.cursor = 'default';
		facade.appendChild(video);

		video.play();

		/* Push video_play event to GTM dataLayer */
		var title = facade.getAttribute('data-title') || facade.getAttribute('alt') || '';
		if (!title) {
			var card = facade.closest('.testimonial-card');
			if (card) {
				var nameEl = card.querySelector('.testimonial-card__name');
				title = nameEl ? nameEl.textContent.trim() : '';
			}
		}
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({
			event: 'video_play',
			video_title: title
		});
	});

	/* ---------- Transcript Toggle ---------- */
	document.addEventListener('click', function(e) {
		if (!e.target.classList.contains('transcript__toggle')) return;

		var btn = e.target;
		var content = btn.nextElementSibling;
		if (!content) return;

		var isOpen = content.classList.contains('transcript__content--open');
		content.classList.toggle('transcript__content--open');
		btn.textContent = isOpen ? 'Show Full Transcript' : 'Hide Transcript';
	});

	/* ---------- Video Lightbox ---------- */
	var lightbox = document.getElementById('video-lightbox');
	if (lightbox) {
		var lightboxVideo = document.getElementById('lightbox-video');
		var closeBtn = lightbox.querySelector('.video-lightbox__close');
		var playBtns = document.querySelectorAll('[data-video-lightbox]');

		function openLightbox() {
			lightbox.classList.add('video-lightbox--open');
			document.body.style.overflow = 'hidden';
			if (lightboxVideo) {
				lightboxVideo.play();
			}
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: 'video_play',
				video_title: document.title,
				video_source: 'lightbox'
			});
		}

		function closeLightbox() {
			if (lightboxVideo) {
				lightboxVideo.pause();
				lightboxVideo.currentTime = 0;
			}
			lightbox.classList.remove('video-lightbox--open');
			document.body.style.overflow = '';
		}

		for (var i = 0; i < playBtns.length; i++) {
			playBtns[i].addEventListener('click', openLightbox);
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', closeLightbox);
		}

		lightbox.addEventListener('click', function(e) {
			if (e.target === lightbox) {
				closeLightbox();
			}
		});

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && lightbox.classList.contains('video-lightbox--open')) {
				closeLightbox();
			}
		});
	}

	/* ---------- Sticky Header Shadow ---------- */
	var header = document.getElementById('site-header');
	if (header) {
		var lastScroll = 0;
		window.addEventListener('scroll', function() {
			var scrollY = window.pageYOffset || document.documentElement.scrollTop;
			if (scrollY > 10) {
				header.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)';
			} else {
				header.style.boxShadow = 'none';
			}
			lastScroll = scrollY;
		}, { passive: true });
	}

})();
