import { Controller } from '@hotwired/stimulus';

/**
 * Clicking a Pokémon (or one of its forme icons) on the Usage Pokémon page fetches
 * just the detail panel via fetch and swaps it in — no full page reload, so the
 * (often long, scrolled-down) Pokémon list never jumps back to the top.
 */
export default class extends Controller {
    static targets = ['list', 'detail'];

    connect() {
        this.onPopState = () => this.load(window.location.href, false);
        window.addEventListener('popstate', this.onPopState);
    }

    disconnect() {
        window.removeEventListener('popstate', this.onPopState);
    }

    selectPokemon(event) {
        event.preventDefault();
        this.load(event.currentTarget.href, true);
    }

    load(url, pushState) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('fetch failed');
                }
                return response.text();
            })
            .then((html) => {
                this.detailTarget.innerHTML = html;
                if (pushState) {
                    window.history.pushState({}, '', url);
                }
                this.updateActiveRow(url);
            })
            .catch(() => {
                window.location.href = url;
            });
    }

    updateActiveRow(url) {
        // match by the "pokemon" param only (not the full URL) so picking a forme icon
        // for the currently-selected Pokémon keeps that same row highlighted
        const slug = new URL(url, window.location.origin).searchParams.get('pokemon');
        this.listTarget.querySelectorAll('.usage-row.is-active').forEach((el) => el.classList.remove('is-active'));
        if (!slug) {
            return;
        }
        const row = Array.from(this.listTarget.querySelectorAll('.usage-row')).find((a) => new URL(a.href).searchParams.get('pokemon') === slug);
        if (row) {
            row.classList.add('is-active');
        }
    }
}
