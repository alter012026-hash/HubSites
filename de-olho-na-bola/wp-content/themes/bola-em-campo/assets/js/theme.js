(function () {
	'use strict';

	/* Revela blocos à medida que entram na tela; conteúdo permanece visível sem suporte a IO. */
	if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		document.body.classList.add('has-motion');
		var revealItems = document.querySelectorAll('.hero-main, .ultimas, .section, .page-head, .post-content, .related, .nl-band');
		var revealObserver = new IntersectionObserver(function (entries, observer) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					observer.unobserve(entry.target);
				}
			});
		}, { rootMargin: '0px 0px -7% 0px', threshold: 0.08 });
		revealItems.forEach(function (item) {
			item.setAttribute('data-reveal', '');
			revealObserver.observe(item);
		});
	}

	/* Cabeçalho compacto e sombra progressiva durante a rolagem. */
	var siteHeader = document.querySelector('.site-header');
	if (siteHeader) {
		var headerTicking = false;
		var updateHeader = function () {
			siteHeader.classList.toggle('is-scrolled', window.scrollY > 18);
			headerTicking = false;
		};
		window.addEventListener('scroll', function () {
			if (!headerTicking) { headerTicking = true; window.requestAnimationFrame(updateHeader); }
		}, { passive: true });
		updateHeader();
	}

	/* Busca: abre/fecha o painel */
	/* Tema claro/escuro: prefere a configuração do aparelho até a pessoa escolher um modo. */
	var themeToggle = document.querySelector('[data-theme-toggle]');
	if (themeToggle) {
		var syncThemeButton = function () {
			var dark = document.documentElement.getAttribute('data-theme') === 'dark';
			themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
			themeToggle.setAttribute('aria-label', 'Modo escuro');
			themeToggle.setAttribute('title', dark ? 'Modo escuro ativo. Clique para modo claro.' : 'Modo claro ativo. Clique para modo escuro.');
		};
		syncThemeButton();
		themeToggle.addEventListener('click', function () {
			var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', next);
			try { localStorage.setItem('bec-theme', next); } catch (err) { /* a escolha vale até fechar a página */ }
			syncThemeButton();
		});
	}

	/* Busca: abre/fecha o painel */
	var sBtn = document.querySelector('.search-toggle');
	var sPanel = document.getElementById('search-panel');
	if (sBtn && sPanel) {
		sBtn.addEventListener('click', function () {
			var open = sPanel.classList.toggle('is-open');
			sBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				var inp = sPanel.querySelector('input[type="search"]');
				if (inp) { inp.focus(); }
			}
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && sPanel.classList.contains('is-open')) {
				sPanel.classList.remove('is-open');
				sBtn.setAttribute('aria-expanded', 'false');
				sBtn.focus();
			}
		});
	}

	/* Barra de progresso de leitura (artigos) */
	var bar = document.querySelector('.progress i');
	var art = document.querySelector('.post-body');
	if (bar && art) {
		var ticking = false;
		var update = function () {
			var r = art.getBoundingClientRect();
			var total = r.height - window.innerHeight * 0.5;
			var done = Math.min(Math.max(-r.top + window.innerHeight * 0.3, 0), total);
			bar.style.width = (total > 0 ? (done / total) * 100 : 0) + '%';
			ticking = false;
		};
		window.addEventListener('scroll', function () {
			if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
		}, { passive: true });
		update();
	}

	/* Compartilhar: copiar link / compartilhamento nativo no celular */
	/* Centro de partidas: os botões e o gesto horizontal mantêm placar e estatísticas sincronizados. */
	document.querySelectorAll('.bec-match-card').forEach(function (card) {
		var viewport = card.querySelector('[data-match-panels]');
		var tabs = Array.prototype.slice.call(card.querySelectorAll('[data-match-tab]'));
		var panels = Array.prototype.slice.call(card.querySelectorAll('[data-match-panel]'));
		if (!viewport || !tabs.length || !panels.length) { return; }
		var activate = function (index, move) {
			index = Math.max(0, Math.min(index, panels.length - 1));
			tabs.forEach(function (tab, i) {
				var active = i === index;
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.tabIndex = active ? 0 : -1;
			});
			if (move) { viewport.scrollTo({ left: index * viewport.clientWidth, behavior: 'smooth' }); }
		};
		activate(0, false);
		tabs.forEach(function (tab, index) {
			tab.addEventListener('click', function () { activate(index, true); });
			tab.addEventListener('keydown', function (event) {
				if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
					event.preventDefault();
					var next = (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
					activate(next, true);
					tabs[next].focus();
				}
			});
		});
		var scrollTicking = false;
		viewport.addEventListener('scroll', function () {
			if (scrollTicking) { return; }
			scrollTicking = true;
			window.requestAnimationFrame(function () {
				activate(Math.round(viewport.scrollLeft / viewport.clientWidth), false);
				scrollTicking = false;
			});
		}, { passive: true });
		window.addEventListener('resize', function () {
			activate(tabs.findIndex(function (tab) { return tab.getAttribute('aria-selected') === 'true'; }), true);
		});
	});

	/* Compartilhar: copiar link / compartilhamento nativo no celular */
	document.querySelectorAll('[data-share]').forEach(function (box) {
		var btn = box.querySelector('[data-copy]');
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var url = box.getAttribute('data-url') || window.location.href;
			var done = function () {
				var old = btn.textContent;
				btn.textContent = 'Link copiado!';
				setTimeout(function () { btn.textContent = old; }, 2000);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copie o link:', url); });
			} else {
				window.prompt('Copie o link:', url);
			}
		});
	});

	/* Newsletter: envio sem recarregar a página (sem JS, o formulário funciona igual via admin-post) */
	document.querySelectorAll('.nl-form').forEach(function (form) {
		var status = form.querySelector('.nl-status');
		var btn = form.querySelector('.nl-btn');
		var email = form.querySelector('input[type="email"]');
		var consent = form.querySelector('input[name="bec_consent"]');
		var say = function (msg, ok) {
			if (!status) { return; }
			status.textContent = msg;
			status.className = 'nl-status ' + (ok ? 'is-ok' : 'is-error');
		};
		form.addEventListener('submit', function (e) {
			var url = form.getAttribute('data-ajax');
			if (!url || !window.fetch || !window.FormData) { return; }
			e.preventDefault();
			if (!email.value || email.value.indexOf('@') < 1) { say('Digite um e-mail válido.', false); email.focus(); return; }
			if (consent && !consent.checked) { say('Marque a caixa de concordância para continuar.', false); return; }
			btn.disabled = true;
			say('Enviando…', true);
			fetch(url, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Erro inesperado. Tente de novo.' }; }); })
				.then(function (data) {
					say(data.message || 'Pronto!', !!data.ok);
					if (data.ok) { form.reset(); }
				})
				.catch(function () { say('Sem conexão. Tente de novo.', false); })
				.then(function () { btn.disabled = false; });
		});
	});

	/* Aviso (confirmação/cancelamento): fecha sozinho e limpa o endereço */
	var toast = document.querySelector('[data-toast]');
	if (toast) {
		var close = function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } };
		var x = toast.querySelector('[data-toast-close]');
		if (x) { x.addEventListener('click', close); }
		setTimeout(close, 9000);
		if (window.history && history.replaceState) {
			try {
				var u = new URL(window.location.href);
				u.searchParams.delete('bec_nl');
				u.searchParams.delete('t');
				history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
			} catch (err) { /* ignora */ }
		}
	}
})();
