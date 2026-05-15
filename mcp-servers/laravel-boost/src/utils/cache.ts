import { statSync } from 'node:fs';
import { CACHE_ENABLED } from '../config.js';

interface CacheEntry<T> {
  value: T;
  mtime: number;
}

const store = new Map<string, CacheEntry<unknown>>();

function getMtime(filePath: string): number | null {
  try {
    return statSync(filePath).mtimeMs;
  } catch {
    return null;
  }
}

export function getCached<T>(filePath: string): T | null {
  if (!CACHE_ENABLED) return null;
  const entry = store.get(filePath) as CacheEntry<T> | undefined;
  if (!entry) return null;
  const mtime = getMtime(filePath);
  if (mtime === null || mtime !== entry.mtime) {
    store.delete(filePath);
    return null;
  }
  return entry.value;
}

export function setCached<T>(filePath: string, value: T): void {
  if (!CACHE_ENABLED) return;
  const mtime = getMtime(filePath);
  if (mtime === null) return;
  store.set(filePath, { value, mtime });
}

export function invalidate(filePath: string): void {
  store.delete(filePath);
}

export function clearAll(): void {
  store.clear();
}

export function cacheSize(): number {
  return store.size;
}
