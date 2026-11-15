const { createHash, randomBytes } = require('node:crypto');
const { createInterface } = require('node:readline/promises');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { DatabaseSync } = require('node:sqlite');
const { setTimeout: sleep } = require('node:timers/promises');

const command = 'صورت حساب';
const digest = (value) => createHash('sha256').update(value).digest('hex');
const identifier = (value) => typeof value === 'string' && /^[\w-]{1,128}$/.test(value);

function state(path, botToken) {
    const db = new DatabaseSync(path);
    db.exec(`
        PRAGMA busy_timeout = 30000;
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS invites (
            hash TEXT PRIMARY KEY, collaborator INTEGER UNIQUE NOT NULL, expires INTEGER NOT NULL,
            chat TEXT, sender TEXT
        );
        CREATE TABLE IF NOT EXISTS bindings (
            collaborator INTEGER PRIMARY KEY, chat TEXT UNIQUE NOT NULL, sender TEXT UNIQUE NOT NULL
        );
        CREATE TABLE IF NOT EXISTS seen (
            chat TEXT NOT NULL, message TEXT NOT NULL, PRIMARY KEY(chat, message)
        );
    `);
    db.prepare("INSERT OR IGNORE INTO settings VALUES ('bot', ?)").run(digest(botToken));
    if (db.prepare("SELECT value FROM settings WHERE key = 'bot'").get().value !== digest(botToken)) {
        db.close();
        throw new Error('Bot configuration does not match the state database');
    }
    return db;
}

function invite(db, collaborator) {
    const code = randomBytes(12).toString('hex');
    db.prepare('INSERT OR REPLACE INTO invites VALUES (?, ?, ?, NULL, NULL)')
        .run(digest(code), collaborator, Date.now() + 30 * 60_000);
    return code;
}

function parseCommand(update, bot) {
    const message = update?.new_message;
    if (update?.type !== 'NewMessage' || message?.sender_type !== 'User' || message.is_edited
        || message.forwarded_from || message.forwarded_no_link || !identifier(update.chat_id) || !identifier(message.sender_id)
        || !identifier(message.message_id) || typeof message.text !== 'string' || message.text.length > 64) return;
    const text = message.text.trim().replace(/[\s\u200c]+/gu, ' ');
    const code = /^اتصال ([a-f0-9]{24})$/.exec(text)?.[1];
    const [first, ...rest] = text.split(' ');
    const mention = bot?.username && `@${bot.username.replace(/^@/, '')}`.toLowerCase();
    const groupCommand = mention && first.toLowerCase() === mention && rest.join(' ') === command;
    if (text === command || code || groupCommand) {
        return { chat: update.chat_id, sender: message.sender_id, id: message.message_id, code, groupCommand };
    }
}

function binding(db, message) {
    return db.prepare('SELECT collaborator FROM bindings WHERE chat = ? AND sender = ?')
        .get(message.chat, message.sender)?.collaborator;
}

async function handleUpdate(db, clients, render, update, groupAccess) {
    const message = parseCommand(update, groupAccess?.bot);
    if (!message) return;
    const claimed = db.prepare('INSERT OR IGNORE INTO seen VALUES (?, ?)').run(message.chat, message.id);
    if (!claimed.changes) return;
    if (message.groupCommand) {
        const approved = groupAccess.enabled.get(message.chat);
        if (!approved) return;
        const resolve = async () => {
            const chat = await clients.getChat(message.chat);
            if (chat?.chat_type !== 'Group' || chat.chat_id !== message.chat) return null;
            const id = await clients.projectGroup(message.chat);
            return approved === id ? id : null;
        };
        const collaborator = await resolve();
        if (!collaborator) return;
        const snapshot = await clients.statement(collaborator);
        if (snapshot === null) return;
        const pdf = await render(validate(snapshot, collaborator));
        if (await resolve() === collaborator) await clients.deliver(message.chat, pdf);
        return;
    }
    if (!await clients.isPrivate(message.chat, message.sender)) return;

    if (message.code) {
        const accepted = db.prepare(`
            UPDATE invites SET chat = ?, sender = ? WHERE hash = ? AND expires > ? AND chat IS NULL
        `).run(message.chat, message.sender, digest(message.code), Date.now()).changes;
        if (accepted) await clients.reply(message.chat, 'درخواست اتصال ثبت شد و منتظر تأیید مدیر است.');
        return;
    }

    const collaborator = binding(db, message);
    if (!collaborator) {
        await clients.reply(message.chat, 'حساب روبیکای شما هنوز به تأیید مدیر نرسیده است.');
        return;
    }
    const snapshot = await clients.statement(collaborator);
    if (snapshot === null) {
        await clients.reply(message.chat, 'صورت‌حساب هنوز در دسترس نیست.');
        return;
    }
    const pdf = await render(validate(snapshot, collaborator));

    // Serialize the final binding check with approval and revocation.
    db.exec('BEGIN IMMEDIATE');
    try {
        if (binding(db, message) === collaborator && await clients.isPrivate(message.chat, message.sender)) {
            await clients.deliver(message.chat, pdf);
        }
    } finally {
        db.exec('ROLLBACK');
    }
}

