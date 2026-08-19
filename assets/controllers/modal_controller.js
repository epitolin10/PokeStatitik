import { Controller } from '@hotwired/stimulus';

/**
 * Drives the "Choix du ..." overlay (Pokémon / capacité / objet / nature):
 * opening fetches a fragment and swaps it into the modal. Picking a row
 * inside is a plain form submit that redirects back to the build panel
 * page, which closes the overlay via a full navigation.
 */
export default class extends Controller {
    static targets = ['backdrop', 'content'];

    open(event) {
        event.preventDefault();
        const url = event.currentTarget.href || event.currentTarget.dataset.modalUrl;
        this.show();
        this.load(url);
    }

    submitGet(event) {
        event.preventDefault();
        const form = event.target.closest('form') || event.target;
        const url = new URL(form.action || window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        this.load(url.toString());
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }
        this.hide();
    }

    refreshStats(event) {
        const input = event.currentTarget;
        const row = input.closest('.stat-alloc-row');
        row.querySelector('.stat-alloc-row__fill').style.width = (input.value / 32 * 100) + '%';
        row.querySelector('.stat-alloc-row__value').textContent = input.value;

        const total = Array.from(this.element.querySelectorAll('[data-modal-target="statInput"]'))
            .reduce((sum, el) => sum + (parseInt(el.value, 10) || 0), 0);
        const totalEl = this.element.querySelector('[data-modal-target="statTotal"]');
        if (totalEl) {
            totalEl.textContent = total;
            totalEl.style.color = total > 66 ? 'var(--color-down)' : '';
        }
    }

    load(url) {
        this.showLoading();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.text())
            .then((html) => this.inject(html))
            .catch(() => this.showError());
    }

    inject(html) {
        this.contentTarget.innerHTML = html;
    }

    show() {
        this.backdropTarget.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    hide() {
        this.backdropTarget.classList.remove('is-open');
        document.body.style.overflow = '';
        this.contentTarget.innerHTML = '';
    }

    showLoading() {
        this.contentTarget.innerHTML = `
            <div class="modal-loading">
                <div class="modal-loading__bounce">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="modal-loading__pokeball" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" fill="#F4F4F8" stroke="#16121F" stroke-width="5" />
                        <path d="M6 50 A44 44 0 0 1 94 50 Z" fill="#FF3D71" stroke="#16121F" stroke-width="5" stroke-linejoin="round" />
                        <rect x="5" y="46" width="90" height="8" fill="#16121F" />
                        <circle cx="50" cy="50" r="15" fill="#16121F" />
                        <circle cx="50" cy="50" r="9" fill="#F4F4F8" stroke="#16121F" stroke-width="4" />
                    </svg>
                </div>
                <p class="modal-loading__text">Chargement…</p>
            </div>
        `;
    }

    showError() {
        this.contentTarget.innerHTML = '<div class="modal-card"><p style="padding:40px; text-align:center;">Une erreur est survenue. <a href="#" data-action="click->modal#close">Fermer</a></p></div>';
    }
}
