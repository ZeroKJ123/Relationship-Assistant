import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { getSpinDelay } from '../../resources/js/modules/selection-presence.js';
function fixture() {
    const listeners=new Map(); let callback; let destination; let cancelled=false;
    const button={addEventListener:(name,fn)=>listeners.set(name,fn)};
    const form={dataset:{moodHistory:'/history'},querySelector:()=>button};
    const context={setTimeout:(fn)=>{callback=fn;return 1;},clearTimeout:()=>{callback=undefined;},Math,window:{location:{assign:(url)=>{destination=url;}}}};
    vm.createContext(context); vm.runInContext(fs.readFileSync('resources/js/modules/care.js','utf8').replace(/^import .*;\n/gm, '').replaceAll('export function','function'),context);
    context.bindMoodHistory(form);
    return {listeners,hold:()=>callback?.(),destination:()=>destination,event:()=>({button:0,isPrimary:true,clientX:0,clientY:0,preventDefault:()=>{cancelled=true;},stopImmediatePropagation:()=>{}}),cancelled:()=>cancelled};
}
test('holding the mood card opens history and prevents a second mood request',()=>{
    const f=fixture(); f.listeners.get('pointerdown')(f.event()); f.hold(); f.listeners.get('pointerup')(f.event()); f.listeners.get('click')(f.event());
    assert.equal(f.destination(),'/history');assert.equal(f.cancelled(),true);
});
test('a short tap scroll or cancelled gesture never opens mood history',()=>{
    for(const kind of ['pointerup','pointercancel','pointermove']) {
        const f=fixture(); f.listeners.get('pointerdown')(f.event()); f.listeners.get(kind)({...f.event(),clientX:50}); f.hold(); assert.equal(f.destination(),undefined);
    }
});
test('history is accessible with Shift Enter without sending a mood request',()=>{
    const f=fixture(); f.listeners.get('keydown')({...f.event(),key:'Enter',shiftKey:true});assert.equal(f.destination(),'/history');assert.equal(f.cancelled(),true);
});
test('partner compliments change only at five minute boundaries and keep the fallback when empty',()=>{
    let now=0;let timer;const paragraph={textContent:'fallback'};
    const card={dataset:{compliments:'["Первый","Второй"]'},querySelector:()=>paragraph};
    const root={querySelectorAll:(selector)=>selector==='[data-compliment]'?[card]:[]};
    const context={Math,JSON,Date:{now:()=>now},setInterval:(fn)=>{timer=fn;return 1;},clearInterval:()=>{}};
    vm.createContext(context);vm.runInContext(fs.readFileSync('resources/js/modules/care.js','utf8').replace(/^import .*;\n/gm, '').replaceAll('export function','function'),context);
    context.initializeCare(root);assert.equal(paragraph.textContent,'Первый');
    now=299999;timer();assert.equal(paragraph.textContent,'Первый');
    now=300000;timer();assert.equal(paragraph.textContent,'Второй');
    card.dataset.compliments='[]';paragraph.textContent='fallback';timer();assert.equal(paragraph.textContent,'fallback');
});

test('choosing a photo submits immediately while cancelling selection never submits',()=>{
    const listeners=new Map();let submitted=0;let opened=0;
    const file={files:[],addEventListener:(name,callback)=>listeners.set(name,callback),click:()=>opened++};
    const choose={hidden:true,addEventListener:(name,callback)=>listeners.set(`choose-${name}`,callback)};
    const submit={hidden:false};
    const form={dataset:{},requestSubmit:()=>submitted++,querySelector:(selector)=>({'[data-avatar-file]':file,'[data-avatar-choose]':choose,'[data-avatar-submit]':submit}[selector])};
    const root={querySelectorAll:(selector)=>selector==='[data-avatar-upload]'?[form]:[]};
    const context={Math,JSON,Date,Map,navigator:{onLine:true},setInterval:()=>1,clearInterval:()=>{}};
    vm.createContext(context);vm.runInContext(fs.readFileSync('resources/js/modules/care.js','utf8').replace(/^import .*;\n/gm,'').replaceAll('export function','function'),context);
    context.initializeCare(root);
    listeners.get('choose-click')();assert.equal(opened,1);
    listeners.get('change')();assert.equal(submitted,0);
    file.files=[{name:'photo.jpg'}];listeners.get('change')();assert.equal(submitted,1);
    context.navigator.onLine=false;listeners.get('change')();assert.equal(submitted,1);
    assert.equal(submit.hidden,true);
});

test('a live refresh preserves the running wheel timeline without advancing or restarting it',()=>{
    let time=5000;
    const wheel={dataset:{spinStart:'10000',serverTime:'5000'},style:{},classList:{remove:()=>{},add:()=>{}}};
    const root={querySelectorAll:(selector)=>selector==='[data-wheel-result]'?[wheel]:[]};
    const context={Math,JSON,Map,Date:{now:()=>time},getSpinDelay,getServerTime:(fallback)=>fallback,setInterval:()=>1,clearInterval:()=>{}};
    vm.createContext(context);vm.runInContext(fs.readFileSync('resources/js/modules/care.js','utf8').replace(/^import .*;\n/gm,'').replaceAll('export function','function'),context);
    context.initializeCare(root);assert.equal(wheel.style.animationDelay,'5s');
    time=6000;context.initializeCare(root);assert.equal(wheel.style.animationDelay,'5s');
});