function validate(snapshot, collaborator) {
    const amount = (value) => typeof value === 'string' && /^-?(0|[1-9]\d{0,19})$/.test(value);
    const safeText = (value, bytes) => typeof value === 'string' && Buffer.byteLength(value) <= bytes;
    if (snapshot?.collaborator_id !== collaborator || !['IRR', 'IRT'].includes(snapshot.currency)
        || !amount(snapshot.opening_balance) || !Array.isArray(snapshot.rows) || snapshot.rows.length > 5000
        || typeof snapshot.synced_at !== 'string'
        || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)$/.test(snapshot.synced_at)
        || !Number.isFinite(Date.parse(snapshot.synced_at))
        || snapshot.rows.some((row) => !row || !safeText(row.date, 80) || !safeText(row.description, 2000)
            || !amount(row.debit) || !amount(row.credit) || !amount(row.balance))) {
        throw new Error('Invalid statement response');
    }
    return snapshot;
}

function approve(db, code, chat, sender) {
    db.exec('BEGIN IMMEDIATE');
    try {
        const row = db.prepare('SELECT * FROM invites WHERE hash = ? AND expires > ?')
            .get(digest(code), Date.now());
        if (!row || row.chat !== chat || row.sender !== sender) throw new Error('Pending connection changed or expired');
        db.prepare('INSERT INTO bindings VALUES (?, ?, ?)').run(row.collaborator, chat, sender);
        db.prepare('DELETE FROM invites WHERE hash = ?').run(digest(code));
        db.exec('COMMIT');
    } catch (error) {
        db.exec('ROLLBACK');
        throw error;
    }
}

function revoke(db, collaborator) {
    db.exec('BEGIN IMMEDIATE');
    try {
        db.prepare('DELETE FROM invites WHERE collaborator = ?').run(collaborator);
        db.prepare('DELETE FROM bindings WHERE collaborator = ?').run(collaborator);
        db.exec('COMMIT');
    } catch (error) {
        db.exec('ROLLBACK');
        throw error;
    }
}

