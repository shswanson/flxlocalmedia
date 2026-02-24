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
