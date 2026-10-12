const HOLD_MILLISECONDS = 600;
const MOVEMENT_LIMIT = 12;
export function bindMoodHistory(form, navigate = (url) => window.location.assign(url)) {
    if (!form.dataset.moodHistory || form.dataset.historyInitialized) return;
    form.dataset.historyInitialized = 'true';
    const button = form.querySelector('button');
    let timer;
    let start;
    let held = false;
    const clear = () => { clearTimeout(timer); start = null; };
    button.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || !event.isPrimary) return;
        held = false;
        start = { x:event.clientX, y:event.clientY };
        timer = setTimeout(() => { held = true; navigate(form.dataset.moodHistory); }, HOLD_MILLISECONDS);
    });
    button.addEventListener('pointermove', (event) => { if (start && Math.hypot(event.clientX-start.x,event.clientY-start.y)>MOVEMENT_LIMIT) clear(); });
    ['pointerup','pointercancel','pointerleave'].forEach((name) => button.addEventListener(name, clear));
    button.addEventListener('contextmenu',(event) => event.preventDefault());
    button.addEventListener('click',(event) => { if (held) { event.preventDefault(); event.stopImmediatePropagation(); held=false; } });
    button.addEventListener('keydown',(event) => { if (event.key==='h' || (event.shiftKey && event.key==='Enter')) { event.preventDefault(); navigate(form.dataset.moodHistory); } });
}
let rotation;
const playedWheels = new Set();
export function initializeCare(root) {
    root.querySelectorAll('[data-wheel-result]').forEach((wheel) => {
        const id=wheel.dataset.wheelResult;
        if(playedWheels.has(id)) { wheel.classList.remove('movie-wheel--result');wheel.classList.add('movie-wheel--settled'); }
        playedWheels.add(id);
    });
    root.querySelectorAll('[data-mood-history]').forEach((form) => bindMoodHistory(form));
    root.querySelectorAll('[data-compliment-editor]').forEach((form) => {
        if (form.dataset.careInitialized) return;
        form.dataset.careInitialized='true';
        form.querySelector('[data-add-compliment]').addEventListener('click',() => {
            const fields=form.querySelector('[data-compliment-fields]');
            if(fields.querySelectorAll('input').length>=50) return;
            const field=fields.lastElementChild.cloneNode(true);
            const input=field.querySelector('input');
            input.value=''; input.id=`compliment-${`${Date.now()}-${fields.querySelectorAll('input').length}`}`;
            field.querySelector('label')?.setAttribute('for',input.id);
            fields.append(field); input.focus();
        });
    });
    clearInterval(rotation);
    const rotate = () => {
        root.querySelectorAll('[data-compliment]').forEach((card) => {
            const texts=JSON.parse(card.dataset.compliments || '[]');
            if(texts.length) card.querySelector('p').textContent=texts[Math.floor(Date.now()/300000)%texts.length];
        });
    };
    rotate();
    rotation=setInterval(rotate, 10000);
}
