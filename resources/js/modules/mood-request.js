const DOUBLE_PRESS_MILLISECONDS = 350;

/**
 * Одно нажатие отправляет запрос партнёру, двойное — вопрос самому себе.
 * Без адреса для себя каждое нажатие сразу отправляет основной запрос.
 */
export function bindMoodRequest(form, send, schedule = setTimeout, cancel = clearTimeout) {
    const selfUrl = form.dataset.moodSelf;
    let pending;
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!selfUrl) { send(form.action); return; }
        if (pending !== undefined) {
            cancel(pending);
            pending = undefined;
            send(selfUrl);
            return;
        }
        pending = schedule(() => { pending = undefined; send(form.action); }, DOUBLE_PRESS_MILLISECONDS);
    });
}