function clientsFor(token, root, user, password, request = fetch) {
    const origin = root ? new URL(root) : null;
    if (origin && (origin.protocol !== 'https:' || origin.username || origin.password || origin.search || origin.hash
        || origin.pathname !== '/')) throw new Error('Configure an HTTPS WordPress origin');
    const basic = Buffer.from(`${user}:${password}`).toString('base64');

    const exchange = (url, options) => request(url, {
        ...options, redirect: 'error', signal: AbortSignal.timeout(20_000),
    });
    async function rubika(method, data) {
        const response = await exchange(`https://botapi.rubika.ir/v3/${encodeURIComponent(token)}/${method}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data),
        });
        const body = response.ok ? await response.json() : null;
        if (body?.status !== 'OK' || !body.data) throw new Error('Rubika request failed');
        return body.data;
    }
    async function wordpress(path) {
        if (!origin || !user || !password) throw new Error('WordPress service account is missing');
        const uri = new URL(`/wp-json/zigurat-statements/v1/${path}`, origin);
        const response = await exchange(uri, { headers: { Authorization: `Basic ${basic}`, Accept: 'application/json' } });
        if (response.status === 404) return null;
        if (!response.ok) throw new Error('Statement API request failed');
        return response.json();
    }
    return {
        getBot: async () => (await rubika('getMe', {})).bot,
        poll: (offset_id) => rubika('getUpdates', offset_id ? { offset_id, limit: 20 } : { limit: 20 }),
        getChat: async (chat) => (await rubika('getChat', { chat_id: chat })).chat,
        async isPrivate(chat, sender) {
            const actual = await this.getChat(chat);
            return actual?.chat_type === 'User' && actual.chat_id === chat && actual.user_id === sender;
        },
        reply: (chat, text) => rubika('sendMessage', { chat_id: chat, text }),
        async statement(collaborator) {
            return (await wordpress(`collaborators/${collaborator}/statement`))?.statement ?? null;
        },
        async projectGroup(chat) {
            if (!identifier(chat)) return null;
            return (await wordpress(`rubika-project-group/${chat}`))?.collaborator_id ?? null;
        },
        async deliver(chat, pdf) {
            if (pdf.length > 50_000_000) throw new Error('Statement exceeds the Rubika file limit');
            const destination = new URL((await rubika('requestSendFile', { type: 'File' })).upload_url);
            if (destination.protocol !== 'https:' || destination.username || destination.password || destination.port
                || !(destination.hostname === 'rubika.ir' || destination.hostname.endsWith('.rubika.ir'))) {
                throw new Error('Unrecognized Rubika upload destination');
            }
            const form = new FormData();
            form.append('file', new Blob([pdf], { type: 'application/pdf' }), 'statement.pdf');
            const upload = await exchange(destination, { method: 'POST', body: form });
            const body = upload.ok ? await upload.json() : null;
            const file = body?.data?.file_id ?? body?.file_id;
            if (typeof file !== 'string' || !file) throw new Error('Rubika upload failed');
            const delivery = await rubika('sendFile', { chat_id: chat, file_id: file });
            if (!delivery.message_id) throw new Error('Rubika delivery unconfirmed');
        },
    };
}

const escapeHtml = (text) => text.replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[char]);

async function renderPdf(snapshot) {
    const { chromium } = require('playwright');
    const font = readFileSync(join(__dirname, '../../assets/fonts/Tanha.woff2')).toString('base64');
    const syncedAt = new Intl.DateTimeFormat('fa-IR', {
        dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Tehran',
    }).format(new Date(snapshot.synced_at));
    const rows = snapshot.rows.map((row) => `<tr><td dir="ltr">${escapeHtml(row.date)}</td>
      <td>${escapeHtml(row.description)}</td><td dir="ltr">${row.debit}</td>
      <td dir="ltr">${row.credit}</td><td dir="ltr">${row.balance}</td></tr>`).join('');
    const html = `<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">
      <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src data:">
      <style>
      @font-face { font-family: Tanha; src: url(data:font/woff2;base64,${font}); }
      @page { size: A4 landscape; margin: 12mm; }
      body { font: 12px Tanha, sans-serif; color: #20272a; }
      table { width: 100%; table-layout: fixed; border-collapse: collapse; }
      thead { display: table-header-group; }
      tr { break-inside: avoid; }
      th, td { border: 1px solid #adb7ba; padding: 5px 7px; text-align: start; overflow-wrap: anywhere; }
      th { background: #eef2f3; }
      </style>
      <h1>صورت‌حساب همکار</h1>
      <p>شناسهٔ همکار <bdi>${snapshot.collaborator_id}</bdi> | واحد ${snapshot.currency === 'IRT' ? 'تومان' : 'ریال'}</p>
      <p>ماندهٔ آغازین <bdi>${snapshot.opening_balance}</bdi> | آخرین همگام‌سازی ${syncedAt} به وقت تهران</p>
      <table><colgroup><col style="width: 14%"><col style="width: 38%"><col span="3" style="width: 16%"></colgroup>
      <thead><tr><th>تاریخ</th><th>شرح</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th></tr></thead>
      <tbody>${rows || '<tr><td colspan="5">گردشی ثبت نشده است.</td></tr>'}</tbody></table></html>`;
    const browser = await chromium.launch(process.env.ZIGGURAT_CHROMIUM_CHANNEL
        ? { channel: process.env.ZIGGURAT_CHROMIUM_CHANNEL } : {});
    try {
        const page = await browser.newPage({ offline: true });
        await page.setContent(html);
        await page.evaluate(() => document.fonts.ready);
        return await page.pdf({ preferCSSPageSize: true, printBackground: true });
    } finally {
        await browser.close();
    }
}

async function main() {
    const { ZIGGURAT_STATEMENTS_DB: path, ZIGGURAT_RUBIKA_TOKEN: token,
        ZIGGURAT_STATEMENTS_ORIGIN: origin, ZIGGURAT_STATEMENTS_USER: user,
        ZIGGURAT_STATEMENTS_APP_PASSWORD: password } = process.env;
    const [action, argument] = process.argv.slice(2);
    if (!path || !token || !['run', 'invite', 'approve', 'revoke'].includes(action)) {
        throw new Error('Expected configured state and bot token; use run, invite, approve or revoke');
    }
    const db = state(path, token);
    try {
        if (action === 'invite' || action === 'revoke') {
            const id = Number(argument);
            if (!/^[1-9]\d*$/.test(argument ?? '') || !Number.isSafeInteger(id)) throw new Error('Invalid collaborator ID');
            if (action === 'invite') console.log(invite(db, id));
            else revoke(db, id);
            return;
        }

        if (action === 'run' && (!origin || !user || !password)) {
            throw new Error('Configure the dedicated WordPress service account');
        }
        const clients = clientsFor(token, origin, user, password);
        if (action === 'approve') {
            const input = createInterface({ input: process.stdin, output: process.stdout });
            try {
                const code = (await input.question('کد اتصال: ')).trim();
                const row = db.prepare('SELECT * FROM invites WHERE hash = ? AND expires > ?')
                    .get(digest(code), Date.now());
                if (!row?.chat || !await clients.isPrivate(row.chat, row.sender)) throw new Error('No verified private request');
                const profile = await clients.getChat(row.chat);
                console.log(JSON.stringify({ collaborator: row.collaborator, user: row.sender,
                    name: [profile.first_name, profile.last_name].filter(Boolean).join(' '), username: profile.username }));
                if (await input.question('پس از احراز هویت همکار، برای تأیید شناسهٔ همکار را وارد کنید: ') !== String(row.collaborator)) return;
                approve(db, code, row.chat, row.sender);
                console.log('اتصال تأیید شد.');
            } finally {
                input.close();
            }
            return;
        }

        const groups = process.env.ZIGGURAT_STATEMENT_GROUPS;
        let groupAccess;
        if (groups) {
            const approvedGroups = Object.entries(JSON.parse(groups));
            if (approvedGroups.some(([group, id]) => !identifier(group) || !Number.isSafeInteger(id) || id < 1)
                || new Set(approvedGroups.map(([, id]) => id)).size !== approvedGroups.length) {
                throw new Error('Invalid statement group mapping');
            }
            const bot = await clients.getBot();
            if (!/^[A-Za-z0-9_]{1,64}$/.test(bot?.username?.replace(/^@/, '') ?? '')) {
                throw new Error('Rubika account has no usable bot username');
            }
            groupAccess = { bot, enabled: new Map(approvedGroups) };
        }
        let offset = db.prepare("SELECT value FROM settings WHERE key = 'offset'").get()?.value;
        while (true) {
            try {
                const batch = await clients.poll(offset);
                for (const update of batch.updates ?? []) {
                    try {
                        await handleUpdate(db, clients, renderPdf, update, groupAccess);
                    } catch {
                        console.error('Statement request could not be completed.');
                    }
                }
                if (typeof batch.next_offset_id === 'string' && batch.next_offset_id) {
                    offset = batch.next_offset_id;
                    db.prepare("INSERT OR REPLACE INTO settings VALUES ('offset', ?)").run(offset);
                }
            } catch {
                console.error('Could not read Rubika updates.');
            }
            await sleep(5000);
        }
    } finally {
        db.close();
    }
}

module.exports = { approve, clientsFor, handleUpdate, invite, renderPdf, revoke, state, validate };
if (require.main === module) main().catch(() => {
    console.error('Command failed. Verify configuration and binding uniqueness.');
    process.exitCode = 1;
});
