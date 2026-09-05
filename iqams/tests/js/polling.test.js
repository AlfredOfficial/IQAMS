import test from 'node:test';
import assert from 'node:assert/strict';
import { createPollingTask } from '../../resources/js/polling.js';

function setup(callback, options = {}) {
    const win = new EventTarget();
    const doc = new EventTarget();
    doc.hidden = false;
    const timers = new Map();
    win.setInterval = (callback, interval) => { timers.set(callback, interval); return callback; };
    win.clearInterval = (timer) => timers.delete(timer);
    const task = createPollingTask(callback, { win, doc, ...options });
    task.start();
    return { win, doc, task, timers };
}

test('personal dashboard defaults to 15 seconds and start is idempotent', () => {
    const { task, timers } = setup(() => {});
    task.start();
    assert.deepEqual([...timers.values()], [15000]);
    task.stop();
    assert.equal(timers.size, 0);
});

test('slow refreshes never overlap and subsequent refresh remains possible', async () => {
    let calls = 0;
    let finish;
    const { task } = setup(() => { calls++; return new Promise(resolve => { finish = resolve; }); });
    const pending = task.refresh();
    await task.refresh();
    assert.equal(calls, 1);
    finish();
    await pending;
    const next = task.refresh();
    assert.equal(calls, 2);
    finish();
    await next;
    task.stop();
});

test('hidden tabs abort current fetch, do not poll, and refresh on visibility', async () => {
    let calls = 0;
    let signal;
    const { task, doc } = setup(async (value) => { calls++; signal = value; });
    const pending = task.refresh();
    doc.hidden = true;
    doc.dispatchEvent(new Event('visibilitychange'));
    assert.equal(signal.aborted, true);
    await pending;
    await task.refresh();
    assert.equal(calls, 1);
    doc.hidden = false;
    doc.dispatchEvent(new Event('visibilitychange'));
    assert.equal(calls, 2);
    task.stop();
});

test('navigation cancels requests and back-cache restoration resumes polling', async () => {
    let calls = 0;
    let signal;
    const { task, win } = setup(async value => { calls++; signal = value; });
    const pending = task.refresh();
    win.dispatchEvent(new Event('iqams:navigating'));
    assert.equal(signal.aborted, true);
    await pending;
    await task.refresh();
    assert.equal(calls, 1);
    win.dispatchEvent(new Event('pageshow'));
    await task.refresh();
    assert.equal(calls, 2);
    task.stop();
    win.dispatchEvent(new Event('pageshow'));
    await task.refresh();
    assert.equal(calls, 2);
});

test('a failed refresh releases its slot and admin polling can retain four seconds', async () => {
    let calls = 0;
    const { task, timers } = setup(async () => { calls++; throw new Error('offline'); }, { interval: 4000 });
    await task.refresh();
    await task.refresh();
    assert.equal(calls, 2);
    assert.deepEqual([...timers.values()], [4000]);
    task.stop();
});
