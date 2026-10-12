import { getServerTime, getSpinDelay } from './selection-presence.js';

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
function showAnnouncement(node) {
    const announcement = node.querySelector?.('[data-spin-announcement]');
    if (typeof announcement?.showModal === 'function' && !announcement.open) announcement.showModal();
}
let rotation;
const playedWheels = new Set();
const outcomeTimers = new Map();
export function initializeCare(root) {
    for (const [node, timer] of outcomeTimers) {
        if (!node.isConnected) { clearTimeout(timer); outcomeTimers.delete(node); }
    }
    root.querySelectorAll('[data-spin-outcome]').forEach((node) => {
        if (outcomeTimers.has(node)) return;
        const until = Number(node.dataset.spinOutcome) + 3000 - getServerTime(Number(node.dataset.serverTime));
        if (until <= 0) return;
        node.hidden = true;
        const timer = setTimeout(() => { node.hidden = false; outcomeTimers.delete(node); showAnnouncement(node); }, until);
        outcomeTimers.set(node, timer);
    });
    root.querySelectorAll('[data-avatar-upload]').forEach((form) => {
        if (form.dataset.avatarInitialized) return;
        form.dataset.avatarInitialized = 'true';
        const file = form.querySelector('[data-avatar-file]');
        file.hidden = true;
        form.querySelector('[data-avatar-choose]').hidden = false;
        form.querySelector('[data-avatar-submit]').hidden = true;
        form.querySelector('[data-avatar-choose]').addEventListener('click', () => file.click());
        file.addEventListener('change', () => { if (file.files.length && navigator.onLine) form.requestSubmit(); });
    });
    root.querySelectorAll('[data-wheel-result]').forEach((wheel) => {
        if (wheel.dataset.spinStart) {
            if (wheel.dataset.spinInitialized) return;
            wheel.dataset.spinInitialized = 'true';
            const serverTime = Number(wheel.dataset.serverTime);
            wheel.dataset.clockOffset ??= String(serverTime - Date.now());
            const delay = getSpinDelay(Number(wheel.dataset.spinStart), getServerTime(Date.now() + Number(wheel.dataset.clockOffset)));
            wheel.style.animationDelay = `${delay}s`;
            if (delay <= -3) { wheel.classList.remove('movie-wheel--result'); wheel.classList.add('movie-wheel--settled'); }
            return;
        }
        const id=wheel.dataset.wheelResult;
        if(playedWheels.has(id)) { wheel.classList.remove('movie-wheel--result');wheel.classList.add('movie-wheel--settled'); }
        playedWheels.add(id);
    });
    root.querySelectorAll('[data-mood-history]').forEach((form) => bindMoodHistory(form));
    root.querySelectorAll('[data-repeat-editor]').forEach((form) => {
        if (form.dataset.careInitialized) return;
        form.dataset.careInitialized='true';
        const limit=Number(form.dataset.repeatLimit);
        form.querySelector('[data-repeat-add]').addEventListener('click',() => {
            const fields=form.querySelector('[data-repeat-fields]');
            if(fields.querySelectorAll('input').length>=limit) return;
            const field=fields.lastElementChild.cloneNode(true);
            const input=field.querySelector('input');
            input.value=''; input.id=`repeat-${`${Date.now()}-${fields.querySelectorAll('input').length}`}`;
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
