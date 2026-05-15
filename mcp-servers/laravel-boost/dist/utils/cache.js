import { statSync } from 'node:fs';
import { CACHE_ENABLED } from '../config.js';
const store = new Map();
function getMtime(filePath) {
    try {
        return statSync(filePath).mtimeMs;
    }
    catch {
        return null;
    }
}
export function getCached(filePath) {
    if (!CACHE_ENABLED)
        return null;
    const entry = store.get(filePath);
    if (!entry)
        return null;
    const mtime = getMtime(filePath);
    if (mtime === null || mtime !== entry.mtime) {
        store.delete(filePath);
        return null;
    }
    return entry.value;
}
export function setCached(filePath, value) {
    if (!CACHE_ENABLED)
        return;
    const mtime = getMtime(filePath);
    if (mtime === null)
        return;
    store.set(filePath, { value, mtime });
}
export function invalidate(filePath) {
    store.delete(filePath);
}
export function clearAll() {
    store.clear();
}
export function cacheSize() {
    return store.size;
}
//# sourceMappingURL=cache.js.map