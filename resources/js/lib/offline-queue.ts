/**
 * Reports made without signal, kept on the phone (IndexedDB, photos included) until they can be
 * sent. Each carries a client_ref so a resend after a lost reply is stored only once.
 */

export type QueuedReport = {
    id?: number;
    url: string;
    fields: Record<string, string>;
    photos: File[];
    queuedAt: string;
};

const DB = 'hse-offline';
const STORE = 'reports';

function open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB, 1);
        request.onupgradeneeded = () =>
            request.result.createObjectStore(STORE, {
                keyPath: 'id',
                autoIncrement: true,
            });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function run<T>(
    mode: IDBTransactionMode,
    work: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const db = await open();

    return new Promise((resolve, reject) => {
        const request = work(db.transaction(STORE, mode).objectStore(STORE));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export const enqueue = (report: QueuedReport) =>
    run('readwrite', (s) => s.add(report));

export const queued = () => run<QueuedReport[]>('readonly', (s) => s.getAll());

const remove = (id: number) => run('readwrite', (s) => s.delete(id));

const xsrf = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

/**
 * Send every queued report. Stops at the first network failure (still offline) or when the
 * session has expired (the user must sign in); a report the server rejects as invalid is dropped
 * so it cannot block the rest, and counted as rejected.
 */
export async function flush(): Promise<{
    sent: number;
    rejected: number;
    signInNeeded: boolean;
}> {
    const result = { sent: 0, rejected: 0, signInNeeded: false };

    for (const report of await queued()) {
        const body = new FormData();
        Object.entries(report.fields).forEach(([k, v]) => body.append(k, v));
        report.photos.forEach((photo) => body.append('photos[]', photo));

        let response: Response;

        try {
            response = await fetch(report.url, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf(),
                },
            });
        } catch {
            break;
        }

        if (response.status === 401 || response.status === 419) {
            result.signInNeeded = true;
            break;
        }

        if (response.ok) {
            result.sent++;
        } else if (response.status === 422) {
            result.rejected++;
        } else {
            break;
        }

        await remove(report.id!);
    }

    return result;
}
