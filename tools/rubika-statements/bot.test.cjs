const assert = require('node:assert/strict');
const { writeFileSync } = require('node:fs');
const { test } = require('node:test');
const { approve, clientsFor, handleUpdate, invite, renderPdf, state, validate } = require('./bot.cjs');

const snapshot = {
    collaborator_id: 123, currency: 'IRT', opening_balance: '-45000',
    synced_at: '2026-11-15T01:00:00+00:00',
    rows: [{ date: '1405/08/24', description: 'کارکرد نصب آزمایشی', debit: '0',
        credit: '10000000000000000000', balance: '9999999999999955000' }],
};

function update(id, text = 'صورت‌حساب', chat = 'private-test', sender = 'user-test') {
    return { type: 'NewMessage', chat_id: chat, new_message: {
        message_id: id, sender_id: sender, sender_type: 'User', text,
    } };
}

test('the approved account exclusively receives the file, once per message', async () => {
    const db = state(':memory:', 'mock-token');
    const calls = [];
    const bytes = Buffer.from('%PDF-test');
    const clients = {
        isPrivate: async (chat) => chat !== 'group-test',
        reply: async (chat) => calls.push(['reply', chat]),
        statement: async (id) => { calls.push(['fetch', id]); return snapshot; },
        deliver: async (chat, pdf) => calls.push(['file', chat, pdf]),
    };
    const send = (message) => handleUpdate(db, clients, async () => bytes, message);
    const code = invite(db, 123);
    await send(update('1', 'اتصال ' + code, 'group-test'));
    assert.throws(() => approve(db, code, 'group-test', 'user-test'));
    assert.deepEqual(calls, []);
    await send(update('2', 'اتصال ' + code));
    await send(update('3'));
    assert.deepEqual(calls, [['reply', 'private-test'], ['reply', 'private-test']]);
    approve(db, code, 'private-test', 'user-test');
    assert.throws(() => approve(db, code, 'private-test', 'user-test'));
    const other = invite(db, 456);
    await send(update('4', 'اتصال ' + other));
    assert.throws(() => approve(db, other, 'private-test', 'user-test'));
    calls.length = 0;
    await send(update('5'));
    await send(update('5'));
    await send(update('6', 'صورت حساب', 'group-test'));
    await send(update('7', 'صورت حساب 456'));
    assert.deepEqual(calls, [['fetch', 123], ['file', 'private-test', bytes]]);
    await send(update('8', 'صورت حساب', 'private-test', 'different-sender'));
    assert.deepEqual(calls.at(-1), ['reply', 'private-test']);
    await handleUpdate(db, clients, async () => {
        db.exec('DELETE FROM bindings');
        return bytes;
    }, update('9'));
    assert.deepEqual(calls.at(-1), ['fetch', 123]);
    db.close();
});

test('the API uses authenticated GET, verified private chat and Rubika-only upload', async () => {
    const requests = [];
    const mock = async (url, options) => {
        requests.push([String(url), options]);
        const path = new URL(url).pathname;
        if (path.endsWith('/statement')) return Response.json({ statement: snapshot });
        if (path.endsWith('/getChat')) return Response.json({ status: 'OK', data: { chat: {
            chat_type: 'User', chat_id: 'private-test', user_id: 'user-test',
        } } });
        if (path.endsWith('/requestSendFile')) return Response.json({ status: 'OK', data: {
            upload_url: 'https://upload.rubika.ir/mock',
        } });
        if (path.endsWith('/mock')) return Response.json({ data: { file_id: 'test-file' } });
        return Response.json({ status: 'OK', data: { message_id: 'test-message' } });
    };
    const api = clientsFor('test-token', 'https://wordpress.example/', 'service', 'test-password', mock);
    assert.equal(await api.isPrivate('private-test', 'user-test'), true);
    assert.equal(await api.isPrivate('private-test', 'different-sender'), false);
    assert.deepEqual(await api.statement(123), snapshot);
    await api.deliver('private-test', Buffer.from('%PDF-test'));
    assert.match(requests[2][0], /\/collaborators\/123\/statement$/);
    assert.match(requests[2][1].headers.Authorization, /^Basic /);
    assert.equal(requests[2][1].redirect, 'error');
    assert.equal(requests[4][1].headers, undefined);
    assert.deepEqual(JSON.parse(requests[5][1].body), { chat_id: 'private-test', file_id: 'test-file' });
    const untrusted = clientsFor('token', 'https://wordpress.example/', 'service', 'test', async () =>
        Response.json({ status: 'OK', data: { upload_url: 'https://rubika.ir.example.org/file' } }));
    await assert.rejects(untrusted.deliver('private-test', Buffer.from('%PDF-test')), /upload destination/);
    assert.throws(() => validate(snapshot, 456));
    assert.throws(() => validate({ ...snapshot, opening_balance: 12 }, 123));
});

test('only the mentioned bot responds in the approved, currently mapped project group', async () => {
    const db = state(':memory:', 'mock-token');
    const sent = [];
    let current = 123;
    let type = 'Group';
    const clients = {
        isPrivate: async () => false,
        getChat: async (chat_id) => ({ chat_id, chat_type: type }),
        projectGroup: async () => current,
        statement: async (id) => { assert.equal(id, 123); return snapshot; },
        deliver: async (chat) => sent.push(chat),
    };
    const access = { bot: { username: 'TestBot' }, enabled: new Map([['group-test', 123]]) };
    const run = (id, text, chat = 'group-test') => handleUpdate(db, clients, async () => Buffer.alloc(1),
        update(id, text, chat), access);
    await run('10', 'صورت حساب');
    await run('11', '@OtherBot صورت حساب');
    await run('12', '@TestBot صورت حساب', 'unapproved-group');
    await run('13', '@TestBot صورت حساب 456');
    type = 'Channel';
    await run('14', '@TestBot صورت حساب');
    type = 'Group';
    current = 456;
    await run('15', '@TestBot صورت حساب');
    current = 123;
    await handleUpdate(db, clients, async () => { current = 456; return Buffer.alloc(1); },
        update('16', '@TestBot صورت حساب', 'group-test'), access);
    assert.deepEqual(sent, []);
    current = 123;
    await run('17', '@TestBot صورت‌حساب');
    await run('17', '@TestBot صورت‌حساب');
    assert.deepEqual(sent, ['group-test']);
    db.close();
});

test('Persian PDF generates with fake rows and exact string amounts', { skip: !process.env.ZIGGURAT_TEST_PDF }, async () => {
    const bytes = await renderPdf(validate(snapshot, 123));
    assert.equal(bytes.subarray(0, 5).toString(), '%PDF-');
    writeFileSync(process.env.ZIGGURAT_TEST_PDF, bytes);
});
