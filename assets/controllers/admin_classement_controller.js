import { Controller } from '@hotwired/stimulus';

/**
 * Rank number fields on the admin classement page save on change via fetch,
 * so typing a rank never reloads the page (see AdminClassementController::setRang()).
 */
export default class extends Controller {
    save(event) {
        const input = event.target;
        const body = new URLSearchParams({
            tiersId: input.dataset.tiersId,
            slug: input.dataset.slug,
            rang: input.value,
        });

        input.classList.remove('is-saved', 'is-error');

        fetch(input.dataset.url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('save failed');
                }
                input.classList.add('is-saved');
                setTimeout(() => input.classList.remove('is-saved'), 1000);
            })
            .catch(() => {
                input.classList.add('is-error');
            });
    }
}
